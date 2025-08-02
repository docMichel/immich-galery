def init_services(app):
    """Initialiser tous les services"""
    services = {}
    
    try:
        logger.info("🚀 Initialisation des services...")
        
        # ... services existants ...
        
        # Service de détection de doublons
        from src.services.duplicate_detection_service import DuplicateDetectionService
        duplicate_service = DuplicateDetectionService()
        services['duplicate_service'] = duplicate_service
        logger.info("✅ DuplicateDetectionService initialisé")
        
        # Stocker les services dans la config Flask
        app.config['SERVICES'] = services
        
        logger.info("🎉 Tous les services initialisés avec succès")
        return True
        
    except Exception as e:
        logger.error(f"❌ Erreur initialisation services: {e}")
        return False