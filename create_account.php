<?php
// SERVER-SIDE: do this first
require_once 'config.php';
requireAdmin();

$db = db();
$error = '';
$success = '';
$activeTab = 'csv'; // Default tab

// ===== CSV Import =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $activeTab = 'csv';
    if ($_FILES['csvFile']['error'] === 0) {
        $file = fopen($_FILES['csvFile']['tmp_name'], 'r');
        fgetcsv($file); // Skip header
        $imported = 0; $skipped = 0;
        while (($data = fgetcsv($file)) !== false) {
            // We expect 8 columns: facultyId, lastName, firstName, middleName, username, role, email, phone
            if (count($data) < 8) continue;
            $facultyId = clean($data[0]);
            $lastName = clean($data[1]);
            $firstName = clean($data[2]);
            $middleName = clean($data[3]);
            $username = clean($data[4]);
            $role = clean($data[5]);
            $email = clean($data[6]);
            $phone = $data[7] ?? '';

            // Skip if role is Admin
            if (strtolower($role) === 'admin') {
                $skipped++;
                continue;
            }

            $stmt = $db->prepare("SELECT id FROM users WHERE faculty_id = ?");
            $stmt->bind_param("s", $facultyId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) { $skipped++; continue; }

            $password = hashPass('DefaultPass123!');
            $stmt = $db->prepare("INSERT INTO users (faculty_id, username, password, first_name, last_name, middle_name, email, phone, role, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssssssss", $facultyId, $username, $password, $firstName, $lastName, $middleName, $email, $phone, $role);
            $stmt->execute();
            $imported++;
        }
        fclose($file);
        logActivity($_SESSION['user_id'], 'CSV Import', "Imported $imported users");
        $success = "Successfully imported $imported user(s). Skipped $skipped duplicate(s)/admin(s).";
    }
}

