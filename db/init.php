<?php
// Include database connection
require_once __DIR__ . '/../includes/functions.php';

// Create database tables if they don't exist
function initializeDatabase() {
    $pdo = getDbConnection();
    
    // Create clients table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            client_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create projects table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            project_id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            notes TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients (client_id)
        )
    ");
    
    // Create work_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS work_sessions (
            session_id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            duration_minutes INTEGER,
            notes TEXT,
            FOREIGN KEY (project_id) REFERENCES projects (project_id)
        )
    ");
    
    // Create work_logs view for easier querying
    $pdo->exec("
        CREATE VIEW IF NOT EXISTS work_logs AS
        SELECT 
            ws.session_id,
            ws.project_id,
            p.name as project_name,
            p.client_id,
            c.name as client_name,
            ws.start_time,
            ws.end_time,
            ws.duration_minutes,
            ws.notes
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        JOIN clients c ON p.client_id = c.client_id
    ");
    
    return true;
}

// Initialize the database
initializeDatabase();

echo "Database initialized successfully!";