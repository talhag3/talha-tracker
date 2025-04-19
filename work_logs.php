<?php
// Include header
require_once 'includes/header.php';

// Get database connection
$pdo = getDbConnection();

// Get filter parameters
$projectId = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT);
$clientId = filter_var($_GET['client_id'] ?? 0, FILTER_VALIDATE_INT);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Validate date format
if (!empty($dateFrom) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!empty($dateTo) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

// Build query conditions
$conditions = [];
$params = [];

if ($projectId) {
    $conditions[] = "ws.project_id = :project_id";
    $params[':project_id'] = $projectId;
}

if ($clientId) {
    $conditions[] = "c.client_id = :client_id";
    $params[':client_id'] = $clientId;
}

if ($dateFrom) {
    $conditions[] = "date(ws.start_time) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo) {
    $conditions[] = "date(ws.start_time) <= :date_to";
    $params[':date_to'] = $dateTo;
}

// Build the WHERE clause
$whereClause = '';
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Get work sessions with pagination
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total sessions for pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    $whereClause
";

try {
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalSessions = $stmt->fetchColumn();
    $totalPages = ceil($totalSessions / $perPage);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $totalSessions = 0;
    $totalPages = 1;
}

// Get work sessions
$query = "
    SELECT 
        ws.session_id,
        ws.start_time,
        ws.end_time,
        ws.duration_minutes,
        ws.notes,
        p.name as project_name,
        c.name as client_name
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    $whereClause
    ORDER BY ws.start_time DESC
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $sessions = [];
}

// Get all clients and projects for filters
$clients = $pdo->query("SELECT client_id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT project_id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Work Logs -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-4">Work Logs</h2>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?php echo $error; ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="bg-gray-50 p-4 rounded-lg mb-4">
        <form method="GET" action="work_logs.php" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                    <select id="client_id" name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" <?php echo $clientId == $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select id="project_id" name="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['project_id']; ?>" <?php echo $projectId == $project['project_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex justify-end">
                <a href="work_logs.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded mr-2">
                    Reset
                </a>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Work Sessions Table -->
    <?php if (empty($sessions)): ?>
    <div class="text-center py-8">
        <p class="text-gray-600 mb-4">No work sessions found.</p>
        <a href="start_work.php" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
            Start Work
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($sessions as $session): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($session['project_name']); ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        <?php echo htmlspecialchars($session['client_name']); ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        <?php echo formatDateTime($session['start_time']); ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        <?php echo $session['end_time'] ? formatDateTime($session['end_time']) : '<span class="text-green-600 font-medium">Active</span>'; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium <?php echo $session['end_time'] ? 'text-blue-600' : 'text-green-600'; ?>">
                        <?php 
                        if ($session['duration_minutes']) {
                            echo formatDuration($session['duration_minutes']);
                        } elseif (!$session['end_time']) {
                            echo 'Running';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                        <?php echo htmlspecialchars($session['notes'] ?? ''); ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                        <a href="delete_work_session.php?id=<?php echo $session['session_id']; ?>" class="text-red-600 hover:text-red-900">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-700">
            Showing <span class="font-medium"><?php echo min(($page - 1) * $perPage + 1, $totalSessions); ?></span> to 
            <span class="font-medium"><?php echo min($page * $perPage, $totalSessions); ?></span> of 
            <span class="font-medium"><?php echo $totalSessions; ?></span> results
        </div>
        
        <div class="flex space-x-1">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $projectId ? "&project_id=$projectId" : ''; ?><?php echo $clientId ? "&client_id=$clientId" : ''; ?><?php echo $dateFrom ? "&date_from=$dateFrom" : ''; ?><?php echo $dateTo ? "&date_to=$dateTo" : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Previous
            </a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $projectId ? "&project_id=$projectId" : ''; ?><?php echo $clientId ? "&client_id=$clientId" : ''; ?><?php echo $dateFrom ? "&date_from=$dateFrom" : ''; ?><?php echo $dateTo ? "&date_to=$dateTo" : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Next
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>