// ===== Create Single User =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $activeTab = 'create';
    $facultyId = clean($_POST['faculty_id']);
    $firstName = clean($_POST['first_name']);
    $lastName = clean($_POST['last_name']);
    $middleName = clean($_POST['middle_name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $role = clean($_POST['role']);

    // Ensure role is not Admin
    if ($role === 'Admin') {
        $error = 'Admin accounts must be created from the Admin Management page.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE faculty_id = ?");
        $stmt->bind_param("s", $facultyId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $username = strtolower($facultyId);
            $password = hashPass('DefaultPass123!');
            $stmt = $db->prepare("INSERT INTO users (faculty_id, username, password, first_name, last_name, middle_name, email, phone, role, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssssssss", $facultyId, $username, $password, $firstName, $lastName, $middleName, $email, $phone, $role);
            $stmt->execute();
            logActivity($_SESSION['user_id'], 'User Created', "Created user: $facultyId");
            $success = "Account created successfully! User: $firstName $lastName";
        } else {
            $error = 'Faculty ID already exists';
        }
    }
}

// ===== Edit User =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $activeTab = 'view';
    $userId = (int)$_POST['user_id'];
    $firstName = clean($_POST['first_name']);
    $lastName = clean($_POST['last_name']);
    $middleName = clean($_POST['middle_name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, middle_name=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("sssssi", $firstName, $lastName, $middleName, $email, $phone, $userId);
    $stmt->execute();
    logActivity($_SESSION['user_id'], 'User Updated', "Updated user ID: $userId");
    $success = "User information updated successfully!";
}

// ===== Archive / Restore / Delete =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user'])) {
    $activeTab = 'view';
    $userId = (int)$_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET status='archived' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    logActivity($_SESSION['user_id'], 'User Archived', "Archived user ID: $userId");
    $success = 'User archived successfully!';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_user'])) {
    $activeTab = 'view';
    $userId = (int)$_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET status='active' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    logActivity($_SESSION['user_id'], 'User Restored', "Restored user ID: $userId");
    $success = 'User restored successfully!';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $activeTab = 'view';
    $userId = (int)$_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    logActivity($_SESSION['user_id'], 'User Deleted', "Permanently deleted user ID: $userId");
    $success = 'User permanently deleted!';
}

// ===== Stats & Data =====
$totalUsers = $db->query("SELECT COUNT(*) as c FROM users WHERE status='active'")->fetch_assoc()['c'];
$nonAdminUsers = $db->query("SELECT COUNT(*) as c FROM users WHERE status='active' AND role!='Admin'")->fetch_assoc()['c'];
$adminUsers = $db->query("SELECT COUNT(*) as c FROM users WHERE status='active' AND role='Admin'")->fetch_assoc()['c'];
$activeUsers = $db->query("SELECT * FROM users WHERE status='active' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$archivedUsers = $db->query("SELECT * FROM users WHERE status='archived' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Create New Account';
$pageSubtitle = 'Create user accounts individually or import in bulk via CSV';
include 'includes/header.php';
?>

<main class="main-content" style="position: relative; z-index: 1; padding: 20px;">
<div class="main-body">

<div id="toastContainer" class="toast-container"></div>

<?php if ($error): ?>
<div class="notification notification-error">
    <i class="fa-solid fa-circle-xmark"></i>
    <span><?= htmlspecialchars($error) ?></span>
    <button class="notification-close" onclick="this.parentElement.remove()">
        <i class="fa-solid fa-times"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="notification notification-success">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($success) ?></span>
    <button class="notification-close" onclick="this.parentElement.remove()">
        <i class="fa-solid fa-times"></i>
    </button>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-details">
            <p>Total Accounts</p>
            <div class="stat-value emerald"><?= $totalUsers ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="stat-details">
            <p>Non-Admin Users</p>
            <div class="stat-value emerald"><?= $nonAdminUsers ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="stat-details">
            <p>Admin Users</p>
            <div class="stat-value emerald"><?= $adminUsers ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="tabs">
        <button class="tab-btn <?= $activeTab === 'csv' ? 'active' : '' ?>" onclick="showTab(event, 'csv')"><i class="fa-solid fa-file-csv"></i> CSV Bulk Import</button>
        <button class="tab-btn <?= $activeTab === 'create' ? 'active' : '' ?>" onclick="showTab(event, 'create')"><i class="fa-solid fa-user-plus"></i> Account Creation</button>
        <button class="tab-btn <?= $activeTab === 'view' ? 'active' : '' ?>" onclick="showTab(event, 'view')"><i class="fa-solid fa-list"></i> View All Accounts</button>
    </div>

    <div id="csvTab" class="tab-content <?= $activeTab === 'csv' ? 'active' : '' ?>">
        <div class="card-body">
            <div class="csv-section-header">
                <i class="fa-solid fa-file-arrow-up"></i>
                <h3>Bulk User Import (CSV)</h3>
            </div>
            <p class="csv-subtitle">Import multiple user accounts from a CSV file</p>

            <div class="download-template-box">
                <div class="download-template-inner">
                    <div class="step-badge">1</div>
                    <div class="download-template-content">
                        <h4>Download the CSV template first <span>to ensure correct format.</span></h4>
                        <button type="button" class="btn btn-primary download-template-link" onclick="confirmDownload()">
                            <i class="fa-solid fa-download"></i>
                            Download Template
                        </button>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="csvUploadForm">
                <div style="margin-bottom: 1.5rem;">
                    <label class="csv-upload-label">Upload CSV File</label>
                    <div class="csv-dropzone" id="csvDropzone"
                         onclick="document.getElementById('csvFileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p class="csv-dropzone-text"><strong>Click to choose a CSV file</strong></p>
                        <p id="csvFileStatus" class="csv-file-status">No file chosen...</p>
                    </div>
                    <input type="file" name="csvFile" id="csvFileInput" accept=".csv" style="display: none;" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-upload"></i>
                    Import Users from CSV
                </button>
            </form>

            <div class="csv-requirements">
                <h4>CSV Format Requirements:</h4>
                <ul>
                    <li>Columns (in order): Faculty ID, Last Name, First Name, Middle Name, Username, Role, Email, Phone</li>
                    <li>All users will be created with default password: <strong>DefaultPass123!</strong></li>
                    <li>Users must change password on first login</li>
                    <li>Duplicate Faculty IDs will be skipped</li>
                    <li>Rows with 'Admin' role will be skipped</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="createTab" class="tab-content <?= $activeTab === 'create' ? 'active' : '' ?>">
        <div class="card-body">
            <div class="user-creation-header">
                <i class="fa-solid fa-user-plus"></i>
                <h3>Create New User Account</h3>
            </div>
            <p class="user-creation-subtitle">Create a single user account with default password: <strong>DefaultPass123!</strong></p>

            <form method="POST" style="margin-top: 1.5rem;">
                <div class="user-creation-form-grid">
                    <div class="form-group">
                        <label>Faculty/ID Number <span class="required">*</span></label>
                        <input type="text" name="faculty_id" class="form-control" placeholder="e.g., STAFF001" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g., staff@bulacan.edu.ph" required>
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name (optional)">
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g., 09171234567" required>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Role/Position <span class="required">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="">Select a role</option>
                            <option value="Full Time Teacher">Full Time Teacher</option>
                            <option value="Part Time Teacher">Part Time Teacher</option>
                            <option value="Registrar">Registrar</option>
                            <option value="Admission">Admission</option>
                            <option value="OPRE">OPRE</option>
                            <option value="Scholarship Office">Scholarship Office</option>
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Guidance Office">Guidance Office</option>
                            <option value="Library">Library</option>
                            <option value="Finance">Finance</option>
                            <option value="Student Affair">Student Affair</option>
                            <option value="Security Personnel and Facility Operator">Security Personnel and Facility Operator</option>
                            <option value="OVPA">OVPA</option>
                            <option value="MIS">MIS</option>
                        </select>
                    </div>
                </div>

                <div class="password-info-box">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Note:</strong> User will be assigned the default password <strong>DefaultPass123!</strong> and will be prompted to change it on first login.
                    </div>
                </div>

                <button type="submit" name="create_user" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-user-plus"></i>
                    Create Account
                </button>
            </form>
        </div>
    </div>

    <div id="viewTab" class="tab-content <?= $activeTab === 'view' ? 'active' : '' ?>">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0;">All Active Accounts</h3>
                <button class="btn btn-secondary" onclick="openArchivedModal()">
                    <i class="fa-solid fa-archive"></i>
                    View Archived Accounts (<?= count($archivedUsers) ?>)
                </button>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Faculty ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activeUsers) > 0): ?>
                        <?php foreach ($activeUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['faculty_id']) ?></td>
                                <td><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm" onclick="editUser(<?= (int)$user['id'] ?>, '<?= addslashes($user['first_name']) ?>', '<?= addslashes($user['last_name']) ?>', '<?= addslashes($user['middle_name']) ?>', '<?= addslashes($user['email']) ?>', '<?= addslashes($user['phone']) ?>')">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="confirmArchive(<?= (int)$user['id'] ?>, '<?= addslashes($user['first_name'].' '.$user['last_name']) ?>')">
                                        <i class="fa-solid fa-archive"></i> Archive
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 2rem; color: var(--gray-600);">
                                <i class="fa-solid fa-users-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                No active accounts found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="editForm" style="display: none; margin-top: 2rem; border-top: 2px solid var(--gray-200); padding-top: 2rem;">
                <h4><i class="fa-solid fa-user-pen"></i> Edit User Information</h4>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-grid">
                        <div class="form-group"><label>First Name <span class="required">*</span></label><input type="text" name="first_name" id="editFirstName" class="form-control" required></div>
                        <div class="form-group"><label>Last Name <span class="required">*</span></label><input type="text" name="last_name" id="editLastName" class="form-control" required></div>
                        <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="editMiddleName" class="form-control"></div>
                        <div class="form-group"><label>Email <span class="required">*</span></label><input type="email" name="email" id="editEmail" class="form-control" required></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                    </div>
                    <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i> Update User
                        </button>
                        <button type="button" onclick="hideEditForm()" class="btn btn-secondary">
                            <i class="fa-solid fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="archivedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-archive"></i> Archived Accounts</h3>
                <button class="modal-close" onclick="closeArchivedModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Faculty ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($archivedUsers) > 0): ?>
                            <?php foreach ($archivedUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['faculty_id']) ?></td>
                                    <td><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                    <td><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="confirmRestore(<?= (int)$user['id'] ?>, '<?= addslashes($user['first_name'].' '.$user['last_name']) ?>')">
                                            <i class="fa-solid fa-rotate-left"></i> Restore
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= (int)$user['id'] ?>, '<?= addslashes($user['first_name'].' '.$user['last_name']) ?>')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 2rem; color: var(--gray-600);">
                                    <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                    No archived accounts
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 id="confirmTitle"><i class="fa-solid fa-circle-exclamation"></i> Confirm Action</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" style="font-size: 1rem; color: var(--gray-700);"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" id="confirmActionBtn" onclick="executeConfirmedAction()">
                    <i class="fa-solid fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <div id="doubleConfirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header" style="background: var(--red-50);">
                <h3 style="color: var(--red-600);"><i class="fa-solid fa-triangle-exclamation"></i> Final Confirmation</h3>
            </div>
            <div class="modal-body">
                <div style="background: var(--red-50); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--red-600);">
                    <p style="color: var(--red-600); font-weight: 600; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-exclamation-triangle"></i> WARNING: This action cannot be undone!
                    </p>
                    <p style="color: var(--gray-700); font-size: 0.9rem; margin: 0;">
                        All user data will be permanently deleted from the system.
                    </p>
                </div>
                <p id="doubleConfirmMessage" style="font-size: 1rem; color: var(--gray-700);"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDoubleConfirmModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button class="btn btn-danger" onclick="executeDeleteAction()">
                    <i class="fa-solid fa-trash"></i> Yes, Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>
