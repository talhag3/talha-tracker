<?php
// Include header
require_once 'includes/header.php';

// Get work logs
$pdo = getDbConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) FROM work_logs");
$totalLogs = $stmt->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Get logs for current page
$stmt = $pdo->prepare("
    SELECT 
        session_id,
        project_name,
        client_name,
        start_time,
        end_time,
        duration_minutes
    FROM work_logs
    ORDER BY start_time DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Work Logs Table -->
<div class="bg-white rounded-xl shadow p-4 mb-4 overflow-x-auto">
    <?php if (empty($logs)): ?>
    <div class="text-center py-4">
        <p class="text-gray-600">No work logs found.</p>
        <a href="start_work.php" class="text-blue-600 underline">Start your first work session</a>
    </div>
    <?php else: ?>
    <table class="min-w-full">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">Date</th>
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">Project</th>
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">Client</th>
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">Start</th>
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">End</th>
                <th class="py-2 px-3 text-left text-sm font-medium text-gray-700">Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="py-2 px-3 text-sm text-gray-700"><?php echo formatDate($log['start_time']); ?></td>
                <td class="py-2 px-3 text-sm text-blue-600"><?php echo htmlspecialchars($log['project_name']); ?></td>
                <td class="py-2 px-3 text-sm text-gray-700"><?php echo htmlspecialchars($log['client_name']); ?></td>
                <td class="py-2 px-3 text-sm text-gray-700"><?php echo formatTime($log['start_time']); ?></td>
                <td class="py-2 px-3 text-sm text-gray-700"><?php echo $log['end_time'] ? formatTime($log['end_time']) : 'Active'; ?></td>
                <td class="py-2 px-3 text-sm font-medium text-green-600">
                    <?php echo $log['duration_minutes'] ? formatDuration($log['duration_minutes']) : 'In progress'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs
        </div>
        <div class="flex space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Previous</a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Next</a>
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