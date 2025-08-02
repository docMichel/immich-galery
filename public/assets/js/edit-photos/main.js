// public/assets/js/edit-photos/main.js - Module principal

import PhotoManager from './modules/PhotoManager.js';
//import MapManager from './modules/MapManager.js';
import DuplicateManager from './modules/DuplicateManager.js';
import ViewManager from './modules/ViewManager.js';
import CaptionEditor from './modules/CaptionEditor.js';

class EditPhotos {
    constructor() {
        this.config = window.editPhotosConfig;

        // Initialiser les modules
        this.photoManager = new PhotoManager();
        //this.mapManager = new MapManager(); // Utiliser la classe globale
        this.duplicateManager = new DuplicateManager(this.config.flaskApiUrl);
        this.viewManager = new ViewManager();
        //this.captionEditor = new CaptionEditor();

        this.init();
    }

    init() {
        // Initialiser les vues
        this.viewManager.init();

        // Initialiser la gestion des photos
        this.photoManager.init();

        // Écouter les changements de sélection
        this.photoManager.on('selectionChanged', (selection) => {
            this.updateToolbarState(selection);
        });

        // Écouter les changements de presse-papier GPS
        this.photoManager.on('clipboardChanged', (clipboard) => {
            this.updateClipboardDisplay(clipboard);
        });

        // Boutons de la toolbar
        this.attachToolbarEvents();

        // Filtres
        this.attachFilterEvents();
    }

    attachToolbarEvents() {
        // Sélection
        document.getElementById('btnSelectAll').addEventListener('click', () => {
            this.photoManager.toggleSelectAll();
        });

        // GPS
        document.getElementById('btnCopyGPS').addEventListener('click', () => {
            this.photoManager.copyGPS();
        });

        document.getElementById('btnPasteGPS').addEventListener('click', () => {
            this.photoManager.pasteGPS();
        });

        document.getElementById('btnRemoveGPS').addEventListener('click', () => {
            this.photoManager.removeGPS();
        });

        // map
        // Dans attachToolbarEvents
        document.getElementById('btnMapSelect').addEventListener('click', () => {
            const selection = this.photoManager.getSelection();

            if (!window.mapManager) {
                window.mapManager = new MapManager();
            }

            window.mapManager.openModal(selection);
        });
        /*
        document.getElementById('btnMapSelect').addEventListener('click', () => {
            const selection = this.photoManager.getSelection();
            this.mapManager.openModal(selection, (coords) => {
                this.photoManager.setClipboardGPS(coords);
            });
        });*/
        // Rotation
        document.getElementById('btnRotateLeft').addEventListener('click', () => {
            this.photoManager.rotateSelection('left');
        });

        document.getElementById('btnRotateRight').addEventListener('click', () => {
            this.photoManager.rotateSelection('right');
        });

        // Légende
        /*  document.getElementById('btnEditCaption').addEventListener('click', () => {
              const selection = this.photoManager.getSelection();
              if (selection.size === 1) {
                  const assetId = Array.from(selection)[0];
                  this.captionEditor.openModal(assetId, this.config.galleryId);
              }
          });
          */

        // Doublons
        document.getElementById('btnFindDuplicates').addEventListener('click', () => {
            const selection = this.photoManager.getSelection();
            const threshold = selection.size > 0 ? 0.85 : 0.92; // Seuil plus élevé si pas de sélection
            this.duplicateManager.findDuplicates(selection, threshold);
        });

        document.getElementById('btnAnalyzeDuplicates').addEventListener('click', () => {
            const threshold = parseFloat(document.getElementById('duplicateThreshold').value);
            this.duplicateManager.analyzeDuplicates(this.config.galleryId, threshold);
        });

        // Slider de seuil
        document.getElementById('duplicateThreshold').addEventListener('input', (e) => {
            document.getElementById('thresholdValue').textContent = e.target.value;
        });
    }

    attachFilterEvents() {
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                this.photoManager.applyFilter(filter);

                // Mettre à jour l'état actif
                document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });
    }

    updateToolbarState(selection) {
        const hasSelection = selection.size > 0;
        const singleSelection = selection.size === 1;

        // Compteur
        document.getElementById('selectionCount').textContent = selection.size;

        // Boutons GPS
        document.getElementById('btnCopyGPS').disabled = !singleSelection;
        document.getElementById('btnPasteGPS').disabled = !hasSelection || !this.photoManager.hasClipboardGPS();
        document.getElementById('btnRemoveGPS').disabled = !hasSelection;
        document.getElementById('btnMapSelect').disabled = !hasSelection;

        // Boutons rotation
        document.getElementById('btnRotateLeft').disabled = !hasSelection;
        document.getElementById('btnRotateRight').disabled = !hasSelection;

        // Bouton légende
        //document.getElementById('btnEditCaption').disabled = !singleSelection;

        // Texte du bouton sélectionner tout
        const allSelected = this.photoManager.isAllSelected();
        document.getElementById('btnSelectAll').innerHTML =
            allSelected ? '⬜ Tout désélectionner' : '☑️ Tout sélectionner';
    }

    updateClipboardDisplay(clipboard) {
        const clipboardInfo = document.getElementById('clipboardInfo');
        const clipboardThumb = document.getElementById('clipboardThumb');
        const clipboardCoords = document.getElementById('clipboardCoords');

        if (clipboard) {
            clipboardInfo.style.display = 'flex';
            clipboardCoords.textContent =
                `${clipboard.latitude.toFixed(4)}, ${clipboard.longitude.toFixed(4)}`;

            if (clipboard.thumbSrc) {
                clipboardThumb.src = clipboard.thumbSrc;
                clipboardThumb.style.display = 'block';
            } else {
                clipboardThumb.style.display = 'none';
            }
        } else {
            clipboardInfo.style.display = 'none';
        }
    }
}
// Fonctions globales pour la modal
window.closeMapModal = function() {
    if (window.mapManager) {
        window.mapManager.closeModal();
    }
}

window.confirmMapLocation = function() {
    if (window.mapManager) {
        const coords = window.mapManager.getSelectedCoords();
        
        if (!coords) return;
        
        // Utiliser l'instance globale d'EditPhotos
        if (window.editPhotos && window.editPhotos.photoManager) {
            window.editPhotos.photoManager.setClipboardGPS(coords);
        }
        
        window.mapManager.closeModal();
    }
}


// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => {
    window.editPhotos = new EditPhotos();
    window.editPhotos.mapManager = new MapManager();
});