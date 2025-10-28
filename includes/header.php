<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>BPC Attendance</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>
    <div class="dashboard-container" id="dashboardContainer">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content" id="mainContent">
            <header class="main-header">
                <div class="header-title">
                    <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
                    <p><?= $pageSubtitle ?? 'Welcome back!' ?></p>
                </div>

                <div class="header-actions">

                    <div id="live-time-date">
                        <div id="live-time">--:-- --</div>
                        <div id="live-date">Loading...</div>
                    </div>

                    <div class="header-user-id">
                        <i class="fa-solid fa-id-badge"></i>
                        <span>ID: <?= htmlspecialchars($_SESSION['faculty_id'] ?? 'N/A') ?></span>
                    </div>

                    <?php
                    // UPDATED: Now checks for Admin AND if the page is the Dashboard
                    if (isAdmin() && isset($pageTitle) && $pageTitle === 'Dashboard'):
                    ?>
                    <div class="header-scanner-status offline" id="scanner-status-widget">
                        <div class="device-icon-container">
                            <i class="fa-brands fa-usb device-status-icon"></i>
                        </div>
                        <div class="scanner-status-text">
                            <div class="scanner-status-text-main">Fingerprint Scanner</div>
                            <div class="scanner-status-text-sub">Device Not Detected</div>
                        </div>
                        <div class="scanner-status-details">
                            <div class="scanner-status-badge">OFFLINE</div>
                            <div class="scanner-status-action">Check connection</div>
                        </div>
                         <div class="scanner-icon-badge">!</div>
                    </div>
                    <?php endif; ?>
                    </div>
            </header>