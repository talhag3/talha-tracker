<?php
// Include header
require_once 'includes/header.php';

// Redirect if there's no active session
if (!$activeSession) {
    setFlashMessage('error', "You don't have an active work session to stop.");
    header('Location: start_work.php');
    exit;
}

// Get more details about the active session
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT 
        ws.session_id,
        ws.start_time,
        ws.notes,
        p.name as project_name,
        c.name as client_name
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    WHERE ws.session_id = :session_id
");
$stmt->execute([':session_id' => $activeSession['session_id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate current duration
$startTime = new DateTime($session['start_time']);
$now = new DateTime();
$interval = $startTime->diff($now);
$currentDuration = $interval->format('%hh %im');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = sanitizeInput($_POST['workNotes'] ?? '');
    
    try {
        // Update the session with end time and notes
        $stmt = $pdo->prepare("
            UPDATE work_sessions
            SET end_time = datetime('now'),
                notes = :notes
            WHERE session_id = :session_id
        ");
        
        $stmt->execute([
            ':notes' => $notes,
            ':session_id' => $session['session_id']
        ]);
        
        setFlashMessage('success', "Work session stopped successfully!");
        header('Location: index.php');
        exit;
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!-- Current Work Session -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Current Session</h2>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?php echo $error; ?></p>
    </div>
    <?php endif; ?>
    
    <div class="mb-6">
        <div class="mb-2">
            <span class="font-medium">Project:</span>
            <span class="text-blue-600 ml-2"><?php echo htmlspecialchars($session['project_name']); ?></span>
        </div>
        <div class="mb-2">
            <span class="font-medium">Client:</span>
            <span class="text-gray-700 ml-2"><?php echo htmlspecialchars($session['client_name']); ?></span>
        </div>
        <div class="mb-2">
            <span class="font-medium">Started at:</span>
            <span class="text-gray-700 ml-2"><?php echo formatDateTime($session['start_time']); ?></span>
        </div>
        <div class="mb-2">
            <span class="font-medium">Current duration:</span>
            <span class="text-green-600 font-bold ml-2" id="current-duration"><?php echo $currentDuration; ?></span>
        </div>
    </div>
    
    <form method="POST" action="stop_work.php">
        <div class="mb-4">
            <label for="workNotes" class="block text-gray-700 font-medium mb-2">Session Notes (Optional)</label>
            <textarea id="workNotes" name="workNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($session['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <div class="flex space-x-2">
                <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                    Cancel
                </a>
                <a href="delete_work_session.php?id=<?php echo $session['session_id']; ?>" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                    Delete Session
                </a>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                Stop Work
            </button>
        </div>
    </form>
</div>

<script>
    // Update current duration in real-time
    function updateCurrentDuration() {
        const startTime = new Date('<?php echo $session['start_time']; ?>');
        const now = new Date();
        const diffMs = now - startTime;
        
        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        document.getElementById('current-duration').textContent = `${hours}h ${minutes}m`;
    }
    
    // Update every minute
    setInterval(updateCurrentDuration, 60000);
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>