// public/assets/js/modules/APIClient.js

class APIClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
        this.defaultTimeout = 30000; // 30 secondes
    }

    // Requête POST générique avec timeout
    async post(endpoint, data, options = {}) {
        const controller = new AbortController();
        const timeout = options.timeout || this.defaultTimeout;
        
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                body: JSON.stringify(data),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
            
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error(`Timeout après ${timeout}ms`);
            } else if (error instanceof TypeError && error.message.includes('Failed to fetch')) {
                throw new Error('Impossible de contacter le serveur');
            }
            
            throw error;
        }
    }

    // Démarrer une génération asynchrone
    async startGeneration(requestData) {
        console.log('📤 Envoi de la requête de génération...');
        
        const response = await this.post('/api/ai/generate-caption-async', requestData);
        
        if (!response.success) {
            throw new Error(response.error || 'Erreur lors du démarrage de la génération');
        }
        
        return response;
    }

    // Régénérer une légende
    async regenerateCaption(data) {
        const response = await this.post('/api/ai/regenerate-final', data);
        
        if (!response.success) {
            throw new Error(response.error || 'Erreur lors de la régénération');
        }
        
        return response;
    }
}

export default APIClient;