// public/assets/js/edit-photos/modules/DuplicateManager.js

export default class DuplicateManager {
    constructor(config) {
        this.config = config;
        this.eventsBound = false;
        this.sseManager = new SSEManager();
        this.currentGroups = [];
        
        this.initUI();
        this.bindEvents();
    }

    initUI() {
        // Garder votre modal existant, juste ajouter un indicateur de qualité
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
                            <label style="margin-left: 20px;">
                                <input type="checkbox" id="analyzeQuality" checked>
                                Analyser la qualité des images
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
                        <button id="btnKeepBest" class="btn btn-warning" style="display: none;">
                            ⭐ Garder seulement les meilleures
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

        // Listeners existants
        document.getElementById('dupThreshold').addEventListener('input', (e) => {
            document.getElementById('dupThresholdValue').textContent = 
                Math.round(e.target.value * 100) + '%';
        });
        
        document.getElementById('btnStartDetection').addEventListener('click', () => {
            this.startDetection();
        });
        
        document.getElementById('btnSaveGroups').addEventListener('click', () => {
            this.saveGroups();
        });
        
        // Nouveau bouton pour garder les meilleures
        document.getElementById('btnKeepBest').addEventListener('click', () => {
            this.keepBestInAllGroups();
        });
    }

    bindEvents() {
        if (this.eventsBound) {
            console.warn('Events déjà bindés !');
            return;
        }
        this.eventsBound = true;

        document.getElementById('btnFindDuplicatesSelection')?.addEventListener('click', () => {
            this.findDuplicates('selection');
        });

        document.getElementById('btnFindDuplicatesAll')?.addEventListener('click', () => {
            this.findDuplicates('all');
        });
    }

    findDuplicates(mode) {
        console.log('findDuplicates appelé avec mode:', mode);
        
        this.mode = mode;
        this.showModal();
        
        if (mode === 'selection') {
            this.assetIds = this.getSelectedAssetIds();
            document.getElementById('dupStatus').textContent = 
                `${this.assetIds.length} photos sélectionnées`;
        } else {
            this.assetIds = this.getAllAssetIds();
            document.getElementById('dupStatus').textContent = 
                `${this.assetIds.length} photos dans la galerie`;
        }
        
        this.currentAssetIds = this.assetIds;
    }

