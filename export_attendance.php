<?php
// export_attendance.php - Export attendance to CSV
require_once 'config.php';
// *** MODIFIED: Allow all logged-in users ***
requireLogin();

$db = db();

// Get filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';

// *** MODIFIED: Force user_id if not admin ***
if (!isAdmin()) {
    $userId = $_SESSION['user_id'];
}

// Build query
$query = "
    SELECT ar.date, u.faculty_id, u.first_name, u.last_name, u.role,
           ar.time_in, ar.time_out, ar.working_hours, ar.status, ar.remarks
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

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Date',
    'Faculty ID',
    'First Name',
    'Last Name',
    'Role',
    'Time In',
    'Time Out',
    'Working Hours',
    'Status',
    'Remarks'
]);

// Write data rows
foreach ($records as $record) {
    fputcsv($output, [
        date('m/d/Y', strtotime($record['date'])),
        $record['faculty_id'],
        $record['first_name'],
        $record['last_name'],
        $record['role'],
        $record['time_in'] ?? '-',
        $record['time_out'] ?? '-',
        $record['working_hours'] ?? '-',
        $record['status'],
        $record['remarks'] ?? ''
    ]);
}

fclose($output);
exit;
?>