<?php
/**
 * File: includes/Database.php
 * Simple PDO database wrapper for BRGYCare
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'final_thesis';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    // Query helper methods
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    public function update($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}
