<?php
// Include header
require_once 'includes/header.php';

// Redirect if there's already an active session
if ($activeSession) {
    setFlashMessage('error', "You already have an active work session. Please stop it before starting a new one.");
    header('Location: stop_work.php');
    exit;
}

// Get all projects for dropdown
$pdo = getDbConnection();
$stmt = $pdo->query("
    SELECT 
        p.project_id, 
        p.name as project_name, 
        c.name as client_name
    FROM projects p
    JOIN clients c ON p.client_id = c.client_id
    WHERE p.is_active = 1
    ORDER BY p.name
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $projectId = filter_var($_POST['projectSelect'] ?? 0, FILTER_VALIDATE_INT);
    $notes = sanitizeInput($_POST['workNotes'] ?? '');
    
    $errors = [];
    
    if (!$projectId) {
        $errors[] = "Please select a project";
    }
    
    // If no errors, start work session
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO work_sessions (project_id, start_time, notes)
                VALUES (:project_id, datetime('now'), :notes)
            ");
            
            $stmt->execute([
                ':project_id' => $projectId,
                ':notes' => $notes
            ]);
            
            setFlashMessage('success', "Work session started successfully!");
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Start Work Form -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (empty($projects)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
        <p>You need to add a project before you can start work.</p>
        <a href="add_project.php" class="text-blue-600 underline">Add a project now</a>
    </div>
    <?php else: ?>
    <form method="POST" action="start_work.php">
        <div class="mb-4">
            <label for="projectSelect" class="block text-gray-700 font-medium mb-2">Select Project</label>
            <select id="projectSelect" name="projectSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select a project</option>
                <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project['project_id']; ?>" <?php echo ($projectId ?? 0) == $project['project_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project['project_name'] . ' (' . $project['client_name'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-4">
            <label for="workNotes" class="block text-gray-700 font-medium mb-2">Notes (Optional)</label>
            <textarea id="workNotes" name="workNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                Start Work
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>