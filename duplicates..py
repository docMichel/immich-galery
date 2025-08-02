#!/usr/bin/env python3
"""
Endpoints Flask pour la détection de doublons avec intégration Immich
"""

from flask import Blueprint, request, jsonify, Response
import json
import time
from typing import Dict, List
import requests
from PIL import Image
from io import BytesIO
import tempfile
import os
from pathlib import Path

# Import du service existant
from services.duplicate_detection_service import DuplicateDetectionService, DuplicateGroup

duplicates_bp = Blueprint('duplicates', __name__)

# Service de détection
duplicate_service = DuplicateDetectionService()

class ImmichImageLoader:
    """Classe pour charger les images depuis Immich"""
    
    def __init__(self, api_url: str, api_key: str):
        self.api_url = api_url.rstrip('/')
        self.api_key = api_key
        self.headers = {'x-api-key': api_key}
        self.temp_dir = Path(tempfile.gettempdir()) / 'immich_cache'
        self.temp_dir.mkdir(exist_ok=True)
    
    def get_image_path(self, asset_id: str, size: str = 'preview') -> str:
        """
        Récupérer une image depuis Immich et retourner le chemin local
        
        Args:
            asset_id: ID de l'asset Immich
            size: 'thumbnail', 'preview' ou 'original'
            
        Returns:
            Chemin vers le fichier temporaire
        """
        # Vérifier le cache local
        cache_path = self.temp_dir / f"{asset_id}_{size}.jpg"
        if cache_path.exists():
            # Vérifier que le fichier n'est pas trop vieux (1 heure)
            if time.time() - cache_path.stat().st_mtime < 3600:
                return str(cache_path)
        
        # Télécharger depuis Immich
        if size == 'original':
            url = f"{self.api_url}/api/assets/{asset_id}/original"
        else:
            url = f"{self.api_url}/api/assets/{asset_id}/thumbnail?size={size}"
        
        try:
            response = requests.get(url, headers=self.headers, timeout=30)
            response.raise_for_status()
            
            # Sauvegarder dans le cache
            with open(cache_path, 'wb') as f:
                f.write(response.content)
            
            return str(cache_path)
            
        except Exception as e:
            print(f"Erreur téléchargement image {asset_id}: {e}")
            return None
    
    def clear_old_cache(self, max_age_hours: int = 24):
        """Nettoyer les vieux fichiers du cache"""
        current_time = time.time()
        for file_path in self.temp_dir.glob("*.jpg"):
            if current_time - file_path.stat().st_mtime > max_age_hours * 3600:
                file_path.unlink()

# Instance globale du loader
immich_loader = None

