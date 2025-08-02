// public/assets/js/edit-photos/modules/MapManager.js

class MapManager {
    constructor() {
        this.map = null;
        this.photoMarkers = [];
        this.selectionMarkers = [];
        this.mapMarker = null;
        this.selectedMapCoords = null;
        this.onConfirmCallback = null;
    }

    openModal(selectedPhotos, onConfirm) {
        this.onConfirmCallback = onConfirm;

        const modal = document.getElementById('mapModal');
        if (!modal) {
            console.error('Map modal not found');
            return;
        }

        modal.classList.add('active');

        // Initialiser la carte si pas déjà fait
        if (!this.map) {
            // Attendre que le modal soit visible
            setTimeout(() => {
                this.initMap();
                if (this.map) {
                    this.setupMapView(selectedPhotos);
                }
            }, 100);
        } else {
            // Nettoyer les marqueurs précédents
            this.clearMarkers();
            this.setupMapView(selectedPhotos);

            // Rafraîchir la carte
            setTimeout(() => {
                this.map.invalidateSize();
            }, 100);
        }

        // Nettoyer les marqueurs précédents
        this.clearMarkers();

        // Ajouter les marqueurs
        this.setupMapView(selectedPhotos);

        // Rafraîchir la carte
        setTimeout(() => {
            this.map.invalidateSize();
        }, 100);
    }

