// public/assets/js/modules/SSEManager.js

class SSEManager {
    constructor(config) {
        this.config = config;
        this.eventSource = null;
        this.sseTimeout = null;
        this.lastMessageTime = null;
        this.handlers = {};
    }

    // Enregistrer les handlers pour les différents événements
    on(event, handler) {
        this.handlers[event] = handler;
    }

    // Démarrer une connexion SSE
    connect(requestId) {
        // Fermer toute connexion existante
        this.disconnect();

        const sseUrl = `${this.config.flaskApiUrl}/api/ai/generate-caption-stream/${requestId}`;
        console.log('🚀 Ouverture connexion SSE:', sseUrl);
        
        try {
            this.eventSource = new EventSource(sseUrl);
        } catch (error) {
            console.error('❌ Impossible de créer EventSource:', error);
            this._triggerHandler('error', { 
                error: 'Impossible de se connecter au flux SSE' 
            });
            return false;
        }
        
        // Démarrer la surveillance du timeout
        this._startTimeout();
        
        // Handler d'ouverture
        this.eventSource.onopen = (event) => {
            console.log('✅ Connexion SSE établie');
            this._resetTimeout();
            this._triggerHandler('connected');
        };

        // Handler principal pour les messages
        this.eventSource.onmessage = (event) => {
            this._handleMessage(event);
        };

        // Handlers pour les événements spécifiques
        ['progress', 'result', 'complete', 'error', 'connected', 'heartbeat'].forEach(eventType => {
            this.eventSource.addEventListener(eventType, (event) => {
                this._handleTypedEvent(eventType, event);
            });
        });

        // Gestion des erreurs
        this.eventSource.onerror = (error) => {
            this._handleError(error);
        };

        return true;
    }

    // Déconnecter SSE
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this._clearTimeout();
    }

    // Gérer les messages génériques
    _handleMessage(event) {
        this._resetTimeout();
        
        try {
            console.log('📨 Message SSE:', event.data);
            
            if (!event.data || event.data.trim() === '') {
                return;
            }
            
            const message = JSON.parse(event.data);
            this._processMessage(message);
        } catch (error) {
            console.error('Erreur parsing SSE:', error);
            console.error('Données brutes:', event.data);
        }
    }

    // Gérer les événements typés
    _handleTypedEvent(eventType, event) {
        this._resetTimeout();
        
        try {
            const data = JSON.parse(event.data);
            this._processMessage({ event: eventType, data: data });
        } catch (error) {
            console.error(`Erreur parsing ${eventType}:`, error);
        }
    }

    // Traiter un message
    _processMessage(message) {
        console.log('🔄 Message SSE:', message);
        
        switch (message.event) {
            case 'connected':
                this._triggerHandler('connected', message);
                break;

            case 'progress':
                this._triggerHandler('progress', {
                    progress: message.data?.progress || 0,
                    details: message.data?.details || ''
                });
                break;

            case 'result':
                this._triggerHandler('result', {
                    step: message.data?.step,
                    result: message.data?.result
                });
                break;

            case 'complete':
                this._triggerHandler('complete', message.data);
                this.disconnect();
                break;

            case 'error':
                this._triggerHandler('error', {
                    error: message.data?.error || 'Erreur inconnue',
                    code: message.data?.code
                });
                this.disconnect();
                break;

            case 'heartbeat':
                console.log('💓 Heartbeat reçu');
                this._triggerHandler('heartbeat', message.data);
                break;

            default:
                console.warn('Type d\'événement SSE inconnu:', message.event);
        }
    }

    // Gérer les erreurs de connexion
    _handleError(error) {
        console.error('❌ Erreur SSE:', error);
        
        if (this.eventSource.readyState === EventSource.CONNECTING) {
            this._triggerHandler('connecting');
        } else if (this.eventSource.readyState === EventSource.CLOSED) {
            console.log('🛑 Connexion SSE fermée');
            this._clearTimeout();
            this._triggerHandler('disconnected');
        }
    }

    // Déclencher un handler
    _triggerHandler(event, data = {}) {
        if (this.handlers[event]) {
            this.handlers[event](data);
        }
    }

    // Gestion du timeout
    _startTimeout() {
        // Timeout de 60 secondes sans message
        this.sseTimeout = setTimeout(() => {
            console.error('⏱️ Timeout SSE: Aucun message reçu depuis 60s');
            this._triggerHandler('timeout');
            this.disconnect();
        }, 60000);
    }
    
    _resetTimeout() {
        this.lastMessageTime = Date.now();
        if (this.sseTimeout) {
            clearTimeout(this.sseTimeout);
            this._startTimeout();
        }
    }
    
    _clearTimeout() {
        if (this.sseTimeout) {
            clearTimeout(this.sseTimeout);
            this.sseTimeout = null;
        }
    }
}

export default SSEManager;