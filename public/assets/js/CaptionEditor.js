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
            'hashtag_generation': 'Génération des hashtags',
            'post_processing': 'Finalisation'
        };

        // Ajouter un listener pour l'événement 'partial'
        const eventSource = sseManager.connect(`caption-${requestId}`, sseUrl, {
            onConnected: (data) => {
                console.log('✅ Connexion établie:', data);
                this.showMessage('Connexion établie avec le serveur', 'success');
            },

            onProgress: (progress, message, step) => {
                console.log('📊 Progress:', { progress, message, step });
                
                // Mettre à jour la barre de progression
                this.updateProgress(progress, message);
                
                // Mettre à jour le label de l'étape si disponible
                if (step && stepLabels[step]) {
                    this.progressText.textContent = `${stepLabels[step]}: ${message}`;
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
                    
                    // Hashtags
                    if (data.hashtags && data.hashtags.length > 0) {
                        const hashtagsText = '\n\n' + data.hashtags.join(' ');
                        this.finalCaption.value += hashtagsText;
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
                this.showMessage(message, 'warning');
            }
        });
    }

    handlePartialResult(data) {
        const type = data.type;
        const content = data.content || {};

        console.log(`📝 Traitement résultat partiel [${type}]:`, content);

        switch(type) {
            case 'image_analysis':
                if (content.description) {
                    this.imageDescription.value = content.description;
                    this.animateField(this.imageDescription);
                    
                    // Afficher la confiance si disponible
                    if (content.confidence) {
                        const confidencePercent = (content.confidence * 100).toFixed(0);
                        this.imageDescription.value += `\n[Confiance: ${confidencePercent}%]`;
                    }
                }
                break;
                
            case 'geolocation':
                const geoTexts = [];
                
                if (content.location) {
                    geoTexts.push(`📍 ${content.location}`);
                }
                
                if (content.coordinates && content.coordinates.length === 2) {
                    geoTexts.push(`GPS: ${content.coordinates[0].toFixed(6)}, ${content.coordinates[1].toFixed(6)}`);
                }
                
                if (content.nearby_places && content.nearby_places.length > 0) {
                    geoTexts.push(`Lieux proches: ${content.nearby_places.join(', ')}`);
                }
                
                if (content.cultural_sites && content.cultural_sites.length > 0) {
                    geoTexts.push(`Sites culturels: ${content.cultural_sites.join(', ')}`);
                }
                
                if (geoTexts.length > 0) {
                    this.geoContext.value = geoTexts.join('\n');
                    this.animateField(this.geoContext);
                }
                break;
                
            case 'cultural_enrichment':
                if (content.text) {
                    // Ajouter ou remplacer l'enrichissement culturel
                    const currentValue = this.culturalEnrichment.value;
                    if (currentValue && content.source === 'travel_llama') {
                        // Ajouter Travel Llama à la suite
                        this.culturalEnrichment.value = currentValue + '\n\n🌍 ' + content.text;
                    } else {
                        this.culturalEnrichment.value = content.text;
                    }
                    this.animateField(this.culturalEnrichment);
                }
                break;
                
            case 'travel_enrichment':
                if (content.text) {
                    // Travel Llama est un enrichissement supplémentaire
                    const currentEnrichment = this.culturalEnrichment.value;
                    const separator = currentEnrichment ? '\n\n🌍 Travel Llama:\n' : '🌍 Travel Llama:\n';
                    this.culturalEnrichment.value = currentEnrichment + separator + content.text;
                    this.animateField(this.culturalEnrichment);
                }
                break;
                
            case 'raw_caption':
                if (content.caption) {
                    this.finalCaption.value = content.caption;
                    this.animateField(this.finalCaption);
                    
                    // Afficher la langue et le style si disponibles
                    const meta = [];
                    if (content.language) meta.push(`Langue: ${content.language}`);
                    if (content.style) meta.push(`Style: ${content.style}`);
                    if (meta.length > 0) {
                        this.showMessage(meta.join(' | '), 'info');
                    }
                }
                break;
                
            case 'hashtags':
                if (content.tags && content.tags.length > 0) {
                    // Ajouter les hashtags à la légende finale
                    const hashtagsText = '\n\n' + content.tags.join(' ');
                    this.finalCaption.value += hashtagsText;
                    this.animateField(this.finalCaption);
                    
                    this.showMessage(`${content.count || content.tags.length} hashtags générés`, 'info');
                }
                break;
                
            default:
                console.log(`Type de résultat partiel non géré: ${type}`, content);
        }
        
        // Sauvegarder après chaque mise à jour
        this.saveToLocalStorage();
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
        // Régénérer uniquement la légende finale à partir des contextes existants
        try {
            this.showMessage('Régénération de la légende...', 'info');
            this.btnRegenerate.disabled = true;

            const requestData = {
                image_description: this.imageDescription.value,
                geo_context: this.geoContext.value,
                cultural_enrichment: this.culturalEnrichment.value,
                language: this.languageSelect?.value || 'français',
                style: this.styleSelect?.value || 'creative'
            };

            const response = await fetch(`${this.flaskApiUrl}/api/ai/regenerate-final`, {
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
                throw new Error(errorData.error || 'Erreur de régénération');
            }

            const result = await response.json();

            if (result.success) {
                this.finalCaption.value = result.caption;
                this.animateField(this.finalCaption);
                this.showMessage('Légende régénérée avec succès!', 'success');
                this.saveToLocalStorage();
            } else {
                throw new Error(result.error || 'Échec de la régénération');
            }

        } catch (error) {
            console.error('Erreur régénération:', error);
            this.showMessage(`Erreur: ${error.message}`, 'error');
        } finally {
            this.btnRegenerate.disabled = false;
        }
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

.message-box {
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 4px;
    font-size: 14px;
}

.message-box.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message-box.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message-box.warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.message-box.info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
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