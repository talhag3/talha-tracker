<?php
// Include header
require_once 'includes/header.php';

// Get all clients for dropdown
$pdo = getDbConnection();
$stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = sanitizeInput($_POST['projectName'] ?? '');
    $clientId = filter_var($_POST['clientSelect'] ?? 0, FILTER_VALIDATE_INT);
    $notes = sanitizeInput($_POST['projectNotes'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Project name is required";
    }
    
    if (!$clientId) {
        $errors[] = "Please select a client";
    }
    
    // If no errors, insert project
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects (client_id, name, notes)
                VALUES (:client_id, :name, :notes)
            ");
            
            $stmt->execute([
                ':client_id' => $clientId,
                ':name' => $name,
                ':notes' => $notes
            ]);
            
            setFlashMessage('success', "Project '{$name}' added successfully!");
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Add Project Form -->
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

    <?php if (empty($clients)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
        <p>You need to add a client before you can create a project.</p>
        <a href="add_client.php" class="text-blue-600 underline">Add a client now</a>
    </div>
    <?php else: ?>
    <form method="POST" action="add_project.php">
        <div class="mb-4">
            <label for="projectName" class="block text-gray-700 font-medium mb-2">Project Name</label>
            <input type="text" id="projectName" name="projectName" value="<?php echo htmlspecialchars($name ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>
        
        <div class="mb-4">
            <label for="clientSelect" class="block text-gray-700 font-medium mb-2">Client</label>
            <select id="clientSelect" name="clientSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client['client_id']; ?>" <?php echo ($clientId ?? 0) == $client['client_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($client['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-4">
            <label for="projectNotes" class="block text-gray-700 font-medium mb-2">Notes</label>
            <textarea id="projectNotes" name="projectNotes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                Save Project
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>