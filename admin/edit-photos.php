// public/assets/js/edit-photos/modules/DuplicateManager.js

class DuplicateManager {
constructor(flaskApiUrl) {
this.flaskApiUrl = flaskApiUrl;
this.duplicateGroups = [];
this.expandedGroups = new Set();
}

async findDuplicates(selectedPhotos, threshold) {
const container = document.getElementById('duplicatesResults');
container.innerHTML = '<div class="loading">Recherche de doublons en cours...</div>';

try {
const assetIds = selectedPhotos.size > 0 ? Array.from(selectedPhotos) : null;

const response = await fetch(`${this.flaskApiUrl}/api/duplicates/find-similar`, {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify({
gallery_id: window.editPhotosConfig.galleryId,
asset_ids: assetIds,
threshold: threshold,
time_window: 24 // heures
})
});

if (!response.ok) {
throw new Error('Erreur serveur');
}

const result = await response.json();
this.displayDuplicates(result.groups || []);

} catch (error) {
console.error('Erreur recherche doublons:', error);
container.innerHTML = `
<div class="error-message">
    Erreur lors de la recherche de doublons.<br>
    Vérifiez que le serveur Flask est démarré sur ${this.flaskApiUrl}
</div>
`;
}
}

async analyzeDuplicates(galleryId, threshold) {
const container = document.getElementById('duplicatesResults');
container.innerHTML = '<div class="loading">Analyse en cours... Cela peut prendre quelques minutes.</div>';

// Envoyer la configuration Immich au serveur Flask
const immichConfig = {
api_url: '<?= $config["immich"]["api_url"] ?>',
api_key: '<?= $config["immich"]["api_key"] ?>'
};

// Utiliser SSE pour le progress
const eventSource = new EventSource(
`${this.flaskApiUrl}/api/duplicates/analyze-album/${galleryId}?threshold=${threshold}`
);

// Envoyer la config Immich en POST avant de démarrer le SSE
fetch(`${this.flaskApiUrl}/api/duplicates/analyze-album/${galleryId}?threshold=${threshold}`, {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify({
immich_config: immichConfig
})
});

eventSource.addEventListener('progress', (e) => {
const data = JSON.parse(e.data);
container.innerHTML = `
<div class="progress-container">
    <div class="progress-bar">
        <div class="progress-fill" style="width: ${data.data.progress}%"></div>
    </div>
    <div class="progress-text">${data.data.details}</div>
</div>
`;
});

eventSource.addEventListener('complete', (e) => {
const data = JSON.parse(e.data);
eventSource.close();
this.displayDuplicates(data.data.groups || []);
this.markDuplicatesInGrid(data.data.groups || []);
});

eventSource.addEventListener('error', (e) => {
eventSource.close();
container.innerHTML = '<div class="error-message">Erreur lors de l\'analyse</div>';
});
}

displayDuplicates(groups) {
const container = document.getElementById('duplicatesResults');

if (groups.length === 0) {
container.innerHTML = '<div class="empty-message">Aucun doublon trouvé</div>';
return;
}

this.duplicateGroups = groups;

const html = groups.map((group, index) => `
<div class="duplicate-group ${this.expandedGroups.has(group.group_id) ? 'expanded' : ''}"
    data-group-id="${group.group_id}">
    <div class="duplicate-group-header">
        <div>
            <strong>Groupe ${index + 1}</strong>
            <span class="group-info">
                ${group.images.length} photos similaires
                (${Math.round(group.similarity_avg * 100)}% de similarité)
            </span>
        </div>
        <button class="btn btn-sm btn-secondary toggle-group"
            onclick="window.editPhotos.duplicateManager.toggleGroup('${group.group_id}')">
            ${this.expandedGroups.has(group.group_id) ? '▼' : '▶'} Voir
        </button>
    </div>

    <div class="duplicate-photos" style="display: ${this.expandedGroups.has(group.group_id) ? 'grid' : 'none'}">
        ${group.images.map(img => `
        <div class="photo-item duplicate-photo ${img.is_primary ? 'primary' : ''}"
            data-asset-id="${img.asset_id}"
            title="${img.filename}">
            <img src="../public/image-proxy.php?id=${img.asset_id}&type=thumbnail"
                alt="${img.filename}">
            <div class="photo-info">
                <small>${new Date(img.date).toLocaleDateString()}</small>
                ${img.is_primary ? '<span class="badge primary">Principal</span>' : ''}
            </div>
            <div class="photo-actions">
                <button class="btn-icon" onclick="window.editPhotos.duplicateManager.keepPhoto('${img.asset_id}', '${group.group_id}')"
                    title="Garder cette photo">✓</button>
                <button class="btn-icon danger" onclick="window.editPhotos.duplicateManager.markForDeletion('${img.asset_id}', '${group.group_id}')"
                    title="Marquer pour suppression">✗</button>
            </div>
        </div>
        `).join('')}
    </div>

    <div class="group-actions" style="display: ${this.expandedGroups.has(group.group_id) ? 'flex' : 'none'}">
        <button class="btn btn-sm btn-primary"
            onclick="window.editPhotos.duplicateManager.keepBest('${group.group_id}')">
            Garder la meilleure
        </button>
        <button class="btn btn-sm btn-secondary"
            onclick="window.editPhotos.duplicateManager.mergeMetadata('${group.group_id}')">
            Fusionner les métadonnées
        </button>
    </div>
</div>
`).join('');

container.innerHTML = `
<div class="duplicates-summary">
    <h3>${groups.length} groupes de doublons trouvés</h3>
    <button class="btn btn-secondary" onclick="window.editPhotos.duplicateManager.expandAll()">
        Tout déplier
    </button>
</div>
${html}
`;
}

markDuplicatesInGrid(groups) {
// Réinitialiser les badges
document.querySelectorAll('.duplicate-badge').forEach(badge => badge.remove());
document.querySelectorAll('.photo-item').forEach(item => {
item.classList.remove('has-duplicates');
});

// Marquer les photos qui ont des doublons
groups.forEach(group => {
group.images.forEach((img, index) => {
const photoItem = document.querySelector(`[data-asset-id="${img.asset_id}"]`);
if (photoItem) {
photoItem.classList.add('has-duplicates');

// Ajouter le badge seulement sur la première photo du groupe
if (index === 0) {
const badge = document.createElement('div');
badge.className = 'duplicate-badge';
badge.textContent = group.images.length;
badge.title = `${group.images.length} photos similaires`;
photoItem.appendChild(badge);
}
}
});
});
}

toggleGroup(groupId) {
const group = document.querySelector(`[data-group-id="${groupId}"]`);
const photos = group.querySelector('.duplicate-photos');
const actions = group.querySelector('.group-actions');
const button = group.querySelector('.toggle-group');

if (this.expandedGroups.has(groupId)) {
this.expandedGroups.delete(groupId);
photos.style.display = 'none';
actions.style.display = 'none';
button.innerHTML = '▶ Voir';
group.classList.remove('expanded');
} else {
this.expandedGroups.add(groupId);
photos.style.display = 'grid';
actions.style.display = 'flex';
button.innerHTML = '▼ Masquer';
group.classList.add('expanded');
}
}

expandAll() {
const allExpanded = this.duplicateGroups.every(g => this.expandedGroups.has(g.group_id));

this.duplicateGroups.forEach(group => {
if (allExpanded) {
this.expandedGroups.delete(group.group_id);
} else {
this.expandedGroups.add(group.group_id);
}
});

// Rafraîchir l'affichage
this.displayDuplicates(this.duplicateGroups);
}

keepPhoto(assetId, groupId) {
const group = this.duplicateGroups.find(g => g.group_id === groupId);
if (!group) return;

// Marquer cette photo comme principale
group.images.forEach(img => {
img.is_primary = img.asset_id === assetId;
});

// Rafraîchir l'affichage du groupe
this.refreshGroup(groupId);

this.showToast('Photo marquée comme principale', 'success');
}

markForDeletion(assetId, groupId) {
const photoDiv = document.querySelector(`.duplicate-photos [data-asset-id="${assetId}"]`);
if (photoDiv) {
photoDiv.classList.add('marked-for-deletion');
}

// Ajouter à une liste de suppression
if (!this.deletionList) {
this.deletionList = new Set();
}
this.deletionList.add(assetId);

this.showToast('Photo marquée pour suppression', 'warning');
}

async keepBest(groupId) {
const group = this.duplicateGroups.find(g => g.group_id === groupId);
if (!group) return;

// Logique pour déterminer la meilleure photo
// Critères : résolution, taille fichier, métadonnées complètes
let bestPhoto = group.images[0];

for (const img of group.images) {
// TODO: Implémenter la logique de sélection
// Pour l'instant, on garde la première
}

this.keepPhoto(bestPhoto.asset_id, groupId);
}

async mergeMetadata(groupId) {
const group = this.duplicateGroups.find(g => g.group_id === groupId);
if (!group) return;

try {
const response = await fetch(`${this.flaskApiUrl}/api/duplicates/merge-metadata`, {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify({
group_id: groupId,
asset_ids: group.images.map(img => img.asset_id)
})
});

if (response.ok) {
this.showToast('Métadonnées fusionnées avec succès', 'success');
}
} catch (error) {
console.error('Erreur fusion métadonnées:', error);
this.showToast('Erreur lors de la fusion', 'error');
}
}

refreshGroup(groupId) {
const group = this.duplicateGroups.find(g => g.group_id === groupId);
if (!group) return;

const groupDiv = document.querySelector(`[data-group-id="${groupId}"]`);
const photosContainer = groupDiv.querySelector('.duplicate-photos');

photosContainer.innerHTML = group.images.map(img => `
<div class="photo-item duplicate-photo ${img.is_primary ? 'primary' : ''}"
    data-asset-id="${img.asset_id}"
    title="${img.filename}">
    <img src="../public/image-proxy.php?id=${img.asset_id}&type=thumbnail"
        alt="${img.filename}">
    <div class="photo-info">
        <small>${new Date(img.date).toLocaleDateString()}</small>
        ${img.is_primary ? '<span class="badge primary">Principal</span>' : ''}
    </div>
    <div class="photo-actions">
        <button class="btn-icon" onclick="window.editPhotos.duplicateManager.keepPhoto('${img.asset_id}', '${group.group_id}')"
            title="Garder cette photo">✓</button>
        <button class="btn-icon danger" onclick="window.editPhotos.duplicateManager.markForDeletion('${img.asset_id}', '${group.group_id}')"
            title="Marquer pour suppression">✗</button>
    </div>
</div>
`).join('');
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

export default DuplicateManager;