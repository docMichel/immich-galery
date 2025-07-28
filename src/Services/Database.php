<?php
// src/Services/Database.php - Gestionnaire de base de données

class Database
{
    private $pdo;
    private static $instance = null;

    public function __construct($config)
    {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $config['user'], $config['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    /**
     * Obtenir l'instance PDO
     */
    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * Singleton pour certains services
     */
    public static function getInstance($config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                $config = include(__DIR__ . '/../../config/config.php');
                $config = $config['database'];
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Préparer une requête
     */
    public function prepare($sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Exécuter une requête directe
     */
    public function query($sql): PDOStatement
    {
        return $this->pdo->query($sql);
    }

    /**
     * Obtenir le dernier ID inséré
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Commencer une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Valider une transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Annuler une transaction
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
