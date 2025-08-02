// public/assets/js/edit-photos/modules/CaptionEditor.js

class CaptionEditor {
    constructor() {
        this.currentAssetId = null;
        this.currentGalleryId = null;
        this.modal = null;
        this.isAIGenerating = false;
    }
    
    openModal(assetId, galleryId) {
        this.currentAssetId = assetId;
        this.currentGalleryId = galleryId;
        
        // Créer le modal s'il n'existe pas
        if (!this.modal) {
            this.createModal();
        }
        
        // Charger les données existantes
        this.loadCaption();
        
        // Afficher le modal
        this.modal.classList.add('active');
    }
    
    createModal() {
        const modalHTML = `
            <div id="captionModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Éditer la légende</h2>
                        <button class="btn-close" onclick="window.editPhotos.captionEditor.closeModal()">✕</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="caption-preview">
                            <img id="captionPreviewImage" src="" alt="Photo">
                            <div id="captionMetadata" class="metadata-info"></div>
                        </div>
                        
                        <div class="caption-form">
                            <div class="form-group">
                                <label>Légende actuelle</label>
                                <textarea id="captionText" rows="4" placeholder="Entrez la légende..."></textarea>
                            </div>
                            
                            <div class="ai-section">
                                <h4>Assistant IA</h4>
                                
                                <div class="ai-options">
                                    <select id="aiLanguage">
                                        <option value="français">Français</option>
                                        <option value="english">English</option>
                                        <option value="bilingual">Bilingue FR/EN</option>
                                    </select>
                                    
                                    <select id="aiStyle">
                                        <option value="descriptive">Descriptif</option>
                                        <option value="creative">Créatif</option>
                                        <option value="minimal">Minimaliste</option>
                                        <option value="technical">Technique</option>
                                    </select>
                                </div>
                                
                                <div class="ai-suggestions" id="aiSuggestions" style="display: none;">
                                    <label>Suggestions IA</label>
                                    <div class="suggestions-list"></div>
                                </div>
                                
                                <div id="aiProgress" class="progress-container" style="display: none;">
                                    <div class="progress-bar">
                                        <div class="progress-fill"></div>
                                    </div>
                                    <div class="progress-text">Analyse en cours...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="window.editPhotos.captionEditor.closeModal()">
                            Annuler
                        </button>
                        <button class="btn btn-ai" id="btnGenerateAI" onclick="window.editPhotos.captionEditor.generateWithAI()">
                            🤖 Générer avec IA
                        </button>
                        <button class="btn btn-primary" onclick="window.editPhotos.captionEditor.saveCaption()">
                            💾 Enregistrer
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('captionModal');
        
        // Ajouter les styles CSS si nécessaire
        if (!document.getElementById('caption-modal-styles')) {
            const styles = `
                <style id="caption-modal-styles">
                    .modal {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0,0,0,0.5);
                        z-index: 1000;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .modal.active {
                        display: flex;
                    }
                    
                    .modal-content {
                        background: white;
                        border-radius: 12px;
                        width: 90%;
                        max-width: 900px;
                        max-height: 90vh;
                        display: flex;
                        flex-direction: column;
                        overflow: hidden;
                    }
                    
                    .modal-header {
                        padding: 20px;
                        border-bottom: 1px solid #e5e7eb;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    
                    .modal-body {
                        flex: 1;
                        overflow-y: auto;
                        padding: 20px;
                        display: grid;
                        grid-template-columns: 1fr 2fr;
                        gap: 20px;
                    }
                    
                    .caption-preview img {
                        width: 100%;
                        height: auto;
                        border-radius: 8px;
                        margin-bottom: 10px;
                    }
                    
                    .metadata-info {
                        background: #f3f4f6;
                        padding: 10px;
                        border-radius: 6px;
                        font-size: 12px;
                        color: #4b5563;
                    }
                    
                    .caption-form {
                        display: flex;
                        flex-direction: column;
                        gap: 20px;
                    }
                    
                    .form-group label {
                        display: block;
                        font-weight: 600;
                        margin-bottom: 8px;
                        color: #374151;
                    }
                    
                    .form-group textarea {
                        width: 100%;
                        padding: 10px;
                        border: 1px solid #d1d5db;
                        border-radius: 6px;
                        resize: vertical;
                        font-family: inherit;
                    }
                    
                    .ai-section {
                        background: #f9fafb;
                        padding: 15px;
                        border-radius: 8px;
                        border: 1px solid #e5e7eb;
                    }
                    
                    .ai-options {
                        display: flex;
                        gap: 10px;
                        margin: 10px 0;
                    }
                    
                    .ai-options select {
                        flex: 1;
                        padding: 8px;
                        border: 1px solid #d1d5db;
                        border-radius: 6px;
                    }
                    
                    .suggestions-list {
                        display: flex;
                        flex-direction: column;
                        gap: 8px;
                        margin-top: 10px;
                    }
                    
                    .suggestion-item {
                        padding: 10px;
                        background: white;
                        border: 1px solid #e5e7eb;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.2s;
                    }
                    
                    .suggestion-item:hover {
                        background: #f3f4f6;
                        border-color: #3b82f6;
                    }
                    
                    .modal-footer {
                        padding: 20px;
                        border-top: 1px solid #e5e7eb;
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                    }
                    
                    .btn-ai {
                        background: #8b5cf6;
                        color: white;
                    }
                    
                    .btn-ai:hover {
                        background: #7c3aed;
                    }
                    
                    @media (max-width: 768px) {
                        .modal-body {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', styles);
        }
    }
    
    async loadCaption() {
        const photo = document.querySelector(`[data-asset-id="${this.currentAssetId}"]`);
        if (!photo) return;
        
        // Charger l'image
        const previewImg = document.getElementById('captionPreviewImage');
        previewImg.src = `../public/image-proxy.php?id=${this.currentAssetId}&type=thumbnail&size=preview`;
        
        // Charger la légende existante
        const captionText = photo.querySelector('.photo-caption-text')?.textContent || '';
        document.getElementById('captionText').value = captionText;
        
        // Charger les métadonnées
        const metadata = document.getElementById('captionMetadata');
        const hasGPS = photo.classList.contains('has-gps');
        const lat = photo.dataset.latitude;
        const lng = photo.dataset.longitude;
        const date = photo.dataset.date;
        
        metadata.innerHTML = `
            <div><strong>Date:</strong> ${new Date(date).toLocaleDateString()}</div>
            ${hasGPS ? `<div><strong>GPS:</strong> ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}</div>` : ''}
        `;
    }
    
    async generateWithAI() {
        if (this.isAIGenerating) return;
        
        this.isAIGenerating = true;
        const btnGenerate = document.getElementById('btnGenerateAI');
        btnGenerate.disabled = true;
        btnGenerate.textContent = '⏳ Génération...';
        
        const progressDiv = document.getElementById('aiProgress');
        const suggestionsDiv = document.getElementById('aiSuggestions');
        
        progressDiv.style.display = 'block';
        suggestionsDiv.style.display = 'none';
        
        try {
            // Récupérer les options
            const language = document.getElementById('aiLanguage').value;
            const style = document.getElementById('aiStyle').value;
            const existingCaption = document.getElementById('captionText').value;
            
            // Récupérer les coordonnées GPS si disponibles
            const photo = document.querySelector(`[data-asset-id="${this.currentAssetId}"]`);
            const latitude = photo?.dataset.latitude || null;
            const longitude = photo?.dataset.longitude || null;
            
            // Appeler l'API Flask
            const response = await fetch(`${window.editPhotosConfig.flaskApiUrl}/api/ai/generate-caption-simple`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    asset_id: this.currentAssetId,
                    gallery_id: this.currentGalleryId,
                    language: language,
                    style: style,
                    existing_caption: existingCaption,
                    latitude: latitude ? parseFloat(latitude) : null,
                    longitude: longitude ? parseFloat(longitude) : null
                })
            });
            
