<?php
// public/views/header.php
// Variables disponibles: $auth, $userRole, $selectedGallery (optionnel)

// Gérer les différents points d'accès (symlink /gallery/ ou /immich-gallery/public/)
$adminPath = '/immich-gallery/admin/galleries.php';
?>
<div class="header">
    <div class="header-content">
        <h1><?= isset($selectedGallery) ? htmlspecialchars($selectedGallery['name']) : 'Mes Galeries' ?></h1>

        <div class="header-meta">
            <div class="user-info">
                <span>Connecté en tant que:</span>
                <span class="user-role"><?= htmlspecialchars($userRole) ?></span>
            </div>

            <?php if ($auth->isAdmin()): ?>
                <a href="<?= $adminPath ?>" class="admin-link">Administration</a>
            <?php endif; ?>

            <a href="?logout=1" class="logout-link">Déconnexion</a>
        </div>
    </div>
</div>