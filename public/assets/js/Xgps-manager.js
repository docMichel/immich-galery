// public/assets/js/gps-manager.js

class GPSManager {
    constructor() {
        this.selectedPhotos = new Set();
        this.clipboardGPS = null;
        this.currentFilter = 'all';
        this.lastClickedPhoto = null; // Pour le Shift+Click
        this.mapManager = new MapManager();

        this.init();
    }

    init() {
        // Éléments DOM
        this.grid = document.getElementById('imagesGrid');
        this.selectionCount = document.getElementById('selectionCount');
        this.clipboardInfo = document.getElementById('clipboardInfo');
        this.clipboardCoords = document.getElementById('clipboardCoords');
        this.clipboardThumb = document.getElementById('clipboardThumb');
        this.toast = document.getElementById('toast');

        // Boutons
        this.btnSelectAll = document.getElementById('btnSelectAll');
        this.btnCopyGPS = document.getElementById('btnCopyGPS');
        this.btnPasteGPS = document.getElementById('btnPasteGPS');
        this.btnRemoveGPS = document.getElementById('btnRemoveGPS');
        this.btnSameDay = document.getElementById('btnSameDay');
        this.btnMapSelect = document.getElementById('btnMapSelect');

        // Map
        this.map = null;
        this.mapMarker = null;
        this.selectedMapCoords = null;

        // Attacher les événements
        this.attachEvents();

        // Initialiser l'état
        this.updateUI();
    }

