<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include functions
require_once __DIR__ . '/functions.php';

// Get active session
$activeSession = getActiveSession();

// Get current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talha Tracker - <?php echo ucfirst(str_replace(['_', '.php'], [' ', ''], $currentPage)); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (in_array($currentPage, ['index.php', 'reports.php'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-center">
                <?php
                switch ($currentPage) {
                    case 'index.php':
                        echo '📈 Talha Tracker';
                        break;
                    case 'add_client.php':
                        echo '➕ Add Client';
                        break;
                    case 'edit_client.php':
                        echo '✏️ Edit Client';
                        break;
                    case 'delete_client.php':
                        echo '🗑️ Delete Client';
                        break;
                    case 'add_project.php':
                        echo '➕ Add Project';
                        break;
                    case 'edit_project.php':
                        echo '✏️ Edit Project';
                        break;
                    case 'delete_project.php':
                        echo '🗑️ Delete Project';
                        break;
                    case 'start_work.php':
                        echo '▶️ Start Work';
                        break;
                    case 'stop_work.php':
                        echo '⏹️ Stop Work';
                        break;
                    case 'work_logs.php':
                        echo '📖 Work Logs';
                        break;
                    case 'reports.php':
                        echo '📊 Reports';
                        break;
                    case 'clients.php':
                        echo '👥 Clients & Projects';
                        break;
                    default:
                        echo 'Talha Tracker';
                }
                ?>
            </h1>
        </header>
        
        <?php displayFlashMessage(); ?>