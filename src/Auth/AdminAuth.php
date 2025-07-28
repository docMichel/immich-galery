<?php
// src/Auth/AdminAuth.php - Authentification spécifique admin

require_once __DIR__ . '/Auth.php';

class AdminAuth extends Auth
{
    /**
     * Vérifier que l'utilisateur est admin et rediriger sinon
     */
    public function requireAdmin(): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: ../public/login.php');
            exit;
        }

        if (!$this->isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            die('
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Accès refusé</title>
                    <style>
                        body {
                            font-family: -apple-system, sans-serif;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            margin: 0;
                            background: #f5f5f5;
                        }
                        .error {
                            text-align: center;
                            padding: 40px;
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        }
                        h1 { color: #dc3545; }
                        a {
                            color: #007bff;
                            text-decoration: none;
                        }
                    </style>
                </head>
                <body>
                    <div class="error">
                        <h1>Accès refusé</h1>
                        <p>Cette section est réservée aux administrateurs.</p>
                        <p><a href="../public/">Retour à l\'accueil</a></p>
                    </div>
                </body>
                </html>
            ');
        }
    }
}
