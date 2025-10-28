<?php
require_once 'config.php';
requireAdmin();

$db = db();
$error = '';
$success = '';

// Handle Edit Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $userId = (int)$_POST['user_id']; // Make sure user_id is passed correctly, maybe from a hidden field in edit form
    $dayOfWeek = clean($_POST['day_of_week']);
    $subject = clean($_POST['subject']);
    $startTime = clean($_POST['start_time']);
    $endTime = clean($_POST['end_time']);
    $room = clean($_POST['room']);

    // Update the schedule
    $stmt = $db->prepare("UPDATE class_schedules SET day_of_week=?, subject=?, start_time=?, end_time=?, room=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sssssii", $dayOfWeek, $subject, $startTime, $endTime, $room, $scheduleId, $userId);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Schedule Updated', "Updated schedule ID: $scheduleId for user ID: $userId");
        $success = 'Schedule updated successfully!';
    } else {
        $error = 'Failed to update schedule';
    }
}

// Get all users for dropdown
$users = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// --- Get Filter Parameters ---
$selectedUserId = $_GET['user_id'] ?? ($users[0]['id'] ?? 0);
$filterDayOfWeek = $_GET['day_of_week'] ?? '';
// Note: Class schedules don't inherently have dates, only days of the week.
// Adding date range filters might not be directly applicable unless you are
// tracking specific instances or linking schedules to a calendar term.
// For now, I'll add the filter fields but the query won't use date ranges
// as the `class_schedules` table doesn't have a date column.
// If you intended to filter *attendance* based on schedule times + dates,
// that would require joining with the attendance_records table.
// Let's keep the filter fields in the UI for now.
$filterStartDate = $_GET['start_date'] ?? ''; // Example: Get start date filter
$filterEndDate = $_GET['end_date'] ?? '';     // Example: Get end date filter

// --- Build Query ---
$schedules = [];
$weeklyHours = 0;

if ($selectedUserId) {
    // Base query
    $query = "SELECT * FROM class_schedules WHERE user_id=? ";
    $params = [$selectedUserId];
    $types = "i";

    // Add day_of_week filter if selected
    if (!empty($filterDayOfWeek)) {
        $query .= " AND day_of_week = ? ";
        $params[] = $filterDayOfWeek;
        $types .= "s";
    }

    // Add ordering
    $query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";

    // Prepare and execute query
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Database query error: " . $db->error;
    }
    
    // Calculate total weekly hours (unfiltered, based on all schedules for the user)
    $stmtHours = $db->prepare("SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total FROM class_schedules WHERE user_id=?");
    if($stmtHours) {
        $stmtHours->bind_param("i", $selectedUserId);
        $stmtHours->execute();
        $weeklyHours = round($stmtHours->get_result()->fetch_assoc()['total'] ?? 0, 1);
    }
}

// Get selected user details
$selectedUser = null;
if ($selectedUserId) {
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id=?");
    if($stmtUser) {
        $stmtUser->bind_param("i", $selectedUserId);
        $stmtUser->execute();
        $selectedUser = $stmtUser->get_result()->fetch_assoc();
    }
}

$pageTitle = 'Schedule Management';
$pageSubtitle = 'Manage class schedules and working hours';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($selectedUser): ?>
    <div class="stats-grid schedule-stats-grid">
         <div class="stat-card stat-card-small">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Name</p>
                <div class="stat-value-name">
                    <?= htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']) ?>
                </div>
                <p class="stat-value-subtext"><?= htmlspecialchars($selectedUser['faculty_id']) ?></p>
            </div>
        </div>

        <div class="stat-card stat-card-small">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Total Weekly Hours</p>
                <div class="stat-value emerald"><?= $weeklyHours ?>h</div>
            </div>
        </div>

        <div class="stat-card stat-card-small">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Total Classes (Filtered)</p>
                <div class="stat-value emerald"><?= count($schedules) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Manage User Schedules</h3>
            <p>Select a user and filter schedules to view and edit</p>
        </div>
        <div class="card-body">
            <form method="GET" style="margin-bottom: 2rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 2rem;">
                 <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label>Select User</label>
                        <select name="user_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label>Day of Week</label>
                        <select name="day_of_week" class="form-control">
                            <option value="">All Days</option>
                            <option value="Monday" <?= $filterDayOfWeek == 'Monday' ? 'selected' : '' ?>>Monday</option>
                            <option value="Tuesday" <?= $filterDayOfWeek == 'Tuesday' ? 'selected' : '' ?>>Tuesday</option>
                            <option value="Wednesday" <?= $filterDayOfWeek == 'Wednesday' ? 'selected' : '' ?>>Wednesday</option>
                            <option value="Thursday" <?= $filterDayOfWeek == 'Thursday' ? 'selected' : '' ?>>Thursday</option>
                            <option value="Friday" <?= $filterDayOfWeek == 'Friday' ? 'selected' : '' ?>>Friday</option>
                            <option value="Saturday" <?= $filterDayOfWeek == 'Saturday' ? 'selected' : '' ?>>Saturday</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label>Start Date (Optional)</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                    </div>
                     <div class="form-group" style="margin: 0;">
                        <label>End Date (Optional)</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                 </div>
            </form>

            <?php if (empty($schedules)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No schedules found matching the selected filters.</p>
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
                            <td style="font-weight: 600;"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                            <td><?= htmlspecialchars($schedule['subject']) ?></td>
                            <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                            <td><?= number_format($hours, 1) ?>h</td>
                            <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                    <?= $schedule['id'] ?>,
                                    '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                    '<?= htmlspecialchars(addslashes($schedule['subject'])) ?>',
                                    '<?= htmlspecialchars($schedule['start_time']) ?>',
                                    '<?= htmlspecialchars($schedule['end_time']) ?>',
                                    '<?= htmlspecialchars(addslashes($schedule['room'] ?? '')) ?>'
                                )">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 40px;">
                <p>Please select a user to manage their schedule.</p>
                <form method="GET" style="margin-top: 1rem;">
                    <div class="form-group" style="margin: 0 auto; max-width: 400px;">
                        <label>Select User</label>
                        <select name="user_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Select a User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="editScheduleModal" class="modal">
    <div class="modal-content modal-small">
        <form method="POST">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Schedule</h3>
                <button type="button" class="modal-close" onclick="closeEditModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="schedule_id" id="editScheduleId">
                <input type="hidden" name="user_id" value="<?= $selectedUserId ?>"> 
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Day of Week</label>
                    <select name="day_of_week" id="editDayOfWeek" class="form-control" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Subject/Course</label>
                    <input type="text" name="subject" id="editSubject" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="editStartTime" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="editEndTime" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" id="editRoom" class="form-control" placeholder="e.g., Room 101">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="edit_schedule" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, day, subject, startTime, endTime, room) {
    document.getElementById('editScheduleId').value = id;
    document.getElementById('editDayOfWeek').value = day;
    document.getElementById('editSubject').value = subject;
    document.getElementById('editStartTime').value = startTime;
    document.getElementById('editEndTime').value = endTime;
    document.getElementById('editRoom').value = room;
    document.getElementById('editScheduleModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editScheduleModal').style.display = 'none';
}

// Close modal if clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('editScheduleModal');
    if (event.target === modal) {
        closeEditModal();
    }
};

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

</script>

<?php include 'includes/footer.php'; ?>