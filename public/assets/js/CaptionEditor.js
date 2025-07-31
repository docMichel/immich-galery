// public/assets/js/CaptionEditorAI.js

import SSEManager from './modules/SSEManager.js';
import UIManager from './modules/UIManager.js';
import ImageProcessor from './modules/ImageProcessor.js';
import APIClient from './modules/APIClient.js';

class CaptionEditorAI {
    constructor(config) {
        this.config = config;
        this.currentRequestId = null;
        
        // Initialiser les modules
        this.ui = new UIManager();
        this.api = new APIClient(config.flaskApiUrl);
        this.imageProcessor = new ImageProcessor(this.ui.elements.mainImage);
        this.sse = new SSEManager(config);
        
        // Configurer les handlers SSE
        this._setupSSEHandlers();
        
        // Configurer les événements UI
        this._setupEventListeners();
    }

    // Configuration des handlers SSE
    _setupSSEHandlers() {
        // Connexion établie
        this.sse.on('connected', () => {
            console.log('🔗 Connecté au flux SSE');
        });

        // Progression
        this.sse.on('progress', (data) => {
            this.ui.updateProgress(data.progress, data.details);
        });

        // Résultats intermédiaires
        this.sse.on('result', (data) => {
            this.ui.handleStepResult(data.step, data.result);
        });

        // Génération terminée
        this.sse.on('complete', (data) => {
            console.log('✅ Génération terminée:', data);
            this.ui.showProgress(false);
            this.ui.showMessage('success', 'Génération terminée avec succès!');
            this.ui.setButtonState('btnGenerate', true);
            this.ui.setButtonState('btnRegenerate', true);
            this.ui.displayComplete(data);
        });

        // Erreur
        this.sse.on('error', (data) => {
            this.ui.showMessage('error', `Erreur: ${data.error}`);
            this.ui.showProgress(false);
            this.ui.setButtonState('btnGenerate', true);
        });

        // Heartbeat
        this.sse.on('heartbeat', () => {
            this.ui.animateHeartbeat();
        });

        // Timeout
        this.sse.on('timeout', () => {
            this.ui.showMessage('error', 'Timeout: Le serveur ne répond plus');
            this.ui.showProgress(false);
            this.ui.setButtonState('btnGenerate', true);
        });

        // Déconnexion
        this.sse.on('disconnected', () => {
            // Si le bouton est encore désactivé, c'est une déconnexion anormale
            if (this.ui.elements.btnGenerate.disabled) {
                this.ui.showMessage('error', 'Connexion au serveur perdue');
                this.ui.showProgress(false);
                this.ui.setButtonState('btnGenerate', true);
            }
        });

        // Reconnexion
        this.sse.on('connecting', () => {
            this.ui.showMessage('warning', 'Reconnexion en cours...');
        });
    }

    // Configuration des événements UI
    _setupEventListeners() {
        this.ui.elements.btnGenerate.addEventListener('click', () => this.generateCaption());
        this.ui.elements.btnRegenerate.addEventListener('click', () => this.regenerateCaption());
    }

    // Générer une nouvelle légende
    async generateCaption() {
        try {
            // Préparer l'UI
            this.ui.showProgress(true);
            this.ui.updateProgress(0, 'Initialisation...');
            this.ui.showMessage('info', 'Préparation de l\'image pour l\'analyse...');
            this.ui.setButtonState('btnGenerate', false);
            this.ui.clearFields();

            // Convertir l'image en base64
            const imageBase64 = await this.imageProcessor.getImageAsBase64();

            // Générer un ID de requête unique
            this.currentRequestId = `request-${Date.now()}`;

            // Préparer la requête
            const formValues = this.ui.getFormValues();
            const requestBody = {
                request_id: this.currentRequestId,
                asset_id: this.config.assetId,
                image_base64: imageBase64,
                language: formValues.language,
                style: formValues.style,
                existing_caption: formValues.finalCaption || ''
            };

            // Ajouter les coordonnées GPS si disponibles
            if (this.config.latitude !== null && this.config.longitude !== null) {
                requestBody.latitude = this.config.latitude;
                requestBody.longitude = this.config.longitude;
            }

            // Démarrer la génération
            const response = await this.api.startGeneration(requestBody);
            
            console.log('✅ Génération démarrée, connexion SSE...');
            
            // Se connecter au flux SSE
            if (!this.sse.connect(this.currentRequestId)) {
                throw new Error('Impossible d\'établir la connexion SSE');
            }

        } catch (error) {
            console.error('❌ Erreur génération:', error);
            this.ui.showMessage('error', `Erreur: ${error.message}`);
            this.ui.showProgress(false);
            this.ui.setButtonState('btnGenerate', true);
            this.sse.disconnect();
        }
    }

    // Régénérer la légende finale
    async regenerateCaption() {
        try {
            this.ui.showMessage('info', 'Régénération de la légende finale...');
            this.ui.setButtonState('btnRegenerate', false);

            const formValues = this.ui.getFormValues();
            
            const response = await this.api.regenerateCaption({
                image_description: formValues.imageDescription,
                geo_context: formValues.geoContext,
                cultural_enrichment: formValues.culturalEnrichment,
                language: formValues.language,
                style: formValues.style
            });

            this.ui.updateField('finalCaption', response.caption);
            this.ui.showMessage('success', 'Légende régénérée avec succès!');

        } catch (error) {
            this.ui.showMessage('error', `Erreur: ${error.message}`);
        } finally {
            this.ui.setButtonState('btnRegenerate', true);
        }
    }
}

// Initialiser l'éditeur au chargement
document.addEventListener('DOMContentLoaded', () => {
    if (window.captionEditorConfig) {
        window.captionEditor = new CaptionEditorAI(window.captionEditorConfig);
    }
});

// Export pour utilisation externe si nécessaire
export default CaptionEditorAI;