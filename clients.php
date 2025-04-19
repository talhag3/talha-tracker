<?php
// Include header
require_once 'includes/header.php';

// Get database connection
$pdo = getDbConnection();

// Get search parameter and filter
$searchTerm = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? 'all');

// Validate status filter
if (!in_array($statusFilter, ['all', 'active', 'inactive'])) {
    $statusFilter = 'all';
}

// Build the query with search condition if provided
$query = "
    SELECT 
        c.client_id,
        c.name,
        c.email,
        c.notes,
        c.created_at,
        COUNT(p.project_id) as project_count,
        SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END) as inactive_projects
    FROM clients c
    LEFT JOIN projects p ON c.client_id = p.client_id
";

// Add search condition if search term is provided
$conditions = [];
$params = [];

if (!empty($searchTerm)) {
    $conditions[] = "(c.name LIKE :search OR c.email LIKE :search OR c.notes LIKE :search)";
    $params[':search'] = "%{$searchTerm}%";
}

// Add conditions if there are any
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY c.client_id";

// Add having clause for status filter if not 'all'
if ($statusFilter === 'active') {
    $query .= " HAVING active_projects > 0";
} elseif ($statusFilter === 'inactive') {
    $query .= " HAVING (project_count > 0 AND active_projects = 0) OR project_count = 0";
}

$query .= " ORDER BY c.name";

// Execute the query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $clients = [];
}

// Function to get projects for a specific client with optional status filter
function getClientProjects($clientId, $statusFilter = 'all') {
    $pdo = getDbConnection();
    
    $query = "
        SELECT 
            p.project_id,
            p.name,
            p.notes,
            p.is_active,
            p.created_at,
            (SELECT SUM(duration_minutes) FROM work_sessions WHERE project_id = p.project_id) as total_minutes
        FROM projects p
        WHERE p.client_id = :client_id
    ";
    
    $params = [':client_id' => $clientId];
    
    // Add status filter if not 'all'
    if ($statusFilter === 'active') {
        $query .= " AND p.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $query .= " AND p.is_active = 0";
    }
    
    $query .= " ORDER BY p.is_active DESC, p.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total counts for the filter badges
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT p.project_id) as total_projects,
        SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END) as inactive_projects
    FROM projects p
");
$projectCounts = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Clients List -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
        <h2 class="text-lg font-semibold">Clients & Projects</h2>
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <!-- Search Form -->
            <form method="GET" action="clients.php" class="flex-grow sm:flex-grow-0 flex">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search clients..." 
                    value="<?php echo htmlspecialchars($searchTerm); ?>" 
                    class="flex-grow px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                <?php if ($statusFilter !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <?php endif; ?>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-3 rounded-r-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>
            <?php if (!empty($searchTerm) || $statusFilter !== 'all'): ?>
            <a href="clients.php" class="text-sm text-gray-600 hover:text-blue-600 py-2">
                Clear filters
            </a>
            <?php endif; ?>
            <a href="add_client.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded text-sm whitespace-nowrap">
                Add New Client
            </a>
        </div>
    </div>

    <!-- Filter Options -->
    <div class="mb-4">
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo 'clients.php' . (!empty($searchTerm) ? '?search=' . urlencode($searchTerm) : ''); ?>" 
               class="<?php echo $statusFilter === 'all' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-gray-100 text-gray-800 border-gray-300 hover:bg-gray-200'; ?> px-3 py-1 rounded-full text-sm border">
                All Projects 
                <span class="font-medium">(<?php echo $projectCounts['total_projects'] ?? 0; ?>)</span>
            </a>
            <a href="<?php echo 'clients.php?status=active' . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''); ?>" 
               class="<?php echo $statusFilter === 'active' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-gray-100 text-gray-800 border-gray-300 hover:bg-gray-200'; ?> px-3 py-1 rounded-full text-sm border">
                Active Projects 
                <span class="font-medium">(<?php echo $projectCounts['active_projects'] ?? 0; ?>)</span>
            </a>
            <a href="<?php echo 'clients.php?status=inactive' . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''); ?>" 
               class="<?php echo $statusFilter === 'inactive' ? 'bg-gray-200 text-gray-800 border-gray-400' : 'bg-gray-100 text-gray-800 border-gray-300 hover:bg-gray-200'; ?> px-3 py-1 rounded-full text-sm border">
                Inactive Projects 
                <span class="font-medium">(<?php echo $projectCounts['inactive_projects'] ?? 0; ?>)</span>
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?php echo $error; ?></p>
    </div>
    <?php endif; ?>

    <?php 
    // Prepare filter description
    $filterDescription = [];
    if (!empty($searchTerm)) {
        $filterDescription[] = "search term: <strong>" . htmlspecialchars($searchTerm) . "</strong>";
    }
    if ($statusFilter !== 'all') {
        $filterDescription[] = "<strong>" . ucfirst($statusFilter) . "</strong> projects only";
    }
    ?>

    <?php if (!empty($filterDescription)): ?>
    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
        <p>
            Filtering by <?php echo implode(' and ', $filterDescription); ?>
            (<?php echo count($clients); ?> client<?php echo count($clients) != 1 ? 's' : ''; ?> found)
        </p>
    </div>
    <?php endif; ?>

    <?php if (empty($clients)): ?>
    <div class="text-center py-8">
        <?php if (!empty($searchTerm) || $statusFilter !== 'all'): ?>
        <p class="text-gray-600 mb-4">No clients found matching your filters.</p>
        <a href="clients.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
            View All Clients
        </a>
        <?php else: ?>
        <p class="text-gray-600 mb-4">No clients found.</p>
        <a href="add_client.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
            Add Your First Client
        </a>
        <?php endif; ?>
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
                    <?php if ($client['inactive_projects'] > 0): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <?php echo $client['inactive_projects']; ?> Inactive
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
                <?php 
                // Get projects with status filter
                $projects = getClientProjects($client['client_id'], $statusFilter);
                $projectCount = count($projects);
                ?>
                
                <?php if ($projectCount > 0): ?>
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
                <?php elseif ($client['project_count'] > 0 && $statusFilter !== 'all'): ?>
                <div class="text-center py-4">
                    <p class="text-gray-600 mb-2">
                        <?php if ($statusFilter === 'active'): ?>
                        No active projects for this client.
                        <?php else: ?>
                        No inactive projects for this client.
                        <?php endif; ?>
                    </p>
                    <div class="flex justify-center gap-2 mt-2">
                        <a href="clients.php<?php echo !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : ''; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                            Show all projects
                        </a>
                        <span class="text-gray-300">|</span>
                        <a href="add_project.php?client_id=<?php echo $client['client_id']; ?>" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Project
                        </a>
                    </div>
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