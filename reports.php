<?php
// Include header
require_once 'includes/header.php';

// Get database connection
$pdo = getDbConnection();

// Default to current month if no date range is specified
$today = new DateTime();
$startOfMonth = new DateTime('first day of this month');
$endOfMonth = new DateTime('last day of this month');

// Get filter parameters
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : $startOfMonth->format('Y-m-d');
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : $endOfMonth->format('Y-m-d');
$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$chartType = isset($_GET['chart_type']) ? sanitizeInput($_GET['chart_type']) : 'bar';

// Validate chart type
if (!in_array($chartType, ['bar', 'pie', 'line', 'doughnut'])) {
    $chartType = 'bar';
}

// Get all clients for filter dropdown
$clientsStmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get projects based on client selection
$projectsQuery = "SELECT project_id, name FROM projects WHERE 1=1";
$projectParams = [];

if ($clientId > 0) {
    $projectsQuery .= " AND client_id = :client_id";
    $projectParams[':client_id'] = $clientId;
}

$projectsQuery .= " ORDER BY name";
$projectsStmt = $pdo->prepare($projectsQuery);
$projectsStmt->execute($projectParams);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get time summaries (keeping your original queries)
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

// Get filtered time data
$filteredTimeQuery = "
    SELECT SUM(duration_minutes) as total_minutes
    FROM work_sessions
    WHERE start_time BETWEEN :start_date AND :end_date
";
$filteredTimeParams = [
    ':start_date' => $startDate,
    ':end_date' => $endDate . ' 23:59:59'
];

if ($clientId > 0) {
    $filteredTimeQuery .= " AND project_id IN (SELECT project_id FROM projects WHERE client_id = :client_id)";
    $filteredTimeParams[':client_id'] = $clientId;
}

if ($projectId > 0) {
    $filteredTimeQuery .= " AND project_id = :project_id";
    $filteredTimeParams[':project_id'] = $projectId;
}

$stmt = $pdo->prepare($filteredTimeQuery);
$stmt->execute($filteredTimeParams);
$filteredMinutes = $stmt->fetchColumn() ?: 0;

// Get project distribution with filters
$projectDistributionQuery = "
    SELECT 
        p.name as project_name,
        SUM(ws.duration_minutes) as minutes,
        SUM(ws.duration_minutes) / 60.0 as hours
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    WHERE ws.duration_minutes IS NOT NULL
    AND ws.start_time BETWEEN :start_date AND :end_date
";
$projectDistributionParams = [
    ':start_date' => $startDate,
    ':end_date' => $endDate . ' 23:59:59'
];

if ($clientId > 0) {
    $projectDistributionQuery .= " AND p.client_id = :client_id";
    $projectDistributionParams[':client_id'] = $clientId;
}

if ($projectId > 0) {
    $projectDistributionQuery .= " AND p.project_id = :project_id";
    $projectDistributionParams[':project_id'] = $projectId;
}

$projectDistributionQuery .= " GROUP BY ws.project_id ORDER BY minutes DESC LIMIT 10";

$stmt = $pdo->prepare($projectDistributionQuery);
$stmt->execute($projectDistributionParams);
$projectDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get client distribution with filters
$clientDistributionQuery = "
    SELECT 
        c.name as client_name,
        SUM(ws.duration_minutes) as minutes,
        SUM(ws.duration_minutes) / 60.0 as hours
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    WHERE ws.duration_minutes IS NOT NULL
    AND ws.start_time BETWEEN :start_date AND :end_date
";
$clientDistributionParams = [
    ':start_date' => $startDate,
    ':end_date' => $endDate . ' 23:59:59'
];

if ($clientId > 0) {
    $clientDistributionQuery .= " AND c.client_id = :client_id";
    $clientDistributionParams[':client_id'] = $clientId;
}

if ($projectId > 0) {
    $clientDistributionQuery .= " AND p.project_id = :project_id";
    $clientDistributionParams[':project_id'] = $projectId;
}

$clientDistributionQuery .= " GROUP BY c.client_id ORDER BY minutes DESC LIMIT 10";

$stmt = $pdo->prepare($clientDistributionQuery);
$stmt->execute($clientDistributionParams);
$clientDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily distribution for the selected period
$dailyDistributionQuery = "
    SELECT 
        date(start_time) as work_date,
        SUM(duration_minutes) as minutes,
        SUM(duration_minutes) / 60.0 as hours
    FROM work_sessions
    WHERE start_time BETWEEN :start_date AND :end_date
";
$dailyDistributionParams = [
    ':start_date' => $startDate,
    ':end_date' => $endDate . ' 23:59:59'
];

if ($clientId > 0) {
    $dailyDistributionQuery .= " AND project_id IN (SELECT project_id FROM projects WHERE client_id = :client_id)";
    $dailyDistributionParams[':client_id'] = $clientId;
}

if ($projectId > 0) {
    $dailyDistributionQuery .= " AND project_id = :project_id";
    $dailyDistributionParams[':project_id'] = $projectId;
}

$dailyDistributionQuery .= " GROUP BY date(start_time) ORDER BY work_date";

