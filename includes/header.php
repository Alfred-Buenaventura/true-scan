<!-- includes/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>BPC Attendance</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

<!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
                    <p><?= $pageSubtitle ?? 'Welcome back!' ?></p>
                </div>

                <?php if (isset($showDeviceStatus) && $showDeviceStatus): ?>
                <div class="device-status <?= $fingerprintConnected ? 'online' : 'offline' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 11v.01M12 14v.01M12 17v.01M15 8.5c0-1.933-1.567-3.5-3.5-3.5S8 6.567 8 8.5V12c0 1.933 1.567 3.5 3.5 3.5S15 13.933 15 12V8.5z"/>
                    </svg>
                    <div>
                        <div style="font-size: 12px; font-weight: 600;">Fingerprint Scanner</div>
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11px;">
                            <span class="status-dot <?= $fingerprintConnected ? 'online' : 'offline' ?>"></span>
                            <span><?= $fingerprintConnected ? 'Online' : 'Offline' ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </header>