    initMap() {
        console.log('initMap called');
        const mapElement = document.getElementById('map');
        console.log('Map element:', mapElement);

        if (!mapElement) {
            console.error('Element with id "map" not found!');
            return;
        }

        this.map = L.map('map').setView([-22.2711, 166.4416], 10);
        console.log('Map created:', this.map);
        // ...


        // Tuiles OSM
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        });

        // Tuiles satellite
        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri',
            maxZoom: 19
        });

        // Tuiles terrain
        const terrain = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenTopoMap contributors',
            maxZoom: 17
        });

        // Ajouter OSM par défaut
        osm.addTo(this.map);

        // Contrôle des couches
        L.control.layers({
            'Carte': osm,
            'Satellite': satellite,
            'Terrain': terrain
        }).addTo(this.map);

        // Ajouter le contrôle de recherche
        this.addSearchControl();

        // Gérer le clic sur la carte
        this.map.on('click', (e) => this.handleMapClick(e));
    }

    addSearchControl() {
        const SearchControl = L.Control.extend({
            onAdd: (map) => {
                const container = L.DomUtil.create('div', 'leaflet-control-search');
                container.style.cssText = 'background: white; padding: 5px; border-radius: 4px; box-shadow: 0 1px 5px rgba(0,0,0,0.4);';

                const input = L.DomUtil.create('input', '', container);
                input.type = 'text';
                input.placeholder = 'Rechercher un lieu...';
                input.style.cssText = 'width: 200px; padding: 5px; border: 1px solid #ccc; border-radius: 3px;';

                const button = L.DomUtil.create('button', '', container);
                button.innerHTML = '🔍';
                button.style.cssText = 'margin-left: 5px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 3px; background: #f0f0f0; cursor: pointer;';

                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);

                const performSearch = () => {
                    const query = input.value.trim();
                    if (query) {
                        this.searchLocation(query);
                    }
                };

                button.onclick = performSearch;
                input.onkeypress = (e) => {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                };

                return container;
            }
        });

        new SearchControl({ position: 'topright' }).addTo(this.map);
    }

    async searchLocation(query) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?` +
                `format=json&q=${encodeURIComponent(query)}&limit=5`
            );

            const results = await response.json();

            if (results.length > 0) {
                const place = results[0];
                const lat = parseFloat(place.lat);
                const lon = parseFloat(place.lon);

                this.map.setView([lat, lon], 14);

                // Ajouter un marqueur temporaire
                if (this.searchMarker) {
                    this.map.removeLayer(this.searchMarker);
                }

                this.searchMarker = L.marker([lat, lon], {
                    icon: this.getColoredIcon('orange')
                }).addTo(this.map);

                this.searchMarker.bindPopup(
                    `<b>${place.display_name}</b><br>` +
                    `Cliquez sur la carte pour sélectionner un point précis`
                ).openPopup();

                setTimeout(() => {
                    if (this.searchMarker) {
                        this.map.removeLayer(this.searchMarker);
                        this.searchMarker = null;
                    }
                }, 5000);
            } else {
                alert(`Aucun résultat trouvé pour "${query}"`);
            }
        } catch (error) {
            console.error('Erreur de recherche:', error);
            alert('Erreur lors de la recherche');
        }
    }

    clearMarkers() {
        this.photoMarkers.forEach(marker => this.map.removeLayer(marker));
        this.photoMarkers = [];

        this.selectionMarkers.forEach(marker => this.map.removeLayer(marker));
        this.selectionMarkers = [];

        if (this.mapMarker) {
            this.map.removeLayer(this.mapMarker);
            this.mapMarker = null;
        }

        if (this.searchMarker) {
            this.map.removeLayer(this.searchMarker);
            this.searchMarker = null;
        }
    }

    setupMapView(selectedPhotos) {
        const bounds = [];

        // Ajouter TOUTES les photos avec GPS (pas seulement les sélectionnées)
        document.querySelectorAll('.photo-item.has-gps').forEach(item => {
            const lat = parseFloat(item.dataset.latitude);
            const lng = parseFloat(item.dataset.longitude);
            const assetId = item.dataset.assetId;
            const isSelected = selectedPhotos.has(assetId);

            if (!isNaN(lat) && !isNaN(lng)) {
                // Utiliser des couleurs différentes
                const marker = L.circleMarker([lat, lng], {
                    radius: isSelected ? 8 : 6,
                    fillColor: isSelected ? '#ff6b6b' : '#007bff',
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: isSelected ? 0.9 : 0.6
                }).addTo(this.map);

                // Popup avec info
                const caption = item.querySelector('.photo-caption-text')?.textContent || 'Photo';
                marker.bindPopup(
                    `<b>${caption}</b><br>` +
                    `GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}<br>` +
                    `${isSelected ? '<span style="color:#ff6b6b">● Sélectionnée</span>' : ''}`
                );

                if (isSelected) {
                    this.selectionMarkers.push(marker);
                } else {
                    this.photoMarkers.push(marker);
                }

                bounds.push([lat, lng]);
            }
        });

        // Ajuster la vue
        if (bounds.length > 0) {
            this.map.fitBounds(bounds, { padding: [50, 50] });
        } else {
            // Vue mondiale si pas de photos avec GPS
            this.map.setView([0, 0], 2);
        }
    }

    handleMapClick(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (this.mapMarker) {
            this.map.removeLayer(this.mapMarker);
        }

        this.mapMarker = L.marker([lat, lng], {
            icon: this.getColoredIcon('green')
        }).addTo(this.map);

        this.mapMarker.bindPopup('<b>Nouvelle position</b>').openPopup();

        this.selectedMapCoords = { latitude: lat, longitude: lng };

        document.querySelector('.map-coords').textContent =
            `GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        document.getElementById('btnConfirmLocation').disabled = false;
    }

    getColoredIcon(color) {
        return L.icon({
            iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color}.png`,
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
    }

    confirmLocation() {
        if (this.selectedMapCoords && this.onConfirmCallback) {
            this.onConfirmCallback(this.selectedMapCoords);
            this.closeModal();
        }
    }

    closeModal() {
        document.getElementById('mapModal').classList.remove('active');
    }
}

// Fonctions globales pour la modal
window.closeMapModal = function () {
    if (window.editPhotos && window.editPhotos.mapManager) {
        window.editPhotos.mapManager.closeModal();
    }
}

window.confirmMapLocation = function () {
    if (window.editPhotos && window.editPhotos.mapManager) {
        window.editPhotos.mapManager.confirmLocation();
    }
}

export default MapManager;