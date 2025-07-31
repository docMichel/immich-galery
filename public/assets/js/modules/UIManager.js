// public/assets/js/modules/UIManager.js

class UIManager {
    constructor() {
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
    }

    // Obtenir les valeurs des formulaires
    getFormValues() {
        return {
            language: this.elements.language.value,
            style: this.elements.style.value,
            imageDescription: this.elements.imageDescription.value,
            geoContext: this.elements.geoContext.value,
            culturalEnrichment: this.elements.culturalEnrichment.value,
            finalCaption: this.elements.finalCaption.value
        };
    }

    // Réinitialiser les champs
    clearFields() {
        this.elements.imageDescription.value = '';
        this.elements.geoContext.value = '';
        this.elements.culturalEnrichment.value = '';
        this.elements.finalCaption.value = '';
    }

    // Mettre à jour un champ spécifique
    updateField(field, value) {
        if (this.elements[field]) {
            this.elements[field].value = value;
            this.animateTextarea(this.elements[field]);
        }
    }

    // Activer/désactiver les boutons
    setButtonState(button, enabled) {
        if (this.elements[button]) {
            this.elements[button].disabled = !enabled;
        }
    }

    // Afficher/masquer la barre de progression
    showProgress(show) {
        this.elements.progressContainer.style.display = show ? 'block' : 'none';
        if (show) {
            this.updateProgress(0, 'Initialisation...');
        }
    }

    // Mettre à jour la progression
    updateProgress(percentage, details) {
        const percent = parseInt(percentage) || 0;
        
        this.elements.progressFill.style.width = `${percent}%`;
        this.elements.progressText.textContent = details || `${percent}%`;
        
        if (percent >= 100) {
            this.elements.progressFill.classList.add('complete');
        } else {
            this.elements.progressFill.classList.remove('complete');
        }
    }

    // Afficher un message
    showMessage(type, message, duration = 5000) {
        this.elements.messageBox.className = `message-box ${type}`;
        this.elements.messageBox.textContent = message;
        this.elements.messageBox.style.display = 'block';

        // Auto-hide sauf pour les erreurs
        if (type !== 'error' && duration > 0) {
            setTimeout(() => {
                this.elements.messageBox.style.display = 'none';
            }, duration);
        }
    }

    // Masquer le message
    hideMessage() {
        this.elements.messageBox.style.display = 'none';
    }

    // Animation de mise à jour de textarea
    animateTextarea(textarea) {
        textarea.style.backgroundColor = '#e8f4f8';
        textarea.style.transition = 'background-color 0.5s ease';
        setTimeout(() => {
            textarea.style.backgroundColor = '';
        }, 500);
    }

    // Animation heartbeat
    animateHeartbeat() {
        // Faire "battre" la barre de progression
        const progressBar = this.elements.progressFill;
        if (progressBar && this.elements.progressContainer.style.display !== 'none') {
            progressBar.style.transition = 'opacity 0.3s ease';
            progressBar.style.opacity = '0.6';
            setTimeout(() => {
                progressBar.style.opacity = '1';
            }, 300);
        }
        
        // Ajouter un indicateur visuel temporaire
        const currentText = this.elements.progressText.textContent;
        if (!currentText.includes('💓')) {
            this.elements.progressText.textContent = currentText + ' 💓';
            setTimeout(() => {
                this.elements.progressText.textContent = currentText;
            }, 500);
        }
    }

    // Gérer les résultats par étape
    handleStepResult(step, result) {
        console.log(`📝 Résultat ${step}:`, result);
        
        switch (step) {
            case 'image_analysis':
                if (result?.description) {
                    this.updateField('imageDescription', result.description);
                }
                break;

            case 'geolocation':
                if (result) {
                    const parts = [];
                    if (result.location_basic) {
                        parts.push(`📍 ${result.location_basic}`);
                    }
                    if (result.cultural_context) {
                        parts.push(`🏛️ ${result.cultural_context}`);
                    }
                    this.updateField('geoContext', parts.join('\n') || 'Pas de contexte géographique');
                }
                break;

            case 'cultural_enrichment':
                if (result?.enrichment) {
                    this.updateField('culturalEnrichment', result.enrichment);
                }
                break;

            case 'raw_caption':
                if (result?.caption) {
                    this.updateField('finalCaption', result.caption);
                }
                break;
        }
    }

    // Afficher le résultat final
    displayComplete(data) {
        // Mettre à jour la légende finale
        if (data.caption) {
            this.updateField('finalCaption', data.caption);
        }

        // Afficher le score de confiance
        if (data.confidence_score !== undefined) {
            const scorePercent = Math.round(data.confidence_score * 100);
            this.showMessage('info', `Score de confiance: ${scorePercent}%`);
        }
    }
}

export default UIManager;