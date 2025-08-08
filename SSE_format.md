# 📡 Format des Événements SSE (Server-Sent Events)

## Vue d'ensemble

Tous les messages SSE suivent le format standard :
```
event: <event_type>
data: <json_object>

```

## 🔗 Événements de Connexion

### `connected`
Envoyé immédiatement après l'établissement de la connexion SSE.

```json
event: connected
data: {
    "message": "Connexion SSE établie",
    "request_id": "caption-1234567890-abc123",
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### `heartbeat`
Envoyé toutes les 30 secondes pour maintenir la connexion active.

```json
event: heartbeat
data: {
    "timestamp": 1705314600000
}
```

## 📊 Événements de Progression

### `progress`
Indique la progression globale de la génération avec un pourcentage et un message.

```json
event: progress
data: {
    "step": "image_analysis",
    "progress": 25,
    "message": "Analyse de l'image en cours...",
    "timestamp": "2024-01-15T10:30:05.000Z"
}
```

**Étapes possibles :**
- `preparation` : Préparation initiale
- `image_analysis` : Analyse de l'image avec LLaVA
- `geolocation` : Géolocalisation et contexte
- `cultural_enrichment` : Enrichissement culturel
- `travel_enrichment` : Enrichissement Travel Llama
- `caption_generation` : Génération de la légende
- `hashtag_generation` : Génération des hashtags
- `post_processing` : Finalisation

## 📝 Résultats Partiels

### `partial`
Contient les résultats intermédiaires de chaque étape de traitement.

#### Analyse d'image
```json
event: partial
data: {
    "type": "image_analysis",
    "content": {
        "description": "Une femme pose devant une église traditionnelle...",
        "confidence": 0.85,
        "model": "llava:7b"
    }
}
```

#### Géolocalisation
```json
event: partial
data: {
    "type": "geolocation",
    "content": {
        "location": "Tiébaghi, Nouvelle-Calédonie",
        "coordinates": [-20.4501, 164.2135],
        "confidence": 0.92,
        "nearby_places": ["Koumac", "Province Nord"],
        "cultural_sites": []
    }
}
```

#### Enrichissement Culturel
```json
event: partial
data: {
    "type": "cultural_enrichment",
    "content": {
        "text": "Tiébaghi, ancienne capitale mondiale du chrome...",
        "source": "geo_enrichment"
    }
}
```

#### Enrichissement Travel Llama
```json
event: partial
data: {
    "type": "travel_enrichment",
    "content": {
        "text": "Les anciennes mines de chrome de Tiébaghi offrent...",
        "source": "travel_llama",
        "model": "llama3.1:70b"
    }
}
```

#### Légende Brute
```json
event: partial
data: {
    "type": "raw_caption",
    "content": {
        "caption": "Dans la lumière du matin naissant...",
        "language": "français",
        "style": "creative"
    }
}
```

#### Hashtags
```json
event: partial
data: {
    "type": "hashtags",
    "content": {
        "tags": ["#Tiébaghi", "#NouvelleCalédonie", "#PatrimoineMondial"],
        "count": 8
    }
}
```

## ✅ Événement de Complétion

### `complete`
Envoyé à la fin du processus avec tous les résultats finaux.

```json
event: complete
data: {
    "success": true,
    "caption": "Dans la lumière du matin naissant, elle se pose devant l'église de Tiébaghi...",
    "hashtags": [
        "#Tiébaghi",
        "#NouvelleCalédonie", 
        "#PatrimoineMondial",
        "#VoyageAuthentique",
        "#HistoireMinière"
    ],
    "confidence_score": 0.87,
    "language": "français",
    "style": "creative",
    "processing_time": 45.3,
    "metadata": {
        "request_id": "caption-1234567890-abc123",
        "asset_id": "asset-xyz-789",
        "timestamp": "2024-01-15T10:30:50.000Z",
        "models_used": {
            "vision": "llava:7b",
            "cultural": "qwen2:7b",
            "travel": "llama3.1:70b",
            "caption": "mistral:7b-instruct"
        }
    }
}
```

## ⚠️ Événements d'Avertissement

### `warning`
Pour les avertissements non-bloquants (ex: service optionnel non disponible).

```json
event: warning
data: {
    "message": "Travel Llama non disponible, utilisation du modèle de fallback",
    "code": "MODEL_FALLBACK",
    "timestamp": "2024-01-15T10:30:20.000Z"
}
```

## ❌ Événements d'Erreur

### `error`
Pour les erreurs qui interrompent le processus.

```json
event: error
data: {
    "error": "Timeout lors de l'analyse d'image",
    "error_type": "TIMEOUT",
    "step": "image_analysis",
    "details": "Le modèle LLaVA n'a pas répondu dans les 30 secondes",
    "timestamp": "2024-01-15T10:31:00.000Z"
}
```

**Types d'erreur possibles :**
- `CONNECTION_ERROR` : Problème de connexion
- `TIMEOUT` : Dépassement du délai
- `MODEL_ERROR` : Erreur du modèle IA
- `API_ERROR` : Erreur d'API externe
- `GENERATION_ERROR` : Erreur de génération
- `UNKNOWN_ERROR` : Erreur non catégorisée

## 📋 Exemple de Flux Complet

```
1. → event: connected
2. → event: progress (0%, "Initialisation...")
3. → event: progress (10%, "Analyse de l'image...")
4. → event: partial (type: "image_analysis")
5. → event: progress (30%, "Géolocalisation...")
6. → event: partial (type: "geolocation")
7. → event: progress (50%, "Enrichissement culturel...")
8. → event: partial (type: "cultural_enrichment")
9. → event: warning ("Travel Llama non disponible")
10. → event: progress (70%, "Génération de la légende...")
11. → event: partial (type: "raw_caption")
12. → event: progress (90%, "Génération des hashtags...")
13. → event: partial (type: "hashtags")
14. → event: progress (100%, "Finalisation...")
15. → event: complete
```

## 🔧 Notes d'Implémentation

1. **Encodage** : Tous les JSON sont encodés en UTF-8
2. **Ligne vide** : Chaque message se termine par deux retours à la ligne (`\n\n`)
3. **Timeout** : La connexion est fermée après 300 secondes d'inactivité
4. **Retry** : Le client doit gérer la reconnexion automatique
5. **Buffer** : Les messages peuvent être bufferisés, utiliser `X-Accel-Buffering: no`