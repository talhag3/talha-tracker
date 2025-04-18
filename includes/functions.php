<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Format duration in minutes to hours and minutes
function formatDuration($minutes) {
    if ($minutes === null) return "0h 0m";
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    return "{$hours}h {$mins}m";
}

// Format datetime for display
function formatDateTime($datetime) {
    if (!$datetime) return "";
    
    $date = new DateTime($datetime);
    return $date->format('M j, Y g:i A'); // Apr 18, 2023 10:30 AM
}

// Format date only
function formatDate($datetime) {
    if (!$datetime) return "";
    
    $date = new DateTime($datetime);
    return $date->format('M j, Y'); // Apr 18, 2023
}

// Format time only
function formatTime($datetime) {
    if (!$datetime) return "";
    
    $date = new DateTime($datetime);
    return $date->format('g:i A'); // 10:30 AM
}

// Get active work session if any
function getActiveSession() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("
        SELECT 
            ws.session_id,
            ws.start_time,
            ws.project_id,
            p.name as project_name,
            c.name as client_name
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        JOIN clients c ON p.client_id = c.client_id
        WHERE ws.end_time IS NULL
        ORDER BY ws.start_time DESC
        LIMIT 1
    ");
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get today's total work time in minutes
function getTodayTotalTime() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("
        SELECT SUM(duration_minutes) as total_minutes
        FROM work_sessions
        WHERE date(start_time) = date('now')
        AND duration_minutes IS NOT NULL
    ");
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_minutes'] ?: 0;
}

// Get today's work distribution by project
function getTodayWorkDistribution() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("
        SELECT 
            p.name as project_name,
            SUM(ws.duration_minutes) as minutes,
            SUM(ws.duration_minutes) / 60.0 as hours
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        WHERE date(ws.start_time) = date('now')
        AND ws.duration_minutes IS NOT NULL
        GROUP BY ws.project_id
        ORDER BY minutes DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get and clear flash message
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Display flash message HTML
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        $bgColor = ($type == 'success') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
        
        echo "<div class='border-l-4 p-4 mb-4 {$bgColor}' role='alert'>";
        echo "<p>{$message}</p>";
        echo "</div>";
    }
}