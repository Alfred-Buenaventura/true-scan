<?php
require_once 'config.php';
// *** MODIFIED: Allow all logged-in users ***
requireLogin(); 

$db = db();
$error = '';
$success = '';
$currentUserId = $_SESSION['user_id'];

// --- Get Filter Parameters ---
$filterSearch = $_GET['search'] ?? ''; 
$filterStartDate = $_GET['start_date'] ?? date('Y-m-d'); // Default to today
$filterEndDate = $_GET['end_date'] ?? date('Y-m-d');   // Default to today
$filterDepartment = $_GET['department'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';

// --- Page-specific variables based on role ---
if (isAdmin()) {
    $pageTitle = 'Attendance Reports';
    $pageSubtitle = 'View and manage all user attendance records';
} else {
    $pageTitle = 'My Attendance';
    $pageSubtitle = 'View your personal attendance history';
}

// --- Calculate Today's Stats ---
if (isAdmin()) {
    $today = date('Y-m-d');
    $entriesTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $entriesToday = $entriesTodayResult ? $entriesTodayResult->fetch_assoc()['c'] : 0;

    $exitsTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_out IS NOT NULL");
    $exitsToday = $exitsTodayResult ? $exitsTodayResult->fetch_assoc()['c'] : 0;

    $presentTodayResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;
} else {
    // --- Stats for regular user ---
    $today = date('Y-m-d');
    $stmtToday = $db->prepare("SELECT time_in, time_out FROM attendance_records WHERE date = ? AND user_id = ?");
    $stmtToday->bind_param("si", $today, $currentUserId);
    $stmtToday->execute();
    $todayRecord = $stmtToday->get_result()->fetch_assoc();

    $entriesToday = $todayRecord && $todayRecord['time_in'] ? 1 : 0;
    $exitsToday = $todayRecord && $todayRecord['time_out'] ? 1 : 0;

    // Change "Users Present" to "Total Days Present (All Time)" for the user
    $presentTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE user_id = $currentUserId AND time_in IS NOT NULL");
    $presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;
}

// --- Build Query for Table Data ---
$query = "
    SELECT ar.*, u.faculty_id, u.first_name, u.last_name, u.role
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

// --- MODIFIED: Apply role-based query constraints ---
if (!isAdmin()) {
    // Regular user can ONLY see their own records
    $query .= " AND ar.user_id = ?";
    $params[] = $currentUserId;
    $types .= "i";
} elseif (!empty($filterSearch)) {
    // Admin is searching
    $searchTerm = "%" . $filterSearch . "%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ? OR u.email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}
// --- End modification ---

// Apply Date Range Filter (applies to both admin and user)
if ($filterStartDate && $filterEndDate) {
    $query .= " AND ar.date BETWEEN ? AND ?";
    $params[] = $filterStartDate;
    $params[] = $filterEndDate;
    $types .= "ss";
} elseif ($filterStartDate) { 
    $query .= " AND ar.date = ?";
    $params[] = $filterStartDate;
    $types .= "s";
}

// Add Ordering
$query .= " ORDER BY ar.date DESC, ar.time_in ASC"; 

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

$totalRecords = count($records); // Count based on filtered results

include 'includes/header.php';
?>

<div class="main-body attendance-reports-page"> 
    
    <?php if (isAdmin()): ?>
    <div class="report-header">
        <div></div>
        <button class="btn history-btn">
            <i class="fa-solid fa-clock-rotate-left"></i> History
        </button>
    </div>
    <?php endif; ?>

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
                <span class="stat-label"><?= isAdmin() ? 'Users Present' : 'Total Days Present' ?></span>
                <span class="stat-value"><?= $presentToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-gray-100 text-gray-600">
                 <i class="fa-solid fa-list-alt"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label"><?= isAdmin() ? 'Total Records' : 'My Records (Filtered)' ?></span>
                <span class="stat-value"><?= $totalRecords ?></span> </div>
        </div>
    </div>

    <?php if (isAdmin()): ?>
    <div class="filter-export-section card">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter and export attendance records for all users</p>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-controls-new">
                <div class="filter-inputs">
                    <div class="form-group filter-item">
                        <label for="searchFilter">Search</label>
                        <div class="search-wrapper">
                            <i class="fa-solid fa-search search-icon-filter"></i>
                            <input type="text" id="searchFilter" name="search" class="form-control search-input-filter" placeholder="Search users..." value="<?= htmlspecialchars($filterSearch) ?>">
                        </div>
                    </div>
                    <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
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
                </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="print_dtr.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&search=<?= urlencode($filterSearch) ?>"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn">
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
    
    <?php else: ?>
    <div class="filter-export-section card">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter your attendance records by date and export</p>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-controls-new">
                <div class="filter-inputs" style="grid-template-columns: 1fr;"> <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
                         <div style="display: flex; gap: 0.5rem;">
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                         </div>
                    </div>
                </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="print_dtr.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&user_id=<?= $currentUserId ?>"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn">
                        <i class="fa-solid fa-download"></i> Download DTR PDF
                    </a>
                    <a href="export_attendance.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&user_id=<?= $currentUserId ?>"
                        class="btn btn-danger export-csv-btn">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="card attendance-table-card">
         <div class="card-body" style="padding: 0;"> <?php if ($error): ?>
                <div class="alert alert-error" style="margin: 1rem;"><?= htmlspecialchars($error) ?></div>
            <?php elseif (empty($records)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No records found matching the selected filters.</p>
            <?php else: ?>
                <table class="attendance-table-new">
                    <thead>
                        <tr>
                            <?php if (isAdmin()): ?>
                                <th>User</th>
                                <th>Department</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <?php if (isAdmin()): ?>
                            <td>
                                <div class="user-cell">
                                    <span class="user-name"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></span>
                                    <span class="user-id"><?= htmlspecialchars($record['faculty_id']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="department-cell">Administration</span> <?php // echo htmlspecialchars($record['department'] ?? 'N/A'); ?>
                            </td>
                            <?php endif; ?>
                            
                            <td>
                                <span class="date-cell"><?= date('m/d/Y', strtotime($record['date'])) ?></span>
                            </td>
                            <td>
                                <?php if ($record['time_in']): ?>
                                    <div class="time-cell time-in">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                        <span><?= date('h:i A', strtotime($record['time_in'])) ?></span>
                                        <span class="status-label"><?= htmlspecialchars($record['status']) ?></span>
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
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('printDtrBtn');
    const searchInput = document.getElementById('searchFilter'); // This will be null for non-admins, which is fine.

    function checkDtrButtonState() {
        if (!printBtn) return; 

        const startDate = document.getElementById('dateRangeStartFilter').value;
        const endDate = document.getElementById('dateRangeEndFilter').value;
        
        // Admin URL
        let href = `print_dtr.php?start_date=${startDate}&end_date=${endDate}`;
        
        <?php if (isAdmin()): ?>
            const searchVal = searchInput ? searchInput.value : '';
            href += `&search=${encodeURIComponent(searchVal)}`;
        <?php else: ?>
            // User URL
            href += `&user_id=<?= $currentUserId ?>`;
        <?php endif; ?>
        
        printBtn.setAttribute('href', href);
    }
    
    checkDtrButtonState();

    const filterInputs = document.querySelectorAll('.filter-controls-new input, .filter-controls-new select');
    filterInputs.forEach(input => {
        input.addEventListener('change', checkDtrButtonState);
        if (input.type === 'text') {
            input.addEventListener('input', checkDtrButtonState); 
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>