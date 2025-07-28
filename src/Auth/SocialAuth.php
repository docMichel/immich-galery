<?php
// src/Auth/SocialAuth.php - Système d'authentification sociale complet

require_once __DIR__ . '/../Services/Database.php';

class SocialAuth
{
    private $config;
    private $db;

    public function __construct()
    {
        // Configuration des providers OAuth
        $this->config = [
            'facebook' => [
                'app_id' => $_ENV['FACEBOOK_APP_ID'] ?? '',
                'app_secret' => $_ENV['FACEBOOK_APP_SECRET'] ?? '',
                'redirect_uri' => $this->getBaseUrl() . '/auth/facebook/callback'
            ],
            'google' => [
                'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
                'redirect_uri' => $this->getBaseUrl() . '/auth/google/callback'
            ],
            'apple' => [
                'client_id' => $_ENV['APPLE_CLIENT_ID'] ?? '',
                'team_id' => $_ENV['APPLE_TEAM_ID'] ?? '',
                'key_id' => $_ENV['APPLE_KEY_ID'] ?? '',
                'private_key' => $_ENV['APPLE_PRIVATE_KEY'] ?? '',
                'redirect_uri' => $this->getBaseUrl() . '/auth/apple/callback'
            ]
        ];

        // Initialiser la session si pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Connexion à la base de données
        $dbConfig = include(__DIR__ . '/../../config/config.php');
        $this->db = Database::getInstance($dbConfig['database'])->getPDO();
    }

    /**
     * Obtenir l'URL de base
     */
    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Génère l'URL de connexion Facebook
     */
    public function getFacebookLoginUrl(): string
    {
        $state = $this->generateState();
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id' => $this->config['facebook']['app_id'],
            'redirect_uri' => $this->config['facebook']['redirect_uri'],
            'scope' => 'email,public_profile',
            'response_type' => 'code',
            'state' => $state
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    /**
     * Génère l'URL de connexion Google
     */
    public function getGoogleLoginUrl(): string
    {
        $state = $this->generateState();
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id' => $this->config['google']['client_id'],
            'redirect_uri' => $this->config['google']['redirect_uri'],
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'access_type' => 'offline',
            'state' => $state
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Génère l'URL de connexion Apple
     */
    public function getAppleLoginUrl(): string
    {
        $state = $this->generateState();
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id' => $this->config['apple']['client_id'],
            'redirect_uri' => $this->config['apple']['redirect_uri'],
            'scope' => 'name email',
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'state' => $state
        ];

        return 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);
    }

    /**
     * Traite le callback Facebook
     */
    public function handleFacebookCallback(string $code, string $state): ?array
    {
        if (!$this->validateState($state)) {
            throw new Exception('État OAuth invalide');
        }

        // Échanger le code contre un token
        $tokenData = $this->exchangeFacebookCode($code);
        if (!$tokenData) {
            throw new Exception('Impossible d\'obtenir le token Facebook');
        }

        // Récupérer les informations utilisateur
        $userInfo = $this->getFacebookUserInfo($tokenData['access_token']);
        if (!$userInfo) {
            throw new Exception('Impossible de récupérer les informations utilisateur Facebook');
        }

        // Créer ou mettre à jour l'utilisateur
        return $this->createOrUpdateUser('facebook', $userInfo, $tokenData);
    }

    /**
     * Traite le callback Google
     */
    public function handleGoogleCallback(string $code, string $state): ?array
    {
        if (!$this->validateState($state)) {
            throw new Exception('État OAuth invalide');
        }

        // Échanger le code contre un token
        $tokenData = $this->exchangeGoogleCode($code);
        if (!$tokenData) {
            throw new Exception('Impossible d\'obtenir le token Google');
        }

        // Récupérer les informations utilisateur depuis le JWT
        $userInfo = $this->decodeGoogleJWT($tokenData['id_token']);
        if (!$userInfo) {
            throw new Exception('Impossible de décoder le token Google');
        }

        // Créer ou mettre à jour l'utilisateur
        return $this->createOrUpdateUser('google', $userInfo, $tokenData);
    }

    /**
     * Traite le callback Apple
     */
    public function handleAppleCallback(string $code, string $state, ?string $user = null): ?array
    {
        if (!$this->validateState($state)) {
            throw new Exception('État OAuth invalide');
        }

        // Échanger le code contre un token
        $tokenData = $this->exchangeAppleCode($code);
        if (!$tokenData) {
            throw new Exception('Impossible d\'obtenir le token Apple');
        }

        // Décoder le JWT Apple
        $userInfo = $this->decodeAppleJWT($tokenData['id_token']);
        if (!$userInfo) {
            throw new Exception('Impossible de décoder le token Apple');
        }

        // Apple peut envoyer des infos utilisateur supplémentaires lors de la première connexion
        if ($user) {
            $additionalUserData = json_decode($user, true);
            if ($additionalUserData && isset($additionalUserData['name'])) {
                $userInfo['name'] = $additionalUserData['name']['firstName'] . ' ' . $additionalUserData['name']['lastName'];
            }
        }

        // Créer ou mettre à jour l'utilisateur
        return $this->createOrUpdateUser('apple', $userInfo, $tokenData);
    }

    /**
     * Échange le code Facebook contre un token
     */
    private function exchangeFacebookCode(string $code): ?array
    {
        $params = [
            'client_id' => $this->config['facebook']['app_id'],
            'client_secret' => $this->config['facebook']['app_secret'],
            'redirect_uri' => $this->config['facebook']['redirect_uri'],
            'code' => $code
        ];

        $response = $this->makeHttpRequest(
            'https://graph.facebook.com/v18.0/oauth/access_token',
            $params
        );

        return $response ? json_decode($response, true) : null;
    }

