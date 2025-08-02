
// public/assets/js/modules/MapManager.js

class MapManager {
    constructor() {
        this.map = null;
        this.photoMarkers = [];
        this.mapMarker = null;
        this.selectedMapCoords = null;
    }
    
    // Ouvrir la modal de carte
    openModal(selectedPhotos) {
        const modal = document.getElementById('mapModal');
        modal.classList.add('active');
        
        // Initialiser la carte si pas déjà fait
        if (!this.map) {
            this.initMap();
        }
        
        // Nettoyer les marqueurs précédents
        this.clearMarkers();
        
        // Ajouter les marqueurs et centrer la vue
        this.setupMapView(selectedPhotos);
        
        // Rafraîchir la carte
        setTimeout(() => {
            this.map.invalidateSize();
        }, 100);
    }
    
    // Initialiser la carte Leaflet
    initMap() {
        this.map = L.map('mapModalContent').setView([-22.2711, 166.4416], 10);
        
        // Ajouter plusieurs couches de cartes
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        });
        
        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri',
            maxZoom: 19
        });
        
        // Ajouter OSM par défaut
        osm.addTo(this.map);
        
        // Contrôle des couches
        L.control.layers({
            'Carte': osm,
            'Satellite': satellite
        }).addTo(this.map);
        
        // Ajouter le contrôle de recherche
        this.addSearchControl();
        
        // Gérer le clic sur la carte
        this.map.on('click', (e) => this.handleMapClick(e));
    }
    
    // Ajouter un contrôle de recherche personnalisé
    addSearchControl() {
        const SearchControl = L.Control.extend({
            onAdd: function(map) {
                const container = L.DomUtil.create('div', 'leaflet-control-search');
                container.style.background = 'white';
                container.style.padding = '5px';
                container.style.borderRadius = '4px';
                container.style.boxShadow = '0 1px 5px rgba(0,0,0,0.4)';
                
                const input = L.DomUtil.create('input', '', container);
                input.type = 'text';
                input.placeholder = 'Rechercher un lieu...';
                input.style.width = '200px';
                input.style.padding = '5px';
                input.style.border = '1px solid #ccc';
                input.style.borderRadius = '3px';
                
                const button = L.DomUtil.create('button', '', container);
                button.innerHTML = '🔍';
                button.style.marginLeft = '5px';
                button.style.padding = '5px 10px';
                button.style.border = '1px solid #ccc';
                button.style.borderRadius = '3px';
                button.style.background = '#f0f0f0';
                button.style.cursor = 'pointer';
                
                // Empêcher la propagation des événements
                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);
                
                // Gérer la recherche
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
            }.bind(this)
        });
        
        this.searchControl = new SearchControl({ position: 'topright' });
        this.searchControl.addTo(this.map);
    }
    
    // Rechercher un lieu avec Nominatim (OpenStreetMap)
    async searchLocation(query) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?` +
                `format=json&q=${encodeURIComponent(query)}&limit=5`
            );
            
            const results = await response.json();
            
            if (results.length > 0) {
                // Si plusieurs résultats, essayer de trouver le plus pertinent
                let place = results[0];
                
                // Si plus d'un résultat, proposer un choix
                if (results.length > 1) {
                    const choices = results.slice(0, 5).map((r, i) => 
                        `${i + 1}. ${r.display_name}`
                    ).join('\n');
                    
                    const choice = prompt(
                        `Plusieurs lieux trouvés:\n\n${choices}\n\n` +
                        `Entrez le numéro (1-${Math.min(5, results.length)}) ou Annuler:`,
                        '1'
                    );
                    
                    if (choice && !isNaN(choice)) {
                        const index = parseInt(choice) - 1;
                        if (index >= 0 && index < results.length) {
                            place = results[index];
                        }
                    } else if (!choice) {
                        return;
                    }
                }
                
                const lat = parseFloat(place.lat);
                const lon = parseFloat(place.lon);
                
                // Centrer la carte sur le résultat
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
                
                // Supprimer le marqueur après 5 secondes
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
    
    // Nettoyer tous les marqueurs
    clearMarkers() {
        if (this.photoMarkers) {
            this.photoMarkers.forEach(marker => this.map.removeLayer(marker));
        }
        this.photoMarkers = [];
        
        if (this.mapMarker) {
            this.map.removeLayer(this.mapMarker);
            this.mapMarker = null;
        }
        
        if (this.searchMarker) {
            this.map.removeLayer(this.searchMarker);
            this.searchMarker = null;
        }
    }
    
    // Configurer la vue de la carte avec les marqueurs
    setupMapView(selectedPhotos) {
        console.trace('setupMapView appelé avec this.map =', this.map);

        let centerLat = 0; // Centre du monde par défaut
        let centerLng = 0;
        let zoom = 2; // Vue mondiale
        
        // Ajouter des marqueurs pour toutes les photos avec GPS
        const bounds = [];
        document.querySelectorAll('.photo-item.has-gps').forEach(item => {
            const lat = parseFloat(item.dataset.latitude);
            const lng = parseFloat(item.dataset.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                // Marqueur bleu pour les photos existantes
                const marker = L.circleMarker([lat, lng], {
                    radius: 6,
                    fillColor: '#007bff',
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(this.map);
                
                // Popup avec info
                const caption = item.querySelector('.photo-caption-text')?.textContent || 'Photo';
                marker.bindPopup(`<b>${caption}</b><br>GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                
                this.photoMarkers.push(marker);
                bounds.push([lat, lng]);
            }
        });
        
        // Si une seule photo sélectionnée avec GPS, la mettre en évidence
        if (selectedPhotos.size === 1) {
            const assetId = Array.from(selectedPhotos)[0];
            const photoItem = document.querySelector(`[data-asset-id="${assetId}"]`);
            if (photoItem && photoItem.classList.contains('has-gps')) {
                centerLat = parseFloat(photoItem.dataset.latitude);
                centerLng = parseFloat(photoItem.dataset.longitude);
                
                // Marqueur rouge pour la photo sélectionnée
                const selectedMarker = L.marker([centerLat, centerLng], {
                    icon: this.getColoredIcon('red')
                }).addTo(this.map);
                
                selectedMarker.bindPopup('<b>Photo sélectionnée</b>').openPopup();
                this.photoMarkers.push(selectedMarker);
                
                zoom = 15;
                this.map.setView([centerLat, centerLng], zoom);
            }
        } else if (bounds.length > 0) {
            // Ajuster la vue pour montrer tous les marqueurs
            this.map.fitBounds(bounds, { padding: [50, 50] });
        } else {
            // Si aucune photo avec GPS, centrer sur la vue mondiale
            this.map.setView([centerLat, centerLng], zoom);
        }
    }
    
    // Gérer le clic sur la carte
    handleMapClick(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        // Supprimer le marqueur précédent
        if (this.mapMarker) {
            this.map.removeLayer(this.mapMarker);
        }
        
        // Ajouter un nouveau marqueur vert pour la nouvelle position
        this.mapMarker = L.marker([lat, lng], {
            icon: this.getColoredIcon('green')
        }).addTo(this.map);
        
        this.mapMarker.bindPopup('<b>Nouvelle position</b>').openPopup();
        
        // Stocker les coordonnées
        this.selectedMapCoords = { latitude: lat, longitude: lng };
        
        // Mettre à jour l'affichage
        document.querySelector('.map-coords').textContent = 
            `GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        document.getElementById('btnConfirmLocation').disabled = false;
    }
    
    // Obtenir une icône colorée
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
    
    // Fermer la modal
    closeModal() {
        document.getElementById('mapModal').classList.remove('active');
    }
    
    // Obtenir les coordonnées sélectionnées
    getSelectedCoords() {
        return this.selectedMapCoords;
    }
}

// Export pour utilisation
window.MapManager = MapManager;
