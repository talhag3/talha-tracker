<?php
// Include header
require_once 'includes/header.php';

// Check if client_id is provided
$clientId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$clientId) {
    setFlashMessage('error', 'Invalid client ID.');
    header('Location: clients.php');
    exit;
}

// Get database connection
$pdo = getDbConnection();

// Get client data
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = :client_id");
    $stmt->execute([':client_id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        setFlashMessage('error', 'Client not found.');
        header('Location: clients.php');
        exit;
    }
    
    // Check if client has projects
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = :client_id");
    $stmt->execute([':client_id' => $clientId]);
    $projectCount = $stmt->fetchColumn();
    
    // Get work session count for this client's projects
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM work_sessions ws
        JOIN projects p ON ws.project_id = p.project_id
        WHERE p.client_id = :client_id
    ");
    $stmt->execute([':client_id' => $clientId]);
    $sessionCount = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: clients.php');
    exit;
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';
    $deleteRelated = isset($_POST['delete_related']) && $_POST['delete_related'] === 'yes';
    
    if (!$confirmDelete) {
        setFlashMessage('error', 'You must confirm deletion.');
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($projectCount > 0) {
                if ($deleteRelated) {
                    // Delete all work sessions for this client's projects
                    $stmt = $pdo->prepare("
                        DELETE FROM work_sessions 
                        WHERE project_id IN (
                            SELECT project_id FROM projects WHERE client_id = :client_id
                        )
                    ");
                    $stmt->execute([':client_id' => $clientId]);
                    
                    // Delete all projects for this client
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE client_id = :client_id");
                    $stmt->execute([':client_id' => $clientId]);
                } else {
                    setFlashMessage('error', 'Cannot delete client with associated projects. Please delete projects first or check the option to delete all related data.');
                    header('Location: delete_client.php?id=' . $clientId);
                    exit;
                }
            }
            
            // Delete the client
            $stmt = $pdo->prepare("DELETE FROM clients WHERE client_id = :client_id");
            $stmt->execute([':client_id' => $clientId]);
            
            $pdo->commit();
            
            setFlashMessage('success', "Client '{$client['name']}' and all associated data have been deleted.");
            header('Location: clients.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
}
?>

<!-- Delete Client Confirmation -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Delete Client</h2>
    
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
                    <p>You are about to delete the client: <strong><?php echo htmlspecialchars($client['name']); ?></strong></p>
                    <?php if ($projectCount > 0): ?>
                    <p class="mt-2">This client has <strong><?php echo $projectCount; ?> project<?php echo $projectCount !== 1 ? 's' : ''; ?></strong> and <strong><?php echo $sessionCount; ?> work session<?php echo $sessionCount !== 1 ? 's' : ''; ?></strong> associated with it.</p>
                    <?php endif; ?>
                    <p class="mt-2">This action cannot be undone.</p>
                </div>
            </div>
        </div>
    </div>
    
    <form method="POST" action="delete_client.php?id=<?php echo $clientId; ?>">
        <?php if ($projectCount > 0): ?>
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="delete_related" value="yes" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                <span class="ml-2 text-gray-700">Also delete all associated projects and work sessions</span>
            </label>
        </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="confirm_delete" value="yes" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" required>
                <span class="ml-2 text-gray-700">I confirm that I want to delete this client</span>
            </label>
        </div>
        
        <div class="flex justify-between">
            <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Delete Client
            </button>
        </div>
    </form>
</div>

<!-- Client Details -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Client Details</h2>
    
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium text-gray-500">Name</h3>
            <p class="mt-1"><?php echo htmlspecialchars($client['name']); ?></p>
        </div>
        
        <?php if (!empty($client['email'])): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Email</h3>
            <p class="mt-1"><?php echo htmlspecialchars($client['email']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($client['notes'])): ?>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Notes</h3>
            <p class="mt-1"><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div>
            <h3 class="text-sm font-medium text-gray-500">Created</h3>
            <p class="mt-1"><?php echo formatDate($client['created_at']); ?></p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>