</main>

<script>
/* Single place for JS. All DOM bindings happen after DOMContentLoaded. */

// State used by confirm/double-confirm flows
let pendingAction = null;
let deleteUserId = null;
let deleteUserName = null;

document.addEventListener('DOMContentLoaded', function() {
    // ----- Tab switching helper (can be triggered from server-side by adding active classes) -----
    window.showTab = function(event, tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        const el = document.getElementById(tab + 'Tab');
        if (el) el.classList.add('active');
        if (event && event.target) event.target.classList.add('active');
    };

    // ----- Auto-dismiss notifications after 5 seconds -----
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    });

    // ----- CSV file upload handling -----
    const csvFileInput = document.getElementById('csvFileInput');
    const csvDropzone = document.getElementById('csvDropzone');
    const csvFileStatus = document.getElementById('csvFileStatus');

    if (csvFileInput && csvDropzone && csvFileStatus) {
        csvFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                csvFileStatus.textContent = this.files[0].name;
                csvFileStatus.classList.add('has-file');
            } else {
                csvFileStatus.textContent = 'No file chosen...';
                csvFileStatus.classList.remove('has-file');
            }
        });

        // Drag and drop functionality
        csvDropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        csvDropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        csvDropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            if (e.dataTransfer.files.length > 0) {
                csvFileInput.files = e.dataTransfer.files;
                csvFileStatus.textContent = e.dataTransfer.files[0].name;
                csvFileStatus.classList.add('has-file');
            }
        });
    }

    // ----- Keep archived modal open after restore/delete POST if server requested it -----
    <?php if ($activeTab === 'view' && (isset($_POST['restore_user']) || isset($_POST['delete_user']))): ?>
    setTimeout(() => {
        openArchivedModal();
    }, 100);
    <?php endif; ?>
});

