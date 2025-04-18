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
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: clients.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = sanitizeInput($_POST['clientName'] ?? '');
    $email = sanitizeInput($_POST['clientEmail'] ?? '');
    $notes = sanitizeInput($_POST['clientNotes'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Client name is required";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, update client
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE clients 
                SET name = :name, email = :email, notes = :notes 
                WHERE client_id = :client_id
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':notes' => $notes,
                ':client_id' => $clientId
            ]);
            
            setFlashMessage('success', "Client '{$name}' updated successfully!");
            header('Location: clients.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Edit Client Form -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Edit Client</h2>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="edit_client.php?id=<?php echo $clientId; ?>">
        <div class="mb-4">
            <label for="clientName" class="block text-gray-700 font-medium mb-2">Client Name</label>
            <input type="text" id="clientName" name="clientName" value="<?php echo htmlspecialchars($client['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>
        
        <div class="mb-4">
            <label for="clientEmail" class="block text-gray-700 font-medium mb-2">Email (Optional)</label>
            <input type="email" id="clientEmail" name="clientEmail" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-4">
            <label for="clientNotes" class="block text-gray-700 font-medium mb-2">Notes (Optional)</label>
            <textarea id="clientNotes" name="clientNotes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                Update Client
            </button>
        </div>
    </form>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>