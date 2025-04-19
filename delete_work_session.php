<?php
// Include header
require_once 'includes/header.php';

// Check if session_id is provided
$sessionId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$sessionId) {
    setFlashMessage('error', 'Invalid work session ID.');
    header('Location: work_logs.php');
    exit;
}

// Get database connection
$pdo = getDbConnection();

// Get work session data
try {
    $stmt = $pdo->prepare("
        SELECT 
            ws.*,
            p.name as project_name,
            c.name as client_name
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        JOIN clients c ON p.client_id = c.client_id
        WHERE ws.session_id = :session_id
    ");
    $stmt->execute([':session_id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        setFlashMessage('error', 'Work session not found.');
        header('Location: work_logs.php');
        exit;
    }
    
    // Check if this is an active session
    $isActiveSession = $session['end_time'] === null;
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: work_logs.php');
    exit;
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';
    
    if (!$confirmDelete) {
        setFlashMessage('error', 'You must confirm deletion.');
    } else {
        try {
            // Delete the work session
            $stmt = $pdo->prepare("DELETE FROM work_sessions WHERE session_id = :session_id");
            $stmt->execute([':session_id' => $sessionId]);
            
            setFlashMessage('success', "Work session has been deleted successfully.");
            
            // If it was an active session, redirect to start_work page
            if ($isActiveSession) {
                header('Location: start_work.php');
            } else {
                header('Location: work_logs.php');
            }
            exit;
            
        } catch (PDOException $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
}

// Format duration for display
$durationText = 'N/A';
if ($session['duration_minutes']) {
    $durationText = formatDuration($session['duration_minutes']);
} elseif ($isActiveSession) {
    $durationText = 'Active session (still running)';
}
?>

<!-- Delete Work Session Confirmation -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Delete Work Session</h2>
    
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">Warning</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p>You are about to delete a work session for project: <strong><?php echo htmlspecialchars($session['project_name']); ?></strong></p>
                    <?php if ($isActiveSession): ?>
                    <p class="mt-2 font-bold">This is an active work session. Deleting it will cancel your current time tracking.</p>
                    <?php endif; ?>
                    <p class="mt-2">This action cannot be undone.</p>
                </div>
            </div>
        </div>
    </div>
    
    <form method="POST" action="delete_work_session.php?id=<?php echo $sessionId; ?>">
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="confirm_delete" value="yes" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" required>
                <span class="ml-2 text-gray-700">I confirm that I want to delete this work session</span>
            </label>
        </div>
        
        <div class="flex justify-between">
            <a href="<?php echo $isActiveSession ? 'stop_work.php' : 'work_logs.php'; ?>" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Delete Work Session
            </button>
        </div>
    </form>
</div>

<!-- Work Session Details -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Work Session Details</h2>
    
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium text-gray-500">Project</h3>
            <p class="mt-1 font-medium text-blue-600"><?php echo htmlspecialchars($session['project_name']); ?></p>
        </div>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Client</h3>
            <p class="mt-1"><?php echo htmlspecialchars($session['client_name']); ?></p>
        </div>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Start Time</h3>
            <p class="mt-1"><?php echo formatDateTime($session['start_time']); ?></p>
        </div>
        
        <?php if (!$isActiveSession): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">End Time</h3>
            <p class="mt-1"><?php echo formatDateTime($session['end_time']); ?></p>
        </div>
        <?php endif; ?>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Duration</h3>
            <p class="mt-1 font-medium <?php echo $isActiveSession ? 'text-green-600' : 'text-blue-600'; ?>">
                <?php echo $durationText; ?>
            </p>
        </div>
        
        <?php if (!empty($session['notes'])): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Notes</h3>
            <p class="mt-1"><?php echo nl2br(htmlspecialchars($session['notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>