    attachEvents() {
        // Filtres
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.addEventListener('click', (e) => this.filterPhotos(e.target.dataset.filter));
        });

        // Sélection des photos
        document.querySelectorAll('.photo-select').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => this.togglePhotoSelection(e.target));
        });

        // Clic sur les photos (sélection rapide)
        document.querySelectorAll('.photo-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Ne pas interférer avec le clic sur la checkbox elle-même
                if (e.target.closest('.selection-checkbox')) {
                    return;
                }

                const checkbox = item.querySelector('.photo-select');
                if (checkbox) {
                    // Gestion du Shift+Click pour sélection multiple
                    if (e.shiftKey && this.lastClickedPhoto) {
                        this.selectRange(this.lastClickedPhoto, item);
                    } else {
                        checkbox.checked = !checkbox.checked;
                        this.togglePhotoSelection(checkbox);
                        this.lastClickedPhoto = item;
                    }
                }
            });
        });

        // Boutons d'action
        this.btnSelectAll.addEventListener('click', () => this.selectAll());
        this.btnCopyGPS.addEventListener('click', () => this.copyGPS());
        this.btnPasteGPS.addEventListener('click', () => this.pasteGPS());
        this.btnRemoveGPS.addEventListener('click', () => this.removeGPS());
        this.btnSameDay.addEventListener('click', () => this.selectSameDay());
        this.btnMapSelect.addEventListener('click', () => this.openMapModal());
    }

    // Sélectionner les photos du même jour
    selectSameDay() {
        if (this.selectedPhotos.size !== 1) {
            this.showToast('Sélectionnez exactement une photo', 'error');
            return;
        }

        const assetId = Array.from(this.selectedPhotos)[0];
        const selectedPhoto = document.querySelector(`[data-asset-id="${assetId}"]`);
        const selectedDate = selectedPhoto.dataset.date;

        if (!selectedDate) {
            this.showToast('Date non disponible pour cette photo', 'error');
            return;
        }

        // Compter les photos du même jour
        let count = 0;
        document.querySelectorAll('.photo-item').forEach(item => {
            if (item.dataset.date === selectedDate) {
                count++;
            }
        });

        // Activer le filtre "même jour"
        this.currentFilter = 'same-day';
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.classList.remove('active');
        });

        const sameDayFilter = document.querySelector('[data-filter="same-day"]');
        sameDayFilter.style.display = 'inline-flex';
        sameDayFilter.classList.add('active');
        sameDayFilter.querySelector('.pill-count').textContent = count;

        // Masquer les photos qui ne sont pas du même jour
        document.querySelectorAll('.photo-item').forEach(item => {
            item.classList.toggle('hidden', item.dataset.date !== selectedDate);
        });

        this.showToast(`${count} photos du ${selectedDate} affichées`, 'success');
        this.updateUI();
    }

    // Filtrer les photos (modifié pour gérer le filtre "même jour")
    filterPhotos(filter) {
        this.currentFilter = filter;

        // Si on quitte le filtre "même jour", le masquer
        if (filter !== 'same-day') {
            const sameDayFilter = document.querySelector('[data-filter="same-day"]');
            sameDayFilter.style.display = 'none';
        }

        // Mettre à jour les boutons de filtre
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        // Filtrer les photos
        document.querySelectorAll('.photo-item').forEach(item => {
            const hasGPS = item.classList.contains('has-gps');
            let show = true;

            switch (filter) {
                case 'with-gps':
                    show = hasGPS;
                    break;
                case 'without-gps':
                    show = !hasGPS;
                    break;
                case 'same-day':
                    // Le filtre même jour est géré dans selectSameDay()
                    return;
            }

            item.classList.toggle('hidden', !show);
        });
    }

    // Sélectionner une plage de photos (Shift+Click)
    selectRange(startItem, endItem) {
        const allVisiblePhotos = Array.from(document.querySelectorAll('.photo-item:not(.hidden)'));
        const startIndex = allVisiblePhotos.indexOf(startItem);
        const endIndex = allVisiblePhotos.indexOf(endItem);

        if (startIndex === -1 || endIndex === -1) return;

        const minIndex = Math.min(startIndex, endIndex);
        const maxIndex = Math.max(startIndex, endIndex);

        // Sélectionner toutes les photos dans la plage
        for (let i = minIndex; i <= maxIndex; i++) {
            const item = allVisiblePhotos[i];
            const checkbox = item.querySelector('.photo-select');
            const assetId = item.dataset.assetId;

            if (checkbox && !checkbox.checked) {
                checkbox.checked = true;
                this.selectedPhotos.add(assetId);
                item.classList.add('selected');
            }
        }

        this.updateUI();
    }

    // Sélectionner/désélectionner une photo
    togglePhotoSelection(checkbox) {
        const photoItem = checkbox.closest('.photo-item');
        const assetId = photoItem.dataset.assetId;

        if (checkbox.checked) {
            this.selectedPhotos.add(assetId);
            photoItem.classList.add('selected');
        } else {
            this.selectedPhotos.delete(assetId);
            photoItem.classList.remove('selected');
        }

        // Mémoriser la dernière photo cliquée
        this.lastClickedPhoto = photoItem;

        this.updateUI();
    }

    // Tout sélectionner/désélectionner
    selectAll() {
        const visiblePhotos = document.querySelectorAll('.photo-item:not(.hidden)');
        const visibleAssetIds = Array.from(visiblePhotos).map(item => item.dataset.assetId);
        const allSelected = visibleAssetIds.every(id => this.selectedPhotos.has(id));

        visiblePhotos.forEach(item => {
            const checkbox = item.querySelector('.photo-select');
            const assetId = item.dataset.assetId;

            if (allSelected) {
                // Tout désélectionner
                checkbox.checked = false;
                this.selectedPhotos.delete(assetId);
                item.classList.remove('selected');
            } else {
                // Tout sélectionner
                checkbox.checked = true;
                this.selectedPhotos.add(assetId);
                item.classList.add('selected');
            }
        });

        this.updateUI();
    }

    // Copier les coordonnées GPS
    copyGPS() {
        if (this.selectedPhotos.size !== 1) {
            this.showToast('Sélectionnez exactement une photo avec GPS', 'error');
            return;
        }

        const assetId = Array.from(this.selectedPhotos)[0];
        const photoItem = document.querySelector(`[data-asset-id="${assetId}"]`);

        if (!photoItem.classList.contains('has-gps')) {
            this.showToast('Cette photo n\'a pas de coordonnées GPS', 'error');
            return;
        }

        // Récupérer l'image miniature
        const thumbImg = photoItem.querySelector('img');

        this.clipboardGPS = {
            latitude: parseFloat(photoItem.dataset.latitude),
            longitude: parseFloat(photoItem.dataset.longitude),
            assetId: assetId,
            thumbSrc: thumbImg ? thumbImg.src : ''
        };

        this.showToast('Coordonnées GPS copiées', 'success');
        this.updateUI();
    }

    // Coller les coordonnées GPS
    async pasteGPS() {
        if (!this.clipboardGPS) {
            this.showToast('Aucune coordonnée GPS en mémoire', 'error');
            return;
        }

        if (this.selectedPhotos.size === 0) {
            this.showToast('Sélectionnez au moins une photo', 'error');
            return;
        }

        const assetIds = Array.from(this.selectedPhotos);

        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_gps',
                    asset_ids: JSON.stringify(assetIds),
                    latitude: this.clipboardGPS.latitude,
                    longitude: this.clipboardGPS.longitude
                })
            });

            const result = await response.json();

            if (result.success) {
                // Mettre à jour l'affichage
                assetIds.forEach(assetId => {
                    const item = document.querySelector(`[data-asset-id="${assetId}"]`);
                    item.classList.remove('no-gps');
                    item.classList.add('has-gps');
                    item.dataset.latitude = this.clipboardGPS.latitude;
                    item.dataset.longitude = this.clipboardGPS.longitude;

                    // Mettre à jour le badge
                    let badge = item.querySelector('.gps-badge');
                    if (badge) {
                        badge.className = 'gps-badge success';
                        badge.innerHTML = '📍 GPS';
                        badge.title = `${this.clipboardGPS.latitude.toFixed(6)}, ${this.clipboardGPS.longitude.toFixed(6)}`;
                    }
                });

                this.showToast(result.message, 'success');
                this.updateCounts();
            }
        } catch (error) {
            this.showToast('Erreur lors de la mise à jour', 'error');
        }
    }

    // Supprimer les coordonnées GPS
    async removeGPS() {
        if (this.selectedPhotos.size === 0) {
            this.showToast('Sélectionnez au moins une photo', 'error');
            return;
        }

        if (!confirm(`Supprimer les coordonnées GPS de ${this.selectedPhotos.size} photo(s) ?`)) {
            return;
        }

        const assetIds = Array.from(this.selectedPhotos);

        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'remove_gps',
                    asset_ids: JSON.stringify(assetIds)
                })
            });

            const result = await response.json();

            if (result.success) {
                // Mettre à jour l'affichage
                assetIds.forEach(assetId => {
                    const item = document.querySelector(`[data-asset-id="${assetId}"]`);
                    item.classList.remove('has-gps');
                    item.classList.add('no-gps');
                    item.dataset.latitude = '';
                    item.dataset.longitude = '';

                    // Mettre à jour le badge
                    let badge = item.querySelector('.gps-badge');
                    if (badge) {
                        badge.className = 'gps-badge warning';
                        badge.innerHTML = '❓ Pas de GPS';
                        badge.title = '';
                    }
                });

                this.showToast(result.message, 'success');
                this.updateCounts();
            }
        } catch (error) {
            this.showToast('Erreur lors de la suppression', 'error');
        }
    }

    // Mettre à jour l'interface
    updateUI() {
        // Nombre de photos sélectionnées
        this.selectionCount.textContent = this.selectedPhotos.size;

        // État des boutons
        const hasSelection = this.selectedPhotos.size > 0;
        const singleSelection = this.selectedPhotos.size === 1;

        this.btnCopyGPS.disabled = !singleSelection;
        this.btnPasteGPS.disabled = !hasSelection || !this.clipboardGPS;
        this.btnRemoveGPS.disabled = !hasSelection;
        this.btnSameDay.disabled = !singleSelection;
        this.btnMapSelect.disabled = !hasSelection;
        //this.btnFindDuplicates.disabled = !hasSelection;


        // Info presse-papier
        if (this.clipboardGPS) {
            this.clipboardInfo.style.display = 'flex';
            this.clipboardCoords.textContent =
                `${this.clipboardGPS.latitude.toFixed(4)}, ${this.clipboardGPS.longitude.toFixed(4)}`;

            // Afficher la miniature si disponible
            if (this.clipboardGPS.thumbSrc) {
                this.clipboardThumb.src = this.clipboardGPS.thumbSrc;
                this.clipboardThumb.style.display = 'block';
                this.clipboardThumb.title = `GPS de cette photo: ${this.clipboardGPS.latitude.toFixed(6)}, ${this.clipboardGPS.longitude.toFixed(6)}`;
            }
        } else {
            this.clipboardInfo.style.display = 'none';
            this.clipboardThumb.style.display = 'none';
        }

        // Texte du bouton "Tout sélectionner"
        const visiblePhotos = document.querySelectorAll('.photo-item:not(.hidden)');
        const visibleAssetIds = Array.from(visiblePhotos).map(item => item.dataset.assetId);
        const allSelected = visibleAssetIds.length > 0 && visibleAssetIds.every(id => this.selectedPhotos.has(id));
        this.btnSelectAll.innerHTML = allSelected ? '⬜ Tout désélectionner' : '☑️ Tout sélectionner';
    }

    // Mettre à jour les compteurs
    updateCounts() {
        const total = document.querySelectorAll('.photo-item').length;
        const withGPS = document.querySelectorAll('.photo-item.has-gps').length;
        const withoutGPS = total - withGPS;

        // Mettre à jour les badges
        document.querySelector('[data-filter="all"] .pill-count').textContent = total;
        document.querySelector('[data-filter="with-gps"] .pill-count').textContent = withGPS;
        document.querySelector('[data-filter="without-gps"] .pill-count').textContent = withoutGPS;
    }

    // Ouvrir la modal de carte

    openMapModal() {
        this.mapManager.openModal(this.selectedPhotos);
    }

    // Afficher une notification
    showToast(message, type = 'info') {
        this.toast.textContent = message;
        this.toast.className = `toast ${type} show`;

        setTimeout(() => {
            this.toast.classList.remove('show');
        }, 3000);
    }
}

// Fonctions globales pour la modal
window.closeMapModal = function () {
    window.gpsManager.mapManager.closeModal();
}

window.confirmMapLocation = function () {
    const manager = window.gpsManager;
    const coords = manager.mapManager.getSelectedCoords();

    if (!coords || manager.selectedPhotos.size === 0) return;

    // Copier les coordonnées de la carte dans le presse-papier
    manager.clipboardGPS = {
        latitude: coords.latitude,
        longitude: coords.longitude,
        assetId: 'map-selection',
        thumbSrc: ''
    };

    // Fermer la modal
    manager.mapManager.closeModal();

    // Mettre à jour l'UI
    manager.updateUI();

    // Coller automatiquement si souhaité
    if (confirm('Appliquer ces coordonnées aux photos sélectionnées ?')) {
        manager.pasteGPS();
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => {
    window.gpsManager = new GPSManager();
});