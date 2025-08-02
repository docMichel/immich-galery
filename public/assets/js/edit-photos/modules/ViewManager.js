// public/assets/js/edit-photos/modules/ViewManager.js

class ViewManager {
    constructor() {
        this.currentView = 'grid';
    }
    
    init() {
        // Gérer les boutons de vue
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.switchView(view);
            });
        });
    }
    
    switchView(view) {
        this.currentView = view;
        
        // Mettre à jour les boutons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        
        // Mettre à jour les conteneurs
        document.querySelectorAll('.view-container').forEach(container => {
            container.classList.remove('active');
        });
        
        const targetContainer = document.getElementById(`view${view.charAt(0).toUpperCase() + view.slice(1)}`);
        if (targetContainer) {
            targetContainer.classList.add('active');
        }
    }
    
    getCurrentView() {
        return this.currentView;
    }
}

export default ViewManager;