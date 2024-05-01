<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseConnector
{
    private PDO $pdo;

    public function __construct()
    {
        $this->initializeConnection();
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function initializeConnection(): void
    {
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];

        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new PDOException("Ошибка подключения к базе: " . $e->getMessage());
        }
    }

    /**
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
