<?php
require_once 'config.php';
requireAdmin();

$db = db();
$error = '';
$success = '';

// Handle date filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';

// Get all users for filter
$users = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Build query
$query = "
    SELECT ar.*, u.faculty_id, u.first_name, u.last_name, u.role
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE ar.date BETWEEN ? AND ?
";

$params = [$startDate, $endDate];
$types = "ss";

if ($userId) {
    $query .= " AND ar.user_id = ?";
    $params[] = $userId;
    $types .= "i";
}

$query .= " ORDER BY ar.date DESC, u.first_name";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalRecords = count($records);
$presentCount = count(array_filter($records, fn($r) => $r['status'] === 'Present'));
$lateCount = count(array_filter($records, fn($r) => $r['status'] === 'Late'));
$absentCount = count(array_filter($records, fn($r) => $r['status'] === 'Absent'));

$pageTitle = 'Attendance Reports';
$pageSubtitle = 'View and export attendance records';
include 'includes/header.php';
?>

<div class="main-body">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Total Records</p>
                <div class="stat-value emerald"><?= $totalRecords ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Present</p>
                <div class="stat-value emerald"><?= $presentCount ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Late</p>
                <div class="stat-value" style="color: #d97706;"><?= $lateCount ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div class="stat-details">
                <p>Absent</p>
                <div class="stat-value red"><?= $absentCount ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Filter Attendance Records</h3>
            <p>Select date range and user to filter records</p>
        </div>
        <div class="card-body">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label>User (Optional)</label>
                    <select name="user_id" id="userFilterDropdown" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $userId == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Filter</button>

                <a href="print_dtr.php?start_date=<?= $startDate ?>&user_id=<?= $userId ?>"
                   class="btn btn-secondary"
                   id="printDtrBtn"
                   target="_blank">
                    <i class="fa-solid fa-print" style="width: 16px; height: 16px;"></i>
                    <span>Print DTR</span>
                </a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Attendance Records</h3>
            <p>Showing <?= $totalRecords ?> record(s)</p>
        </div>
        <div class="card-body">
            <?php if (empty($records)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No records found matching the selected filters.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Faculty ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= date('m/d/Y', strtotime($record['date'])) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($record['faculty_id']) ?></td>
                            <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                            <td><?= htmlspecialchars($record['role']) ?></td>
                            <td><?= $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : '-' ?></td>
                            <td><?= $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : '-' ?></td>
                            <td><?= htmlspecialchars($record['working_hours'] ?? '-') ?></td>
                            <td>
                                <?php if ($record['status'] === 'Present'): ?>
                                    <span style="background: var(--emerald-100); color: var(--emerald-700); padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Present</span>
                                <?php elseif ($record['status'] === 'Late'): ?>
                                    <span style="background: var(--yellow-100); color: #92400e; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Late</span>
                                <?php else: ?>
                                    <span style="background: var(--red-50); color: var(--red-600); padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.getElementById('userFilterDropdown');
    const printBtn = document.getElementById('printDtrBtn');
    const printBtnText = printBtn ? printBtn.querySelector('span') : null; // Check if printBtn exists
    const startDateInput = document.querySelector('input[name="start_date"]');

    function checkDtrButtonState() {
        // Ensure all elements exist before proceeding
        if (!userDropdown || !printBtn || !printBtnText || !startDateInput) return;

        const selectedUserId = userDropdown.value;
        const startDate = startDateInput.value;

        if (selectedUserId) {
            // User is selected
            printBtn.classList.remove('btn-disabled'); // Re-enable style
            printBtn.setAttribute('href', `print_dtr.php?start_date=${startDate}&user_id=${selectedUserId}`);
            printBtnText.textContent = 'Print DTR';
            printBtn.style.opacity = '1';
            printBtn.style.pointerEvents = 'auto'; // Make clickable
        } else {
            // "All Users" is selected
            printBtn.classList.add('btn-disabled'); // Disable style
            printBtn.removeAttribute('href'); // Remove link to prevent click
            printBtnText.textContent = 'Select a user to print';
            printBtn.style.opacity = '0.6';
            printBtn.style.pointerEvents = 'none'; // Make unclickable
        }
    }

    // Add a simple disabled style only once
    if (!document.getElementById('btn-disabled-style')) {
        const style = document.createElement('style');
        style.id = 'btn-disabled-style';
        style.innerHTML = `.btn-disabled { background-color: var(--gray-200) !important; color: var(--gray-500) !important; cursor: not-allowed; border-color: var(--gray-300) !important; }`;
        document.head.appendChild(style);
    }

    // Check state on page load
    checkDtrButtonState();

    // Add event listeners only if elements exist
    if (userDropdown) {
        userDropdown.addEventListener('change', checkDtrButtonState);
    }
    if (startDateInput) {
        startDateInput.addEventListener('change', checkDtrButtonState);
    }
});
</script>
<?php include 'includes/footer.php'; ?>