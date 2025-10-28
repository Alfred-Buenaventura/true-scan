<?php
require_once 'config.php';

// --- Page Variables ---
$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, System Administrator!';
// --- End Page Variables ---

require_once 'includes/header.php'; // Include header first

$db = db(); // Get database connection

// --- Fetch Dashboard Data ---

// Example data (replace with dynamic DB values if needed later)
// Fetch counts directly from the DB
$totalUsersResult = $db->query("SELECT COUNT(*) as c FROM users WHERE status='active'");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_assoc()['c'] : 0;

// Placeholder for Active Today - Requires logic based on attendance_records for the current date
$activeToday = 0; 

$pendingRegResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=0");
$pendingRegistrations = $pendingRegResult ? $pendingRegResult->fetch_assoc()['c'] : 0;


// Fetch the latest 10 activity logs
$activityLogs = [];
$logQuery = "
    SELECT al.*, u.first_name, u.last_name 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 5 
";
$logResult = $db->query($logQuery);
if ($logResult) {
    $activityLogs = $logResult->fetch_all(MYSQLI_ASSOC);
}
// --- End Fetch Dashboard Data ---

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
            <?php if (empty($activityLogs)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 2rem;">No recent activity found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>User</th>
                            <th>Time & Date</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['description']) // Use 'description' field ?></td>
                                <td><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? 'System')) ?></td> 
                                <td><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td> 
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
             <div style="text-align: right; margin-top: 1rem;">
                 <a href="activity_log.php" class="btn btn-sm btn-secondary">View All Activity &rarr;</a>
             </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>