<?php
// Include header
require_once 'includes/header.php';

// Get database connection
$pdo = getDbConnection();

// Get time summaries
$stmt = $pdo->query("
    SELECT SUM(duration_minutes) as total_minutes
    FROM work_sessions
    WHERE date(start_time) = date('now')
");
$todayMinutes = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("
    SELECT SUM(duration_minutes) as total_minutes
    FROM work_sessions
    WHERE start_time >= date('now', 'weekday 0', '-7 days')
");
$weekMinutes = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("
    SELECT SUM(duration_minutes) as total_minutes
    FROM work_sessions
    WHERE start_time >= date('now', 'start of month')
");
$monthMinutes = $stmt->fetchColumn() ?: 0;

// Get project distribution
$stmt = $pdo->query("
    SELECT 
        p.name as project_name,
        SUM(ws.duration_minutes) as minutes,
        SUM(ws.duration_minutes) / 60.0 as hours
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    WHERE ws.duration_minutes IS NOT NULL
    GROUP BY ws.project_id
    ORDER BY minutes DESC
    LIMIT 10
");
$projectDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get client distribution
$stmt = $pdo->query("
    SELECT 
        c.name as client_name,
        SUM(ws.duration_minutes) as minutes,
        SUM(ws.duration_minutes) / 60.0 as hours
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    WHERE ws.duration_minutes IS NOT NULL
    GROUP BY c.client_id
    ORDER BY minutes DESC
    LIMIT 10
");
$clientDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$projectLabels = [];
$projectData = [];
$clientLabels = [];
$clientData = [];

$chartColors = [
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 99, 132, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)',
    'rgba(255, 159, 64, 0.6)'
];

$chartBorders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)'
];

foreach ($projectDistribution as $project) {
    $projectLabels[] = $project['project_name'];
    $projectData[] = $project['hours'];
}

foreach ($clientDistribution as $client) {
    $clientLabels[] = $client['client_name'];
    $clientData[] = $client['hours'];
}
?>

<!-- Time Summary -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Time Summary</h2>
    
    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-blue-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">Today</h3>
            <p class="text-xl font-bold text-blue-600"><?php echo formatDuration($todayMinutes); ?></p>
        </div>
        <div class="bg-green-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">This Week</h3>
            <p class="text-xl font-bold text-green-600"><?php echo formatDuration($weekMinutes); ?></p>
        </div>
        <div class="bg-purple-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">This Month</h3>
            <p class="text-xl font-bold text-purple-600"><?php echo formatDuration($monthMinutes); ?></p>
        </div>
    </div>
</div>

<!-- Project Distribution Chart -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Project Distribution</h2>
    <?php if (empty($projectDistribution)): ?>
    <p class="text-gray-600 text-center py-4">No project data available yet.</p>
    <?php else: ?>
    <div class="mb-3">
        <canvas id="projectChart"></canvas>
    </div>
    <?php endif; ?>
</div>

<!-- Client Distribution Chart -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Client Distribution</h2>
    <?php if (empty($clientDistribution)): ?>
    <p class="text-gray-600 text-center py-4">No client data available yet.</p>
    <?php else: ?>
    <div class="mb-3">
        <canvas id="clientChart"></canvas>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($projectDistribution) || !empty($clientDistribution)): ?>
<script>
    <?php if (!empty($projectDistribution)): ?>
    // Project Distribution Chart
    const projectCtx = document.getElementById('projectChart').getContext('2d');
    const projectChart = new Chart(projectCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($projectLabels); ?>,
            datasets: [{
                label: 'Hours Worked',
                data: <?php echo json_encode($projectData); ?>,
                backgroundColor: <?php echo json_encode(array_slice($chartColors, 0, count($projectLabels))); ?>,
                borderColor: <?php echo json_encode(array_slice($chartBorders, 0, count($projectLabels))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($clientDistribution)): ?>
    // Client Distribution Chart
    const clientCtx = document.getElementById('clientChart').getContext('2d');
    const clientChart = new Chart(clientCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($clientLabels); ?>,
            datasets: [{
                label: 'Hours Worked',
                data: <?php echo json_encode($clientData); ?>,
                backgroundColor: <?php echo json_encode(array_slice($chartColors, 0, count($clientLabels))); ?>,
                borderColor: <?php echo json_encode(array_slice($chartBorders, 0, count($clientLabels))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>
</script>
<?php endif; ?>

<?php
// Include footer
require_once 'includes/footer.php';
?>