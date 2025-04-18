<?php
// Include header
require_once 'includes/header.php';

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
    
    // If no errors, insert client
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO clients (name, email, notes)
                VALUES (:name, :email, :notes)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':notes' => $notes
            ]);
            
            setFlashMessage('success', "Client '{$name}' added successfully!");
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!-- Add Client Form -->
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

    <form method="POST" action="add_client.php">
        <div class="mb-4">
            <label for="clientName" class="block text-gray-700 font-medium mb-2">Client Name</label>
            <input type="text" id="clientName" name="clientName" value="<?php echo htmlspecialchars($name ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>
        
        <div class="mb-4">
            <label for="clientEmail" class="block text-gray-700 font-medium mb-2">Email</label>
            <input type="email" id="clientEmail" name="clientEmail" value="<?php echo htmlspecialchars($email ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-4">
            <label for="clientNotes" class="block text-gray-700 font-medium mb-2">Notes</label>
            <textarea id="clientNotes" name="clientNotes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-between">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                Cancel
            </a>
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                Save Client
            </button>
        </div>
    </form>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>