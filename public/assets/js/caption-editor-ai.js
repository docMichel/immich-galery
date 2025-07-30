// public/assets/js/caption-editor-ai.js

class CaptionEditorAI {
    constructor(config) {
        this.config = config;
        this.eventSource = null;
        this.currentRequestId = null;
        
        this.elements = {
            btnGenerate: document.getElementById('btnGenerate'),
            btnRegenerate: document.getElementById('btnRegenerate'),
            progressContainer: document.getElementById('progressContainer'),
            progressFill: document.getElementById('progressFill'),
            progressText: document.getElementById('progressText'),
            messageBox: document.getElementById('messageBox'),
            imageDescription: document.getElementById('imageDescription'),
            geoContext: document.getElementById('geoContext'),
            culturalEnrichment: document.getElementById('culturalEnrichment'),
            finalCaption: document.getElementById('finalCaption'),
            language: document.getElementById('language'),
            style: document.getElementById('style'),
            mainImage: document.getElementById('mainImage')
        };
        
        this.initEventListeners();
    }
    
    initEventListeners() {
        this.elements.btnGenerate.addEventListener('click', () => this.generateCaption());
        this.elements.btnRegenerate.addEventListener('click', () => this.regenerateCaption());
    }
    
    async generateCaption() {
        try {
            this.showProgress(true);
            this.showMessage('info', 'Préparation de l\'image pour l\'analyse...');
            this.elements.btnGenerate.disabled = true;
            
            // Convertir l'image en base64
            const imageBase64 = await this.getImageAsBase64();
            
            // Générer un ID de requête unique
            this.currentRequestId = `request-${Date.now()}`;
            
            // Démarrer l'écoute SSE
            this.startSSE(this.currentRequestId);
            
            // Envoyer la requête de génération
            const requestBody = {
                request_id: this.currentRequestId,
                asset_id: this.config.assetId,
                image_base64: imageBase64,
                language: this.elements.language.value,
                style: this.elements.style.value
            };
            
            // Ajouter latitude/longitude seulement si disponibles
            if (this.config.latitude !== null && this.config.longitude !== null) {
                requestBody.latitude = this.config.latitude;
                requestBody.longitude = this.config.longitude;
            }
            
            const response = await fetch(`${this.config.flaskApiUrl}/api/ai/generate-caption-async`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestBody)
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Erreur lors du démarrage de la génération');
            }
            
        } catch (error) {
            this.showMessage('error', `Erreur: ${error.message}`);
            this.showProgress(false);
            this.elements.btnGenerate.disabled = false;
        }
    }
    
    async regenerateCaption() {
        try {
            this.showMessage('info', 'Régénération de la légende finale...');
            this.elements.btnRegenerate.disabled = true;
            
            const response = await fetch(`${this.config.flaskApiUrl}/api/ai/regenerate-final`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_description: this.elements.imageDescription.value,
                    geo_context: this.elements.geoContext.value,
                    cultural_enrichment: this.elements.culturalEnrichment.value,
                    language: this.elements.language.value,
                    style: this.elements.style.value
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.elements.finalCaption.value = data.caption;
                this.showMessage('success', 'Légende régénérée avec succès!');
            } else {
                throw new Error(data.error || 'Erreur lors de la régénération');
            }
            
        } catch (error) {
            this.showMessage('error', `Erreur: ${error.message}`);
        } finally {
            this.elements.btnRegenerate.disabled = false;
        }
    }
    
    startSSE(requestId) {
        // Fermer toute connexion SSE existante
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        // Créer une nouvelle connexion SSE
        this.eventSource = new EventSource(
            `${this.config.flaskApiUrl}/api/ai/generate-caption-stream/${requestId}`
        );
        
        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleSSEMessage(data);
            } catch (error) {
                console.error('Erreur parsing SSE:', error);
            }
        };
        
        this.eventSource.onerror = (error) => {
            console.error('Erreur SSE:', error);
            this.eventSource.close();
            this.showMessage('error', 'Connexion au serveur perdue');
            this.showProgress(false);
            this.elements.btnGenerate.disabled = false;
        };
    }
    
    handleSSEMessage(data) {
        switch (data.event) {
            case 'progress':
                this.updateProgress(data.data.progress, data.data.details);
                break;
                
            case 'result':
                this.handleStepResult(data.data.step, data.data.result);
                break;
                
            case 'complete':
                this.handleComplete(data.data);
                break;
                
            case 'error':
                this.showMessage('error', data.data.error);
                this.showProgress(false);
                this.elements.btnGenerate.disabled = false;
                if (this.eventSource) {
                    this.eventSource.close();
                }
                break;
        }
    }
    
    handleStepResult(step, result) {
        switch (step) {
            case 'image_analysis':
                this.elements.imageDescription.value = result.description || '';
                this.animateTextarea(this.elements.imageDescription);
                break;
                
            case 'geo_enrichment':
                this.elements.geoContext.value = result.context || '';
                this.animateTextarea(this.elements.geoContext);
                break;
                
            case 'cultural_enrichment':
                this.elements.culturalEnrichment.value = result.enrichment || '';
                this.animateTextarea(this.elements.culturalEnrichment);
                break;
                
            case 'caption_generation':
                this.elements.finalCaption.value = result.caption || '';
                this.animateTextarea(this.elements.finalCaption);
                break;
        }
    }
    
    handleComplete(data) {
        this.showProgress(false);
        this.showMessage('success', 'Génération terminée avec succès!');
        this.elements.btnGenerate.disabled = false;
        this.elements.btnRegenerate.disabled = false;
        
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        // Mettre à jour tous les champs avec les données finales
        if (data.image_description) {
            this.elements.imageDescription.value = data.image_description;
        }
        if (data.geo_context) {
            this.elements.geoContext.value = data.geo_context;
        }
        if (data.cultural_enrichment) {
            this.elements.culturalEnrichment.value = data.cultural_enrichment;
        }
        if (data.caption) {
            this.elements.finalCaption.value = data.caption;
        }
    }
    
    updateProgress(percentage, details) {
        this.elements.progressFill.style.width = `${percentage}%`;
        this.elements.progressText.textContent = details || `${percentage}%`;
    }
    
    showProgress(show) {
        this.elements.progressContainer.style.display = show ? 'block' : 'none';
        if (show) {
            this.updateProgress(0, 'Initialisation...');
        }
    }
    
    showMessage(type, message) {
        this.elements.messageBox.className = `message-box ${type}`;
        this.elements.messageBox.textContent = message;
        this.elements.messageBox.style.display = 'block';
        
        // Masquer automatiquement après 5 secondes
        setTimeout(() => {
            this.elements.messageBox.style.display = 'none';
        }, 5000);
    }
    
    animateTextarea(textarea) {
        textarea.style.backgroundColor = '#e8f4f8';
        setTimeout(() => {
            textarea.style.backgroundColor = '';
        }, 500);
    }
    
    async getImageAsBase64() {
        return new Promise((resolve, reject) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            img.crossOrigin = 'anonymous';
            
            img.onload = () => {
                // Redimensionner si l'image est trop grande
                const maxSize = 1920;
                let width = img.width;
                let height = img.height;
                
                if (width > maxSize || height > maxSize) {
                    if (width > height) {
                        height = (height / width) * maxSize;
                        width = maxSize;
                    } else {
                        width = (width / height) * maxSize;
                        height = maxSize;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);
                
                const base64 = canvas.toDataURL('image/jpeg', 0.9);
                resolve(base64);
            };
            
            img.onerror = () => {
                reject(new Error('Impossible de charger l\'image'));
            };
            
            img.src = this.elements.mainImage.src;
        });
    }
}

// Initialiser l'éditeur au chargement
document.addEventListener('DOMContentLoaded', () => {
    if (window.captionEditorConfig) {
        window.captionEditor = new CaptionEditorAI(window.captionEditorConfig);
    }
});