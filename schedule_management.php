<?php
require_once 'config.php';
requireAdmin();

$db = db();
$error = '';
$success = '';

// Handle Add Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $userId = (int)$_POST['user_id'];
    $dayOfWeek = clean($_POST['day_of_week']);
    $subject = clean($_POST['subject']);
    $startTime = clean($_POST['start_time']);
    $endTime = clean($_POST['end_time']);
    $room = clean($_POST['room']);
    
    $stmt = $db->prepare("INSERT INTO class_schedules (user_id, day_of_week, subject, start_time, end_time, room) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $dayOfWeek, $subject, $startTime, $endTime, $room);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Schedule Added', "Added schedule for user ID: $userId");
        $success = 'Schedule added successfully!';
    } else {
        $error = 'Failed to add schedule';
    }
}

// Handle Delete Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $stmt = $db->prepare("DELETE FROM class_schedules WHERE id=?");
    $stmt->bind_param("i", $scheduleId);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Schedule Deleted', "Deleted schedule ID: $scheduleId");
        $success = 'Schedule deleted successfully!';
    }
}

// Get all users for dropdown
$users = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get selected user's schedules
$selectedUserId = $_GET['user_id'] ?? ($users[0]['id'] ?? 0);
$schedules = [];
$weeklyHours = 0;

if ($selectedUserId) {
    $stmt = $db->prepare("SELECT * FROM class_schedules WHERE user_id=? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total weekly hours
    $stmt = $db->prepare("SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total FROM class_schedules WHERE user_id=?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $weeklyHours = round($stmt->get_result()->fetch_assoc()['total'] ?? 0, 1);
}

// Get selected user details
$selectedUser = null;
if ($selectedUserId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $selectedUser = $stmt->get_result()->fetch_assoc();
}

$pageTitle = 'Schedule Management';
$pageSubtitle = 'Manage class schedules and working hours';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- User Selection -->
    <div class="card">
        <div class="card-header">
            <h3>Select User</h3>
            <p>Choose a user to view and manage their schedule</p>
        </div>
        <div class="card-body">
            <form method="GET" style="display: flex; gap: 16px; align-items: end;">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label>Select User</label>
                    <select name="user_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                <?= $user['first_name'] . ' ' . $user['last_name'] ?> (<?= $user['faculty_id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedUser): ?>
    <!-- User Info & Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
        <div class="stat-card">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Name</p>
                <div style="font-size: 18px; font-weight: 600; color: var(--emerald-700);">
                    <?= $selectedUser['first_name'] . ' ' . $selectedUser['last_name'] ?>
                </div>
                <p style="font-size: 12px; color: var(--gray-600);"><?= $selectedUser['faculty_id'] ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Weekly Hours</p>
                <div class="stat-value emerald"><?= $weeklyHours ?>h</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Total Classes</p>
                <div class="stat-value emerald"><?= count($schedules) ?></div>
            </div>
        </div>
    </div>

    <!-- Add Schedule -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Schedule</h3>
            <p>Add a new class schedule for this user</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div class="form-group" style="margin: 0;">
                        <label>Day of Week</label>
                        <select name="day_of_week" class="form-control" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Subject/Course</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Room</label>
                        <input type="text" name="room" class="form-control" placeholder="e.g., Room 101">
                    </div>
                    
                    <div style="display: flex; align-items: end;">
                        <button type="submit" name="add_schedule" class="btn btn-primary" style="width: 100%;">Add Schedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Table -->
    <div class="card">
        <div class="card-header">
            <h3>Current Schedule</h3>
            <p>All scheduled classes for this user</p>
        </div>
        <div class="card-body">
            <?php if (empty($schedules)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No schedules found</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Room</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                        <?php 
                            $start = new DateTime($schedule['start_time']);
                            $end = new DateTime($schedule['end_time']);
                            $duration = $start->diff($end);
                            $hours = $duration->h + ($duration->i / 60);
                        ?>
                        <tr>
                            <td style="font-weight: 600;"><?= $schedule['day_of_week'] ?></td>
                            <td><?= htmlspecialchars($schedule['subject']) ?></td>
                            <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                            <td><?= number_format($hours, 1) ?>h</td>
                            <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                    <button type="submit" name="delete_schedule" class="btn-icon" onclick="return confirm('Delete this schedule?')" style="color: var(--red-600);">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="js/main.js"></script>
<?php include 'includes/footer.php'; ?>
