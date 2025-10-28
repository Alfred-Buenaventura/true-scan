<?php
require_once 'config.php';
requireAdmin();

$db = db();

// Fetch pending and registered users
$pendingCount = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=0")->fetch_assoc()['c'];
$pendingUsers = $db->query("SELECT * FROM users WHERE status='active' AND fingerprint_registered=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Complete Registration";
// UPDATED Subtitle
$pageSubtitle = "Manage and Complete User Fingerprint Registration.";
include 'includes/header.php';
?>
<div class="main-body">
    <div class="container mx-auto p-6">

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-amber-100 text-amber-600 p-2 rounded-full">
                    <i class="fa fa-fingerprint"></i>
                </div>
                <div>
                    <p class="text-sm text-amber-800 font-semibold">Pending Fingerprint Registrations</p>
                    <h3 class="text-3xl font-bold text-amber-700"><?= $pendingCount ?></h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa fa-fingerprint text-emerald-600"></i>
                    Users Awaiting Fingerprint Registration
                </h3>
            </div>

            <div class="p-6">
                <?php if (empty($pendingUsers)): ?>
                    <div class="text-center py-12">
                        <div class="bg-emerald-100 text-emerald-600 w-16 h-16 rounded-full mx-auto flex items-center justify-center mb-4">
                            <i class="fa fa-fingerprint fa-2x"></i>
                        </div>
                        <p class="text-lg font-semibold text-gray-700 mb-2">No Pending Registrations</p>
                        <p class="text-gray-500 mb-6">All users have completed their fingerprint registration.</p>
                        <a href="create_account.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md font-medium">
                            <i class="fa fa-user-plus"></i> Create New Account
                        </a>
                    </div>
                <?php else: ?>
                    <table class="w-full border-collapse larger-text-table">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-2 text-sm text-gray-600 font-semibold">Faculty ID</th>
                                <th class="text-left px-4 py-2 text-sm text-gray-600 font-semibold">Name</th>
                                <th class="text-left px-4 py-2 text-sm text-gray-600 font-semibold">Email</th>
                                <th class="text-left px-4 py-2 text-sm text-gray-600 font-semibold">Role</th>
                                <th class="text-left px-4 py-2 text-sm text-gray-600 font-semibold">Account Created</th>
                                <th class="text-center px-4 py-2 text-sm text-gray-600 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingUsers as $u): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-gray-800"><?= htmlspecialchars($u['faculty_id']) ?></td>
                                    <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="bg-blue-100 text-blue-700 text-xs px-3 py-1 rounded-full"><?= htmlspecialchars($u['role']) ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-sm"><?= date('m/d/Y', strtotime($u['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="fingerprint_registration.php?user_id=<?= $u['id'] ?>"
                                           class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center register-btn-special">
                                            <i class="fa fa-fingerprint"></i>
                                            <span>Register</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>