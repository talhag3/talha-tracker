<?php
// Include header
require_once 'includes/header.php';

// Check if project_id is provided
$projectId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$projectId) {
    setFlashMessage('error', 'Invalid project ID.');
    header('Location: clients.php');
    exit;
}

// Get database connection
$pdo = getDbConnection();

// Get project data
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name as client_name,
            c.client_id
        FROM projects p
        JOIN clients c ON p.client_id = c.client_id
        WHERE p.project_id = :project_id
    ");
    $stmt->execute([':project_id' => $projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        setFlashMessage('error', 'Project not found.');
        header('Location: clients.php');
        exit;
    }
    
    // Get work session count for this project
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM work_sessions 
        WHERE project_id = :project_id
    ");
    $stmt->execute([':project_id' => $projectId]);
    $sessionCount = $stmt->fetchColumn();
    
    // Get total time spent on this project
    $stmt = $pdo->prepare("
        SELECT SUM(duration_minutes) FROM work_sessions 
        WHERE project_id = :project_id
    ");
    $stmt->execute([':project_id' => $projectId]);
    $totalMinutes = $stmt->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: clients.php');
    exit;
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';
    
    if (!$confirmDelete) {
        setFlashMessage('error', 'You must confirm deletion.');
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete all work sessions for this project
            $stmt = $pdo->prepare("DELETE FROM work_sessions WHERE project_id = :project_id");
            $stmt->execute([':project_id' => $projectId]);
            
            // Delete the project
            $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = :project_id");
            $stmt->execute([':project_id' => $projectId]);
            
            $pdo->commit();
            
            setFlashMessage('success', "Project '{$project['name']}' and all associated work sessions have been deleted.");
            header('Location: clients.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
}
?>

<!-- Delete Project Confirmation -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Delete Project</h2>
    
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
                    <p>You are about to delete the project: <strong><?php echo htmlspecialchars($project['name']); ?></strong></p>
                    <?php if ($sessionCount > 0): ?>
                    <p class="mt-2">This project has <strong><?php echo $sessionCount; ?> work session<?php echo $sessionCount !== 1 ? 's' : ''; ?></strong> with a total of <strong><?php echo formatDuration($totalMinutes); ?></strong> tracked time.</p>
                    <p class="mt-2">All work sessions and time tracking data for this project will be permanently deleted.</p>
                    <?php endif; ?>
                    <p class="mt-2">This action cannot be undone.</p>
                </div>
            </div>
        </div>
    </div>
    
    <form method="POST" action="delete_project.php?id=<?php echo $projectId; ?>">
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="confirm_delete" value="yes" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" required>
                <span class="ml-2 text-gray-700">I confirm that I want to delete this project and all associated data</span>
            </label>
        </div>
        
        <div class="flex justify-between">
            <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Delete Project
            </button>
        </div>
    </form>
</div>

<!-- Project Details -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Project Details</h2>
    
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium text-gray-500">Project Name</h3>
            <p class="mt-1"><?php echo htmlspecialchars($project['name']); ?></p>
        </div>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Client</h3>
            <p class="mt-1"><?php echo htmlspecialchars($project['client_name']); ?></p>
        </div>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Status</h3>
            <p class="mt-1">
                <?php if ($project['is_active']): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                </span>
                <?php else: ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Inactive
                </span>
                <?php endif; ?>
            </p>
        </div>
        
        <?php if (!empty($project['notes'])): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Notes</h3>
            <p class="mt-1"><?php echo nl2br(htmlspecialchars($project['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Created</h3>
            <p class="mt-1"><?php echo formatDate($project['created_at']); ?></p>
        </div>
        
        <?php if ($sessionCount > 0): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Work Sessions</h3>
            <p class="mt-1"><?php echo $sessionCount; ?> session<?php echo $sessionCount !== 1 ? 's' : ''; ?></p>
        </div>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Total Time</h3>
            <p class="mt-1 font-medium text-blue-600"><?php echo formatDuration($totalMinutes); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>