$stmt = $pdo->prepare($dailyDistributionQuery);
$stmt->execute($dailyDistributionParams);
$dailyDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$projectLabels = [];
$projectData = [];
$clientLabels = [];
$clientData = [];
$dailyLabels = [];
$dailyData = [];

$chartColors = [
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 99, 132, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)',
    'rgba(255, 159, 64, 0.6)',
    'rgba(201, 203, 207, 0.6)',
    'rgba(255, 99, 71, 0.6)',
    'rgba(50, 205, 50, 0.6)',
    'rgba(138, 43, 226, 0.6)'
];

$chartBorders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(201, 203, 207, 1)',
    'rgba(255, 99, 71, 1)',
    'rgba(50, 205, 50, 1)',
    'rgba(138, 43, 226, 1)'
];

foreach ($projectDistribution as $project) {
    $projectLabels[] = $project['project_name'];
    $projectData[] = $project['hours'];
}

foreach ($clientDistribution as $client) {
    $clientLabels[] = $client['client_name'];
    $clientData[] = $client['hours'];
}

foreach ($dailyDistribution as $day) {
    $date = new DateTime($day['work_date']);
    $dailyLabels[] = $date->format('M j');
    $dailyData[] = $day['hours'];
}

// Get detailed work sessions for the selected period
$sessionsQuery = "
    SELECT 
        ws.session_id,
        ws.project_id,
        p.name as project_name,
        c.client_id,
        c.name as client_name,
        ws.start_time,
        ws.end_time,
        ws.duration_minutes,
        ws.notes
    FROM work_sessions ws
    JOIN projects p ON ws.project_id = p.project_id
    JOIN clients c ON p.client_id = c.client_id
    WHERE ws.start_time BETWEEN :start_date AND :end_date
";
$sessionsParams = [
    ':start_date' => $startDate,
    ':end_date' => $endDate . ' 23:59:59'
];

if ($clientId > 0) {
    $sessionsQuery .= " AND c.client_id = :client_id";
    $sessionsParams[':client_id'] = $clientId;
}

if ($projectId > 0) {
    $sessionsQuery .= " AND p.project_id = :project_id";
    $sessionsParams[':project_id'] = $projectId;
}

$sessionsQuery .= " ORDER BY ws.start_time DESC";

$stmt = $pdo->prepare($sessionsQuery);
$stmt->execute($sessionsParams);
$workSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Report Filters</h2>
    
    <form method="GET" action="reports.php" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Date Range -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Client Filter -->
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select id="client_id" name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="0">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['client_id']; ?>" <?php echo $clientId == $client['client_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($client['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Project Filter -->
            <div>
                <label for="project_id" class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                <select id="project_id" name="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="0">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?php echo $project['project_id']; ?>" <?php echo $projectId == $project['project_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($project['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Chart Type -->
            <div>
                <label for="chart_type" class="block text-sm font-medium text-gray-700 mb-1">Chart Type</label>
                <select id="chart_type" name="chart_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="bar" <?php echo $chartType === 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                    <option value="line" <?php echo $chartType === 'line' ? 'selected' : ''; ?>>Line Chart</option>
                    <option value="pie" <?php echo $chartType === 'pie' ? 'selected' : ''; ?>>Pie Chart</option>
                    <option value="doughnut" <?php echo $chartType === 'doughnut' ? 'selected' : ''; ?>>Doughnut Chart</option>
                </select>
            </div>
            
            <!-- Quick Date Ranges -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quick Date Range</label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="setDateRange('today')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        Today
                    </button>
                    <button type="button" onclick="setDateRange('yesterday')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        Yesterday
                    </button>
                    <button type="button" onclick="setDateRange('this-week')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        This Week
                    </button>
                    <button type="button" onclick="setDateRange('last-week')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        Last Week
                    </button>
                    <button type="button" onclick="setDateRange('this-month')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        This Month
                    </button>
                    <button type="button" onclick="setDateRange('last-month')" class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded">
                        Last Month
                    </button>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded">
                    Generate Report
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Time Summary -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Time Summary</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
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
        <div class="bg-amber-50 p-3 rounded-lg">
            <h3 class="text-sm font-medium text-gray-700">Filtered Period</h3>
            <p class="text-xl font-bold text-amber-600"><?php echo formatDuration($filteredMinutes); ?></p>
            <p class="text-xs text-gray-500">
                <?php echo (new DateTime($startDate))->format('M j, Y'); ?> - 
                <?php echo (new DateTime($endDate))->format('M j, Y'); ?>
            </p>
        </div>
    </div>
</div>

<!-- Daily Distribution Chart -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Daily Time Distribution</h2>
    <?php if (empty($dailyDistribution)): ?>
    <p class="text-gray-600 text-center py-4">No data available for the selected period.</p>
    <?php else: ?>
    <div class="mb-3" style="height: 300px;">
        <canvas id="dailyChart"></canvas>
    </div>
    <?php endif; ?>
</div>

<!-- Project Distribution Chart -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Project Distribution</h2>
    <?php if (empty($projectDistribution)): ?>
    <p class="text-gray-600 text-center py-4">No project data available for the selected period.</p>
    <?php else: ?>
    <div class="mb-3" style="height: 300px;">
        <canvas id="projectChart"></canvas>
    </div>
    <?php endif; ?>
</div>

<!-- Client Distribution Chart -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Client Distribution</h2>
    <?php if (empty($clientDistribution)): ?>
    <p class="text-gray-600 text-center py-4">No client data available for the selected period.</p>
    <?php else: ?>
    <div class="mb-3" style="height: 300px;">
        <canvas id="clientChart"></canvas>
    </div>
    <?php endif; ?>
</div>

<!-- Detailed Work Sessions -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Detailed Work Sessions</h2>
    
    <?php if (empty($workSessions)): ?>
    <p class="text-gray-600 text-center py-4">No work sessions found for the selected period.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($workSessions as $session): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo formatDate($session['start_time']); ?>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($session['client_name']); ?>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($session['project_name']); ?>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo date('g:i A', strtotime($session['start_time'])); ?>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                        <?php echo formatDuration($session['duration_minutes']); ?>
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-900 max-w-xs truncate">
                        <?php echo htmlspecialchars($session['notes'] ?? ''); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
// Quick date range selection
function setDateRange(range) {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    const today = new Date();
    let startDate = new Date();
    let endDate = new Date();
    
    switch(range) {
        case 'today':
            // Start and end are both today
            break;
            
        case 'yesterday':
            startDate.setDate(today.getDate() - 1);
            endDate.setDate(today.getDate() - 1);
            break;
            
        case 'this-week':
            // Start of week (Monday)
            startDate.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));
            break;
            
        case 'last-week':
            // Start of last week (Monday)
            startDate.setDate(today.getDate() - today.getDay() - 6);
            // End of last week (Sunday)
            endDate.setDate(today.getDate() - today.getDay());
            break;
            
        case 'this-month':
            startDate.setDate(1);
            endDate.setDate(new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate());
            break;
            
        case 'last-month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
    }
    
    startDateInput.value = formatDateForInput(startDate);
    endDateInput.value = formatDateForInput(endDate);
}

