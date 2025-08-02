#!/usr/bin/env python3
"""
📍 src/api/duplicate_routes.py

Routes API pour la détection de doublons d'images
Le Flask récupère directement les images depuis Immich
"""

from flask import Blueprint, request, jsonify, Response, current_app
import logging
import json
import time
import requests
from typing import Dict, List
from pathlib import Path
import tempfile

# Import du service de détection
from src.services.duplicate_detection_service import DuplicateDetectionService
from src.config.server_config import ServerConfig

logger = logging.getLogger(__name__)

# Créer le blueprint
duplicate_bp = Blueprint('duplicates', __name__)

# Instance du service de détection
duplicate_service = DuplicateDetectionService()


class ImmichImageLoader:
    """Classe pour charger les images depuis Immich"""
    
    def __init__(self, proxy_url: str, api_key: str):
        self.proxy_url = proxy_url.rstrip('/')
        self.api_key = api_key
        self.headers = {'x-api-key': api_key}
        self.temp_dir = Path(tempfile.gettempdir()) / 'duplicate_detection'
        self.temp_dir.mkdir(exist_ok=True)
    
    def download_image(self, asset_id: str, size: str = 'preview') -> Path:
        """
        Télécharger une image depuis Immich
        
        Args:
            asset_id: ID de l'asset Immich
            size: 'thumbnail', 'preview' ou 'original'
        
        Returns:
            Chemin vers le fichier temporaire
        """
        # Utiliser le cache local si disponible
        cache_file = self.temp_dir / f"{asset_id}_{size}.jpg"
        if cache_file.exists() and cache_file.stat().st_mtime > time.time() - 3600:
            return cache_file
        
        # Télécharger depuis Immich
        if size == 'original':
            url = f"{self.proxy_url}/api/assets/{asset_id}/original"
        else:
            url = f"{self.proxy_url}/api/assets/{asset_id}/thumbnail?size={size}"
        
        try:
            response = requests.get(url, headers=self.headers, timeout=30)
            response.raise_for_status()
            
            # Sauvegarder dans le cache
            cache_file.write_bytes(response.content)
            return cache_file
            
        except Exception as e:
            logger.error(f"Erreur téléchargement {asset_id}: {e}")
            return None
    
    def cleanup_old_cache(self, max_age_hours: int = 24):
        """Nettoyer les vieux fichiers du cache"""
        current_time = time.time()
        for file_path in self.temp_dir.glob("*.jpg"):
            if current_time - file_path.stat().st_mtime > max_age_hours * 3600:
                file_path.unlink()


