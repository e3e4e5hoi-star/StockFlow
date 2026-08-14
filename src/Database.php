<?php
declare(strict_types=1);
final class Database {
    private PDO $pdo;
    public function __construct(string $path) {
        $this->pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
    public function pdo(): PDO { return $this->pdo; }
    public function initialize(string $schemaPath): void { $this->pdo->exec((string) file_get_contents($schemaPath)); }
}
