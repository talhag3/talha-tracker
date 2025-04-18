<?php
// Include header
require_once 'includes/header.php';

// Get today's total time
$todayTotalMinutes = getTodayTotalTime();
$todayTotalFormatted = formatDuration($todayTotalMinutes);

// Get today's work distribution
$todayDistribution = getTodayWorkDistribution();

// Prepare chart data
$projectLabels = [];
$projectData = [];
$projectColors = [
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 99, 132, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)',
    'rgba(255, 159, 64, 0.6)'
];
$projectBorders = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)'
];

foreach ($todayDistribution as $index => $project) {
    $projectLabels[] = $project['project_name'];
    $projectData[] = $project['hours'];
}
?>

<!-- Active Work Session -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-2">Current Session</h2>
    <div class="active-session mb-3">
        <?php if ($activeSession): ?>
        <div>
            <p class="font-medium">Project: <span class="text-blue-600"><?php echo htmlspecialchars($activeSession['project_name']); ?></span></p>
            <p class="text-sm text-gray-600">Started at: <?php echo formatTime($activeSession['start_time']); ?></p>
            <p class="text-sm text-gray-600">
                Duration: 
                <span id="session-duration">
                    <?php 
                    $startTime = new DateTime($activeSession['start_time']);
                    $now = new DateTime();
                    $interval = $startTime->diff($now);
                    echo $interval->format('%hh %im');
                    ?>
                </span>
            </p>
        </div>
        <?php else: ?>
        <p class="text-gray-700">No active session</p>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <a href="start_work.php" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded text-center <?php echo $activeSession ? 'opacity-50 pointer-events-none' : ''; ?>">
            Start Work
        </a>
        <a href="stop_work.php" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded text-center <?php echo !$activeSession ? 'opacity-50 pointer-events-none' : ''; ?>">
            Stop Work
        </a>
    </div>
</div>

<!-- Today's Summary -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-2">Today's Summary</h2>
    <p class="text-2xl font-bold text-blue-600 mb-3"><?php echo $todayTotalFormatted; ?></p>
    <div class="mb-3">
        <canvas id="todayChart"></canvas>
    </div>
</div>

<!-- Quick Links -->
<div class="bg-white rounded-xl shadow p-4 mb-4">
    <h2 class="text-lg font-semibold mb-3">Quick Links</h2>
    <div class="grid grid-cols-2 gap-3">
        <a href="clients.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded text-center">
            Clients & Projects
        </a>
        <a href="add_client.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded text-center">
            Add Client
        </a>
        <a href="add_project.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded text-center">
            Add Project
        </a>
        <a href="work_logs.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded text-center">
            Work Logs
        </a>
        <a href="reports.php" class="bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded text-center">
            Reports
        </a>
    </div>
</div>

<script>
    // Today's chart
    const ctx = document.getElementById('todayChart').getContext('2d');
    const todayChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($projectLabels); ?>,
            datasets: [{
                label: 'Hours Worked',
                data: <?php echo json_encode($projectData); ?>,
                backgroundColor: <?php echo json_encode(array_slice($projectColors, 0, count($projectLabels))); ?>,
                borderColor: <?php echo json_encode(array_slice($projectBorders, 0, count($projectLabels))); ?>,
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

    <?php if ($activeSession): ?>
    // Update session duration in real-time
    function updateSessionDuration() {
        const startTime = new Date('<?php echo $activeSession['start_time']; ?>');
        const now = new Date();
        const diffMs = now - startTime;
        
        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        document.getElementById('session-duration').textContent = `${hours}h ${minutes}m`;
    }
    
    // Update every minute
    setInterval(updateSessionDuration, 60000);
    <?php endif; ?>
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>