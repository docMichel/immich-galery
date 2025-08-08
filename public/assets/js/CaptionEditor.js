// public/assets/js/CaptionEditor.js
// Module pour l'édition de légendes avec IA via SSE

class CaptionEditor {
    constructor() {
        this.config = window.captionEditorConfig || {};
        this.flaskApiUrl = this.config.flaskApiUrl || 'http://localhost:5001';
        this.assetId = this.config.assetId;
        this.galleryId = this.config.galleryId;
        this.latitude = this.config.latitude;
        this.longitude = this.config.longitude;

        // On ne stocke pas SSEManager ici, on le récupérera quand on en aura besoin
        this.currentRequestId = null;
        this.init();
    }

    // Méthode pour obtenir SSEManager de façon sûre
    getSSEManager() {
        if (!window.sseManager) {
            // Si pas encore disponible, essayer de le créer
            if (window.SSEManager) {
                window.sseManager = new window.SSEManager();
            } else {
                console.error('SSEManager non trouvé. Assurez-vous que SSEManager.js est chargé.');
                return null;
            }
        }
        return window.sseManager;
    }

    init() {
        // Boutons
        this.btnGenerate = document.getElementById('btnGenerate');
        this.btnRegenerate = document.getElementById('btnRegenerate');

        // Textareas
        this.imageDescription = document.getElementById('imageDescription');
        this.geoContext = document.getElementById('geoContext');
        this.culturalEnrichment = document.getElementById('culturalEnrichment');
        this.finalCaption = document.getElementById('finalCaption');

        // Options
        this.languageSelect = document.getElementById('language');
        this.styleSelect = document.getElementById('style');

        // UI
        this.progressContainer = document.getElementById('progressContainer');
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
        this.messageBox = document.getElementById('messageBox');

        // Event listeners
        if (this.btnGenerate) {
            this.btnGenerate.addEventListener('click', () => this.generateCaption());
        }

        if (this.btnRegenerate) {
            this.btnRegenerate.addEventListener('click', () => this.regenerateCaption());
        }

        // Auto-save sur changement de la légende finale
        if (this.finalCaption) {
            this.finalCaption.addEventListener('input', () => {
                this.saveToLocalStorage();
            });
        }

        // Restaurer depuis localStorage
        this.restoreFromLocalStorage();
    }

