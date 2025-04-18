<?php
// Database connection configuration
function getDbConnection() {
    $db_path = __DIR__ . '/../database.sqlite';
    
    try {
        // Create a new PDO instance
        $pdo = new PDO('sqlite:' . $db_path);
        
        // Set error mode to exception
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        return $pdo;
    } catch (PDOException $e) {
        // Handle connection error
        die("Database connection failed: " . $e->getMessage());
    }
}