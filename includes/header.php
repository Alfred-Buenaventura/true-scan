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

                    <div class="user-profile">
        <button class="user-btn" id="user-menu-btn" aria-label="User Menu">
            <img src="<?= $profilePicUrl ?? 'https://placehold.co/40x40/E2E8F0/64748B?text=U' ?>" alt="User" class="user-avatar">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
            </div>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-menu" id="user-menu-dropdown">
            <a href="profile.php"><i class="fa-solid fa-user-circle"></i> Profile</a>
            <a href="change_password.php"><i class="fa-solid fa-key"></i> Change Password</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>


    <!-- ============================================= -->
    <!-- === START: NEW NOTIFICATION SYSTEM === -->
    <!-- ============================================= -->
    <?php
    // This block fetches and displays unread notifications
    // It's placed here so it's included on every page
    if (isset($_SESSION['user_id'])) {
        $db = db(); // Re-establish db connection if not in scope
        $current_user_id = $_SESSION['user_id'];
        
        $notif_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $notif_stmt->bind_param("i", $current_user_id);
        $notif_stmt->execute();
        $notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (!empty($notifications)) {
            echo '<div id="toast-container">';
            foreach ($notifications as $notif) {
                $icon = 'fa-solid fa-circle-info'; // Default
                if ($notif['type'] == 'warning') {
                    $icon = 'fa-solid fa-triangle-exclamation';
                } elseif ($notif['type'] == 'success') {
                    $icon = 'fa-solid fa-check-circle';
                }
                
                echo '
                <div class="toast-message toast-' . htmlspecialchars($notif['type']) . '" id="toast-' . $notif['id'] . '">
                    <div class="toast-icon">
                        <i class="' . $icon . '"></i>
                    </div>
                    <div class="toast-content">
                        <p>' . htmlspecialchars($notif['message']) . '</p>
                    </div>
                    <button class="toast-close" onclick="dismissToast(' . $notif['id'] . ')">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>';
            }
            echo '</div>';
        }
    }
    ?>

    <script>
    function dismissToast(notificationId) {
        const toastElement = document.getElementById('toast-' + notificationId);
        
        // 1. Send to server to mark as read
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 2. Animate and remove from UI
                if (toastElement) {
                    toastElement.style.opacity = '0';
                    toastElement.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        toastElement.remove();
                        // Optional: remove container if empty
                        const container = document.getElementById('toast-container');
                        if (container && container.childElementCount === 0) {
                            container.remove();
                        }
                    }, 300);
                }
            } else {
                // Failed, just hide it for this session
                if (toastElement) toastElement.style.display = 'none'; 
            }
        })
        .catch(error => {
            console.error('Error dismissing notification:', error);
            if (toastElement) toastElement.style.display = 'none';
        });
    }
    </script>
            </header>