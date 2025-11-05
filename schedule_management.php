<?php
require_once 'config.php';
requireLogin(); // Security check

$db = db();
$error = '';
$success = '';
$users = [];
$selectedUserId = null;
$selectedUser = null;

// --- Role-Specific Logic: Determine which user we are managing ---
if (isAdmin()) {
    $users = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
    // Admins can select a user. '' means 'All Users'
    $selectedUserId = $_GET['user_id'] ?? ''; 
} else {
    // Regular users are locked to their own ID
    $selectedUserId = $_SESSION['user_id'];
}
// --- End Role-Specific Logic ---


// ===== Handle POST Actions (Add/Edit/Delete) =====

// Handle Add Multiple Schedules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    
    // NEW: Admins cannot add schedules.
    if (isAdmin()) {
        $error = 'Access Denied. Administrators can manage schedules but not create them.';
    
    } else {
        // User is NOT an admin. They must be adding for themselves.
        $userIdToAdd = (int)$_POST['user_id_add'];
        
        if ($userIdToAdd !== $_SESSION['user_id']) {
            $error = 'Access Denied. You can only add schedules for your own account.';
        } else {
            // User is adding for themselves. Proceed.
            $days = $_POST['day_of_week'] ?? [];
            $subjects = $_POST['subject'] ?? [];
            $startTimes = $_POST['start_time'] ?? [];
            $endTimes = $_POST['end_time'] ?? [];
            $rooms = $_POST['room'] ?? [];
            $addedCount = 0;
            $skippedCount = 0;
            
            $stmt = $db->prepare("INSERT INTO class_schedules (user_id, day_of_week, subject, start_time, end_time, room) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($days as $index => $day) {
                $subject = clean($subjects[$index]);
                $startTime = clean($startTimes[$index]);
                $endTime = clean($endTimes[$index]);
                $room = clean($rooms[$index]);
                $day = clean($day);

                if (empty($subject) || empty($startTime) || empty($endTime) || empty($day)) {
                    $skippedCount++;
                    continue; 
                }
                $stmt->bind_param("isssss", $userIdToAdd, $day, $subject, $startTime, $endTime, $room);
                $stmt->execute();
                $addedCount++;
            }

            if ($addedCount > 0) {
                logActivity($_SESSION['user_id'], 'Schedule Added', "Added $addedCount schedule(s) for user ID: $userIdToAdd");
                $success = "Successfully added $addedCount new schedule(s)!";
                if ($skippedCount > 0) {
                    $success .= " (Skipped $skippedCount empty rows)";
                }
            } else {
                $error = 'No schedules were added. Please ensure all fields are filled out.';
            }
        }
    }
}

// Handle Edit Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $userIdToEdit = (int)$_POST['user_id_edit']; 

    if (!isAdmin() && $userIdToEdit !== $_SESSION['user_id']) {
        $error = 'Access Denied. You can only edit your own schedules.';
    } else {
        $dayOfWeek = clean($_POST['day_of_week']);
        $subject = clean($_POST['subject']);
        $startTime = clean($_POST['start_time']);
        $endTime = clean($_POST['end_time']);
        $room = clean($_POST['room']);
        $stmt = $db->prepare("UPDATE class_schedules SET day_of_week=?, subject=?, start_time=?, end_time=?, room=? WHERE id=? AND user_id=?");
        $stmt->bind_param("sssssii", $dayOfWeek, $subject, $startTime, $endTime, $room, $scheduleId, $userIdToEdit);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Schedule Updated', "Updated schedule ID: $scheduleId for user ID: $userIdToEdit");
            $success = 'Schedule updated successfully!';
        } else {
            $error = 'Failed to update schedule';
        }
    }
}

// Handle Delete Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id_delete'];
    $userIdToDelete = (int)$_POST['user_id_delete'];

    if (!isAdmin() && $userIdToDelete !== $_SESSION['user_id']) {
        $error = 'Access Denied. You can only delete your own schedules.';
    } else {
        $stmt = $db->prepare("DELETE FROM class_schedules WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $scheduleId, $userIdToDelete);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Schedule Deleted', "Deleted schedule ID: $scheduleId for user ID: $userIdToDelete");
            $success = 'Schedule deleted successfully!';
        } else {
            $error = 'Failed to delete schedule.';
        }
    }
}
// ===== End POST Actions =====


// --- Get Filter Parameters (from URL) ---
$filterDayOfWeek = $_GET['day_of_week'] ?? '';
$filterStartDate = $_GET['start_date'] ?? ''; // Not used in query, but kept for UI
$filterEndDate = $_GET['end_date'] ?? '';     // Not used in query, but kept for UI