@duplicates_bp.route('/api/duplicates/find-similar', methods=['POST'])
def find_similar():
    """Trouver les images similaires à partir d'une sélection"""
    global immich_loader
    
    try:
        data = request.json
        gallery_id = data.get('gallery_id')
        asset_ids = data.get('asset_ids', [])
        threshold = float(data.get('threshold', 0.85))
        time_window = int(data.get('time_window', 24))
        
        # Configuration Immich
        immich_config = data.get('immich_config', {})
        if not immich_loader and immich_config:
            immich_loader = ImmichImageLoader(
                immich_config.get('api_url'),
                immich_config.get('api_key')
            )
        
        if not immich_loader:
            return jsonify({
                'success': False,
                'error': 'Configuration Immich manquante'
            }), 400
        
        # Nettoyer le cache
        immich_loader.clear_old_cache()
        
        # TODO: Récupérer les métadonnées depuis votre base de données
        # Pour l'exemple, on simule
        groups = []
        
        return jsonify({
            'success': True,
            'groups': groups,
            'total_groups': len(groups)
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@duplicates_bp.route('/api/duplicates/analyze-album/<gallery_id>', methods=['POST'])
def analyze_album(gallery_id):
    """Analyser tout un album pour trouver les doublons (SSE)"""
    global immich_loader
    
    threshold = float(request.args.get('threshold', 0.85))
    
    # Configuration Immich depuis les données POST
    data = request.json or {}
    immich_config = data.get('immich_config', {})
    
    if not immich_loader and immich_config:
        immich_loader = ImmichImageLoader(
            immich_config.get('api_url'),
            immich_config.get('api_key')
        )
    
    def generate():
        try:
            yield f"data: {json.dumps({'event': 'start', 'data': {'gallery_id': gallery_id}})}\n\n"
            
            # TODO: Récupérer les assets de la galerie depuis votre DB
            # Pour l'exemple, on suppose qu'on a une fonction get_gallery_assets
            assets = get_gallery_assets_from_db(gallery_id)
            total = len(assets)
            
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 0, 'details': f'Analyse de {total} images'}})}\n\n"
            
            # Préparer les données pour le service
            images_data = []
            
            for i, asset in enumerate(assets):
                # Télécharger l'image depuis Immich
                image_path = immich_loader.get_image_path(asset['immich_asset_id'], 'preview')
                
                if image_path:
                    images_data.append({
                        'id': asset['immich_asset_id'],
                        'path': image_path,
                        'filename': asset.get('filename', f"IMG_{asset['immich_asset_id']}.jpg"),
                        'date': asset.get('created_at', '2024-01-01T00:00:00'),
                        'thumbnail_url': f"/image-proxy.php?id={asset['immich_asset_id']}&type=thumbnail"
                    })
                
                if i % 10 == 0:
                    progress = int((i / total) * 30)
                    yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': progress, 'details': f'Téléchargement: {i}/{total}'}})}\n\n"
            
            # Analyser avec le service de doublons
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 30, 'details': 'Calcul des similarités'}})}\n\n"
            
            def progress_callback(progress, details):
                # Ajuster le progress pour qu'il aille de 30 à 90
                adjusted_progress = 30 + int(progress * 0.6)
                yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': adjusted_progress, 'details': details}})}\n\n"
            
            groups = duplicate_service.analyze_album_for_duplicates(
                images_data,
                threshold=threshold,
                time_window_hours=24,
                progress_callback=progress_callback
            )
            
            # Convertir les groupes en format sérialisable
            groups_data = []
            for group in groups:
                groups_data.append({
                    'group_id': group.group_id,
                    'images': [
                        {
                            'asset_id': img.asset_id,
                            'similarity': img.similarity,
                            'filename': img.filename,
                            'date': img.date,
                            'thumbnail_url': img.thumbnail_url,
                            'is_primary': img.is_primary,
                            'quality_score': getattr(img, 'quality_score', 0),
                            'blur_score': getattr(img, 'blur_score', 0)
                        }
                        for img in group.images
                    ],
                    'similarity_avg': group.similarity_avg,
                    'total_images': group.total_images
                })
            
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 100, 'details': 'Analyse terminée'}})}\n\n"
            yield f"data: {json.dumps({'event': 'complete', 'data': {'groups': groups_data, 'total_groups': len(groups_data)}})}\n\n"
            
        except Exception as e:
            yield f"data: {json.dumps({'event': 'error', 'data': {'error': str(e)}})}\n\n"
    
    return Response(generate(), mimetype='text/event-stream')

@duplicates_bp.route('/api/duplicates/merge-metadata', methods=['POST'])
def merge_metadata():
    """Fusionner les métadonnées de plusieurs images"""
    try:
        data = request.json
        group_id = data.get('group_id')
        asset_ids = data.get('asset_ids', [])
        
        # TODO: Implémenter la fusion des métadonnées
        # - Récupérer les métadonnées de chaque image depuis Immich
        # - Fusionner les tags, descriptions, etc.
        # - Appliquer à l'image principale
        
        return jsonify({
            'success': True,
            'message': 'Métadonnées fusionnées'
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@duplicates_bp.route('/api/duplicates/stats', methods=['GET'])
def get_stats():
    """Obtenir les statistiques du service"""
    stats = duplicate_service.get_stats()
    return jsonify(stats)

def get_gallery_assets_from_db(gallery_id):
    """
    Récupérer les assets depuis l'API PHP
    """
    try:
        # Appeler l'endpoint PHP pour récupérer les assets
        # Ajuster l'URL selon votre configuration
        php_url = "http://localhost/immich-gallery/admin/helpers/duplicate-db.php"
        
        response = requests.get(php_url, params={
            'action': 'get_gallery_assets',
            'gallery_id': gallery_id
        })
        
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return data.get('assets', [])
        
        # Fallback : retourner une liste vide
        return []
        
    except Exception as e:
        print(f"Erreur récupération assets: {e}")
        return []
