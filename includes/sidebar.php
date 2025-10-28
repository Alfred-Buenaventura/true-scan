<!-- includes/sidebar.php -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 11v.01M12 14v.01M12 17v.01M15 8.5c0-1.933-1.567-3.5-3.5-3.5S8 6.567 8 8.5V12c0 1.933 1.567 3.5 3.5 3.5S15 13.933 15 12V8.5z"/>
                </svg>
            </div>
            <div class="sidebar-title">
                <h1>BPC Attendance</h1>
                <p>Admin Panel</p>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item">🏠 Home</a>
        <a href="create_account.php" class="nav-item">👤 Create Account</a>
        <a href="complete_registration.php" class="nav-item">🧾 Complete Registration</a>
        <a href="attendance_reports.php" class="nav-item">📊 Attendance Reports</a>
        <a href="schedule_management.php" class="nav-item">📅 Schedule Management</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-info-inner">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1) . substr($_SESSION['last_name'] ?? 'D', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <p>Logged in as</p>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                    <div class="user-id">ID: <?= htmlspecialchars($_SESSION['faculty_id'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>

        <button class="btn logout-btn" onclick="if(confirm('Log out?')) window.location.href='logout.php'">
            🚪 Log out
        </button>
    </div>
</aside>