// --- Build Query ---
$schedules = [];
$params = [];
$types = "";

if (isAdmin()) {
    $query = "SELECT cs.*, u.first_name, u.last_name, u.faculty_id 
              FROM class_schedules cs 
              JOIN users u ON cs.user_id = u.id";
    $conditions = [];

    if ($selectedUserId) { // Admin is filtering by a specific user
        $conditions[] = "cs.user_id = ?";
        $params[] = $selectedUserId;
        $types .= "i";
    }
    if ($filterDayOfWeek) {
        $conditions[] = "cs.day_of_week = ?";
        $params[] = $filterDayOfWeek;
        $types .= "s";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY u.last_name, u.first_name, FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";

} else {
    // Regular user query (locked to their ID)
    $query = "SELECT *, null as first_name, null as last_name, null as faculty_id 
              FROM class_schedules 
              WHERE user_id = ?";
    $params = [$selectedUserId];
    $types = "i";
    
    if ($filterDayOfWeek) {
        $query .= " AND day_of_week = ?";
        $params[] = $filterDayOfWeek;
        $types .= "s";
    }
    $query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";
}

// Execute query
$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // This is where the first part of your error is coming from
    $error = "Database query error: " . $db->error;
}
    
// --- Get Stats ---
// =================================================================
// === START: REVISED STATS BLOCK (More detailed errors) ========
// =================================================================
$weeklyHours = 0;
$totalSchedules = 0;
$totalUsersWithSchedules = 0;

if ($selectedUserId) {
    // Get details for the *specific* user (for both admin filter and regular user)
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id=?");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $selectedUserId);
        if ($stmtUser->execute()) {
            $result = $stmtUser->get_result();
            if ($result) {
                $selectedUser = $result->fetch_assoc();
            } else {
                $error .= " Failed to get user results. DB Error: " . $db->error;
            }
        } else {
            $error .= " Failed to execute user query. DB Error: " . $stmtUser->error;
        }
        $stmtUser->close();
    } else {
        $error .= " Failed to prepare user query. DB Error: " . $db->error;
    }

    // Calculate total weekly hours for this user
    $stmtHours = $db->prepare("SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total FROM class_schedules WHERE user_id=?");
    if ($stmtHours) { // Check if prepare() succeeded
        $stmtHours->bind_param("i", $selectedUserId);
        if ($stmtHours->execute()) { // Check if execute() succeeded
            $result = $stmtHours->get_result();
            if ($result) { // Check if get_result() succeeded
                $row = $result->fetch_assoc();
                $weeklyHours = round($row['total'] ?? 0, 1);
            } else {
                $error .= " Failed to get schedule hour results. DB Error: " . $db->error;
                $weeklyHours = 0; // Default value
            }
        } else {
            $error .= " Failed to execute schedule hour query. DB Error: " . $stmtHours->error;
            $weeklyHours = 0; // Default value
        }
        $stmtHours->close(); // Good practice to close
    } else {
        // This is where the second part of your error comes from
        $error .= " Failed to prepare schedule hour query. DB Error: " . $db->error; 
        $weeklyHours = 0; // Default value
    }

} elseif (isAdmin()) {
    // Admin is in "All Users" view. Get system-wide stats.
    $schedulesResult = $db->query("SELECT COUNT(*) as c FROM class_schedules");
    if ($schedulesResult) {
        $totalSchedules = $schedulesResult->fetch_assoc()['c'];
    } else {
        $totalSchedules = 0; // Default
        $error .= " Failed to get total schedules count. DB Error: " . $db->error;
    }
    
    $usersResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM class_schedules");
    if ($usersResult) {
        $totalUsersWithSchedules = $usersResult->fetch_assoc()['c'];
    } else {
        $totalUsersWithSchedules = 0; // Default
        $error .= " Failed to get total users with schedules count. DB Error: " . $db->error;
    }
}
// =================================================================
// === END: REVISED STATS BLOCK ====================================
// =================================================================
// --- End Stats ---


