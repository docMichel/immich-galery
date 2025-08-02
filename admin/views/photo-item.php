<?php
// admin/views/photo-item.php - Template pour une photo
$hasGPS = $photo['latitude'] !== null && $photo['longitude'] !== null;
$thumbnailUrl = "../public/image-proxy.php?id={$photo['immich_asset_id']}&type=thumbnail";
$index = $index ?? uniqid(); // Index unique si non fourni
?>

<div class="photo-item gps-item <?= $hasGPS ? 'has-gps' : 'no-gps' ?>"
    data-asset-id="<?= htmlspecialchars($photo['immich_asset_id']) ?>"
    data-latitude="<?= $photo['latitude'] ?>"
    data-longitude="<?= $photo['longitude'] ?>"
    data-date="<?= $photo['photo_date'] ?>">

    <img src="<?= $thumbnailUrl ?>"
        alt="<?= htmlspecialchars($photo['caption'] ?: 'Photo') ?>"
        loading="lazy">

    <!-- Checkbox de sélection -->
    <div class="selection-checkbox">
        <input type="checkbox" class="photo-select" id="select-<?= $index ?>">
        <label for="select-<?= $index ?>"></label>
    </div>

    <!-- Indicateur GPS -->
    <?php if ($hasGPS): ?>
        <div class="gps-badge success" title="<?= number_format($photo['latitude'], 6) ?>, <?= number_format($photo['longitude'], 6) ?>">
            📍 GPS
        </div>
    <?php else: ?>
        <div class="gps-badge warning">
            ❓ Pas de GPS
        </div>
    <?php endif; ?>

    <!-- Badge doublons (sera ajouté dynamiquement) -->

    <!-- Bouton éditer légende (au survol) -->
    <button class="caption-edit-btn"
        onclick="event.stopPropagation(); window.open('../admin/edit-caption.php?asset=<?= $photo['immich_asset_id'] ?>&gallery=<?= $galleryId ?>', 'caption-editor', 'width=1000,height=700')"
        title="Éditer la légende">
        ✏️
    </button>

    <!-- Légende au survol -->
    <div class="photo-caption">
        <div class="photo-caption-text">
            <?= htmlspecialchars($photo['caption'] ?: 'Photo') ?>
            <?php if ($hasGPS): ?>
                <br><small>📍 <?= number_format($photo['latitude'], 4) ?>,
                    <?= number_format($photo['longitude'], 4) ?></small>
            <?php endif; ?>
            <br><small style="opacity: 0.7;">ID: <?= htmlspecialchars($photo['immich_asset_id']) ?></small>
        </div>
    </div>
</div>