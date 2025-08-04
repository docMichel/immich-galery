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

    displayResults(data) {
        // TODO: Afficher les résultats
        console.log('Résultats:', data);
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