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
            completed: false,  // Flag pour savoir si on a reçu complete
            hasReceivedComplete: false
        };

        // Handler d'ouverture
        eventSource.onopen = (e) => {
            this.log(id, '✅ Connexion SSE établie', 'success');
            if (handlers.onOpen) handlers.onOpen(e);
        };

        // Handler principal pour les messages SANS type d'événement spécifique
        // (messages envoyés avec juste "data:" comme connected et heartbeat)
        // Handler principal pour les messages SANS type d'événement spécifique
        eventSource.onmessage = (event) => {
            connection.lastMessageTime = Date.now();

            try {
                const data = JSON.parse(event.data);

                // Ne traiter que les messages qui n'ont PAS de listener spécifique
                const eventType = data.event;
                if (['progress', 'result', 'complete', 'error'].includes(eventType)) {
                    // Ces événements ont des listeners dédiés, on les ignore ici
                    return;
                }

                // Traiter connected, heartbeat et autres messages génériques
                console.log('📨 MESSAGE SSE (onmessage):', data);
                this.handleMessage(id, data);
            } catch (error) {
                console.error('Erreur parsing:', error, 'Data:', event.data);
                this.log(id, `⚠️ Message non-JSON: ${event.data}`, 'warning');
            }
        };

        // IMPORTANT: Ajouter des listeners pour CHAQUE type d'événement spécifique
        // (messages envoyés avec "event: type" puis "data:")
        const typedEvents = ['progress', 'result', 'complete']//, 'error'];

        typedEvents.forEach(eventType => {
            eventSource.addEventListener(eventType, (event) => {

                try {

                    /*--debug ---
                    connection.lastMessageTime = Date.now();
                    console.log(`🔍 RAW EVENT [${eventType}]:`, event);
                    console.log(`🔍 event.data:`, event.data);
                    console.log(`🔍 typeof event.data:`, typeof event.data);

                    if (!event.data) {
                        console.warn(`⚠️ Event sans data pour [${eventType}]`);
                        return;
                    }
                    // -- end debug
                    */
                    const data = JSON.parse(event.data);
                    console.log(`📨 MESSAGE SSE [${eventType}]:`, data);
                    this.handleMessage(id, data);
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
                // Ne pas fermer ici, laisser le navigateur gérer la reconnexion
            } else if (eventSource.readyState === EventSource.CONNECTING) {
                this.log(id, '🔄 Tentative de reconnexion...', 'info');
                if (handlers.onConnecting) handlers.onConnecting();
            }
        };

        // Enregistrer la connexion
        this.connections.set(id, connection);

        // Démarrer le monitoring de timeout
        this.startTimeoutMonitor(id);

        return eventSource;
    }

    handleMessage(id, data) {
        const connection = this.connections.get(id);
        if (!connection) return;

        const handlers = connection.handlers;
        const eventType = data.event;

        // Log tous les messages
        this.log(id, `📩 [${eventType}] Message reçu`, 'info');

        switch (eventType) {
            case 'connected':
                this.log(id, '🔗 ' + (data.message || 'Connecté'), 'info');
                if (handlers.onConnected) handlers.onConnected(data);
                break;

            case 'progress':
                const progress = data.data?.progress || data.progress || 0;
                const details = data.data?.details || data.details || '';
                const step = data.data?.step || data.step || '';
                this.log(id, `📊 [${progress}%] ${step}: ${details}`, 'progress');
                if (handlers.onProgress) handlers.onProgress(progress, details, step);
                break;

            case 'result':
                const resultStep = data.data?.step || data.step || 'unknown';
                const result = data.data?.result || data.result || {};
                this.log(id, `📝 [${resultStep}] Résultat intermédiaire reçu`, 'result');
                if (handlers.onResult) handlers.onResult(resultStep, result);
                break;

            case 'complete':
                // Marquer comme complété AVANT de traiter
                connection.hasReceivedComplete = true;
                connection.completed = true;

                this.log(id, '🎉 Génération terminée avec succès!', 'success');

                // Passer les données complètes au handler
                if (handlers.onComplete) {
                    const completeData = data.data || data;
                    console.log('✅ Données complètes reçues:', completeData);
                    handlers.onComplete(completeData);
                }

                // Fermer proprement après un petit délai pour laisser le handler finir
                setTimeout(() => {
                    this.close(id, true); // true = fermeture normale
                }, 100);
                break;

            case 'error':
                const error = data.data?.error || data.error || 'Erreur inconnue';
                const code = data.data?.code || data.code || 'UNKNOWN_ERROR';
                this.log(id, `❌ Erreur serveur: ${error} (${code})`, 'error');

                if (handlers.onError) {
                    handlers.onError(error, code);
                }

                // Fermer après une erreur serveur
                setTimeout(() => {
                    this.close(id, false); // false = fermeture sur erreur
                }, 100);
                break;

            case 'heartbeat':
                this.log(id, '💓 Heartbeat', 'heartbeat');
                if (handlers.onHeartbeat) handlers.onHeartbeat(data);
                break;

            default:
                this.log(id, `❓ Événement inconnu: ${eventType}`, 'unknown');
                if (handlers.onUnknown) handlers.onUnknown(data);
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
            if (timeSinceLastMessage > 60000) { // 60 secondes
                this.log(id, '⏱️ Timeout: pas de message depuis 60s', 'error');
                if (connection.handlers.onTimeout) {
                    connection.handlers.onTimeout();
                }
                this.close(id, false);
                clearInterval(checkInterval);
            }
        }, 5000); // Vérifier toutes les 5 secondes

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

        // Log console avec emoji selon le type
        const emoji = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️',
            'progress': '📊',
            'result': '📝',
            'heartbeat': '💓',
            'unknown': '❓'
        }[type] || '📌';

        console.log(`[${timestamp}] ${emoji} ${message}`);
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

// Instance globale du SSE Manager
const sseManager = new SSEManager();
