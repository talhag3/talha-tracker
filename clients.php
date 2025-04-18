<?php
// Include header
require_once 'includes/header.php';

// Get database connection
$pdo = getDbConnection();

// Get all clients with their project counts
$stmt = $pdo->query("
    SELECT 
        c.client_id,
        c.name,
        c.email,
        c.notes,
        c.created_at,
        COUNT(p.project_id) as project_count,
        SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_projects
    FROM clients c
    LEFT JOIN projects p ON c.client_id = p.client_id
    GROUP BY c.client_id
    ORDER BY c.name
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get projects for a specific client
function getClientProjects($clientId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT 
            p.project_id,
            p.name,
            p.notes,
            p.is_active,
            p.created_at,
            (SELECT SUM(duration_minutes) FROM work_sessions WHERE project_id = p.project_id) as total_minutes
        FROM projects p
        WHERE p.client_id = :client_id
        ORDER BY p.is_active DESC, p.name
    ");
    $stmt->execute([':client_id' => $clientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Clients List -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">Clients & Projects</h2>
        <a href="add_client.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded text-sm">
            Add New Client
        </a>
    </div>

    <?php if (empty($clients)): ?>
    <div class="text-center py-8">
        <p class="text-gray-600 mb-4">No clients found.</p>
        <a href="add_client.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
            Add Your First Client
        </a>
    </div>
    <?php else: ?>
    <div class="space-y-6">
        <?php foreach ($clients as $client): ?>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <!-- Client Header -->
            <div class="bg-gray-50 p-4 flex flex-col sm:flex-row sm:justify-between sm:items-center">
                <div>
                    <h3 class="text-lg font-medium text-blue-600"><?php echo htmlspecialchars($client['name']); ?></h3>
                    <?php if ($client['email']): ?>
                    <p class="text-sm text-gray-600">
                        <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="hover:underline">
                            <?php echo htmlspecialchars($client['email']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="mt-2 sm:mt-0 flex flex-wrap gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <?php echo $client['project_count']; ?> Project<?php echo $client['project_count'] != 1 ? 's' : ''; ?>
                    </span>
                    <?php if ($client['active_projects'] > 0): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <?php echo $client['active_projects']; ?> Active
                    </span>
                    <?php endif; ?>
                    <div class="flex gap-2">
                        <a href="edit_client.php?id=<?php echo $client['client_id']; ?>" class="text-sm text-gray-600 hover:text-blue-600">
                            Edit
                        </a>
                        <span class="text-gray-300">|</span>
                        <a href="delete_client.php?id=<?php echo $client['client_id']; ?>" class="text-sm text-gray-600 hover:text-red-600">
                            Delete
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Client Projects -->
            <div class="p-4">
                <?php if ($client['project_count'] > 0): ?>
                <?php $projects = getClientProjects($client['client_id']); ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Time</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($projects as $project): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm">
                                    <?php if ($project['is_active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo formatDuration($project['total_minutes'] ?? 0); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo formatDate($project['created_at']); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="edit_project.php?id=<?php echo $project['project_id']; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="delete_project.php?id=<?php echo $project['project_id']; ?>" class="text-red-600 hover:text-red-900">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-right">
                    <a href="add_project.php?client_id=<?php echo $client['client_id']; ?>" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Project
                    </a>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-600 mb-2">No projects for this client yet.</p>
                    <a href="add_project.php?client_id=<?php echo $client['client_id']; ?>" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Project
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($client['notes']): ?>
            <div class="border-t border-gray-200 px-4 py-3">
                <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Notes</h4>
                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>