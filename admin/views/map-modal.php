<?php
// admin/views/map-modal.php - Modal de sélection GPS sur carte
?>
<div id="mapModal" class="map-modal">
    <div class="map-container">
        <div class="map-header">
            <h3>Sélectionner une position GPS</h3>
            <button class="btn-close" onclick="closeMapModal()">✕</button>
        </div>
        <div id="map"></div>
        <div class="map-footer">
            <div class="map-coords">
                Cliquez sur la carte pour sélectionner une position
            </div>
            <div>
                <button class="btn btn-secondary" onclick="closeMapModal()">Annuler</button>
                <button id="btnConfirmLocation" class="btn btn-primary" disabled onclick="confirmMapLocation()">
                    Valider cette position
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .map-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        padding: 20px;
    }

    .map-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .map-container {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 900px;
        height: 80vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .map-header {
        padding: 16px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .map-header h3 {
        margin: 0;
        font-size: 18px;
    }

    #map {
        flex: 1;
        width: 100%;
    }

    .map-footer {
        padding: 12px 20px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f5f5f5;
    }

    .map-coords {
        font-family: monospace;
        font-size: 14px;
    }

    /* Styles Leaflet personnalisés */
    .leaflet-control-search {
        box-shadow: none !important;
    }

    /* Amélioration des popups */
    .leaflet-popup-content {
        margin: 10px;
        line-height: 1.4;
    }

    .leaflet-popup-content b {
        display: block;
        margin-bottom: 5px;
    }
</style>