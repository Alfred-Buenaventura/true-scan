<?php
require_once 'config.php';
requireLogin(); // Ensure user is logged in

$db = db();

// Get parameters from URL
$userId = $_GET['user_id'] ?? 0;
$startDate = $_GET['start_date'] ?? date('Y-m-01');

// Validate User ID
if (empty($userId)) {
    // Check if the logged-in user is NOT an admin and is trying to print their own DTR
    if (!isAdmin()) {
        $userId = $_SESSION['user_id'];
    } else {
        // Show a friendly error page instead of die()
        $pageTitle = 'Error';
        $pageSubtitle = 'Cannot generate DTR';
        include 'includes/header.php';
        echo '<div class="main-body">';
        echo '<div class="alert alert-error" style="background-color: var(--red-50); color: var(--red-600); border: 1px solid var(--red-200); padding: 1.5rem; border-radius: 12px;">';
        echo '<h3 style="font-size: 1.2rem; font-weight: 600; color: var(--red-700); margin-bottom: 0.5rem;">No User Selected</h3>';
        echo '<p style="color: var(--red-600);">As an administrator, you must select a specific user from the dropdown on the reports page before you can print a DTR.</p>';
        echo '</div>';
        echo '<a href="attendance_reports.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fa-solid fa-arrow-left"></i> Back to Reports</a>';
        echo '</div>';
        include 'includes/footer.php';
        exit; // Stop further execution
    }
}

// Get User Details
$user = getUser($userId);
if (!$user) {
    die("User not found.");
}
// Format full name: "DELA CRUZ, JUAN P."
$fullName = strtoupper($user['last_name'] . ', ' . $user['first_name'] . ' ' . ($user['middle_name'] ? substr($user['middle_name'], 0, 1) . '.' : ''));

// Get Month and Year from start date
$monthTimestamp = strtotime($startDate);
$monthName = date('F', $monthTimestamp);
$year = date('Y', $monthTimestamp);
$daysInMonth = date('t', $monthTimestamp);
$monthStartDate = date('Y-m-01', $monthTimestamp);
$monthEndDate = date('Y-m-t', $monthTimestamp);