            if (!response.ok) {
                throw new Error('Erreur serveur');
            }
            
            const result = await response.json();
            
            // Afficher les suggestions
            progressDiv.style.display = 'none';
            suggestionsDiv.style.display = 'block';
            
            const suggestionsList = suggestionsDiv.querySelector('.suggestions-list');
            suggestionsList.innerHTML = '';
            
            if (result.suggestions && result.suggestions.length > 0) {
                result.suggestions.forEach(suggestion => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.textContent = suggestion;
                    item.onclick = () => {
                        document.getElementById('captionText').value = suggestion;
                    };
                    suggestionsList.appendChild(item);
                });
            } else if (result.caption) {
                // Une seule suggestion
                document.getElementById('captionText').value = result.caption;
                this.showToast('Légende générée avec succès', 'success');
            }
            
        } catch (error) {
            console.error('Erreur génération IA:', error);
            this.showToast('Erreur lors de la génération IA', 'error');
            progressDiv.style.display = 'none';
        } finally {
            this.isAIGenerating = false;
            btnGenerate.disabled = false;
            btnGenerate.textContent = '🤖 Générer avec IA';
        }
    }
    
    async saveCaption() {
        const caption = document.getElementById('captionText').value.trim();
        
        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'update_caption',
                    asset_id: this.currentAssetId,
                    caption: caption
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Mettre à jour la légende dans la grille
                const photo = document.querySelector(`[data-asset-id="${this.currentAssetId}"]`);
                if (photo) {
                    const captionElement = photo.querySelector('.photo-caption-text');
                    if (captionElement) {
                        captionElement.textContent = caption;
                    }
                }
                
                this.showToast('Légende enregistrée', 'success');
                this.closeModal();
            } else {
                this.showToast('Erreur lors de l\'enregistrement', 'error');
            }
            
        } catch (error) {
            console.error('Erreur sauvegarde:', error);
            this.showToast('Erreur lors de l\'enregistrement', 'error');
        }
    }
    
    closeModal() {
        if (this.modal) {
            this.modal.classList.remove('active');
        }
        
        // Réinitialiser
        this.currentAssetId = null;
        this.currentGalleryId = null;
        document.getElementById('aiSuggestions').style.display = 'none';
        document.getElementById('aiProgress').style.display = 'none';
    }
    
    showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

export default CaptionEditor;