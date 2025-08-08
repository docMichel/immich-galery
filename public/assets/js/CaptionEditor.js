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

        this.currentRequestId = null;
        this.init();
    }

    // Méthode pour obtenir SSEManager de façon sûre
    getSSEManager() {
        if (!window.sseManager) {
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

        // Textareas pour les résultats intermédiaires
        this.imageDescription = document.getElementById('imageDescription');
        this.geoContext = document.getElementById('geoContext');
        this.culturalEnrichment = document.getElementById('culturalEnrichment');
        this.finalCaption = document.getElementById('finalCaption');

        // Options
        this.languageSelect = document.getElementById('language');
        this.styleSelect = document.getElementById('style');

        // UI Progress
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
            this.btnRegenerate.disabled = true;

            // Vider les champs pour la nouvelle génération
            this.clearFields();

            // Générer un request ID unique
            this.currentRequestId = `caption-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

            // Préparer les données
            const requestData = {
                request_id: this.currentRequestId,
                asset_id: this.assetId,
                language: this.languageSelect?.value || 'français',
                style: this.styleSelect?.value || 'creative',
                include_hashtags: true
            };

            // Ajouter les coordonnées GPS si disponibles
            if (this.latitude !== null && this.longitude !== null) {
                requestData.latitude = this.latitude;
                requestData.longitude = this.longitude;
            }

            // Lancer la génération
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
            this.btnRegenerate.disabled = false;
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

        const sseUrl = `${this.flaskApiUrl}/api/ai/generate-caption-stream/${requestId}`;
        console.log('🔌 Connexion SSE:', sseUrl);

        // Map des étapes pour l'affichage
        const stepLabels = {
            'preparation': 'Préparation',
            'image_analysis': 'Analyse de l\'image',
            'geolocation': 'Géolocalisation',
            'cultural_enrichment': 'Enrichissement culturel',
            'travel_enrichment': 'Travel Llama',
            'caption_generation': 'Génération créative',
            'hashtags': 'Hashtags',
            'post_processing': 'Finalisation'
        };

        sseManager.connect(`caption-${requestId}`, sseUrl, {
            onProgress: (progress, message, step) => {
                console.log('📊 Progress:', { progress, message, step });
                
                // Mettre à jour la barre de progression
                this.updateProgress(progress, message);
                
                // Mettre à jour le label de l'étape si disponible
                if (step && stepLabels[step]) {
                    this.progressText.textContent = `${stepLabels[step]}: ${message}`;
                }
            },

            onResult: (step, result) => {
                console.log('📝 Résultat intermédiaire:', { step, result });
                
                // Traiter selon l'étape
                switch(step) {
                    case 'image_analysis':
                        if (result.description) {
                            this.imageDescription.value = result.description;
                            this.animateField(this.imageDescription);
                        }
                        break;
                        
                    case 'geolocation':
                        if (result.location_basic || result.cultural_context) {
                            const geoText = [
                                result.location_basic,
                                result.cultural_context
                            ].filter(text => text && text.trim()).join('\n\n');
                            
                            if (geoText) {
                                this.geoContext.value = geoText;
                                this.animateField(this.geoContext);
                            }
                        }
                        break;
                        
                    case 'cultural_enrichment':
                        if (result.enrichment) {
                            this.culturalEnrichment.value = result.enrichment;
                            this.animateField(this.culturalEnrichment);
                        }
                        break;
                        
                    case 'travel_enrichment':
                        if (result.enrichment) {
                            // Ajouter à l'enrichissement culturel
                            const currentEnrichment = this.culturalEnrichment.value;
                            this.culturalEnrichment.value = currentEnrichment + 
                                (currentEnrichment ? '\n\n🌍 Travel Llama:\n' : '') + 
                                result.enrichment;
                            this.animateField(this.culturalEnrichment);
                        }
                        break;
                        
                    case 'caption_generation':
                    case 'raw_caption':
                        if (result.caption) {
                            this.finalCaption.value = result.caption;
                            this.animateField(this.finalCaption);
                        }
                        break;
                        
                    default:
                        console.log(`Étape non gérée: ${step}`, result);
                }
            },

            onComplete: (data) => {
                console.log('✅ Génération terminée:', data);
                
                // Traiter les données finales
                if (data.success) {
                    // Légende finale
                    if (data.caption) {
                        this.finalCaption.value = data.caption;
                        this.animateField(this.finalCaption);
                    }
                    
                    // Hashtags (optionnel)
                    if (data.hashtags && data.hashtags.length > 0) {
                        const hashtagsText = '\n\n' + data.hashtags.join(' ');
                        this.finalCaption.value += hashtagsText;
                    }
                    
                    // Remplir les champs intermédiaires si pas déjà fait
                    if (data.intermediate_results) {
                        const ir = data.intermediate_results;
                        
                        if (ir.image_analysis && !this.imageDescription.value) {
                            this.imageDescription.value = ir.image_analysis.description || '';
                        }
                        
                        if (ir.geo_context && !this.geoContext.value) {
                            const geo = ir.geo_context;
                            this.geoContext.value = [
                                geo.location_basic,
                                geo.cultural_context
                            ].filter(t => t).join('\n\n');
                        }
                        
                        if (ir.cultural_enrichment && !this.culturalEnrichment.value) {
                            this.culturalEnrichment.value = ir.cultural_enrichment;
                        }
                        
                        if (ir.travel_enrichment && !this.culturalEnrichment.value.includes('Travel Llama')) {
                            this.culturalEnrichment.value += '\n\n🌍 Travel Llama:\n' + ir.travel_enrichment;
                        }
                    }
                    
                    // Score de confiance
                    const confidence = data.confidence_score || 0;
                    const confidenceText = `(Confiance: ${(confidence * 100).toFixed(0)}%)`;
                    
                    // Message de succès
                    this.showMessage(`Légende générée avec succès! ${confidenceText}`, 'success');
                    this.saveToLocalStorage();
                    
                } else {
                    this.showMessage('Erreur: Génération échouée', 'error');
                }
                
                this.hideProgress();
                this.btnGenerate.disabled = false;
                this.btnRegenerate.disabled = false;
            },

            onError: (error) => {
                console.error('❌ Erreur SSE:', error);
                this.showMessage(`Erreur: ${error}`, 'error');
                this.hideProgress();
                this.btnGenerate.disabled = false;
                this.btnRegenerate.disabled = false;
            },

            onWarning: (message) => {
                console.warn('⚠️ Warning:', message);
                // Afficher temporairement le warning
                this.showMessage(message, 'warning');
            }
        });
    }

    clearFields() {
        // Vider tous les champs sauf les options
        this.imageDescription.value = '';
        this.geoContext.value = '';
        this.culturalEnrichment.value = '';
        this.finalCaption.value = '';
    }

    animateField(field) {
        // Animation visuelle quand un champ est mis à jour
        if (field) {
            field.classList.add('field-updated');
            setTimeout(() => {
                field.classList.remove('field-updated');
            }, 1000);
        }
    }

    async regenerateCaption() {
        // Relancer la génération complète
        await this.generateCaption();
    }

    updateProgress(percent, text) {
        this.progressFill.style.width = `${percent}%`;
        this.progressText.textContent = text || `${percent}%`;
        
        // Couleur selon le pourcentage
        if (percent < 30) {
            this.progressFill.style.backgroundColor = '#3498db'; // Bleu
        } else if (percent < 70) {
            this.progressFill.style.backgroundColor = '#f39c12'; // Orange
        } else {
            this.progressFill.style.backgroundColor = '#2ecc71'; // Vert
        }
    }

    showProgress() {
        this.progressContainer.style.display = 'block';
        this.progressFill.style.width = '0%';
        this.progressText.textContent = 'Initialisation...';
    }

    hideProgress() {
        // Afficher 100% avant de cacher
        this.updateProgress(100, 'Terminé!');
        setTimeout(() => {
            this.progressContainer.style.display = 'none';
        }, 1000);
    }

    showMessage(text, type = 'info') {
        this.messageBox.textContent = text;
        this.messageBox.className = `message-box ${type}`;
        this.messageBox.style.display = 'block';

        // Auto-hide après 5 secondes (sauf erreurs)
        if (type !== 'error') {
            setTimeout(() => {
                this.messageBox.style.display = 'none';
            }, 5000);
        }
    }

    saveToLocalStorage() {
        const data = {
            imageDescription: this.imageDescription.value,
            geoContext: this.geoContext.value,
            culturalEnrichment: this.culturalEnrichment.value,
            finalCaption: this.finalCaption.value,
            language: this.languageSelect?.value,
            style: this.styleSelect?.value,
            timestamp: new Date().toISOString()
        };

        localStorage.setItem(`caption-${this.assetId}`, JSON.stringify(data));
    }

    restoreFromLocalStorage() {
        const saved = localStorage.getItem(`caption-${this.assetId}`);
        if (saved) {
            try {
                const data = JSON.parse(saved);

                if (data.imageDescription) this.imageDescription.value = data.imageDescription;
                if (data.geoContext) this.geoContext.value = data.geoContext;
                if (data.culturalEnrichment) this.culturalEnrichment.value = data.culturalEnrichment;
                if (data.finalCaption) this.finalCaption.value = data.finalCaption;
                if (data.language && this.languageSelect) this.languageSelect.value = data.language;
                if (data.style && this.styleSelect) this.styleSelect.value = data.style;

            } catch (e) {
                console.error('Erreur lors de la restauration depuis localStorage:', e);
            }
        }
    }

    cleanup() {
        const sseManager = this.getSSEManager();
        if (this.currentRequestId && sseManager) {
            sseManager.close(`caption-${this.currentRequestId}`);
        }
    }
}

// CSS pour l'animation des champs
const style = document.createElement('style');
style.textContent = `
.field-updated {
    animation: fieldPulse 1s ease-out;
}

@keyframes fieldPulse {
    0% {
        background-color: #e8f5e9;
        transform: scale(1);
    }
    50% {
        background-color: #c8e6c9;
        transform: scale(1.01);
    }
    100% {
        background-color: transparent;
        transform: scale(1);
    }
}

.heartbeat-indicator {
    position: absolute;
    top: 5px;
    right: 10px;
    font-size: 20px;
}

.heartbeat-indicator.pulse {
    animation: heartbeatPulse 0.3s ease-out;
}

@keyframes heartbeatPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.progress-fill {
    transition: width 0.5s ease-out, background-color 0.5s ease-out;
}
`;
document.head.appendChild(style);

// Initialisation
let captionEditor;

function initializeCaptionEditor() {
    if (typeof SSEManager !== 'undefined') {
        if (!window.sseManager) {
            window.sseManager = new SSEManager();
            console.log('SSEManager instance créée');
        }

        captionEditor = new CaptionEditor();
        console.log('CaptionEditor initialisé');
    } else {
        console.log('SSEManager pas encore disponible, nouvelle tentative dans 100ms...');
        setTimeout(initializeCaptionEditor, 100);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCaptionEditor);
} else {
    initializeCaptionEditor();
}

window.addEventListener('beforeunload', () => {
    if (captionEditor) {
        captionEditor.cleanup();
    }
});

window.CaptionEditor = CaptionEditor;