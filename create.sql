-- Schema de base de données pour le système de galeries avec interactions utilisateurs
-- Compatible PostgreSQL et MySQL

-- Table des utilisateurs (authentification sociale)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    provider VARCHAR(50) NOT NULL, -- 'facebook', 'google', 'apple'
    provider_id VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    avatar_url TEXT,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(provider, provider_id)
);

-- Table des galeries créées par l'admin
CREATE TABLE galleries (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) NOT NULL UNIQUE,
    cover_image_url TEXT,
    
    -- Paramètres de visibilité
    is_public BOOLEAN DEFAULT FALSE,
    requires_auth BOOLEAN DEFAULT FALSE,
    
    -- Métadonnées
    view_count INTEGER DEFAULT 0,
    image_count INTEGER DEFAULT 0,
    
    -- Auto-generation des légendes
    auto_generate_captions BOOLEAN DEFAULT TRUE,
    caption_model VARCHAR(100) DEFAULT 'blip-base',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_galleries_slug (slug),
    INDEX idx_galleries_public (is_public)
);

-- Association galeries <-> albums Immich
CREATE TABLE gallery_immich_albums (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    immich_album_id VARCHAR(255) NOT NULL,
    immich_album_name VARCHAR(255),
    sort_order INTEGER DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(gallery_id, immich_album_id),
    INDEX idx_gallery_albums_gallery (gallery_id)
);

-- Table des images avec métadonnées et légendes
CREATE TABLE gallery_images (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    immich_asset_id VARCHAR(255) NOT NULL,
    
    -- URLs et dimensions
    thumbnail_url TEXT,
    full_url TEXT,
    width INTEGER,
    height INTEGER,
    
    -- Légendes et métadonnées
    caption TEXT,
    caption_source VARCHAR(50) DEFAULT 'auto', -- 'auto', 'manual', 'user'
    caption_model VARCHAR(100),
    caption_confidence DECIMAL(3,2),
    
    -- EXIF et métadonnées Immich
    exif_data JSON,
    immich_metadata JSON,
    
    -- Gestion par utilisateurs
    user_submitted BOOLEAN DEFAULT FALSE,
    submitted_by_user_id INTEGER REFERENCES users(id),
    moderation_status VARCHAR(50) DEFAULT 'approved', -- 'pending', 'approved', 'rejected'
    
    -- Statistiques
    view_count INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(gallery_id, immich_asset_id),
    INDEX idx_images_gallery (gallery_id),
    INDEX idx_images_immich_asset (immich_asset_id),
    INDEX idx_images_moderation (moderation_status)
);

-- Table des commentaires (galeries et images)
CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    image_id INTEGER REFERENCES gallery_images(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, -- Pour les réponses
    
    content TEXT NOT NULL,
    
    -- Modération
    is_approved BOOLEAN DEFAULT TRUE,
    moderation_reason TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_comments_gallery (gallery_id),
    INDEX idx_comments_image (image_id),
    INDEX idx_comments_user (user_id),
    INDEX idx_comments_parent (parent_id),
    INDEX idx_comments_approved (is_approved)
);