$pageTitle = 'Schedule Management';
$pageSubtitle = isAdmin() ? 'Manage class schedules and working hours' : 'Manage your class schedule';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="stats-grid schedule-stats-grid">
        <?php if ($selectedUser): // Specific User view (Admin filter or Regular User) ?>
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Viewing Schedule For</p>
                    <div class="stat-value-name">
                        <?= htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']) ?>
                    </div>
                    <p class="stat-value-subtext"><?= htmlspecialchars($selectedUser['faculty_id']) ?></p>
                </div>
            </div>

            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
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
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Classes (Filtered)</p>
                    <div class="stat-value emerald"><?= count($schedules) ?></div>
                </div>
            </div>

        <?php elseif (isAdmin()): // Admin "All Users" view ?>
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Viewing</p>
                    <div class="stat-value-name">All Users</div>
                </div>
            </div>
            
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Schedules (System)</p>
                    <div class="stat-value emerald"><?= $totalSchedules ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Users with Schedules</p>
                    <div class="stat-value emerald"><?= $totalUsersWithSchedules ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" id="schedule-card">
        <div class="card-header card-header-flex">
            <div>
                <h3>Manage Schedules</h3>
                <p>Filter schedules to view, edit, or add new ones</p>
            </div>
            
            <div class="card-header-actions">
                <button class="btn btn-secondary" id="toggleManageBtn">
                    <i class="fa-solid fa-pen-to-square"></i> Manage Schedules
                </button>
                
                <?php if (!isAdmin()): // ONLY show "Add" button to non-admins ?>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> Add New Schedule(s)
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="schedule-filter-form">
                 <div class="schedule-filter-grid">
                    
                    <?php if (isAdmin()): // Show User Filter only to Admins ?>
                    <div class="form-group">
                        <label>Select User</label>
                        <select name="user_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- All Users --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Day of Week</label>
                        <select name="day_of_week" class="form-control" onchange="this.form.submit()">
                            <option value="">All Days</option>
                            <option value="Monday" <?= $filterDayOfWeek == 'Monday' ? 'selected' : '' ?>>Monday</option>
                            <option value="Tuesday" <?= $filterDayOfWeek == 'Tuesday' ? 'selected' : '' ?>>Tuesday</option>
                            <option value="Wednesday" <?= $filterDayOfWeek == 'Wednesday' ? 'selected' : '' ?>>Wednesday</option>
                            <option value="Thursday" <?= $filterDayOfWeek == 'Thursday' ? 'selected' : '' ?>>Thursday</option>
                            <option value="Friday" <?= $filterDayOfWeek == 'Friday' ? 'selected' : '' ?>>Friday</option>
                            <option value="Saturday" <?= $filterDayOfWeek == 'Saturday' ? 'selected' : '' ?>>Saturday</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date (Optional)</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                    </div>
                     <div class="form-group">
                        <label>End Date (Optional)</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                 </div>
            </form>

            <?php if (empty($schedules)): ?>
                <p class="empty-schedule-message">No schedules found matching the selected filters.</p>
            <?php else: ?>
                <table id="schedule-table"> <thead>
                        <tr>
                            <?php if (isAdmin() && !$selectedUserId): // Show User column in 'All Users' view ?>
                                <th>User</th>
                            <?php endif; ?>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Room</th>
                            <th class="table-actions">Actions</th> </tr>
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
                            <?php if (isAdmin() && !$selectedUserId): // Show User data in 'All Users' view ?>
                                <td>
                                    <div class="table-user-name"><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></div>
                                    <div class="table-user-id"><?= htmlspecialchars($schedule['faculty_id']) ?></div>
                                </td>
                            <?php endif; ?>

                            <td class="table-day-highlight"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                            <td><?= htmlspecialchars($schedule['subject']) ?></td>
                            <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                            <td><?= number_format($hours, 1) ?>h</td>
                            <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                            <td class="table-actions"> <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                    <?= $schedule['id'] ?>,
                                    <?= $schedule['user_id'] ?>,
                                    '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                    '<?= htmlspecialchars(addslashes($schedule['subject'])) ?>',
                                    '<?= htmlspecialchars($schedule['start_time']) ?>',
                                    '<?= htmlspecialchars($schedule['end_time']) ?>',
                                    '<?= htmlspecialchars(addslashes($schedule['room'] ?? '')) ?>'
                                )">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $schedule['id'] ?>, <?= $schedule['user_id'] ?>, '<?= htmlspecialchars(addslashes($schedule['subject'])) ?>', '<?= htmlspecialchars($schedule['day_of_week']) ?>')">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="addScheduleModal" class="modal">
    <div class="modal-content modal-lg">
        <form method="POST">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus"></i> Add New Schedule(s)</h3>
                <button type="button" class="modal-close" onclick="closeModal('addScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="user_id_add" id="addScheduleUserId" value="<?= $selectedUserId ?>"> 
                
                <div id="schedule-entry-list">
                    </div>

                <button type="button" class="btn btn-secondary mt-1" onclick="addScheduleRow()">
                    <i class="fa-solid fa-plus"></i> Add Another Row
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addScheduleModal')">Cancel</button>
                <button type="submit" name="add_schedule" class="btn btn-primary">Save All Schedules</button>
            </div>
        </form>
    </div>