// Get Attendance Records for the entire month
$stmt = $db->prepare("
    SELECT * FROM attendance_records
    WHERE user_id = ?
    AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$stmt->bind_param("iss", $userId, $monthStartDate, $monthEndDate);
$stmt->execute();
$recordsResult = $stmt->get_result();

// Process records into an associative array for easy lookup
$records = [];
while ($row = $recordsResult->fetch_assoc()) {
    $day = date('j', strtotime($row['date'])); // Day of the month (1-31)
    $records[$day] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR - <?= htmlspecialchars($fullName) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: var(--gray-100);
            font-family: Arial, sans-serif;
            color: #000;
        }

        .dtr-container {
            width: 8.5in;
            min-height: 11in;
            margin: 2rem auto;
            background: #fff;
            padding: 0.75in;
            border: 1px solid #ccc;
            box-shadow: var(--shadow-soft);
            display: flex;
            flex-direction: column;
        }

        .dtr-header {
            text-align: center;
            font-family: 'Times New Roman', Times, serif;
        }
        .dtr-header h3 {
            font-weight: bold;
            font-size: 1.1rem;
            margin: 0;
            text-align: left;
        }
        .dtr-header h2 {
            font-weight: bold;
            font-size: 1.3rem;
            margin: 0.5rem 0;
        }

        .info-table {
            width: 100%;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 0;
        }
        .info-table .label {
            white-space: nowrap;
            padding-right: 10px;
        }
        .info-table .value {
            border-bottom: 1px solid #000;
            width: 100%;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            border: 2px solid #000;
            text-align: center;
        }
        .attendance-table th,
        .attendance-table td {
            border: 1px solid #000;
            padding: 5px;
            height: 25px; /* Fixed height for rows */
        }
        .attendance-table th {
            font-weight: bold;
            font-size: 0.8rem;
        }
        .attendance-table .day-col {
            width: 8%;
        }
        .attendance-table .col-small {
            width: 10%;
        }
        .attendance-table .col-large {
            width: 13%;
        }
        .attendance-table .total-row td {
            font-weight: bold;
            text-align: right;
            padding-right: 1rem;
        }
        .attendance-table .total-row td:first-child {
            text-align: center;
        }
        .attendance-table .time-val {
            font-size: 0.85rem;
        }

        .dtr-footer-content {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
            flex-grow: 1; /* Pushes signature to bottom */
        }

        .signature-block {
            margin-top: 3rem;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 300px;
            margin: 0 auto;
            padding-top: 2rem;
        }
        .signature-label {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: var(--shadow-strong);
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .print-controls .btn {
            display: flex;
            gap: 0.5rem;
            text-decoration: none;
            margin: 0;
        }

        /* Print-specific styles */
        @media print {
            body {
                background: #fff;
                margin: 0;
            }
            .print-controls, .back-link {
                display: none !important;
            }
            .dtr-container {
                width: 100%;
                min-height: 100%;
                margin: 0;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .attendance-table {
                font-size: 0.8rem; /* Shrink table slightly to fit */
            }
            .attendance-table th,
            .attendance-table td {
                padding: 4px;
                height: 23px;
            }
            .attendance-table .time-val {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Print DTR
        </button>
        <button class="btn btn-secondary back-link" onclick="window.close()">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Reports
        </button>
    </div>

    <div style="display: flex; justify-content: center; gap: 2rem;">

        <?php for ($i = 0; $i < 2; $i++): // Loop to create two identical forms ?>
        <div class="dtr-container">
            <div class="dtr-header">
                <h3>CS Form 48</h3>
                <h2>DAILY TIME RECORD</h2>
            </div>

            <table class="info-table">
                <tr>
                    <td class="label">Name</td>
                    <td class="value" style="text-align: center; font-weight: bold; font-size: 1rem;"><?= htmlspecialchars($fullName) ?></td>
                </tr>
                <tr>
                    <td class="label">For the month of</td>
                    <td class="value"><?= htmlspecialchars($monthName . " " . $year) ?></td>
                </tr>
                <tr>
                    <td class="label">Office Hours (regular days)</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">Arrival & Departure</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">Saturdays</td>
                    <td class="value"></td>
                </tr>
            </table>

            <table class="attendance-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="day-col">Day</th>
                        <th colspan="2">A.M.</th>
                        <th colspan="2">P.M.</th>
                        <th colspan="2">Hours</th>
                    </tr>
                    <tr>
                        <th class="col-small">Arrival</th>
                        <th class="col-small">Departure</th>
                        <th class="col-small">Arrival</th>
                        <th class="col-small">Departure</th>
                        <th class="col-large">Hours</th>
                        <th class="col-large">Min.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalHours = 0;
                    $totalMinutes = 0;
                    for ($day = 1; $day <= 31; $day++):
                        $am_in = '';
                        $am_out = '';
                        $pm_in = '';
                        $pm_out = '';
                        $day_hours = '';
                        $day_minutes = '';

                        if ($day <= $daysInMonth && isset($records[$day])) {
                            $rec = $records[$day];

                            // This is a simplified logic. We assume time_in is AM Arrival
                            // and time_out is PM Departure, as your database doesn't
                            // store the 4 separate time points.

                            if ($rec['time_in']) {
                                $am_in = date('g:i', strtotime($rec['time_in']));
                            }
                            if ($rec['time_out']) {
                                $pm_out = date('g:i', strtotime($rec['time_out']));
                            }

                            // Calculate total hours/minutes for the day
                            if ($rec['working_hours']) {
                                $wh = floatval($rec['working_hours']);
                                $day_hours = floor($wh);
                                $day_minutes = round(($wh - $day_hours) * 60);

                                $totalHours += $day_hours;
                                $totalMinutes += $day_minutes;
                            }
                        } elseif ($day > $daysInMonth) {
                            // Day is outside the current month (e.g., 31st in Feb)
                            // We can fill this to show it's not applicable.
                            $am_in = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $am_out = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $pm_in = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $pm_out = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                        }
                    ?>
                    <tr>
                        <td><?= $day ?></td>
                        <td class="time-val"><?= $am_in ?></td>
                        <td class="time-val"><?= $am_out ?></td>
                        <td class="time-val"><?= $pm_in ?></td>
                        <td class="time-val"><?= $pm_out ?></td>
                        <td><?= $day_hours ?></td>
                        <td><?= $day_minutes ?></td>
                    </tr>
                    <?php endfor; ?>

                    <?php
                    // Consolidate minutes to hours for the total
                    $totalHours += floor($totalMinutes / 60);
                    $totalMinutes = $totalMinutes % 60;
                    ?>
                    <tr class="total-row">
                        <td colspan="5">Total</td>
                        <td><?= $totalHours > 0 ? $totalHours : '' ?></td>
                        <td><?= $totalMinutes > 0 ? $totalMinutes : '' ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="dtr-footer-content">
                I certify on my honor that the above is true and correct record of the hours of work performed, record of which was made daily at the time of arrival and departure from the office.
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">(Signature)</div>
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">(In-charge)</div>
            </div>

        </div>
        <?php endfor; ?>
    </div>

</body>
</html>