-- Table des demandes de suppression
CREATE TABLE deletion_requests (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    image_id INTEGER REFERENCES gallery_images(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    
    reason TEXT,
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    admin_response TEXT,
    processed_by_admin_id INTEGER REFERENCES users(id),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    
    INDEX idx_deletion_requests_status (status),
    INDEX idx_deletion_requests_user (user_id)
);

-- Table des uploads utilisateurs
CREATE TABLE user_uploads (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_size INTEGER,
    mime_type VARCHAR(100),
    
    -- Status de modération
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    rejection_reason TEXT,
    processed_by_admin_id INTEGER REFERENCES users(id),
    
    -- Métadonnées auto-générées
    auto_caption TEXT,
    caption_confidence DECIMAL(3,2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    
    INDEX idx_user_uploads_gallery (gallery_id),
    INDEX idx_user_uploads_user (user_id),
    INDEX idx_user_uploads_status (status)
);

-- Table des sessions utilisateurs (pour l'auth sociale)
CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    provider_access_token TEXT,
    provider_refresh_token TEXT,
    
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_sessions_token (session_token),
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_expires (expires_at)
);

-- Table de cache pour les légendes IA
CREATE TABLE caption_cache (
    id SERIAL PRIMARY KEY,
    immich_asset_id VARCHAR(255) NOT NULL UNIQUE,
    
    -- Différents modèles de légendes
    blip_base_caption TEXT,
    blip_large_caption TEXT,
    custom_model_caption TEXT,
    
    -- Métadonnées du traitement
    processing_model VARCHAR(100),
    confidence_score DECIMAL(3,2),
    processing_time INTEGER, -- en millisecondes
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_caption_cache_asset (immich_asset_id)
);

-- Table des permissions d'accès aux galeries
CREATE TABLE gallery_permissions (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    
    permission_type VARCHAR(50) NOT NULL, -- 'view', 'comment', 'upload', 'moderate'
    granted_by_admin_id INTEGER REFERENCES users(id),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(gallery_id, user_id, permission_type),
    INDEX idx_permissions_gallery (gallery_id),
    INDEX idx_permissions_user (user_id)
);

-- Table des analytics et statistiques
CREATE TABLE gallery_analytics (
    id SERIAL PRIMARY KEY,
    gallery_id INTEGER REFERENCES galleries(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    
    action_type VARCHAR(50) NOT NULL, -- 'view', 'comment', 'upload', 'share'
    target_id INTEGER, -- ID de l'image si applicable
    
    -- Données de session
    ip_address INET,
    user_agent TEXT,
    referrer TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_analytics_gallery (gallery_id),
    INDEX idx_analytics_action (action_type),
    INDEX idx_analytics_date (created_at)
);

-- Vue pour les statistiques des galeries
CREATE VIEW gallery_stats AS
SELECT 
    g.id,
    g.name,
    g.slug,
    COUNT(DISTINCT gi.id) as total_images,
    COUNT(DISTINCT c.id) as total_comments,
    COUNT(DISTINCT ga.id) as total_views,
    COUNT(DISTINCT uu.id) as pending_uploads,
    COUNT(DISTINCT dr.id) as pending_deletions,
    MAX(ga.created_at) as last_activity
FROM galleries g
LEFT JOIN gallery_images gi ON g.id = gi.gallery_id
LEFT JOIN comments c ON g.id = c.gallery_id
LEFT JOIN gallery_analytics ga ON g.id = ga.gallery_id AND ga.action_type = 'view'
LEFT JOIN user_uploads uu ON g.id = uu.gallery_id AND uu.status = 'pending'
LEFT JOIN deletion_requests dr ON g.id = dr.gallery_id AND dr.status = 'pending'
GROUP BY g.id, g.name, g.slug;

-- Index composites pour les performances
CREATE INDEX idx_comments_gallery_approved ON comments(gallery_id, is_approved);
CREATE INDEX idx_images_gallery_status ON gallery_images(gallery_id, moderation_status);
CREATE INDEX idx_analytics_gallery_date ON gallery_analytics(gallery_id, created_at);

-- Triggers pour maintenir les compteurs
-- (À adapter selon le SGBD utilisé)

-- Fonction pour mettre à jour le compteur d'images
CREATE OR REPLACE FUNCTION update_gallery_image_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE galleries 
        SET image_count = image_count + 1 
        WHERE id = NEW.gallery_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE galleries 
        SET image_count = image_count - 1 
        WHERE id = OLD.gallery_id;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Trigger pour les images
CREATE TRIGGER trigger_update_image_count
    AFTER INSERT OR DELETE ON gallery_images
    FOR EACH ROW EXECUTE FUNCTION update_gallery_image_count();

-- Fonction pour mettre à jour le compteur de commentaires
CREATE OR REPLACE FUNCTION update_image_comment_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' AND NEW.image_id IS NOT NULL THEN
        UPDATE gallery_images 
        SET comment_count = comment_count + 1 
        WHERE id = NEW.image_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' AND OLD.image_id IS NOT NULL THEN
        UPDATE gallery_images 
        SET comment_count = comment_count - 1 
        WHERE id = OLD.image_id;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Trigger pour les commentaires
CREATE TRIGGER trigger_update_comment_count
    AFTER INSERT OR DELETE ON comments
    FOR EACH ROW EXECUTE FUNCTION update_image_comment_count();