    async generateCaption() {
        const sseManager = this.getSSEManager();
        if (!sseManager) {
            this.showMessage('Erreur: SSEManager non disponible', 'error');
            return;
        }

        try {
            this.showProgress();
            this.btnGenerate.disabled = true;

            // Générer un request ID unique
            this.currentRequestId = `caption-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

            // Préparer les données - Structure similaire au test
            const requestData = {
                request_id: this.currentRequestId,
                asset_id: this.assetId,
                language: this.languageSelect?.value || 'français',
                style: this.styleSelect?.value || 'creative'
            };

            // Ajouter les coordonnées GPS si disponibles
            if (this.latitude !== null && this.longitude !== null) {
                requestData.latitude = this.latitude;
                requestData.longitude = this.longitude;
            }

            // Si on a des contenus existants, les ajouter
            if (this.imageDescription.value) {
                requestData.existing_description = this.imageDescription.value;
            }
            if (this.geoContext.value) {
                requestData.existing_geo_context = this.geoContext.value;
            }
            if (this.culturalEnrichment.value) {
                requestData.existing_cultural = this.culturalEnrichment.value;
            }

            // OPTIONNEL : Si on a l'image en local (par ex depuis le proxy)
            // On pourrait l'ajouter, mais ce n'est pas nécessaire si Flask peut la récupérer
            if (window.captionEditorConfig.includeImageData) {
                // Récupérer l'image depuis l'élément img si nécessaire
                const mainImage = document.getElementById('mainImage');
                if (mainImage && mainImage.src) {
                    // Si l'image est déjà en base64
                    if (mainImage.src.startsWith('data:')) {
                        requestData.image_base64 = mainImage.src;
                    }
                    // Sinon on laisse Flask la récupérer depuis Immich
                }
            }

            // Utiliser le bon endpoint comme dans le test
            const response = await fetch(`${this.flaskApiUrl}/api/ai/generate-caption-async`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                mode: 'cors',
                credentials: 'omit',
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`HTTP ${response.status}: ${errorData.error || 'Erreur inconnue'}`);
            }

            const result = await response.json();

            if (result.success) {
                // Se connecter au stream SSE
                this.connectSSE(this.currentRequestId);
            } else {
                throw new Error(result.error || 'Erreur lors du lancement de la génération');
            }

        } catch (error) {
            console.error('Erreur:', error);
            this.showMessage(`Erreur: ${error.message}`, 'error');
            this.hideProgress();
            this.btnGenerate.disabled = false;
        }
    }

    connectSSE(requestId) {
        const sseManager = this.getSSEManager();
        if (!sseManager) {
            this.showMessage('Erreur: SSEManager non disponible', 'error');
            this.hideProgress();
            this.btnGenerate.disabled = false;
            return;
        }

        // Utiliser le bon endpoint pour SSE
        const sseUrl = `${this.flaskApiUrl}/api/ai/generate-caption-stream/${requestId}`;

        // Indicateur de heartbeat
        let lastHeartbeat = Date.now();
        const heartbeatElement = document.createElement('div');
        heartbeatElement.className = 'heartbeat-indicator';
        heartbeatElement.innerHTML = '💓';
        this.progressContainer.appendChild(heartbeatElement);

        sseManager.connect(`caption-${requestId}`, sseUrl, {
            onProgress: (progress, details, step) => {
                this.updateProgress(progress, details);

                // Mise à jour progressive des champs si on reçoit des résultats
                // Le step peut contenir les données ou être une string simple
            },

            onResult: (data) => {
                console.log('Résultat intermédiaire:', data);

                // Mettre à jour les champs selon l'étape
                if (data.step === 'image_analysis' && data.result) {
                    if (data.result.description) {
                        this.imageDescription.value = data.result.description;
                    }
                } else if (data.step === 'geolocation' && data.result) {
                    const geoText = [
                        data.result.location_basic,
                        data.result.cultural_context
                    ].filter(text => text && text.trim()).join('\n');

                    if (geoText) {
                        this.geoContext.value = geoText;
                    }
                } else if (data.step === 'cultural_enrichment' && data.result) {
                    if (data.result.enrichment) {
                        this.culturalEnrichment.value = data.result.enrichment;
                    }
                } else if (data.step === 'raw_caption' && data.result) {
                    if (data.result.caption) {
                        // Optionnel : montrer la légende brute avant post-traitement
                        this.finalCaption.value = data.result.caption;
                    }
                }
            },

            onComplete: (data) => {
                console.log('Génération terminée:', data);

                // FIX: Extraire les vraies données du double wrapping
                let realData = data;
                if (data && data.data) {
                    realData = data.data;
                }
                if (realData && realData.data) {
                    realData = realData.data;
                }

                console.log('Données extraites:', realData);
                // Retirer l'indicateur de heartbeat
                if (heartbeatElement.parentNode) {
                    heartbeatElement.remove();
                }
                // Vérifier que les données sont valides
                if (!realData || typeof realData !== 'object') {
                    console.error('Structure de données invalide:', data);
                    this.showMessage('Erreur: données reçues invalides', 'error');
                    this.hideProgress();
                    this.btnGenerate.disabled = false;
                    return;
                }

                // Mise à jour des champs avec la structure correcte de la réponse
                if (realData.intermediate_results) {
                    // Description de l'image
                    if (realData.intermediate_results.image_analysis && realData.intermediate_results.image_analysis.description) {
                        this.imageDescription.value = realData.intermediate_results.image_analysis.description;
                    }

                    // Contexte géographique
                    if (realData.intermediate_results.geo_context) {
                        const geoContext = realData.intermediate_results.geo_context;
                        // Combiner les infos géo disponibles
                        const geoText = [
                            geoContext.location_basic,
                            geoContext.cultural_context
                        ].filter(text => text && text.trim()).join('\n');

                        if (geoText) {
                            this.geoContext.value = geoText;
                        } else if (geoContext.location_basic) {
                            // Fallback sur les coordonnées si c'est tout ce qu'on a
                            this.geoContext.value = `Coordonnées: ${geoContext.location_basic}`;
                        }
                    }

                    // Enrichissement culturel
                    if (realData.intermediate_results.cultural_enrichment) {
                        this.culturalEnrichment.value = realData.intermediate_results.cultural_enrichment;
                    }
                }

                // Légende finale
                if (realData.caption) {
                    this.finalCaption.value = realData.caption;
                    this.saveToLocalStorage();
                }

                // Afficher le score de confiance
                if (realData.confidence_score !== undefined) {
                    const confidenceText = `Confiance: ${(realData.confidence_score * 100).toFixed(0)}%`;
                    this.showMessage(`Légende générée avec succès! (${confidenceText})`, 'success');
                } else {
                    this.showMessage('Légende générée avec succès!', 'success');
                }

                this.hideProgress();
                this.btnGenerate.disabled = false;
                this.btnRegenerate.disabled = false;
            },

            onError: (error) => {
                console.error('Erreur SSE:', error);

                // Retirer l'indicateur de heartbeat
                if (heartbeatElement.parentNode) {
                    heartbeatElement.remove();
                }

                this.showMessage(`Erreur: ${error}`, 'error');
                this.hideProgress();
                this.btnGenerate.disabled = false;
            },

            onLog: (logEntry) => {
                console.log(`[${logEntry.type}] ${logEntry.message}`);

                // Détecter les heartbeats
                if (logEntry.type === 'heartbeat') {
                    lastHeartbeat = Date.now();
                    // Animation du heartbeat
                    heartbeatElement.classList.add('pulse');
                    setTimeout(() => {
                        heartbeatElement.classList.remove('pulse');
                    }, 300);
                }

                // Logs de debug pour géolocalisation
                if (logEntry.message && logEntry.message.includes('géo')) {
                    console.warn('🌍 Info géo:', logEntry);
                }

                // Optionnel : afficher certains logs à l'utilisateur
                if (logEntry.type === 'error' || logEntry.type === 'warning') {
                    this.showMessage(logEntry.message, logEntry.type);
                }
            }
        });

        // Vérifier la connexion toutes les 5 secondes
        const heartbeatChecker = setInterval(() => {
            const timeSinceLastHeartbeat = Date.now() - lastHeartbeat;
            if (timeSinceLastHeartbeat > 35000) { // Plus de 35 secondes sans heartbeat
                clearInterval(heartbeatChecker);
                if (heartbeatElement.parentNode) {
                    heartbeatElement.remove();
                }
                this.showMessage('Connexion perdue avec le serveur', 'warning');
            }
        }, 5000);
    }

    async regenerateCaption() {
        // Effacer les champs intermédiaires mais garder la légende finale
        this.imageDescription.value = '';
        this.geoContext.value = '';
        this.culturalEnrichment.value = '';

        // Relancer la génération
        await this.generateCaption();
    }

    updateProgress(percent, text) {
        this.progressFill.style.width = `${percent}%`;
        this.progressText.textContent = text || `${percent}%`;
    }

    showProgress() {
        this.progressContainer.style.display = 'block';
        this.progressFill.style.width = '0%';
        this.progressText.textContent = 'Initialisation...';
    }

    hideProgress() {
        setTimeout(() => {
            this.progressContainer.style.display = 'none';
        }, 500);
    }

    showMessage(text, type = 'info') {
        this.messageBox.textContent = text;
        this.messageBox.className = `message-box ${type}`;
        this.messageBox.style.display = 'block';

        // Auto-hide après 5 secondes
        setTimeout(() => {
            this.messageBox.style.display = 'none';
        }, 5000);
    }

    saveToLocalStorage() {
        const data = {
            imageDescription: this.imageDescription.value,
            geoContext: this.geoContext.value,
            culturalEnrichment: this.culturalEnrichment.value,
            finalCaption: this.finalCaption.value,
            language: this.languageSelect?.value,
            style: this.styleSelect?.value
        };

        localStorage.setItem(`caption-${this.assetId}`, JSON.stringify(data));
    }

    restoreFromLocalStorage() {
        const saved = localStorage.getItem(`caption-${this.assetId}`);
        if (saved) {
            try {
                const data = JSON.parse(saved);

                if (realData.imageDescription) this.imageDescription.value = realData.imageDescription;
                if (realData.geoContext) this.geoContext.value = realData.geoContext;
                if (realData.culturalEnrichment) this.culturalEnrichment.value = realData.culturalEnrichment;
                if (realData.finalCaption) this.finalCaption.value = realData.finalCaption;
                if (realData.language && this.languageSelect) this.languageSelect.value = realData.language;
                if (realData.style && this.styleSelect) this.styleSelect.value = realData.style;

            } catch (e) {
                console.error('Erreur lors de la restauration depuis localStorage:', e);
            }
        }
    }

    // Méthode pour nettoyer les connexions SSE lors de la fermeture
    cleanup() {
        const sseManager = this.getSSEManager();
        if (this.currentRequestId && sseManager) {
            sseManager.close(`caption-${this.currentRequestId}`);
        }
    }
}

// Initialiser l'éditeur au chargement de la page
let captionEditor;

// Fonction pour initialiser quand tout est prêt
function initializeCaptionEditor() {
    // Vérifier si SSEManager est disponible
    if (typeof SSEManager !== 'undefined') {
        // Créer l'instance si elle n'existe pas
        if (!window.sseManager) {
            window.sseManager = new SSEManager();
            console.log('SSEManager instance créée par CaptionEditor');
        }

        // Créer CaptionEditor
        captionEditor = new CaptionEditor();
        console.log('CaptionEditor initialisé');
    } else {
        // Réessayer dans 100ms
        console.log('SSEManager pas encore disponible, nouvelle tentative dans 100ms...');
        setTimeout(initializeCaptionEditor, 100);
    }
}

// Lancer l'initialisation quand le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCaptionEditor);
} else {
    // DOM déjà chargé
    initializeCaptionEditor();
}

// Nettoyer lors de la fermeture de la fenêtre
window.addEventListener('beforeunload', () => {
    if (captionEditor) {
        captionEditor.cleanup();
    }
});

// Export pour usage externe si nécessaire
window.CaptionEditor = CaptionEditor;