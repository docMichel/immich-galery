// public/assets/js/edit-photos/modules/PhotoManager.js

class PhotoManager {
    constructor() {
        this.selectedPhotos = new Set();
        this.clipboardGPS = null;
        this.currentFilter = 'all';
        this.listeners = {};
    }
    
    init() {
        // Sélection individuelle
        document.querySelectorAll('.photo-select').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.togglePhotoSelection(e.target);
            });
        });
        
        // Clic sur les photos
        document.querySelectorAll('.photo-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.closest('.selection-checkbox')) return;
                
                const checkbox = item.querySelector('.photo-select');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    this.togglePhotoSelection(checkbox);
                }
            });
            
            // Double-clic pour les piles de doublons
            item.addEventListener('dblclick', (e) => {
                if (item.classList.contains('has-duplicates')) {
                    this.emit('expandDuplicates', item.dataset.assetId);
                }
            });
        });
        
        // Sélection par date
        document.querySelectorAll('.date-select').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.toggleDateSelection(e.target);
            });
        });
        
        this.updateUI();
    }
    
    // Gestion des événements
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }
    
    // Sélection
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
        
        this.updateUI();
        this.emit('selectionChanged', this.selectedPhotos);
    }
    
    toggleDateSelection(checkbox) {
        const date = checkbox.dataset.date;
        const photos = document.querySelectorAll(`.photo-item[data-date="${date}"]`);
        
        photos.forEach(item => {
            const photoCheckbox = item.querySelector('.photo-select');
            if (photoCheckbox) {
                photoCheckbox.checked = checkbox.checked;
                this.togglePhotoSelection(photoCheckbox);
            }
        });
    }
    
    toggleSelectAll() {
        const visiblePhotos = document.querySelectorAll('.photo-item:not(.hidden)');
        const allSelected = this.isAllSelected();
        
        visiblePhotos.forEach(item => {
            const checkbox = item.querySelector('.photo-select');
            if (checkbox) {
                checkbox.checked = !allSelected;
                this.togglePhotoSelection(checkbox);
            }
        });
        
        // Mettre à jour les checkboxes de date
        document.querySelectorAll('.date-select').forEach(dateCheckbox => {
            dateCheckbox.checked = !allSelected;
        });
    }
    
    isAllSelected() {
        const visiblePhotos = document.querySelectorAll('.photo-item:not(.hidden)');
        return Array.from(visiblePhotos).every(item => 
            this.selectedPhotos.has(item.dataset.assetId)
        );
    }
    
    getSelection() {
        return this.selectedPhotos;
    }
    
    // GPS
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
        
        const thumbImg = photoItem.querySelector('img');
        
        this.clipboardGPS = {
            latitude: parseFloat(photoItem.dataset.latitude),
            longitude: parseFloat(photoItem.dataset.longitude),
            assetId: assetId,
            thumbSrc: thumbImg ? thumbImg.src : ''
        };
        
        this.showToast('Coordonnées GPS copiées', 'success');
        this.emit('clipboardChanged', this.clipboardGPS);
    }
    
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
                this.updatePhotosGPS(assetIds, this.clipboardGPS.latitude, this.clipboardGPS.longitude);
                this.showToast(result.message, 'success');
            }
        } catch (error) {
            this.showToast('Erreur lors de la mise à jour', 'error');
        }
    }
    
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
                this.updatePhotosGPS(assetIds, null, null);
                this.showToast(result.message, 'success');
            }
        } catch (error) {
            this.showToast('Erreur lors de la suppression', 'error');
        }
    }
    
    setClipboardGPS(coords) {
        this.clipboardGPS = {
            latitude: coords.latitude,
            longitude: coords.longitude,
            assetId: 'map-selection',
            thumbSrc: ''
        };
        
        this.emit('clipboardChanged', this.clipboardGPS);
        
        if (confirm('Appliquer ces coordonnées aux photos sélectionnées ?')) {
            this.pasteGPS();
        }
    }
    
    hasClipboardGPS() {
        return this.clipboardGPS !== null;
    }
    
    // Rotation
    async rotateSelection(direction) {
        if (this.selectedPhotos.size === 0) return;
        
        for (const assetId of this.selectedPhotos) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rotate_image',
                        asset_id: assetId,
                        direction: direction
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Recharger l'image
                    const img = document.querySelector(`[data-asset-id="${assetId}"] img`);
                    if (img) {
                        img.src = img.src + '&t=' + Date.now();
                    }
                }
            } catch (error) {
                console.error('Erreur rotation:', error);
            }
        }
        
        this.showToast(`${this.selectedPhotos.size} photo(s) pivotée(s)`, 'success');
    }
    
    // Filtres
    applyFilter(filter) {
        this.currentFilter = filter;
        
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
            }
            
            item.classList.toggle('hidden', !show);
        });
        
        // Masquer les séparateurs de date vides
        document.querySelectorAll('.date-separator').forEach(separator => {
            const date = separator.dataset.date;
            const hasVisiblePhotos = document.querySelector(`.photo-item[data-date="${date}"]:not(.hidden)`);
            separator.classList.toggle('hidden', !hasVisiblePhotos);
        });
    }
    
    // Helpers
    updatePhotosGPS(assetIds, lat, lng) {
        assetIds.forEach(assetId => {
            const item = document.querySelector(`[data-asset-id="${assetId}"]`);
            if (!item) return;
            
            if (lat !== null && lng !== null) {
                item.classList.remove('no-gps');
                item.classList.add('has-gps');
                item.dataset.latitude = lat;
                item.dataset.longitude = lng;
                
                let badge = item.querySelector('.gps-badge');
                if (badge) {
                    badge.className = 'gps-badge success';
                    badge.innerHTML = '📍 GPS';
                    badge.title = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                }
            } else {
                item.classList.remove('has-gps');
                item.classList.add('no-gps');
                item.dataset.latitude = '';
                item.dataset.longitude = '';
                
                let badge = item.querySelector('.gps-badge');
                if (badge) {
                    badge.className = 'gps-badge warning';
                    badge.innerHTML = '❓ Pas de GPS';
                    badge.title = '';
                }
            }
        });
        
        this.updateCounts();
    }
    
    updateCounts() {
        const total = document.querySelectorAll('.photo-item').length;
        const withGPS = document.querySelectorAll('.photo-item.has-gps').length;
        const withoutGPS = total - withGPS;
        
        document.querySelector('[data-filter="all"] .pill-count').textContent = total;
        document.querySelector('[data-filter="with-gps"] .pill-count').textContent = withGPS;
        document.querySelector('[data-filter="without-gps"] .pill-count').textContent = withoutGPS;
    }
    
    updateUI() {
        // Mettre à jour les checkboxes de date
        document.querySelectorAll('.date-select').forEach(checkbox => {
            const date = checkbox.dataset.date;
            const photos = document.querySelectorAll(`.photo-item[data-date="${date}"]`);
            const checkedPhotos = document.querySelectorAll(`.photo-item[data-date="${date}"] .photo-select:checked`);
            
            checkbox.checked = photos.length > 0 && photos.length === checkedPhotos.length;
            checkbox.indeterminate = checkedPhotos.length > 0 && checkedPhotos.length < photos.length;
        });
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

export default PhotoManager;