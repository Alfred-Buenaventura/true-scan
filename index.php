<?php
require_once 'config.php';

// --- Page Variables ---
// These are now passed to includes/header.php to build the header
$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, System Administrator!';
// --- End Page Variables ---

require_once 'includes/header.php';

// Example data (replace with dynamic DB values)
$totalUsers = 2;
$activeToday = 0;
$pendingRegistrations = 1;

// Example activity log (replace with DB query)
$activityLogs = [
    ['action' => 'Login', 'detail' => 'User logged in', 'time' => '10:56 AM'],
    ['action' => 'Logout', 'detail' => 'User logged out', 'time' => '10:42 AM'],
    ['action' => 'User Created', 'detail' => 'Created user: TEACHER01', 'time' => '10:36 AM'],
];
?>

<div class="main-body">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon emerald">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-details">
                <p>Total Users</p>
                <div class="stat-value emerald"><?= $totalUsers ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div class="stat-details">
                <p>Active Today</p>
                <div class="stat-value"><?= $activeToday ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div class="stat-details">
                <p>Pending Registration</p>
                <div class="stat-value red"><?= $pendingRegistrations ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Recent Activity</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activityLogs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['detail']) ?></td>
                            <td><?= htmlspecialchars($log['time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>