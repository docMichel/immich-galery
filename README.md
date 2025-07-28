# Immich PhotoSwipe Gallery

## Installation

1. Placez ce dossier dans votre répertoire web (www)
2. Configurez `config/config.php` avec vos paramètres Immich
3. Importez `data/database.sql` dans votre base MySQL
4. Rendez les dossiers uploads/ et cache/ accessibles en écriture
5. Accédez à public/admin pour configurer vos galeries

## Structure

- `public/` - Fichiers web accessibles
- `src/` - Code PHP (modèles, services)
- `config/` - Configuration
- `data/` - Données (géolocalisation, UNESCO)
- `uploads/` - Photos uploadées par users
- `cache/` - Cache des thumbnails et données

## Configuration Immich

Récupérez votre clé API depuis Immich > Account Settings > API Keys
