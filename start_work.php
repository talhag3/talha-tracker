<?php
// Include header
require_once 'includes/header.php';

include_once 'includes/functions.php';

// Check if there's already an active session
if ($activeSession) {
    setFlashMessage('error', 'You already have an active work session. Please stop it before starting a new one.');
    header('Location: stop_work.php');
    exit;
}

// Get all active projects for dropdown
$pdo = getDbConnection();
$stmt = $pdo->query("
    SELECT 
        p.project_id,
        p.name as project_name,
        c.name as client_name
    FROM projects p
    JOIN clients c ON p.client_id = c.client_id
    WHERE p.is_active = 1
    ORDER BY c.name, p.name
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $projectId = filter_var($_POST['projectSelect'] ?? 0, FILTER_VALIDATE_INT);
    $notes = sanitizeInput($_POST['sessionNotes'] ?? '');
    
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
            
            setFlashMessage('success', "Work session started!");
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
        <p>You don't have any active projects. Please add a project or activate an existing one before starting work.</p>
        <div class="mt-3 flex space-x-3">
            <a href="add_project.php" class="text-blue-600 underline">Add a project</a>
            <a href="clients.php" class="text-blue-600 underline">Manage projects</a>
        </div>
    </div>
    <?php else: ?>
    <form method="POST" action="start_work.php">
        <div class="mb-4">
            <label for="projectSelect" class="block text-gray-700 font-medium mb-2">Select Project</label>
            <select id="projectSelect" name="projectSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select a project</option>
                <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project['project_id']; ?>" <?php echo ($projectId ?? 0) == $project['project_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project['client_name'] . ' - ' . $project['project_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-4">
            <label for="sessionNotes" class="block text-gray-700 font-medium mb-2">Session Notes (Optional)</label>
            <textarea id="sessionNotes" name="sessionNotes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                Start Working
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Recent Projects -->
<?php
// Get recent projects
$stmt = $pdo->query("
    SELECT 
        p.project_id,
        p.name as project_name,
        c.name as client_name,
        MAX(ws.start_time) as last_used
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    WHERE p.is_active = 1
    GROUP BY p.project_id
    ORDER BY last_used DESC
    LIMIT 5
");
$recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($recentProjects)):
?>
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Recent Projects</h2>
    <div class="space-y-2">
        <?php foreach ($recentProjects as $project): ?>
        <form method="POST" action="start_work.php" class="border border-gray-200 rounded p-3 hover:bg-gray-50">
            <input type="hidden" name="projectSelect" value="<?php echo $project['project_id']; ?>">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-medium text-blue-600"><?php echo htmlspecialchars($project['project_name']); ?></h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($project['client_name']); ?></p>
                </div>
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded text-sm">
                    Start
                </button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
require_once 'includes/footer.php';
?>