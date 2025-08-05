// public/assets/js/edit-photos/modules/DuplicateManager.js

//import SSEManager from '../../modules/SSEManager.js';

export default class DuplicateManager {
    constructor(config) {
        this.config = config;
        this.eventsBound = false; // Flag

        this.sseManager = new SSEManager();
        this.currentGroups = [];

        this.initUI();
        this.bindEvents();
    }

    initUI() {
        // Créer le modal pour les doublons
        const modal = document.createElement('div');
        modal.innerHTML = `
            <div id="duplicateModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 90%; width: 1200px;">
                    <div class="modal-header">
                        <h2>🔍 Détection de doublons</h2>
                        <span id="heartbeatIndicator" class="heartbeat-indicator">💓</span>

                        <button class="btn-close" onclick="duplicateManager.closeModal()">✕</button>
                    </div>
                    
                    <div class="modal-body">
                        <!-- Options -->
                        <div class="duplicate-options">
                            <label>
                                Seuil de similarité:
                                <input type="range" id="dupThreshold" min="0.7" max="0.95" step="0.05" value="0.85">
                                <span id="dupThresholdValue">85%</span>
                            </label>
                        </div>
                        
                        <!-- Progress -->
                        <div id="dupProgress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill" id="dupProgressFill">0%</div>
                            </div>
                            <div id="dupStatus"></div>
                        </div>
                        
                        <!-- Results -->
                        <div id="dupResults"></div>
                    </div>
                    
                    <div class="modal-footer">
                        <button id="btnStartDetection" class="btn btn-primary">
                            🚀 Lancer la détection
                        </button>
                        <button id="btnSaveGroups" class="btn btn-success" style="display: none;">
                            💾 Sauvegarder les groupes
                        </button>
                        <button class="btn btn-secondary" onclick="duplicateManager.closeModal()">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Listener pour le seuil
        document.getElementById('dupThreshold').addEventListener('input', (e) => {
            document.getElementById('dupThresholdValue').textContent =
                Math.round(e.target.value * 100) + '%';
        });
        // Bouton démarrer ICI
        document.getElementById('btnStartDetection').addEventListener('click', () => {
            this.startDetection();
        });

        // Bouton sauvegarder aussi
        document.getElementById('btnSaveGroups').addEventListener('click', () => {
            this.saveGroups();
        });

    }

    bindEvents() {
        if (this.eventsBound) {
            console.warn('Events déjà bindés !');
            return;
        }
        console.log('Binding events...');
        this.eventsBound = true;

        // Bouton sélection
        document.getElementById('btnFindDuplicatesSelection')?.addEventListener('click', () => {
            this.findDuplicates('selection');
        });

        // Bouton galerie complète
        document.getElementById('btnFindDuplicatesAll')?.addEventListener('click', () => {
            this.findDuplicates('all');
        });

        // Bouton démarrer
        /* document.getElementById('btnStartDetection')?.addEventListener('click', () => {
            this.startDetection();
        });
        */
    }

    findDuplicates(mode) {
        console.log('findDuplicates appelé avec mode:', mode);

        this.mode = mode;
        this.showModal();

        // Récupérer les assets selon le mode
        if (mode === 'selection') {
            this.assetIds = this.getSelectedAssetIds();
            console.log('Mode sélection, assets:', this.currentAssetIds);

            document.getElementById('dupStatus').textContent =
                `${this.assetIds.length} photos sélectionnées`;
        } else {
            this.assetIds = this.getAllAssetIds();
            document.getElementById('dupStatus').textContent =
                `${this.assetIds.length} photos dans la galerie`;
            console.log('Mode galerie, assets:', this.currentAssetIds);

        }
        this.currentAssetIds = this.assetIds;  // <-- Ajouter cette ligne

    }

    async startDetection() {
        const threshold = parseFloat(document.getElementById('dupThreshold').value);
        const requestId = `dup-${Date.now()}`;

        document.getElementById('dupProgress').style.display = 'block';
        document.getElementById('btnStartDetection').disabled = true;

        try {
            // POST avec la config
            const response = await fetch(`${this.config.flaskUrl}/api/duplicates/find-similar-async`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    selected_asset_ids: this.assetIds,
                    threshold: threshold, //parseFloat(document.getElementById('dupThreshold').value),
                    group_by_time: true,
                    time_window_hours: 24
                })
            });

            const result = await response.json();


            if (result.success) {
                // Se connecter au SSE
                this.connectSSE(result.request_id);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors du démarrage de la détection');
        }
    }

    connectSSE(requestId) {
        // const url = `api/duplicates.php?action=stream/${requestId}`;
        const url = `${this.config.flaskUrl}/api/duplicates/find-similar-stream/${requestId}`;
        console.log('Config Flask URL:', this.config.flaskUrl);
        console.log('URL SSE complète:', url);
        console.log('Request ID:', requestId);

        this.sseManager.connect(`dup-${requestId}`, url, {
            onHeartbeat: (data) => {
                // Faire clignoter l'indicateur
                this.flashHeartbeat();
            },
            onProgress: (progress, details, step) => {
                const fill = document.getElementById('dupProgressFill');
                fill.style.width = `${progress}%`;
                fill.textContent = `${progress}% - ${details}`;
            },

            onComplete: (data) => {
                this.displayResults(data);
                document.getElementById('btnStartDetection').disabled = false;
                document.getElementById('btnSaveGroups').style.display = 'inline-block';
            },

            onError: (error) => {
                console.error('Erreur SSE:', error);
                document.getElementById('btnStartDetection').disabled = false;

                alert('Erreur pendant la détection');
            }
        });
    }
    flashHeartbeat() {
        const indicator = document.getElementById('heartbeatIndicator');
        if (indicator) {
            indicator.classList.add('beating');
            setTimeout(() => {
                indicator.classList.remove('beating');
            }, 500);
        }
    }

    displayResults(data) {
        // TODO: Afficher les résultats
        console.log('Résultats:', data);
    }
    renderThumbnail(img, groupIndex, imgIndex) {
        // Récupérer le template
        let template = document.getElementById('duplicate-thumbnail-template').innerHTML;

        // Données à remplacer
        const data = {
            assetId: img.asset_id,
            groupIndex: groupIndex,
            imgIndex: imgIndex,
            filename: img.filename || 'Image',
            filenameShort: (img.filename || 'Image').substring(0, 20),
            primary: img.is_primary ? 'primary' : '',
            checked: img.is_primary ? 'checked' : '',
            isPrimary: img.is_primary,
            hasGPS: !!(img.latitude && img.longitude),
            lat: img.latitude ? img.latitude.toFixed(4) : '',
            lng: img.longitude ? img.longitude.toFixed(4) : ''
        };

        // Remplacer les variables simples
        Object.keys(data).forEach(key => {
            template = template.replace(new RegExp(`{{${key}}}`, 'g'), data[key]);
        });

        // Gérer les conditions
        template = template.replace(/{{#(\w+)}}([\s\S]*?){{\/\1}}/g, (match, key, content) => {
            return data[key] ? content : '';
        });

        return template;
    }
    getSelectedAssetIds() {
        const selected = [];
        document.querySelectorAll('.photo-select:checked').forEach(cb => {
            const photoItem = cb.closest('.photo-item');
            if (photoItem) {
                selected.push(photoItem.dataset.assetId);
            }
        });
        return selected;
    }

    getAllAssetIds() {
        const all = [];
        document.querySelectorAll('.photo-item').forEach(item => {
            all.push(item.dataset.assetId);
        });
        return all;
    }

    showModal() {
        document.getElementById('duplicateModal').style.display = 'block';
    }

    closeModal() {
        document.getElementById('duplicateModal').style.display = 'none';
        this.sseManager.closeAll();
    }

    displayResults(data) {
        console.log('Résultats reçus:', data);

        const resultsDiv = document.getElementById('dupResults');

        if (!data.groups || data.groups.length === 0) {
            resultsDiv.innerHTML = '<div class="no-results">✅ Aucun doublon trouvé !</div>';
            return;
        }

        // Sauvegarder les groupes
        this.currentGroups = data.groups;

        // Afficher les groupes
        let html = `
        <div class="duplicate-summary">
            <h3>📊 ${data.groups.length} groupe(s) de doublons trouvés</h3>
            <p>${data.total_images || data.groups.reduce((sum, g) => sum + g.images.length, 0)} images analysées</p>
        </div>
    `;

        data.groups.forEach((group, groupIndex) => {
            html += `
            <div class="duplicate-group" data-group-index="${groupIndex}">
                <div class="group-header">
                    <h4>Groupe ${groupIndex + 1} - ${group.images.length} images similaires</h4>
                    <span class="similarity-badge">Similarité: ${(group.similarity_avg * 100).toFixed(0)}%</span>
                </div>
                
                <div class="group-images">
                    ${group.images.map((img, imgIndex) =>
                this.renderThumbnail(img, groupIndex, imgIndex)  // <-- ICI, on utilise renderThumbnail
            ).join('')}
                </div>
                
                <div class="group-actions">
                    <button class="btn btn-sm" onclick="duplicateManager.splitGroup(${groupIndex})">
                        ✂️ Séparer le groupe
                    </button>
                </div>
            </div>
        `;
        });

        resultsDiv.innerHTML = html;

        // Activer le bouton de sauvegarde
        document.getElementById('btnSaveGroups').style.display = 'inline-block';
    }

    XdisplayResults(data) {
        console.log('Résultats reçus:', data);
        console.log('Résultats complets Flask:', JSON.stringify(data, null, 2));

        const resultsDiv = document.getElementById('dupResults');

        if (!data.groups || data.groups.length === 0) {
            resultsDiv.innerHTML = '<div class="no-results">✅ Aucun doublon trouvé !</div>';
            return;
        }

        // Sauvegarder les groupes pour la validation
        this.currentGroups = data.groups;

        // Afficher les groupes
        let html = `
        <div class="duplicate-summary">
            <h3>📊 ${data.groups.length} groupe(s) de doublons trouvés</h3>
<p>${data.total_images || data.groups.reduce((sum, g) => sum + g.images.length, 0)} images analysées</p>

        </div>
    `;

        data.groups.forEach((group, groupIndex) => {
            html += `
            <div class="duplicate-group" data-group-index="${groupIndex}">
                <div class="group-header">
                    <h4>Groupe ${groupIndex + 1} - ${group.images.length} images similaires</h4>
                    <span class="similarity-badge">Similarité: ${(group.similarity_avg * 100).toFixed(0)}%</span>
                </div>
                
                <div class="group-images">
                    ${group.images.map((img, imgIndex) => `
                        <div class="dup-image ${img.is_primary ? 'primary' : ''}" 
                             data-asset-id="${img.asset_id}"
                             data-group-index="${groupIndex}"
                             data-image-index="${imgIndex}">
                            
                            <img src="../public/image-proxy.php?id=${img.asset_id}&type=thumbnail" 
                                 alt="${img.filename || 'Image'}">
                            
                            <div class="image-info">
                                ${img.is_primary ? '<span class="primary-badge">⭐ Principale</span>' : ''}
                                <label class="select-primary">
                                    <input type="radio" 
                                           name="primary-${groupIndex}" 
                                           value="${imgIndex}"
                                           ${img.is_primary ? 'checked' : ''}
                                           onchange="duplicateManager.setPrimary(${groupIndex}, ${imgIndex})">
                                    Principale
                                </label>
                            </div>
                            
                            <button class="btn-remove" 
                                    onclick="duplicateManager.removeFromGroup(${groupIndex}, ${imgIndex})"
                                    title="Retirer du groupe">
                                ❌
                            </button>
                        </div>
                    `).join('')}
                </div>
                
                <div class="group-actions">
                    <button class="btn btn-sm" onclick="duplicateManager.splitGroup(${groupIndex})">
                        ✂️ Séparer le groupe
                    </button>
                </div>
            </div>
        `;
        });

        resultsDiv.innerHTML = html;

        // Activer le bouton de sauvegarde
        document.getElementById('btnSaveGroups').style.display = 'inline-block';
    }

    // Méthodes pour gérer les actions
    setPrimary(groupIndex, imageIndex) {
        console.log(`Définir image ${imageIndex} comme principale du groupe ${groupIndex}`);

        // Mettre à jour dans currentGroups
        this.currentGroups[groupIndex].images.forEach((img, idx) => {
            img.is_primary = (idx === imageIndex);
        });

        // Rafraîchir l'affichage
        this.refreshGroup(groupIndex);
    }

    removeFromGroup(groupIndex, imageIndex) {
        if (confirm('Retirer cette image du groupe de doublons ?')) {
            // Retirer de currentGroups
            this.currentGroups[groupIndex].images.splice(imageIndex, 1);

            // Si le groupe n'a plus qu'une image, le supprimer
            if (this.currentGroups[groupIndex].images.length <= 1) {
                this.currentGroups.splice(groupIndex, 1);
                this.displayResults({ groups: this.currentGroups });
            } else {
                this.refreshGroup(groupIndex);
            }
        }
    }

    splitGroup(groupIndex) {
        if (confirm('Séparer ce groupe ? Chaque image deviendra indépendante.')) {
            this.currentGroups.splice(groupIndex, 1);
            this.displayResults({ groups: this.currentGroups });
        }
    }

    refreshGroup(groupIndex) {
        // Réafficher juste ce groupe
        const groupDiv = document.querySelector(`[data-group-index="${groupIndex}"]`);
        if (groupDiv) {
            // Recréer le HTML pour ce groupe
            // (ou utiliser displayResults avec les groupes modifiés)
            this.displayResults({ groups: this.currentGroups });
        }
    }
}