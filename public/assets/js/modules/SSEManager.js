// public/assets/js/modules/SSEManager.js

// =================================================================
// MODULE SSE MANAGER - Réutilisable
// =================================================================
class SSEManager {
    constructor() {
        this.connections = new Map();
    }

    connect(id, url, handlers = {}) {
        // Fermer connexion existante
        this.close(id);

        const eventSource = new EventSource(url);
        const connection = {
            eventSource,
            url,
            handlers,
            logs: [],
            lastMessageTime: Date.now(),
            completed: false,
            hasReceivedComplete: false
        };

        // Handler d'ouverture
        eventSource.onopen = (e) => {
            this.log(id, '✅ Connexion SSE établie', 'success');
            if (handlers.onOpen) handlers.onOpen(e);
        };

        // Handler principal pour les messages SANS type d'événement spécifique
        eventSource.onmessage = (event) => {
            connection.lastMessageTime = Date.now();

            try {
                const data = JSON.parse(event.data);
                console.log('📨 MESSAGE SSE (onmessage):', data);
                
                // Ces messages n'ont pas de listener dédié, on les traite ici
                if (data.event === 'connected' || data.event === 'heartbeat') {
                    this.handleMessage(id, data);
                }
            } catch (error) {
                console.error('Erreur parsing:', error, 'Data:', event.data);
                this.log(id, `⚠️ Message non-JSON: ${event.data}`, 'warning');
            }
        };

        // Ajouter des listeners pour CHAQUE type d'événement spécifique
        const typedEvents = ['connected', 'progress', 'partial', 'result', 'complete', 'error', 'warning', 'heartbeat'];

        typedEvents.forEach(eventType => {
            eventSource.addEventListener(eventType, (event) => {
                connection.lastMessageTime = Date.now();
                
                try {
                    const data = JSON.parse(event.data);
                    console.log(`📨 EVENT [${eventType}]:`, data);
                    
                    // Pour les événements typés, on passe directement les données
                    // avec le type d'événement ajouté
                    this.handleTypedEvent(id, eventType, data);
                } catch (error) {
                    console.error(`Erreur parsing ${eventType}:`, error, 'Data:', event.data);
                    this.log(id, `⚠️ Erreur parsing ${eventType}: ${event.data}`, 'warning');
                }
            });
        });

        // Handler d'erreur
        eventSource.onerror = (e) => {
            const connection = this.connections.get(id);

            // Si on a déjà reçu complete, c'est une fermeture normale
            if (connection && connection.hasReceivedComplete) {
                this.log(id, '✅ Connexion fermée après succès', 'info');
                return;
            }

            // Sinon, c'est une vraie erreur
            console.error('Erreur SSE:', e);
            this.log(id, `❌ Erreur SSE: readyState=${eventSource.readyState}`, 'error');

            if (eventSource.readyState === EventSource.CLOSED) {
                this.log(id, '🔌 Connexion fermée (erreur)', 'error');
                if (handlers.onError) handlers.onError('Connexion fermée de manière inattendue');
            } else if (eventSource.readyState === EventSource.CONNECTING) {
                this.log(id, '🔄 Tentative de reconnexion...', 'info');
                if (handlers.onConnecting) handlers.onConnecting();
            }
        };

        // Enregistrer la connexion
        this.connections.set(id, connection);

        // Démarrer le monitoring de timeout (60 secondes au lieu de 60)
        this.startTimeoutMonitor(id);

        return eventSource;
    }

    handleTypedEvent(id, eventType, data) {
        const connection = this.connections.get(id);
        if (!connection) return;

        const handlers = connection.handlers;

        switch (eventType) {
            case 'connected':
                this.log(id, '🔗 ' + (data.message || 'Connecté'), 'info');
                if (handlers.onConnected) handlers.onConnected(data);
                break;

            case 'progress':
                const step = data.step || '';
                const progress = data.progress || 0;
                const message = data.message || '';
                this.log(id, `📊 [${progress}%] ${step}: ${message}`, 'progress');
                if (handlers.onProgress) handlers.onProgress(progress, message, step);
                break;

            case 'partial':
                const partialType = data.type || 'unknown';
                this.log(id, `📝 [${partialType}] Résultat partiel reçu`, 'partial');
                if (handlers.onPartial) handlers.onPartial(data);
                break;

            case 'result':
                const resultStep = data.step || 'unknown';
                const result = data.result || {};
                this.log(id, `📝 [${resultStep}] Résultat intermédiaire reçu`, 'result');
                if (handlers.onResult) handlers.onResult(resultStep, result);
                break;

            case 'warning':
                const warningMsg = data.message || 'Avertissement';
                const code = data.code || 'WARNING';
                this.log(id, `⚠️ ${warningMsg} (${code})`, 'warning');
                if (handlers.onWarning) handlers.onWarning(warningMsg, code);
                break;

            case 'complete':
                connection.hasReceivedComplete = true;
                connection.completed = true;
                this.log(id, '🎉 Génération terminée avec succès!', 'success');

                if (handlers.onComplete) {
                    console.log('✅ Données complètes reçues:', data);
                    handlers.onComplete(data);
                }

                // Fermer proprement après un petit délai
                setTimeout(() => {
                    this.close(id, true);
                }, 100);
                break;

            case 'error':
                const error = data.error || 'Erreur inconnue';
                const errorType = data.error_type || 'UNKNOWN_ERROR';
                this.log(id, `❌ Erreur: ${error} (${errorType})`, 'error');

                if (handlers.onError) {
                    handlers.onError(error, errorType);
                }

                setTimeout(() => {
                    this.close(id, false);
                }, 100);
                break;

            case 'heartbeat':
                // Juste mettre à jour le timestamp, pas de log pour éviter le spam
                if (handlers.onHeartbeat) handlers.onHeartbeat(data);
                break;
        }
    }