// ----- Edit user functions -----
function editUser(id, firstName, lastName, middleName, email, phone) {
    const elId = document.getElementById('editUserId');
    if (elId) elId.value = id;
    const f = document.getElementById('editFirstName'); if (f) f.value = firstName || '';
    const l = document.getElementById('editLastName'); if (l) l.value = lastName || '';
    const m = document.getElementById('editMiddleName'); if (m) m.value = middleName || '';
    const e = document.getElementById('editEmail'); if (e) e.value = email || '';
    const p = document.getElementById('editPhone'); if (p) p.value = phone || '';
    const form = document.getElementById('editForm');
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function hideEditForm() {
    const form = document.getElementById('editForm');
    if (form) form.style.display = 'none';
}

// ----- Modal functions -----
function openArchivedModal() {
    const m = document.getElementById('archivedModal');
    if (m) m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeArchivedModal() {
    const m = document.getElementById('archivedModal');
    if (m) m.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ----- Download template confirmation -----
function confirmDownload() {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-download"></i> Download Template';
    document.getElementById('confirmMessage').textContent = 'Download the CSV template file? This template shows the correct format for bulk user import.';
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-primary';
    btn.innerHTML = '<i class="fa-solid fa-download"></i> Download';

    pendingAction = function() {
        window.location.href = 'download_template.php';
    };

    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// ----- Confirmation flows: archive / restore / delete -----
function confirmArchive(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-archive"></i> Confirm Archive';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to archive ${userName}? They will no longer have access to the system.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-warning';
    btn.innerHTML = '<i class="fa-solid fa-archive"></i> Archive';

    pendingAction = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="archive_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    };

    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function confirmRestore(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirm Restore';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to restore ${userName}? They will regain access to the system.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-success';
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Restore';

    pendingAction = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="restore_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    };

    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function confirmDelete(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-trash"></i> Confirm Delete';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to permanently delete ${userName}? This action cannot be undone.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-danger';
    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';

    deleteUserId = userId;
    deleteUserName = userName;

    pendingAction = function() {
        // Show second confirmation
        closeConfirmModal();
        setTimeout(() => {
            const doubleMsg = document.getElementById('doubleConfirmMessage');
            if (doubleMsg) doubleMsg.textContent = `Type confirmation: Are you absolutely sure you want to delete ${userName}?`;
            const doubleModal = document.getElementById('doubleConfirmModal');
            if (doubleModal) doubleModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }, 300);
    };

    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    const confirm = document.getElementById('confirmModal');
    if (confirm) confirm.style.display = 'none';
    document.body.style.overflow = 'auto';
    // Keep archived modal open if it was open
    const archived = document.getElementById('archivedModal');
    if (archived && archived.style.display === 'flex') {
        document.body.style.overflow = 'hidden';
    }
    pendingAction = null;
}

function closeDoubleConfirmModal() {
    const doubleModal = document.getElementById('doubleConfirmModal');
    if (doubleModal) doubleModal.style.display = 'none';
    document.body.style.overflow = 'auto';
    const archived = document.getElementById('archivedModal');
    if (archived && archived.style.display === 'flex') {
        document.body.style.overflow = 'hidden';
    }
    deleteUserId = null;
    deleteUserName = null;
}

function executeConfirmedAction() {
    if (pendingAction) pendingAction();
    closeConfirmModal();
}

function executeDeleteAction() {
    if (deleteUserId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${deleteUserId}"><input type="hidden" name="delete_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
    closeDoubleConfirmModal();
}

// ----- Close modals when clicking outside -----
window.onclick = function(event) {
    const archivedModal = document.getElementById('archivedModal');
    const confirmModal = document.getElementById('confirmModal');
    const doubleConfirmModal = document.getElementById('doubleConfirmModal');

    if (event.target === archivedModal) {
        closeArchivedModal();
    }
    if (event.target === confirmModal) {
        closeConfirmModal();
    }
    if (event.target === doubleConfirmModal) {
        closeDoubleConfirmModal();
    }
};
</script>

<?php include 'includes/footer.php'; ?>