</div>

<div id="editScheduleModal" class="modal">
    <div class="modal-content modal-small">
        <form method="POST">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Schedule</h3>
                <button type="button" class="modal-close" onclick="closeModal('editScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="schedule_id" id="editScheduleId">
                <input type="hidden" name="user_id_edit" id="editUserId"> 
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Day of Week</label>
                    <select name="day_of_week" id="editDayOfWeek" class="form-control" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value-="Saturday">Saturday</option>
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" name="edit_schedule" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteScheduleModal" class="modal">
    <div class="modal-content modal-small">
        <form method="POST">
            <div class="modal-header modal-header-danger">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirm Delete</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="schedule_id_delete" id="deleteScheduleId">
                <input type="hidden" name="user_id_delete" id="deleteUserId">
                <p class="fs-large">
                    Are you sure you want to permanently delete the following schedule?
                </p>
                <div class="modal-confirm-detail">
                    <strong id="deleteScheduleSubject"></strong>
                    <span id="deleteScheduleDay"></span>
                </div>
                <p class="fs-small text-danger mt-1">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteScheduleModal')">Cancel</button>
                <button type="submit" name="delete_schedule" class="btn btn-danger">Yes, Delete Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
// --- NEW JAVASCRIPT for Multi-Add Modal ---
const scheduleList = document.getElementById('schedule-entry-list');
const addScheduleUserIdField = document.getElementById('addScheduleUserId');

function createScheduleRowHTML() {
    return `
        <div class="schedule-entry-row">
            <div class="form-group form-group-day">
                <label>Day</label>
                <select name="day_of_week[]" class="form-control" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            <div class="form-group form-group-subject">
                <label>Subject</label>
                <input type="text" name="subject[]" class="form-control" placeholder="e.g., IT 101" required>
            </div>
            <div class="form-group form-group-time">
                <label>Start Time</label>
                <input type="time" name="start_time[]" class="form-control" required>
            </div>
            <div class="form-group form-group-time">
                <label>End Time</label>
                <input type="time" name="end_time[]" class="form-control" required>
            </div>
            <div class="form-group form-group-room">
                <label>Room</label>
                <input type="text" name="room[]" class="form-control" placeholder="e.g., Room 101">
            </div>
            <button type="button" class="btn btn-danger" onclick="removeScheduleRow(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
}

function addScheduleRow() {
    if (scheduleList) {
        scheduleList.insertAdjacentHTML('beforeend', createScheduleRowHTML());
    }
}

function removeScheduleRow(button) {
    button.closest('.schedule-entry-row').remove();
    if (scheduleList && scheduleList.childElementCount === 0) {
        addScheduleRow();
    }
}

function openAddModal() {
    if (scheduleList) {
        scheduleList.innerHTML = '';
        addScheduleRow();
    }
    openModal('addScheduleModal');
}


// --- Functions for Edit and Delete Modals (from previous step) ---
function openEditModal(id, userId, day, subject, startTime, endTime, room) {
    document.getElementById('editScheduleId').value = id;
    document.getElementById('editUserId').value = userId;
    document.getElementById('editDayOfWeek').value = day;
    document.getElementById('editSubject').value = subject;
    document.getElementById('editStartTime').value = startTime;
    document.getElementById('editEndTime').value = endTime;
    document.getElementById('editRoom').value = room;
    openModal('editScheduleModal');
}

function openDeleteModal(id, userId, subject, day) {
    document.getElementById('deleteScheduleId').value = id;
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteScheduleSubject').textContent = subject;
    document.getElementById('deleteScheduleDay').textContent = day;
    openModal('deleteScheduleModal');
}

// --- NEW: Toggle Manage Mode ---
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleManageBtn');
    const scheduleTable = document.getElementById('schedule-table');

    if (toggleBtn && scheduleTable) {
        toggleBtn.addEventListener('click', function() {
            // Toggle the 'managing' class on the table
            const isManaging = scheduleTable.classList.toggle('managing');
            
            // Toggle button text and icon
            if (isManaging) {
                toggleBtn.innerHTML = '<i class="fa-solid fa-check"></i> Done Managing';
                toggleBtn.classList.add('btn-success'); // Make it green
                toggleBtn.classList.remove('btn-secondary');
            } else {
                toggleBtn.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Manage Schedules';
                toggleBtn.classList.remove('btn-success');
                toggleBtn.classList.add('btn-secondary');
            }
        });
    }

    // Auto-hide alerts
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