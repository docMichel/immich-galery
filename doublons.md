# Guide d'intégration - Détection de doublons

## 📋 Modifications apportées

### 1. **DuplicateManager.js**
- Compatible avec votre interface modale existante
- Ajout de l'analyse de qualité optionnelle
- Nouveau bouton "⭐ Garder seulement les meilleures"
- Affichage des scores de qualité dans les thumbnails
- Modal de détails qualité pour chaque groupe

### 2. **Côté serveur Python**
- Le service charge CLIP seulement quand nécessaire
- Analyse automatique de la qualité des images
- Marquage `is_primary: true` pour la meilleure image

### 3. **Interface utilisateur**
- Les images principales ont une bordure verte
- Badge ⭐ sur la meilleure image
- Scores de qualité visibles
- Actions par groupe ou globales

## 🔧 Installation

### 1. Ajouter les styles CSS
```html
<!-- Dans edit-photos.php -->
<link rel="stylesheet" href="../public/assets/css/duplicate-modal.css">
```

### 2. Mettre à jour le template
Remplacer votre template actuel par la version avec `{{qualityInfo}}`

### 3. Ajouter le handler PHP
Dans `edit-photos-ajax.php`, ajouter le case `delete_duplicates`

## 💡 Utilisation

### Interface modale
1. **Lancer la détection** : Cliquer sur "🔍 Doublons sélection/galerie"
2. **Options** :
   - Ajuster le seuil de similarité (0.70-0.95)
   - Cocher "Analyser la qualité" pour activer le scoring
3. **Actions disponibles** :
   - Par image : Radio pour changer l'image principale
   - Par groupe : "⭐ Garder la meilleure"
   - Global : "⭐ Garder seulement les meilleures"

### Critères de qualité
- **Netteté** : Analyse de la variance du Laplacien
- **Exposition** : Distribution de l'histogramme
- **Contraste** : Écart-type des intensités
- **Résolution** : Bonus pour les hautes résolutions

### Workflow recommandé
1. Lancer la détection avec analyse de qualité
2. Vérifier les groupes (l'algo choisit déjà la meilleure)
3. Ajuster manuellement si nécessaire (radio buttons)
4. Cliquer "Garder seulement les meilleures"
5. Confirmer et sauvegarder

## 🎯 Points d'attention

### Performance
- CLIP charge en ~5-10s la première fois
- Se décharge automatiquement après 5 min
- Pour >100 images, prévoir 30-60s de traitement

### Mémoire
- CLIP utilise ~500MB RAM (ou 2GB VRAM sur GPU)
- Les embeddings sont mis en cache

### Limitations
- L'analyse de qualité nécessite `image_quality_service.py`
- Sans GPU, le traitement est plus lent
- Maximum recommandé : 500 images par batch

## 🐛 Debugging

### "CLIP non disponible"
```bash
pip install sentence-transformers scikit-learn
```

### "Timeout SSE"
- Augmenter le timeout nginx/apache
- Vérifier les logs Flask

### "Mémoire insuffisante"
- Réduire le batch_size
- Forcer CPU : modifier `device = "cpu"` dans le service

## 📊 Structure des données

### Requête
```json
{
    "request_id": "dup-1234567890",
    "selected_asset_ids": ["id1", "id2"],
    "threshold": 0.85,
    "analyze_quality": true
}
```

### Réponse
```json
{
    "groups": [{
        "group_id": "group_0",
        "similarity_avg": 0.92,
        "images": [{
            "asset_id": "abc123",
            "is_primary": true,
            "quality_score": 85.2,
            "quality_reasons": ["Image nette", "4MP"],
            "quality_metrics": {
                "sharpness": 78,
                "exposure": -5,
                "resolution": "2048x2048"
            }
        }]
    }]
}
```