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
include 'includes/header.php';
?>

<!-- ===== Tailwind + FontAwesome + Custom Config ===== -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        emerald: {
          50: '#ecfdf5',
          100: '#d1fae5',
          200: '#a7f3d0',
          400: '#34d399',
          500: '#10b981',
          600: '#059669',
          700: '#047857',
        }
      }
    }
  }
}
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ===== Inline Styling for Animations & Layout ===== -->
<style>
body {
    background-color: #f9fafb;
    min-height: 100vh;
}
.main-body {
    padding: 2rem;
}
.scan-step {
    transition: all 0.3s ease;
}
.scan-step.bg-emerald-500 {
    transform: scale(1.2);
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}
@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.6; }
}
.pulse {
  animation: pulse 1s infinite;
}
</style>

<!-- ===== Main Content ===== -->
<div class="main-body flex items-center justify-center">
  <div class="w-full max-w-2xl bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
    <h2 class="text-center text-2xl font-bold text-gray-800 mb-2">Fingerprint Registration</h2>
    <p class="text-center text-gray-600 mb-6">
      Registering Fingerprint for 
      <span class="font-semibold text-emerald-700"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span><br>
      Faculty ID: <span class="text-gray-500"><?= htmlspecialchars($user['faculty_id']) ?></span>
    </p>

    <!-- Device Status -->
    <div id="deviceStatusContainer" class="border border-emerald-200 bg-emerald-50 text-emerald-700 py-2 px-4 rounded-lg flex items-center justify-center gap-2 mb-6">
      <i class="fa fa-check-circle"></i> 
      <span id="deviceStatus">Device Connected</span>
    </div>

    <!-- Fingerprint Scanner -->
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

      <form method="POST" id="fingerprintForm">
        <input type="hidden" name="fingerprint_data" id="fingerprintData">
        <button type="button" id="scanBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md font-medium shadow">
          <i class="fa fa-fingerprint"></i> Scan
        </button>
        <a href="complete_registration.php" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium shadow">Cancel</a>
      </form>
    </div>

    <!-- Instructions -->
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

<!-- ===== ZKTeco Fingerprint Integration Script ===== -->
<script>
let scanStep = 1;
const totalSteps = 5;

document.getElementById('scanBtn').addEventListener('click', () => {
    const icon = document.getElementById('fingerIcon');
    icon.classList.add('pulse');
    document.getElementById('scanStatus').textContent = "Scanning... Place your finger on the device.";
    startZKScan(icon);
});

function startZKScan(icon) {
    // ZKTeco WebSocket SDK (ensure ZK service is running locally)
    const socket = new WebSocket("ws://127.0.0.1:8080");

    socket.onopen = () => socket.send(JSON.stringify({ command: "enroll_start" }));

    socket.onmessage = (event) => {
        const data = JSON.parse(event.data);

        if (data.status === "success" && data.template) {
            document.getElementById('fingerprintData').value = data.template;
            document.getElementById('scanStatus').textContent = `Scan ${scanStep} complete.`;
            document.getElementById(`scanStep${scanStep}`).classList.add('bg-emerald-500', 'border-emerald-500');

            if (scanStep < totalSteps) {
                scanStep++;
                setTimeout(() => {
                    document.getElementById('scanStatus').textContent = "Place your finger again for next scan...";
                }, 1500);
            } else {
                icon.classList.remove('pulse');
                document.getElementById('scanStatus').textContent = "All scans complete!";
                setTimeout(() => document.getElementById('fingerprintForm').submit(), 1500);
            }
        } else if (data.status === "error") {
            icon.classList.remove('pulse');
            document.getElementById('scanStatus').textContent = "Scan failed. Please try again.";
        }
    };

    socket.onerror = () => {
        icon.classList.remove('pulse');
        const status = document.getElementById('deviceStatus');
        status.textContent = "Device not connected.";
        status.classList.replace('text-emerald-700', 'text-red-600');
        document.getElementById('deviceStatusContainer').classList.replace('bg-emerald-50', 'bg-red-50');
        document.getElementById('deviceStatusContainer').classList.replace('border-emerald-200', 'border-red-200');
    };
}
</script>

<?php include 'includes/footer.php'; ?>
