<?php
require_once 'config.php';
requireAdmin();

$db = db();
$userId = $_GET['user_id'] ?? 0;
$user = $db->query("SELECT * FROM users WHERE id='$userId' AND status='active'")->fetch_assoc();

if (!$user) {
    header("Location: complete_registration.php");
    exit;
}

// Handle fingerprint data submission (still done via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fingerprint_data'])) {
    $fingerprintData = $_POST['fingerprint_data'];
    $stmt = $db->prepare("UPDATE users SET fingerprint_data=?, fingerprint_registered=1 WHERE id=?");
    $stmt->bind_param("si", $fingerprintData, $userId);
    $stmt->execute();

    logActivity($_SESSION['user_id'], "Fingerprint Registration", "Completed for {$user['first_name']} {$user['last_name']} ({$user['faculty_id']})");
    header("Location: complete_registration.php?success=1");
    exit;
}

$pageTitle = "Fingerprint Registration";
// UPDATED Subtitle
$pageSubtitle = "Fingerprint Registration Process";
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* Base modal styles if not already in main.css */
/* Ensure modal styles from style.css are correctly applied */

.scan-step { transition: all 0.3s ease; }
.scan-step.bg-emerald-500 {
    transform: scale(1.1);
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
}
@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.05); opacity: 0.7; }
}
.pulse { animation: pulse 1s infinite ease-in-out; }

/* Styles for disabled button */
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<div class="main-body flex items-center justify-center">
  <div class="w-full max-w-2xl bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
    <h2 class="text-center text-2xl font-bold text-gray-800 mb-2">Fingerprint Registration</h2>
    <p class="text-center text-gray-600 mb-6">
      Registering Fingerprint for
      <span class="font-semibold text-emerald-700"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span><br>
      Faculty ID: <span class="text-gray-500"><?= htmlspecialchars($user['faculty_id']) ?></span>
    </p>

    <div id="deviceStatusContainer" class="border border-gray-200 bg-gray-50 text-gray-600 py-3 px-4 rounded-lg flex items-center justify-center gap-3 mb-6">
      <i id="deviceStatusIcon" class="fa fa-spinner fa-spin"></i>
      <span id="deviceStatusText" class="font-medium">Connecting to device...</span>
    </div>

    <div class="flex flex-col items-center text-center border border-emerald-100 rounded-xl p-8 mb-6">
      <div class="w-40 h-40 rounded-full border-4 border-emerald-100 flex items-center justify-center mb-4">
        <i class="fa fa-fingerprint fa-4x text-emerald-600" id="fingerIcon"></i>
      </div>
      <p class="text-gray-700 font-medium mb-4" id="scanStatus">Ready to scan fingerprint...</p>
      <div class="flex gap-3 mb-6">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <div id="scanStep<?= $i ?>" class="scan-step w-6 h-6 rounded-full border border-gray-300"></div>
        <?php endfor; ?>
      </div>

      <form method="POST" id="fingerprintForm" class="flex items-center gap-3">
        <input type="hidden" name="fingerprint_data" id="fingerprintData">
        <button type="button" id="scanBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md font-medium shadow flex items-center gap-2" disabled>
          <i class="fa fa-fingerprint"></i> Scan
        </button>
        <a href="complete_registration.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium shadow">Cancel</a>
      </form>
    </div>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg text-sm">
      <strong>Instructions:</strong>
      <ul class="list-disc list-inside mt-2 text-blue-700 space-y-1">
        <li>Ensure your finger is clean and dry.</li>
        <li>Place your finger firmly on the scanner.</li>
        <li>Hold still until each scan is complete.</li>
        <li>All 5 scans must be completed for successful registration.</li>
      </ul>
    </div>
  </div>
</div>

<div id="deviceErrorModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--red-50);">
            <h3 style="color: var(--red-700);"><i class="fa-solid fa-triangle-exclamation"></i> Device Not Detected</h3>
            <button type="button" class="modal-close" onclick="closeModal('deviceErrorModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-700); font-size: 1rem;">
                The fingerprint scanner could not be detected. Please ensure:
            </p>
            <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem; color: var(--gray-600);">
                <li>The device is properly connected to the computer via USB.</li>
                <li>The necessary ZKTeco service or software is running on the computer.</li>
                <li>No other application is currently using the device.</li>
            </ul>
            <p style="color: var(--gray-700); font-size: 1rem; margin-top: 1rem;">
                Once checked, you may need to refresh this page.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('deviceErrorModal')">OK</button>
        </div>
    </div>
</div>

<div id="scanNoticeModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--yellow-50);">
            <h3 style="color: var(--yellow-700);"><i class="fa-solid fa-circle-info"></i> Important Notice</h3>
            <button type="button" class="modal-close" onclick="closeModal('scanNoticeModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
             <p style="color: var(--gray-700); font-size: 1rem;">
                Before scanning, please ensure:
            </p>
             <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem; color: var(--gray-600);">
                <li>The user's finger is clean and dry.</li>
                <li>The fingerprint scanner surface is clean.</li>
             </ul>
             <p style="color: var(--gray-700); font-size: 1rem; margin-top: 1rem;">
                Press "Proceed" to start the scan.
            </p>
        </div>
        <div class="modal-footer">
             <button type="button" class="btn btn-secondary" onclick="closeModal('scanNoticeModal')">Cancel Scan</button>
             <button type="button" id="proceedScanBtn" class="btn btn-primary">Proceed</button>
        </div>
    </div>
</div>


