<?php
require_once 'config.php';
requireLogin(); // Use requireLogin() instead of config.php

// --- Page Variables ---
$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($_SESSION['first_name'] ?? 'User') . '!';
// --- End Page Variables ---

require_once 'includes/header.php'; // Include header first

$db = db(); // Get database connection

// --- Fetch Dashboard Data ---
$activityLogs = [];

if (isAdmin()) {
    // --- Admin Data ---
    $pageSubtitle = 'Welcome back, System Administrator!';

    $totalUsersResult = $db->query("SELECT COUNT(*) as c FROM users WHERE status='active'");
    $totalUsers = $totalUsersResult ? $totalUsersResult->fetch_assoc()['c'] : 0;

    // Placeholder for Active Today
    $activeToday = 0;

    $pendingRegResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=0");
    $pendingRegistrations = $pendingRegResult ? $pendingRegResult->fetch_assoc()['c'] : 0;

    // Fetch the latest 5 activity logs (all users)
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
                                    <td><?= htmlspecialchars($log['description']) ?></td>
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

<?php
} else {
    // --- Regular User Data ---

    // 1. Get User Registration Status
    $stmtUser = $db->prepare("SELECT fingerprint_registered FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $_SESSION['user_id']);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $fingerprint_registered = $user['fingerprint_registered'] ?? 0;

    // 2. Get Today's Attendance
    $today = date('Y-m-d');
    $stmtAtt = $db->prepare("SELECT * FROM attendance_records WHERE user_id = ? AND date = ?");
    $stmtAtt->bind_param("is", $_SESSION['user_id'], $today);
    $stmtAtt->execute();
    $attendance = $stmtAtt->get_result()->fetch_assoc(); // Use fetch_assoc() as there's only one record per day

    // 3. Get Recent Activity
    $logQuery = "
        SELECT al.*
        FROM activity_logs al
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 5
    ";
    $stmtLogs = $db->prepare($logQuery);
    $stmtLogs->bind_param("i", $_SESSION['user_id']);
    $stmtLogs->execute();
    $logResult = $stmtLogs->get_result();

    if ($logResult) {
        $activityLogs = $logResult->fetch_all(MYSQLI_ASSOC);
    }
?>
    <div class="main-body user-dashboard-body"> <div class="ud-grid"> <div class="ud-card"> <h3 class="ud-card-title">Registration Status</h3> <div class="ud-card-content"> <div class="ud-card-row"> <span class="ud-card-label">Account Created</span> <span class="ud-badge completed">Completed</span> </div>
                    <div class="ud-card-row"> <span class="ud-card-label">Fingerprint Registered</span> <?php if ($fingerprint_registered): ?>
                            <span class="ud-badge completed">Completed</span> <?php else: ?>
                            <span class="ud-badge pending">Pending</span> <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ud-card"> <h3 class="ud-card-title">Today's Attendance</h3> <div class="ud-card-content"> <div class="ud-card-row"> <span class="ud-card-label">Status</span> <?php
                            // Check if $attendance is not null before accessing keys
                            $status = $attendance['status'] ?? 'Not Present';
                            $statusClass = 'not-present';
                            if ($status === 'Present') $statusClass = 'completed';
                            if ($status === 'Late') $statusClass = 'pending';
                        ?>
                        <span class="ud-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span> </div>
                    <div class="ud-card-row"> <span class="ud-card-label">Time In</span> <span class="ud-card-value"> <?= isset($attendance['time_in']) ? date('g:i A', strtotime($attendance['time_in'])) : '------' ?>
                        </span>
                    </div>
                    <div class="ud-card-row"> <span class="ud-card-label">Time Out</span> <span class="ud-card-value"> <?= isset($attendance['time_out']) ? date('g:i A', strtotime($attendance['time_out'])) : '------' ?>
                        </span>
                    </div>
                </div>
            </div>

        </div> <div class="ud-card ud-activity-card"> <h3 class="ud-card-title">My Recent Activity</h3> <div class="ud-card-content"> <?php if (empty($activityLogs)): ?>
                    <div class="ud-activity-empty"> <i class="fa-solid fa-chart-line"></i>
                        <span>No activity recorded.</span> </div>
                <?php else: ?>
                    <div class="ud-activity-list"> <?php foreach ($activityLogs as $log): ?>
                            <div class="ud-activity-item"> <div class="ud-activity-details"> <strong class="ud-activity-action"><?= htmlspecialchars($log['action']) ?></strong> <span class="ud-activity-description"><?= htmlspecialchars($log['description']) ?></span> </div>
                                <span class="ud-activity-time"> <?= date('M d, Y g:i A', strtotime($log['created_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
             </div>
        </div>

    </div>
<?php
} // End else (regular user view)

require_once 'includes/footer.php'; // Include footer
?>