    /**
     * Récupère les informations utilisateur Facebook
     */
    private function getFacebookUserInfo(string $accessToken): ?array
    {
        $fields = 'id,name,email,picture.width(200).height(200)';
        $url = "https://graph.facebook.com/v18.0/me?fields={$fields}&access_token={$accessToken}";

        $response = $this->makeHttpRequest($url);
        if (!$response) return null;

        $data = json_decode($response, true);

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'avatar_url' => $data['picture']['data']['url'] ?? null
        ];
    }

    /**
     * Échange le code Google contre un token
     */
    private function exchangeGoogleCode(string $code): ?array
    {
        $params = [
            'client_id' => $this->config['google']['client_id'],
            'client_secret' => $this->config['google']['client_secret'],
            'redirect_uri' => $this->config['google']['redirect_uri'],
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $response = $this->makeHttpRequest(
            'https://oauth2.googleapis.com/token',
            $params,
            'POST'
        );

        return $response ? json_decode($response, true) : null;
    }

    /**
     * Décode le JWT Google
     */
    private function decodeGoogleJWT(string $idToken): ?array
    {
        // Décoder le JWT (sans vérification de signature pour la démo)
        // En production, il faut vérifier la signature avec les clés publiques Google
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) return null;

        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

        if (!$payload) return null;

        return [
            'id' => $payload['sub'],
            'name' => $payload['name'] ?? '',
            'email' => $payload['email'] ?? null,
            'avatar_url' => $payload['picture'] ?? null
        ];
    }

    /**
     * Échange le code Apple contre un token
     */
    private function exchangeAppleCode(string $code): ?array
    {
        $clientSecret = $this->generateAppleClientSecret();

        $params = [
            'client_id' => $this->config['apple']['client_id'],
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->config['apple']['redirect_uri']
        ];

        $response = $this->makeHttpRequest(
            'https://appleid.apple.com/auth/token',
            $params,
            'POST'
        );

        return $response ? json_decode($response, true) : null;
    }

    /**
     * Génère le client secret Apple (JWT)
     */
    private function generateAppleClientSecret(): string
    {
        // En production, utilisez une bibliothèque JWT appropriée
        // comme firebase/php-jwt pour générer un JWT ES256
        $header = [
            'alg' => 'ES256',
            'kid' => $this->config['apple']['key_id']
        ];

        $payload = [
            'iss' => $this->config['apple']['team_id'],
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->config['apple']['client_id']
        ];

        // Pour la démo, retourner un placeholder
        // En production, vous devez signer avec la clé privée Apple
        return 'APPLE_CLIENT_SECRET_JWT_PLACEHOLDER';
    }

    /**
     * Décode le JWT Apple
     */
    private function decodeAppleJWT(string $idToken): ?array
    {
        // Même logique que Google JWT
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) return null;

        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

        if (!$payload) return null;

        return [
            'id' => $payload['sub'],
            'name' => '', // Apple ne fournit le nom que lors de la première connexion
            'email' => $payload['email'] ?? null,
            'avatar_url' => null // Apple ne fournit pas d'avatar
        ];
    }

    /**
     * Crée ou met à jour un utilisateur
     */
    private function createOrUpdateUser(string $provider, array $userInfo, array $tokenData): array
    {
        // Chercher l'utilisateur existant
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE provider = ? AND provider_id = ?
        ");
        $stmt->execute([$provider, $userInfo['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Mettre à jour l'utilisateur existant
            $stmt = $this->db->prepare("
                UPDATE users 
                SET name = ?, email = ?, avatar_url = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $userInfo['name'],
                $userInfo['email'],
                $userInfo['avatar_url'],
                $user['id']
            ]);

            $userId = $user['id'];
        } else {
            // Créer un nouvel utilisateur
            $stmt = $this->db->prepare("
                INSERT INTO users (provider, provider_id, email, name, avatar_url, created_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $provider,
                $userInfo['id'],
                $userInfo['email'],
                $userInfo['name'],
                $userInfo['avatar_url']
            ]);

            $userId = $this->db->lastInsertId();
        }

        // Créer une session
        $sessionToken = $this->generateSessionToken();
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, session_token, provider_access_token, expires_at) 
            VALUES (?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))
        ");
        $stmt->execute([
            $userId,
            $sessionToken,
            $tokenData['access_token'] ?? null
        ]);

        // Mettre en session
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_token'] = $sessionToken;

        // Retourner les informations utilisateur
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Effectue une requête HTTP
     */
    private function makeHttpRequest(string $url, ?array $params = null, string $method = 'GET'): ?string
    {
        $ch = curl_init();

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ];

        if ($method === 'POST' && $params) {
            $curlOptions[CURLOPT_URL] = $url;
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            $queryString = $params ? '?' . http_build_query($params) : '';
            $curlOptions[CURLOPT_URL] = $url . $queryString;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Erreur cURL: " . $error);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Erreur HTTP {$httpCode}: " . $response);
            return null;
        }

        return $response;
    }

    /**
     * Génère un état OAuth sécurisé
     */
    private function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Valide l'état OAuth
     */
    private function validateState(string $state): bool
    {
        return isset($_SESSION['oauth_state']) &&
            $_SESSION['oauth_state'] === $state;
    }

    /**
     * Génère un token de session sécurisé
     */
    private function generateSessionToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
    }

    /**
     * Obtient l'utilisateur connecté
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT u.* FROM users u
            INNER JOIN user_sessions s ON u.id = s.user_id
            WHERE s.session_token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['session_token']]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        if (isset($_SESSION['session_token'])) {
            // Supprimer la session de la base de données
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions WHERE session_token = ?
            ");
            $stmt->execute([$_SESSION['session_token']]);
        }

        // Détruire la session PHP
        session_destroy();
    }
}
