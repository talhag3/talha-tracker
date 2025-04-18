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
            c.name as client_name
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
    
    // Check if there's an active work session for this project
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM work_sessions 
        WHERE project_id = :project_id AND end_time IS NULL
    ");
    $stmt->execute([':project_id' => $projectId]);
    $hasActiveSession = $stmt->fetchColumn() > 0;
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: clients.php');
    exit;
}

// Get all clients for dropdown
$stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = sanitizeInput($_POST['projectName'] ?? '');
    $clientId = filter_var($_POST['clientSelect'] ?? 0, FILTER_VALIDATE_INT);
    $notes = sanitizeInput($_POST['projectNotes'] ?? '');
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Project name is required";
    }
    
    if (!$clientId) {
        $errors[] = "Please select a client";
    }
    
    // If project has active session, it can't be set to inactive
    if ($hasActiveSession && !$isActive) {
        $errors[] = "Cannot set project to inactive while it has an active work session.";
        $isActive = 1; // Force active
    }
    
    // If no errors, update project
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET name = :name, client_id = :client_id, notes = :notes, is_active = :is_active
                WHERE project_id = :project_id
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':client_id' => $clientId,
                ':notes' => $notes,
                ':is_active' => $isActive,
                ':project_id' => $projectId
            ]);
            
            setFlashMessage('success', "Project '{$name}' updated successfully!");
            header('Location: clients.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Edit Project Form -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Edit Project</h2>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($hasActiveSession): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
        <p>This project has an active work session. You cannot set it to inactive until the session is stopped.</p>
    </div>
    <?php endif; ?>

    <form method="POST" action="edit_project.php?id=<?php echo $projectId; ?>">
        <div class="mb-4">
            <label for="projectName" class="block text-gray-700 font-medium mb-2">Project Name</label>
            <input type="text" id="projectName" name="projectName" value="<?php echo htmlspecialchars($project['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>
        
        <div class="mb-4">
            <label for="clientSelect" class="block text-gray-700 font-medium mb-2">Client</label>
            <select id="clientSelect" name="clientSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client['client_id']; ?>" <?php echo $project['client_id'] == $client['client_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($client['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-4">
            <label for="projectNotes" class="block text-gray-700 font-medium mb-2">Notes (Optional)</label>
            <textarea id="projectNotes" name="projectNotes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($project['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="isActive" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo $project['is_active'] ? 'checked' : ''; ?> <?php echo $hasActiveSession ? 'disabled' : ''; ?>>
                <span class="ml-2 text-gray-700">Active Project</span>
            </label>
        </div>
        
        <div class="flex justify-between">
            <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                Update Project
            </button>
        </div>
    </form>
</div>

<!-- Project Statistics -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Project Statistics</h2>
    
    <?php
    // Get project statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as session_count,
            SUM(duration_minutes) as total_minutes,
            MIN(start_time) as first_session,
            MAX(start_time) as last_session
        FROM work_sessions
        WHERE project_id = :project_id
        AND duration_minutes IS NOT NULL
    ");
    $stmt->execute([':project_id' => $projectId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-blue-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">Total Time</h3>
            <p class="text-xl font-bold text-blue-600"><?php echo formatDuration($stats['total_minutes'] ?? 0); ?></p>
        </div>
        <div class="bg-green-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">Work Sessions</h3>
            <p class="text-xl font-bold text-green-600"><?php echo $stats['session_count'] ?? 0; ?></p>
        </div>
        <?php if ($stats['first_session']): ?>
        <div class="bg-purple-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">First Session</h3>
            <p class="text-sm font-medium text-purple-600"><?php echo formatDate($stats['first_session']); ?></p>
        </div>
        <div class="bg-yellow-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">Last Session</h3>
            <p class="text-sm font-medium text-yellow-600"><?php echo formatDate($stats['last_session']); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>