    async startDetection() {
        const threshold = parseFloat(document.getElementById('dupThreshold').value);
        const analyzeQuality = document.getElementById('analyzeQuality').checked;
        const requestId = `dup-${Date.now()}`;
        
        document.getElementById('dupProgress').style.display = 'block';
        document.getElementById('btnStartDetection').disabled = true;
        
        try {
            const response = await fetch(`${this.config.flaskUrl}/api/duplicates/find-similar-async`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    selected_asset_ids: this.assetIds,
                    threshold: threshold,
                    analyze_quality: analyzeQuality,  // Nouveau paramètre
                    group_by_time: true,
                    time_window_hours: 24
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.connectSSE(result.request_id);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors du démarrage de la détection');
        }
    }

    connectSSE(requestId) {
        const url = `${this.config.flaskUrl}/api/duplicates/find-similar-stream/${requestId}`;
        
        this.sseManager.connect(`dup-${requestId}`, url, {
            onHeartbeat: (data) => {
                this.flashHeartbeat();
            },
            
            onProgress: (progress, details, step) => {
                const fill = document.getElementById('dupProgressFill');
                fill.style.width = `${progress}%`;
                fill.textContent = `${progress}% - ${details}`;
                
                // Afficher l'étape en cours
                document.getElementById('dupStatus').textContent = step;
            },
            
            onResult: (step, data) => {
                // Notifications pour certaines étapes
                if (step === 'model_loaded') {
                    this.showToast('Modèle CLIP chargé', 'info');
                } else if (step === 'quality') {
                    this.showToast('Analyse de qualité en cours...', 'info');
                }
            },
            
            onComplete: (data) => {
                this.displayResults(data);
                document.getElementById('btnStartDetection').disabled = false;
                document.getElementById('btnSaveGroups').style.display = 'inline-block';
                
                // Montrer le bouton "Garder les meilleures" si analyse qualité
                if (data.groups.some(g => g.images.some(img => img.quality_score !== undefined))) {
                    document.getElementById('btnKeepBest').style.display = 'inline-block';
                }
            },
            
            onError: (error) => {
                console.error('Erreur SSE:', error);
                document.getElementById('btnStartDetection').disabled = false;
                alert('Erreur pendant la détection');
            }
        });
    }

    displayResults(data) {
        console.log('Résultats reçus:', data);
        
        const resultsDiv = document.getElementById('dupResults');
        
        if (!data.groups || data.groups.length === 0) {
            resultsDiv.innerHTML = '<div class="no-results">✅ Aucun doublon trouvé !</div>';
            return;
        }
        
        this.currentGroups = data.groups;
        
        let html = `
            <div class="duplicate-summary">
                <h3>📊 ${data.groups.length} groupe(s) de doublons trouvés</h3>
                <p>${data.total_duplicates || 0} doublons au total</p>
            </div>
        `;
        
        data.groups.forEach((group, groupIndex) => {
            html += `
                <div class="duplicate-group" data-group-index="${groupIndex}">
                    <div class="group-header">
                        <h4>Groupe ${groupIndex + 1} - ${group.images.length} images similaires</h4>
                        <span class="similarity-badge">Similarité: ${(group.similarity_avg * 100).toFixed(0)}%</span>
                        <button class="btn btn-sm btn-keep-best-group" onclick="duplicateManager.keepBestInGroup(${groupIndex})">
                            ⭐ Garder la meilleure
                        </button>
                    </div>
                    
                    <div class="group-images">
                        ${group.images.map((img, imgIndex) => 
                            this.renderThumbnail(img, groupIndex, imgIndex)
                        ).join('')}
                    </div>
                    
                    <div class="group-actions">
                        <button class="btn btn-sm" onclick="duplicateManager.splitGroup(${groupIndex})">
                            ✂️ Séparer le groupe
                        </button>
                        <button class="btn btn-sm" onclick="duplicateManager.showGroupDetails(${groupIndex})">
                            📊 Détails qualité
                        </button>
                    </div>
                </div>
            `;
        });
        
        resultsDiv.innerHTML = html;
        document.getElementById('btnSaveGroups').style.display = 'inline-block';
    }

    renderThumbnail(img, groupIndex, imgIndex) {
        let template = document.getElementById('duplicate-thumbnail-template').innerHTML;
        
        // Ajouter les infos de qualité si disponibles
        const qualityInfo = img.quality_score !== undefined ? `
            <div class="quality-info">
                <span class="quality-score ${img.is_primary ? 'best' : ''}">
                    ${img.is_primary ? '⭐' : ''} ${img.quality_score.toFixed(1)}
                </span>
                ${img.quality_reasons ? `<span class="quality-reasons">${img.quality_reasons.join(', ')}</span>` : ''}
            </div>
        ` : '';
        
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
            lng: img.longitude ? img.longitude.toFixed(4) : '',
            qualityInfo: qualityInfo  // Nouveau
        };
        
        // Remplacer les variables
        Object.keys(data).forEach(key => {
            template = template.replace(new RegExp(`{{${key}}}`, 'g'), data[key]);
        });
        
        // Gérer les conditions
        template = template.replace(/{{#(\w+)}}([\s\S]*?){{\/\1}}/g, (match, key, content) => {
            return data[key] ? content : '';
        });
        
        return template;
    }

    // Nouvelle méthode pour garder la meilleure dans un groupe
    keepBestInGroup(groupIndex) {
        const group = this.currentGroups[groupIndex];
        if (!group) return;
        
        const bestImage = group.images.find(img => img.is_primary) || group.images[0];
        const othersCount = group.images.length - 1;
        
        if (confirm(`Garder seulement "${bestImage.filename}" et supprimer ${othersCount} doublons ?`)) {
            // Marquer pour suppression
            group.images.forEach(img => {
                if (img.asset_id !== bestImage.asset_id) {
                    img.marked_for_deletion = true;
                }
            });
            
            // Rafraîchir l'affichage
            this.refreshGroup(groupIndex);
            
            // Activer le bouton de sauvegarde
            document.getElementById('btnSaveGroups').style.display = 'inline-block';
        }
    }
    
    // Garder les meilleures dans tous les groupes
    keepBestInAllGroups() {
        const totalDuplicates = this.currentGroups.reduce((sum, g) => 
            sum + g.images.filter(img => !img.is_primary).length, 0
        );
        
        if (confirm(`Garder seulement les meilleures images et supprimer ${totalDuplicates} doublons ?`)) {
            this.currentGroups.forEach((group, idx) => {
                group.images.forEach(img => {
                    if (!img.is_primary) {
                        img.marked_for_deletion = true;
                    }
                });
            });
            
            this.displayResults({ groups: this.currentGroups });
        }
    }
    
    // Afficher les détails de qualité
    showGroupDetails(groupIndex) {
        const group = this.currentGroups[groupIndex];
        if (!group) return;
        
        let detailsHtml = `
            <div class="quality-details-modal">
                <h3>Détails qualité - Groupe ${groupIndex + 1}</h3>
                <table class="quality-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Score</th>
                            <th>Netteté</th>
                            <th>Exposition</th>
                            <th>Résolution</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        group.images.forEach(img => {
            const metrics = img.quality_metrics || {};
            detailsHtml += `
                <tr class="${img.is_primary ? 'primary-row' : ''}">
                    <td>${img.filename || img.asset_id.substr(0, 8)}</td>
                    <td>${img.quality_score ? img.quality_score.toFixed(1) : '-'}</td>
                    <td>${metrics.sharpness || '-'}</td>
                    <td>${metrics.exposure || '-'}</td>
                    <td>${metrics.resolution || '-'}</td>
                </tr>
            `;
        });
        
        detailsHtml += `
                    </tbody>
                </table>
                <button onclick="this.parentElement.remove()">Fermer</button>
            </div>
        `;
        
        // Créer un overlay temporaire
        const overlay = document.createElement('div');
        overlay.className = 'quality-details-overlay';
        overlay.innerHTML = detailsHtml;
        document.body.appendChild(overlay);
    }
    
    // Méthodes existantes adaptées
    setPrimary(groupIndex, imageIndex) {
        console.log(`Définir image ${imageIndex} comme principale du groupe ${groupIndex}`);
        
        this.currentGroups[groupIndex].images.forEach((img, idx) => {
            img.is_primary = (idx === imageIndex);
        });
        
        this.refreshGroup(groupIndex);
    }

    removeFromGroup(groupIndex, imageIndex) {
        if (confirm('Retirer cette image du groupe de doublons ?')) {
            this.currentGroups[groupIndex].images.splice(imageIndex, 1);
            
            if (this.currentGroups[groupIndex].images.length <= 1) {
                this.currentGroups.splice(groupIndex, 1);
                this.displayResults({ groups: this.currentGroups });
            } else {
                // Réassigner la meilleure si nécessaire
                if (!this.currentGroups[groupIndex].images.some(img => img.is_primary)) {
                    // Choisir la meilleure selon le score
                    const best = this.currentGroups[groupIndex].images.reduce((prev, curr) => 
                        (curr.quality_score || 0) > (prev.quality_score || 0) ? curr : prev
                    );
                    best.is_primary = true;
                }
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
        this.displayResults({ groups: this.currentGroups });
    }
    
    // Sauvegarder avec gestion des suppressions
    async saveGroups() {
        // Collecter les images à supprimer
        const toDelete = [];
        this.currentGroups.forEach(group => {
            group.images.forEach(img => {
                if (img.marked_for_deletion) {
                    toDelete.push(img.asset_id);
                }
            });
        });
        
        if (toDelete.length > 0) {
            if (confirm(`Supprimer ${toDelete.length} doublons ?`)) {
                try {
                    // Appel API pour supprimer
                    const response = await fetch('/admin/edit-photos-ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_duplicates',
                            gallery: this.config.galleryId,
                            asset_ids: JSON.stringify(toDelete)
                        })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        this.showToast(`${toDelete.length} doublons supprimés`, 'success');
                        this.closeModal();
                        // Recharger la page ou mettre à jour l'affichage
                        location.reload();
                    }
                } catch (error) {
                    console.error('Erreur suppression:', error);
                    alert('Erreur lors de la suppression');
                }
            }
        }
        
        // Sauvegarder les groupes validés
        console.log('Groupes finaux:', this.currentGroups);
    }
    
    // Utilitaires
    flashHeartbeat() {
        const indicator = document.getElementById('heartbeatIndicator');
        if (indicator) {
            indicator.classList.add('beating');
            setTimeout(() => {
                indicator.classList.remove('beating');
            }, 500);
        }
    }
    
    showToast(message, type = 'info') {
        // Utiliser votre système de toast existant
        const toast = document.getElementById('toast');
        if (toast) {
            toast.textContent = message;
            toast.className = `toast show ${type}`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
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
}