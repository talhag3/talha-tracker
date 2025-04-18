<?php
// Include database configuration
require_once __DIR__ . '/../config/database.php';

/* // Database connection
function getDbConnection() {
    try {
        $pdo = new PDO("sqlite:" . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
} */

// Get active work session
function getActiveSession() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("
        SELECT 
            ws.session_id,
            ws.project_id,
            ws.start_time,
            p.name as project_name,
            c.name as client_name
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        JOIN clients c ON p.client_id = c.client_id
        WHERE ws.end_time IS NULL
        ORDER BY ws.start_time DESC
        LIMIT 1
    ");
    
    return $stmt->fetch();
}

// Format date
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M j, Y');
}

// Format time
function formatTime($timeString) {
    $time = new DateTime($timeString);
    return $time->format('g:i A');
}

// Format duration
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . "m";
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($mins === 0) {
        return $hours . "h";
    }
    
    return $hours . "h " . $mins . "m";
}

// Get today's total time
function getTodayTotalTime() {
    $pdo = getDbConnection();
    
    // Get completed sessions for today
    $stmt = $pdo->query("
        SELECT SUM(duration_minutes) as total_minutes
        FROM work_sessions
        WHERE date(start_time) = date('now')
        AND duration_minutes IS NOT NULL
    ");
    $completedMinutes = $stmt->fetchColumn() ?: 0;
    
    // Get active session duration if any
    $activeSession = getActiveSession();
    $activeMinutes = 0;
    
    if ($activeSession && date('Y-m-d', strtotime($activeSession['start_time'])) === date('Y-m-d')) {
        $startTime = new DateTime($activeSession['start_time']);
        $now = new DateTime();
        $diff = $startTime->diff($now);
        $activeMinutes = ($diff->h * 60) + $diff->i;
    }
    
    return $completedMinutes + $activeMinutes;
}

// Get today's work distribution
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
    
    return $stmt->fetchAll();
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        
        $bgColor = $type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
        
        echo "<div class='{$bgColor} border-l-4 p-4 mb-4' role='alert'>";
        echo "<p>{$message}</p>";
        echo "</div>";
        
        unset($_SESSION['flash']);
    }
}

// Sanitize input
function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// Check if client exists
function clientExists($clientId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE client_id = :client_id");
    $stmt->execute([':client_id' => $clientId]);
    return $stmt->fetchColumn() > 0;
}

// Check if project exists
function projectExists($projectId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_id = :project_id");
    $stmt->execute([':project_id' => $projectId]);
    return $stmt->fetchColumn() > 0;
}

function formatDateTime($dateTimeString) {
    $dateTime = new DateTime($dateTimeString);
    return $dateTime->format('M j, Y g:i A'); // Example: "Apr 18, 2025 8:42 PM"
}