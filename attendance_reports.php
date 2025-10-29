<?php
require_once 'config.php';
requireAdmin(); // Use requireAdmin to ensure only admins can access

$db = db();
$error = '';
$success = '';

// --- Get Filter Parameters ---
$filterSearch = $_GET['search'] ?? ''; // New search filter
$filterStartDate = $_GET['start_date'] ?? date('Y-m-d'); // Default to today for initial view
$filterEndDate = $_GET['end_date'] ?? date('Y-m-d');   // Default to today for initial view
// Placeholders for filters shown in the image but not yet implemented in DB/logic
$filterDepartment = $_GET['department'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';

// --- Calculate Today's Stats ---
$today = date('Y-m-d');
$entriesTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
$entriesToday = $entriesTodayResult ? $entriesTodayResult->fetch_assoc()['c'] : 0;

$exitsTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_out IS NOT NULL");
$exitsToday = $exitsTodayResult ? $exitsTodayResult->fetch_assoc()['c'] : 0;

$presentTodayResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
$presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;

// --- Build Query for Table Data ---
$query = "
    SELECT ar.*, u.faculty_id, u.first_name, u.last_name, u.role
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

// Apply Date Range Filter
if ($filterStartDate && $filterEndDate) {
    $query .= " AND ar.date BETWEEN ? AND ?";
    $params[] = $filterStartDate;
    $params[] = $filterEndDate;
    $types .= "ss";
} elseif ($filterStartDate) { // Handle single date selection if end date is empty
    $query .= " AND ar.date = ?";
    $params[] = $filterStartDate;
    $types .= "s";
}

// Apply Search Filter (searching name, faculty_id, email)
if (!empty($filterSearch)) {
    $searchTerm = "%" . $filterSearch . "%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ? OR u.email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

// Add Ordering
$query .= " ORDER BY ar.date DESC, ar.time_in ASC"; // Order by date then time_in

// Prepare and Execute Query
$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Database query error: " . $db->error;
    $records = [];
}

$totalRecords = count($records); // Count based on filtered results for the table

// Set Page Variables
$pageTitle = 'Attendance Reports';
$pageSubtitle = 'View and manage all user attendance records';
include 'includes/header.php';
?>

<div class="main-body attendance-reports-page"> <div class="report-header">
        <div>
            </div>
        <button class="btn history-btn">
            <i class="fa-solid fa-clock-rotate-left"></i> History
        </button>
    </div>

    <div class="report-stats-grid">
        <div class="report-stat-card">
            <div class="stat-icon-bg bg-emerald-100 text-emerald-600">
                 <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Today's Entries</span>
                <span class="stat-value"><?= $entriesToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-red-100 text-red-600">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Today's Exits</span>
                <span class="stat-value"><?= $exitsToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-blue-100 text-blue-600">
                 <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Users Present</span>
                <span class="stat-value"><?= $presentToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-gray-100 text-gray-600">
                 <i class="fa-solid fa-list-alt"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Total Records</span>
                <span class="stat-value"><?= $totalRecords ?></span> </div>
        </div>
    </div>

    <div class="filter-export-section card">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter and export attendance records for all users</p>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-controls">
                <div class="form-group filter-item">
                    <label for="searchFilter">Search</label>
                    <div class="search-wrapper">
                        <i class="fa-solid fa-search search-icon-filter"></i>
                        <input type="text" id="searchFilter" name="search" class="form-control search-input-filter" placeholder="Search users..." value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                </div>
                <div class="form-group filter-item">
                    <label for="dateRangeFilter">Date Range</label>
                     <div style="display: flex; gap: 0.5rem;">
                         <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                         <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                     </div>
                </div>
                <div class="form-group filter-item">
                    <label for="deptFilter">Department</label>
                    <select id="deptFilter" name="department" class="form-control">
                        <option value="all" <?= $filterDepartment == 'all' ? 'selected' : '' ?>>All Departments</option>
                        </select>
                </div>
                    <div class="filter-actions filter-item">
                        <button type="submit" class="btn btn-primary apply-filter-btn">Apply Filters</button>
                            <a href="print_dtr.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&search=<?= urlencode($filterSearch) ?>"
                            class="btn btn-primary download-btn"
                            id="printDtrBtn"
                            target="_blank">
                            <i class="fa-solid fa-download"></i> Download DTR PDF
                            </a>
     
                            <a href="export_attendance.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&search=<?= urlencode($filterSearch) ?>"
                            class="btn btn-danger export-csv-btn">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                            </a>
                    </div>
            </form>
        </div>
    </div>

    <div class="card attendance-table-card">
         <div class="card-body" style="padding: 0;"> <?php if ($error): ?>
                <div class="alert alert-error" style="margin: 1rem;"><?= htmlspecialchars($error) ?></div>
            <?php elseif (empty($records)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No records found matching the selected filters.</p>
            <?php else: ?>
                <table class="attendance-table-new">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Department</th> <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="user-name"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></span>
                                    <span class="user-id"><?= htmlspecialchars($record['faculty_id']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="department-cell">Administration</span> <?php // echo htmlspecialchars($record['department'] ?? 'N/A'); // Use this when department data is available ?>
                            </td>
                            <td>
                                <span class="date-cell"><?= date('m/d/Y', strtotime($record['date'])) ?></span>
                            </td>
                            <td>
                                <?php if ($record['time_in']): ?>
                                    <div class="time-cell time-in">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                        <span><?= date('h:i A', strtotime($record['time_in'])) ?></span>
                                        <span class="status-label"><?= htmlspecialchars($record['status']) // Display status like 'Late' ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="time-cell no-time">-</div>
                                <?php endif; ?>
                            </td>
                             <td>
                                <?php if ($record['time_out']): ?>
                                    <div class="time-cell time-out">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                        <span><?= date('h:i A', strtotime($record['time_out'])) ?></span>
                                         <?php
                                            // Optional: You could add an 'Overtime' or 'Undertime' label here based on comparison with expected end time
                                         ?>
                                    </div>
                                <?php else: ?>
                                    <div class="time-cell no-time">-</div>
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
// Basic script to enable/disable DTR button based on search (user selection is removed)
// You might want more complex logic if DTR should only be for one user
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('printDtrBtn');
    const searchInput = document.getElementById('searchFilter');

    function checkDtrButtonState() {
        if (!printBtn) return; // Exit if button doesn't exist

        // Simple check: If search is empty, maybe disable? Or always enable?
        // For now, let's keep it enabled but update the URL
        const startDate = document.getElementById('dateRangeStartFilter').value;
        const endDate = document.getElementById('dateRangeEndFilter').value;
        const searchVal = searchInput ? searchInput.value : '';

        // Update href with current filter values
        printBtn.setAttribute('href', `print_dtr.php?start_date=${startDate}&end_date=${endDate}&search=${encodeURIComponent(searchVal)}`);

        // Example: Disable if search is empty (adjust logic as needed)
        /*
        if (searchVal.trim() === '') {
            printBtn.classList.add('btn-disabled-custom'); // Add a custom disabled style class
            printBtn.style.pointerEvents = 'none';
        } else {
            printBtn.classList.remove('btn-disabled-custom');
            printBtn.style.pointerEvents = 'auto';
        }
        */
    }

     // Add a simple disabled style (if needed)
     /*
    if (!document.getElementById('btn-disabled-style-custom')) {
        const style = document.createElement('style');
        style.id = 'btn-disabled-style-custom';
        style.innerHTML = `.btn-disabled-custom { opacity: 0.6; cursor: not-allowed; }`;
        document.head.appendChild(style);
    }
    */

    // Check state on page load
    checkDtrButtonState();

    // Add listeners to update button state when filters change
    const filterInputs = document.querySelectorAll('.filter-controls input, .filter-controls select');
    filterInputs.forEach(input => {
        input.addEventListener('change', checkDtrButtonState);
        if (input.type === 'text') {
            input.addEventListener('input', checkDtrButtonState); // Update as user types in search
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>