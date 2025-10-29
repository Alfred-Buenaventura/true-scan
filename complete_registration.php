<?php
require_once 'config.php';
requireAdmin();

$db = db();

// Fetch counts for stat cards
$totalResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active'");
$totalUsers = $totalResult ? $totalResult->fetch_assoc()['c'] : 0;

$registeredResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=1");
$registeredUsers = $registeredResult ? $registeredResult->fetch_assoc()['c'] : 0;

$pendingResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=0");
$pendingCount = $pendingResult ? $pendingResult->fetch_assoc()['c'] : 0;

// Fetch pending users
$pendingUsers = $db->query("SELECT * FROM users WHERE status='active' AND fingerprint_registered=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Complete Registration";
$pageSubtitle = "Complete user registration by scanning fingerprints for biometric authentication."; // Updated Subtitle
include 'includes/header.php';
?>

<div class="main-body registration-page"> <div class="search-bar-container">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" id="userSearchInput" class="search-input" placeholder="Search by name, faculty ID, or email...">
    </div>

    <div class="registration-stats-grid">
        <div class="reg-stat-card total-users">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="reg-stat-details">
                <p>Total Users</p>
                <span class="reg-stat-value"><?= $totalUsers ?></span>
            </div>
        </div>
        <div class="reg-stat-card registered">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="reg-stat-details">
                <p>Registered</p>
                <span class="reg-stat-value"><?= $registeredUsers ?></span>
            </div>
        </div>
        <div class="reg-stat-card pending">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div class="reg-stat-details">
                <p>Pending</p>
                <span class="reg-stat-value"><?= $pendingCount ?></span>
            </div>
        </div>
    </div>

    <div class="pending-registrations-section">
        <h3 class="section-title">Pending Registrations (<?= $pendingCount ?>)</h3>

        <?php if (empty($pendingUsers)): ?>
            <div class="empty-state-card">
                 <i class="fa-solid fa-check-circle empty-icon"></i>
                <p class="empty-text-title">No Pending Registrations</p>
                <p class="empty-text-subtitle">All active users have completed fingerprint registration.</p>
                <a href="create_account.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fa fa-user-plus"></i> Create New Account
                </a>
            </div>
        <?php else: ?>
            <div class="user-cards-container">
                <?php foreach ($pendingUsers as $u): ?>
                    <div class="user-card" data-search-term="<?= strtolower(htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' ' . $u['faculty_id'] . ' ' . $u['email'])) ?>">
                        <div class="user-card-header">
                            <span class="user-card-status pending">Pending</span>
                            <span class="user-card-role"><?= htmlspecialchars(str_replace(' ', '_', strtoupper($u['role']))) ?></span>
                        </div>
                        <div class="user-card-details">
                            <p class="user-card-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['faculty_id']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['email']) ?></p>
                        </div>
                        <a href="fingerprint_registration.php?user_id=<?= $u['id'] ?>" class="user-card-register-btn">
                            <i class="fa fa-fingerprint"></i>
                            Register Fingerprint
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Simple live search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearchInput');
    const userCards = document.querySelectorAll('.user-card');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            userCards.forEach(card => {
                const cardSearchTerm = card.getAttribute('data-search-term');
                if (cardSearchTerm && cardSearchTerm.includes(searchTerm)) {
                    card.style.display = ''; // Show card
                } else {
                    card.style.display = 'none'; // Hide card
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>