<script>
let scanStep = 1;
const totalSteps = 5;
let isDeviceConnected = false; // Track connection status
let socket = null; // WebSocket connection

const deviceStatusContainer = document.getElementById('deviceStatusContainer');
const deviceStatusIcon = document.getElementById('deviceStatusIcon');
const deviceStatusText = document.getElementById('deviceStatusText');
const scanBtn = document.getElementById('scanBtn');
const fingerIcon = document.getElementById('fingerIcon');
const scanStatus = document.getElementById('scanStatus');

// --- Modal Helper Functions (Ensure openModal/closeModal are defined in main.js) ---
// If not, add them here or in main.js:
/*
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}
*/


// --- WebSocket Connection Logic ---
function connectWebSocket() {
    // ZKTeco WebSocket SDK (ensure ZK service is running locally)
    socket = new WebSocket("ws://127.0.0.1:8080");

    socket.onopen = () => {
        isDeviceConnected = true;
        deviceStatusContainer.classList.replace('border-gray-200', 'border-emerald-200');
        deviceStatusContainer.classList.replace('bg-gray-50', 'bg-emerald-50');
        deviceStatusContainer.classList.replace('text-gray-600', 'text-emerald-700');
        deviceStatusIcon.className = 'fa fa-check-circle'; // Change icon
        deviceStatusText.textContent = "Device Connected";
        scanBtn.disabled = false; // Enable scan button
        console.log("WebSocket Connected");
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            console.log("Message from server:", data);

            // Handle scan progress
            if (data.status === "success" && data.template) {
                document.getElementById('fingerprintData').value = data.template;
                scanStatus.textContent = `Scan ${scanStep} complete.`;
                document.getElementById(`scanStep${scanStep}`).classList.add('bg-emerald-500', 'border-emerald-500');

                if (scanStep < totalSteps) {
                    scanStep++;
                    setTimeout(() => {
                        scanStatus.textContent = "Place your finger again for next scan...";
                    }, 1500);
                } else {
                    fingerIcon.classList.remove('pulse');
                    scanStatus.textContent = "All scans complete! Saving...";
                    // Submit the form after a short delay
                    setTimeout(() => document.getElementById('fingerprintForm').submit(), 1500);
                }
            } else if (data.status === "error") {
                fingerIcon.classList.remove('pulse');
                scanStatus.textContent = `Scan failed: ${data.message || 'Please try again.'}`;
                // Optionally reset steps or allow retry
            } else if (data.status === "progress") {
                // Handle intermediate messages if the device sends them
                scanStatus.textContent = data.message || "Scanning...";
            }
        } catch (e) {
            console.error("Error parsing message:", e);
             fingerIcon.classList.remove('pulse');
             scanStatus.textContent = "Received invalid data from device.";
        }
    };

    socket.onerror = () => {
        isDeviceConnected = false;
        deviceStatusContainer.classList.replace('border-emerald-200', 'border-red-200');
        deviceStatusContainer.classList.replace('bg-emerald-50', 'bg-red-50');
        deviceStatusContainer.classList.replace('text-emerald-700', 'text-red-600');
        deviceStatusIcon.className = 'fa fa-times-circle'; // Error icon
        deviceStatusText.textContent = "Device Not Detected";
        scanBtn.disabled = true; // Disable scan button
        openModal('deviceErrorModal'); // Show error modal
        console.error("WebSocket Error");
    };

     socket.onclose = () => {
        // Handle unexpected close if needed, maybe retry connection?
        if (isDeviceConnected) { // Only show if it was previously connected
             isDeviceConnected = false;
             deviceStatusContainer.classList.replace('border-emerald-200', 'border-red-200');
             deviceStatusContainer.classList.replace('bg-emerald-50', 'bg-red-50');
             deviceStatusContainer.classList.replace('text-emerald-700', 'text-red-600');
             deviceStatusIcon.className = 'fa fa-times-circle';
             deviceStatusText.textContent = "Connection Lost";
             scanBtn.disabled = true;
             console.log("WebSocket Closed");
        }
    };
}

// --- Start Scan Process (Called after notice confirmed) ---
function startZKScan() {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
        scanStatus.textContent = "Device not ready. Please wait or refresh.";
        return;
    }

    fingerIcon.classList.add('pulse');
    scanStatus.textContent = "Scanning... Place your finger on the device.";

    // Reset steps visually if starting a new scan sequence
    if (scanStep > totalSteps) scanStep = 1;
    for(let i = 1; i <= totalSteps; i++) {
        document.getElementById(`scanStep${i}`).classList.remove('bg-emerald-500', 'border-emerald-500');
    }

    // Tell the device to start enrolling
    socket.send(JSON.stringify({ command: "enroll_start" }));
}

// --- Event Listeners ---
document.addEventListener('DOMContentLoaded', () => {
    connectWebSocket(); // Attempt connection on page load

    scanBtn.addEventListener('click', () => {
        if (isDeviceConnected) {
            openModal('scanNoticeModal'); // Show notice first
        } else {
            openModal('deviceErrorModal'); // Show error if disconnected
        }
    });

    // Handle proceeding after notice
    document.getElementById('proceedScanBtn').addEventListener('click', () => {
        closeModal('scanNoticeModal');
        startZKScan(); // Now start the actual scan
    });

    // Close modals if clicking outside (Ensure this is handled globally in main.js or add here)
    // Example:
    // window.addEventListener('click', function(event) {
    //     if (event.target.classList.contains('modal')) {
    //          closeModal(event.target.id);
    //     }
    // });
});

</script>

<?php include 'includes/footer.php'; ?>