@duplicate_bp.route('/duplicates/find-similar', methods=['POST'])
def find_similar():
    """Trouver les images similaires à partir d'une sélection"""
    try:
        data = request.json
        gallery_id = data.get('gallery_id')
        asset_ids = data.get('asset_ids', [])
        threshold = float(data.get('threshold', 0.85))
        time_window = int(data.get('time_window', 24))
        
        if not gallery_id:
            return jsonify({
                'success': False,
                'error': 'gallery_id manquant'
            }), 400
        
        logger.info(f"🔍 Recherche doublons pour {len(asset_ids)} images")
        
        # Initialiser le loader Immich
        immich_loader = ImmichImageLoader(
            ServerConfig.IMMICH_PROXY_URL,
            ServerConfig.IMMICH_API_KEY
        )
        
        # Récupérer et analyser les images sélectionnées
        # TODO: Implémenter la recherche ciblée
        
        return jsonify({
            'success': True,
            'groups': [],
            'total_groups': 0
        })
        
    except Exception as e:
        logger.error(f"❌ Erreur recherche doublons: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@duplicate_bp.route('/duplicates/analyze-album/<gallery_id>', methods=['POST'])
def analyze_album(gallery_id):
    """Analyser tout un album pour trouver les doublons (SSE)"""
    threshold = float(request.args.get('threshold', 0.85))
    
    def generate():
        try:
            yield f"data: {json.dumps({'event': 'start', 'data': {'gallery_id': gallery_id}})}\n\n"
            
            # Récupérer les assets depuis la DB MySQL via requête directe
            assets = get_gallery_assets_from_db(gallery_id)
            total = len(assets)
            
            if total == 0:
                yield f"data: {json.dumps({'event': 'error', 'data': {'error': 'Aucune image dans la galerie'}})}\n\n"
                return
            
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 0, 'details': f'Analyse de {total} images'}})}\n\n"
            
            # Initialiser le loader Immich
            immich_loader = ImmichImageLoader(
                ServerConfig.IMMICH_PROXY_URL,
                ServerConfig.IMMICH_API_KEY
            )
            
            # Nettoyer le cache
            immich_loader.cleanup_old_cache()
            
            # Préparer les données pour l'analyse
            images_data = []
            download_errors = 0
            
            for i, asset in enumerate(assets):
                # Télécharger l'image depuis Immich
                image_path = immich_loader.download_image(asset['immich_asset_id'], 'preview')
                
                if image_path:
                    images_data.append({
                        'id': asset['immich_asset_id'],
                        'path': str(image_path),
                        'filename': asset.get('filename', f"IMG_{asset['immich_asset_id'][:8]}.jpg"),
                        'date': asset.get('created_at', '2024-01-01T00:00:00'),
                        'thumbnail_url': f"/image-proxy.php?id={asset['immich_asset_id']}&type=thumbnail"
                    })
                else:
                    download_errors += 1
                
                if i % 10 == 0:
                    progress = int((i / total) * 30)
                    yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': progress, 'details': f'Téléchargement: {i}/{total} (erreurs: {download_errors})'}})}\n\n"
            
            if not images_data:
                yield f"data: {json.dumps({'event': 'error', 'data': {'error': 'Impossible de télécharger les images'}})}\n\n"
                return
            
            # Analyser avec le service
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 30, 'details': 'Calcul des similarités...'}})}\n\n"
            
            def progress_callback(progress, details):
                # Ajuster le progress de 30 à 90%
                adjusted_progress = 30 + int(progress * 0.6)
                yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': adjusted_progress, 'details': details}})}\n\n"
            
            # Analyser les doublons
            groups = duplicate_service.analyze_album_for_duplicates(
                images_data,
                threshold=threshold,
                time_window_hours=24,
                progress_callback=progress_callback
            )
            
            # Convertir en format JSON
            groups_data = format_duplicate_groups(groups)
            
            yield f"data: {json.dumps({'event': 'progress', 'data': {'progress': 100, 'details': 'Analyse terminée'}})}\n\n"
            yield f"data: {json.dumps({'event': 'complete', 'data': {'groups': groups_data, 'total_groups': len(groups_data)}})}\n\n"
            
        except Exception as e:
            logger.error(f"❌ Erreur analyse doublons: {e}")
            import traceback
            logger.error(traceback.format_exc())
            yield f"data: {json.dumps({'event': 'error', 'data': {'error': str(e)}})}\n\n"
    
    return Response(generate(), mimetype='text/event-stream', headers={
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'Access-Control-Allow-Origin': '*',
        'X-Accel-Buffering': 'no'
    })


def get_gallery_assets_from_db(gallery_id: str) -> List[Dict]:
    """
    Récupérer les assets directement depuis MySQL
    """
    try:
        # Utiliser la connexion MySQL du GeoService
        from flask import current_app
        geo_service = current_app.config.get('SERVICES', {}).get('geo_service')
        
        if not geo_service:
            logger.error("GeoService non disponible")
            return []
        
        geo_service.connect_db()
        
        query = """
            SELECT 
                gi.id,
                gi.immich_asset_id,
                gi.caption,
                gi.created_at,
                gia.immich_album_id
            FROM gallery_images gi
            LEFT JOIN gallery_immich_albums gia ON gi.gallery_id = gia.gallery_id
            WHERE gi.gallery_id = %s
            ORDER BY gi.created_at ASC
        """
        
        geo_service.cursor.execute(query, (gallery_id,))
        assets = geo_service.cursor.fetchall()
        
        geo_service.disconnect_db()
        
        logger.info(f"📊 {len(assets)} assets trouvés pour galerie {gallery_id}")
        return assets
        
    except Exception as e:
        logger.error(f"❌ Erreur récupération assets DB: {e}")
        return []


def format_duplicate_groups(groups: List) -> List[Dict]:
    """Formater les groupes de doublons pour l'API"""
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
    
    return groups_data


@duplicate_bp.route('/duplicates/stats', methods=['GET'])
def get_duplicate_stats():
    """Obtenir les statistiques du service"""
    stats = duplicate_service.get_stats()
    return jsonify({
        'success': True,
        'stats': stats,
        'cache_info': {
            'temp_dir': str(Path(tempfile.gettempdir()) / 'duplicate_detection'),
            'cache_size': 'N/A'
        }
    })