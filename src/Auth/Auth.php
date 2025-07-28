<?php
// src/Services/Auth.php - Authentification simple par mot de passe

class Auth
{
    private $config;

    public function __construct()
    {
        $this->config = include(__DIR__ . '/../../config/config.php');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Connexion simple par mot de passe
     */
    public function login($password): bool
    {
        // Mots de passe configurés (en production, utiliser une vraie DB)
        $validPasswords = [
            'admin' => 'admin123',      // Admin
            'family' => 'family2024',   // Famille
            'friends' => 'friends2024'  // Amis
        ];

        foreach ($validPasswords as $role => $validPassword) {
            if (
                password_verify($password, password_hash($validPassword, PASSWORD_DEFAULT)) ||
                $password === $validPassword
            ) {

                $_SESSION['authenticated'] = true;
                $_SESSION['user_role'] = $role;
                $_SESSION['login_time'] = time();

                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Obtenir le rôle de l'utilisateur
     */
    public function getUserRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->getUserRole() === 'admin';
    }

    /**
     * Déconnexion
     */
    public function logout(): void
    {
        session_destroy();
        session_start();
    }

    /**
     * Forcer la connexion (redirect si pas connecté)
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Forcer admin
     */
    public function requireAdmin(): void
    {
        $this->requireAuth();
        if (!$this->isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            die('Accès réservé aux administrateurs');
        }
    }

    /**
     * Formulaire de connexion HTML
     */
    public function getLoginForm(): string
    {
        return '
        <div class="login-container">
            <form method="POST" class="login-form">
                <h2>Connexion à la galerie</h2>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Mot de passe" required>
                </div>
                <button type="submit">Se connecter</button>
            </form>
            <style>
                .login-container {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 80vh;
                }
                .login-form {
                    background: white;
                    padding: 2rem;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                .login-form h2 {
                    text-align: center;
                    margin-bottom: 1.5rem;
                    color: #333;
                }
                .form-group {
                    margin-bottom: 1rem;
                }
                .form-group input {
                    width: 100%;
                    padding: 0.75rem;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 1rem;
                }
                .login-form button {
                    width: 100%;
                    background: #007bff;
                    color: white;
                    padding: 0.75rem;
                    border: none;
                    border-radius: 4px;
                    font-size: 1rem;
                    cursor: pointer;
                }
                .login-form button:hover {
                    background: #0056b3;
                }
            </style>
        </div>';
    }
}
