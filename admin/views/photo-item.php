<?php
// admin/views/photo-item.php - Template pour une photo
$hasGPS = $image['latitude'] !== null && $image['longitude'] !== null;
$thumbnailUrl = "../public/image-proxy.php?id={$image['immich_asset_id']}&type=thumbnail";
$index = $index ?? uniqid(); // Index unique si non fourni
?>

<div class="photo-item gps-item <?= $hasGPS ? 'has-gps' : 'no-gps' ?>"
    data-asset-id="<?= htmlspecialchars($image['immich_asset_id']) ?>"
    data-latitude="<?= $image['latitude'] ?>"
    data-longitude="<?= $image['longitude'] ?>"
    data-date="<?= $image['photo_date'] ?>">

    <img src="<?= $thumbnailUrl ?>"
        alt="<?= htmlspecialchars($image['caption'] ?: 'Photo') ?>"
        loading="lazy">

    <!-- Checkbox de sélection -->
    <div class="selection-checkbox">
        <input type="checkbox" class="photo-select" id="select-<?= $index ?>">
        <label for="select-<?= $index ?>"></label>
    </div>

    <!-- Indicateur GPS -->
    <?php if ($hasGPS): ?>
        <div class="gps-badge success" title="<?= number_format($image['latitude'], 6) ?>, <?= number_format($image['longitude'], 6) ?>">
            📍 GPS
        </div>
    <?php else: ?>
        <div class="gps-badge warning">
            ❓ Pas de GPS
        </div>
    <?php endif; ?>

    <!-- Badge doublons (sera ajouté dynamiquement) -->

    <!-- Légende au survol -->
    <div class="photo-caption">
        <div class="photo-caption-text">
            <?= htmlspecialchars($image['caption'] ?: 'Photo') ?>
            <?php if ($hasGPS): ?>
                <br><small>📍 <?= number_format($image['latitude'], 4) ?>, <?= number_format($image['longitude'], 4) ?></small>
            <?php endif; ?>
        </div>
    </div>
</div>