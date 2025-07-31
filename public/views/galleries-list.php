<?php
// public/views/galleries-list.php
// Variables disponibles: $galleryModel, $userRole, $auth

// Récupérer les galeries accessibles
$galleries = $galleryModel->getGalleriesForRole($userRole);
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <span>Galeries</span>
</div>

<?php if (empty($galleries)): ?>
    <div class="empty-state">
        <h3>Aucune galerie disponible</h3>
        <p>Aucune galerie n'est accessible avec votre niveau d'autorisation.</p>
        <?php if ($auth->isAdmin()): ?>
            <p style="margin-top: 20px;">
                <a href="../admin/galleries.php" class="admin-link">Créer une galerie</a>
            </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="cards-grid">
        <?php foreach ($galleries as $gallery): ?>
            <div class="card" onclick="window.location.href='?gallery=<?= urlencode($gallery['slug']) ?>'">
                <img src="<?= htmlspecialchars($gallery['cover_image_url'] ?? 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 220"%3E%3Crect fill="%23e0e0e0" width="400" height="220"/%3E%3C/svg%3E') ?>"
                    alt="<?= htmlspecialchars($gallery['name']) ?>"
                    class="card-image loading"
                    onload="this.classList.remove('loading')">

                <?php if ($auth->isAdmin()): ?>
                    <div class="gallery-actions" style="position: absolute; top: 10px; right: 10px;">
                        <button class="btn-gps"
                            onclick="event.stopPropagation(); window.location.href='../admin/gps-manager.php?gallery=<?= $gallery['id'] ?>'"
                            title="Gérer les positions GPS"
                            style="background: rgba(255,255,255,0.9); border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 16px;">
                            📍
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card-content">
                    <h3 class="card-title"><?= htmlspecialchars($gallery['name']) ?></h3>

                    <?php if ($gallery['description']): ?>
                        <p class="card-description"><?= htmlspecialchars($gallery['description']) ?></p>
                    <?php endif; ?>

                    <div class="card-meta">
                        <span><?= $gallery['image_count'] ?> photos</span>
                        <span><?= count($gallery['albums']) ?> album<?= count($gallery['albums']) > 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (!empty($gallery['permissions'])): ?>
                        <div class="card-permissions">
                            <?php foreach ($gallery['permissions'] as $perm): ?>
                                <span class="permission-badge"><?= htmlspecialchars($perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>