    handleMessage(id, data) {
        // Pour les messages génériques (legacy)
        const eventType = data.event || data.type;
        
        if (eventType === 'connected') {
            this.handleTypedEvent(id, 'connected', data);
        } else if (eventType === 'heartbeat') {
            this.handleTypedEvent(id, 'heartbeat', data);
        } else {
            this.log(id, `❓ Message générique: ${eventType}`, 'unknown');
            const connection = this.connections.get(id);
            if (connection?.handlers.onUnknown) {
                connection.handlers.onUnknown(data);
            }
        }
    }

    startTimeoutMonitor(id) {
        const checkInterval = setInterval(() => {
            const connection = this.connections.get(id);
            if (!connection) {
                clearInterval(checkInterval);
                return;
            }

            // Ne pas timeout si on a reçu complete
            if (connection.completed) {
                clearInterval(checkInterval);
                return;
            }

            const timeSinceLastMessage = Date.now() - connection.lastMessageTime;
            // Augmenter le timeout à 120 secondes (2 minutes) car Travel Llama peut être lent
            if (timeSinceLastMessage > 120000) {
                this.log(id, '⏱️ Timeout: pas de message depuis 120s', 'error');
                if (connection.handlers.onTimeout) {
                    connection.handlers.onTimeout();
                }
                this.close(id, false);
                clearInterval(checkInterval);
            }
        }, 10000); // Vérifier toutes les 10 secondes

        // Stocker l'interval pour pouvoir le nettoyer
        const connection = this.connections.get(id);
        if (connection) {
            connection.timeoutMonitor = checkInterval;
        }
    }

    log(id, message, type = 'info') {
        const connection = this.connections.get(id);
        if (!connection) return;

        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        const logEntry = { timestamp, message, type };
        connection.logs.push(logEntry);

        // Limiter la taille des logs
        if (connection.logs.length > 100) {
            connection.logs.shift();
        }

        // Appeler le handler de log si défini
        if (connection.handlers.onLog) {
            connection.handlers.onLog(logEntry);
        }

        // Log console avec emoji selon le type (pas de log pour heartbeat)
        if (type !== 'heartbeat') {
            const emoji = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️',
                'progress': '📊',
                'result': '📝',
                'partial': '📋',
                'unknown': '❓'
            }[type] || '📌';

            console.log(`[${timestamp}] ${emoji} ${message}`);
        }
    }

    close(id, isNormalClosure = false) {
        const connection = this.connections.get(id);
        if (connection) {
            // Nettoyer le timeout monitor
            if (connection.timeoutMonitor) {
                clearInterval(connection.timeoutMonitor);
            }

            // Fermer l'EventSource
            connection.eventSource.close();

            // Supprimer de la map
            this.connections.delete(id);

            // Log approprié selon le type de fermeture
            if (isNormalClosure || connection.completed) {
                console.log(`✅ Connexion SSE fermée normalement: ${id}`);
            } else {
                console.log(`🛑 Connexion SSE fermée (erreur): ${id}`);
            }
        }
    }

    closeAll() {
        for (const id of this.connections.keys()) {
            this.close(id);
        }
    }

    isConnected(id) {
        const connection = this.connections.get(id);
        return connection && connection.eventSource.readyState === EventSource.OPEN;
    }

    getConnection(id) {
        return this.connections.get(id);
    }

    getLogs(id) {
        const connection = this.connections.get(id);
        return connection ? connection.logs : [];
    }

    getStats() {
        const stats = {
            activeConnections: this.connections.size,
            connections: []
        };

        for (const [id, connection] of this.connections) {
            stats.connections.push({
                id: id,
                readyState: connection.eventSource.readyState,
                completed: connection.completed,
                lastMessageTime: new Date(connection.lastMessageTime).toISOString(),
                logsCount: connection.logs.length
            });
        }

        return stats;
    }
}

// Export si utilisé comme module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SSEManager;
}

// Instance globale
if (typeof window !== 'undefined') {
    window.SSEManager = SSEManager;
}