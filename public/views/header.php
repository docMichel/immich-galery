<?php
// public/views/header.php
// Variables disponibles: $auth, $userRole, $selectedGallery (optionnel)
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
                <a href="../admin/galleries.php" class="admin-link">Administration</a>
            <?php endif; ?>

            <a href="?logout=1" class="logout-link">Déconnexion</a>
        </div>
    </div>
</div>