// Format date for input field (YYYY-MM-DD)
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

<?php if (!empty($dailyDistribution)): ?>
// Daily Distribution Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: '<?php echo $chartType; ?>',
    data: {
        labels: <?php echo json_encode($dailyLabels); ?>,
        datasets: [{
            label: 'Hours Worked',
            data: <?php echo json_encode($dailyData); ?>,
            backgroundColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartColors, 0, count($dailyLabels))) : "'rgba(54, 162, 235, 0.6)'"; ?>,
            borderColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartBorders, 0, count($dailyLabels))) : "'rgba(54, 162, 235, 1)'"; ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: <?php echo in_array($chartType, ['pie', 'doughnut']) ? "'right'" : "'top'"; ?>
            }
        },
        scales: {
            <?php if (!in_array($chartType, ['pie', 'doughnut'])): ?>
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Hours'
                }
            }
            <?php endif; ?>
        }
    }
});
<?php endif; ?>

<?php if (!empty($projectDistribution)): ?>
// Project Distribution Chart
const projectCtx = document.getElementById('projectChart').getContext('2d');
const projectChart = new Chart(projectCtx, {
    type: '<?php echo $chartType; ?>',
    data: {
        labels: <?php echo json_encode($projectLabels); ?>,
        datasets: [{
            label: 'Hours Worked',
            data: <?php echo json_encode($projectData); ?>,
            backgroundColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartColors, 0, count($projectLabels))) : "'rgba(255, 99, 132, 0.6)'"; ?>,
            borderColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartBorders, 0, count($projectLabels))) : "'rgba(255, 99, 132, 1)'"; ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: <?php echo in_array($chartType, ['pie', 'doughnut']) ? "'right'" : "'top'"; ?>
            }
        },
        scales: {
            <?php if (!in_array($chartType, ['pie', 'doughnut'])): ?>
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Hours'
                }
            }
            <?php endif; ?>
        }
    }
});
<?php endif; ?>

<?php if (!empty($clientDistribution)): ?>
// Client Distribution Chart
const clientCtx = document.getElementById('clientChart').getContext('2d');
const clientChart = new Chart(clientCtx, {
    type: '<?php echo $chartType; ?>',
    data: {
        labels: <?php echo json_encode($clientLabels); ?>,
        datasets: [{
            label: 'Hours Worked',
            data: <?php echo json_encode($clientData); ?>,
            backgroundColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartColors, 0, count($clientLabels))) : "'rgba(75, 192, 192, 0.6)'"; ?>,
            borderColor: <?php echo in_array($chartType, ['pie', 'doughnut']) ? json_encode(array_slice($chartBorders, 0, count($clientLabels))) : "'rgba(75, 192, 192, 1)'"; ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: <?php echo in_array($chartType, ['pie', 'doughnut']) ? "'right'" : "'top'"; ?>
            }
        },
        scales: {
            <?php if (!in_array($chartType, ['pie', 'doughnut'])): ?>
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Hours'
                }
            }
            <?php endif; ?>
        }
    }
});
<?php endif; ?>
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>