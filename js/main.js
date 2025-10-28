// Simplified main.js - Common functions for BPC Attendance

// Format date (m/d/Y)
function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        // Check if date is valid
        if (isNaN(d.getTime())) return '-';
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const year = d.getFullYear();
        return `${month}/${day}/${year}`;
    } catch (e) {
        console.error("Error formatting date:", e);
        return '-';
    }
}

// Format time (12h format)
function formatTime(timeStr) {
    if (!timeStr || !timeStr.includes(':')) return '-';
    try {
        const [hours, minutes] = timeStr.split(':');
        let h = parseInt(hours);
        if (isNaN(h) || isNaN(parseInt(minutes))) return '-';
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12; // Convert hour 0 to 12
        // Ensure minutes are padded
        const paddedMinutes = String(minutes).padStart(2, '0');
        return `${String(h)}:${paddedMinutes} ${ampm}`; // Removed padding from hour
    } catch (e) {
        console.error("Error formatting time:", e);
        return '-';
    }
}


// Show toast notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer') || document.body; // Prefer container if exists
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    // Trigger reflow for transition
    toast.offsetHeight;

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
        // Remove element after transition ends
        toast.addEventListener('transitionend', () => toast.remove());
    }, 3000);
}


// Show loading
function showLoading(message = 'Loading...') {
    hideLoading(); // Remove existing loader if any
    const loader = document.createElement('div');
    loader.id = 'loader';
    loader.className = 'modal'; // Use modal class for overlay
    loader.style.display = 'flex'; // Ensure it's visible
    loader.innerHTML = `
        <div style="text-align: center;">
            <div class="spinner"></div>
            <p style="color: white; margin-top: 16px; font-weight: 600;">${message}</p>
        </div>
    `;
    document.body.appendChild(loader);
    document.body.style.overflow = 'hidden'; // Prevent scrolling while loading
}


// Hide loading
function hideLoading() {
    const loader = document.getElementById('loader');
    if (loader) {
        loader.remove();
    }
    document.body.style.overflow = ''; // Restore scrolling
}


// Confirm action (using custom modal if available, fallback to browser confirm)
function confirmAction(message, callback) {
    // Check if your custom confirm modal exists
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal && window.openConfirmModal) {
        // Assuming you have a function like openConfirmModal(message, callback)
        window.openConfirmModal(message, callback);
    } else {
        // Fallback to basic browser confirm
        if (confirm(message)) {
            callback();
        }
    }
}


// Validate email
function validateEmail(email) {
    if (!email) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).toLowerCase());
}


// Validate phone (PH format, allows optional +63)
function validatePhone(phone) {
    if (!phone) return false;
    const cleaned = String(phone).replace(/\s|-/g, ''); // Remove spaces and dashes
    return /^(09|\+639)\d{9}$/.test(cleaned);
}


// Calculate hours between times (HH:MM format)
function calculateHours(start, end) {
    if (!start || !end || !start.includes(':') || !end.includes(':')) return 0;
    try {
        const startTime = new Date(`1970-01-01T${start}:00`);
        const endTime = new Date(`1970-01-01T${end}:00`);
        if (isNaN(startTime.getTime()) || isNaN(endTime.getTime())) return 0;

        let diff = (endTime - startTime) / (1000 * 60 * 60); // Difference in hours
        if (diff < 0) {
             diff += 24; // Handle overnight case if necessary, assuming end time is next day
        }
        return Math.round(diff * 100) / 100; // Round to 2 decimal places
    } catch (e) {
        console.error("Error calculating hours:", e);
        return 0;
    }
}


// Add toast styles (ensure this runs only once)
if (!document.getElementById('toastStyles')) {
    const style = document.createElement('style');
    style.id = 'toastStyles';
    style.textContent = `
        #toastContainer { /* Optional: Container for toasts */
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 14px 20px; /* Slightly adjusted padding */
            border-radius: 8px; /* Consistent border-radius */
            color: white;
            font-weight: 500; /* Adjusted weight */
            font-size: 0.9rem; /* Adjusted size */
            opacity: 0;
            transform: translateX(100%); /* Start off-screen */
            transition: opacity 0.3s ease, transform 0.4s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 250px;
            max-width: 350px;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0); /* Slide in */
        }

        .toast-success { background: var(--emerald-600); }
        .toast-error { background: var(--red-600); }
        .toast-warning { background: var(--yellow-400); color: var(--gray-900); } /* Ensure contrast */

        /* Spinner for loading */
        #loader { background-color: rgba(0, 0, 0, 0.7); backdrop-filter: blur(3px); } /* Darker overlay */
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);
}

// ===========================================
// Sidebar Toggle Logic
// ===========================================
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleButton = document.getElementById('sidebarToggle');
    const toggleIconContainer = toggleButton ? toggleButton.querySelector('svg') : null; // Get SVG container
    const dashboardContainer = document.getElementById('dashboardContainer');

    // SVG icons (Solid Style)
    const hamburgerIconSVG = `
        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
    `;
    const closeIconSVG = `
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
    `;

    // Function to set sidebar state
    const setSidebarState = (isCollapsed) => {
        if (!sidebar || !mainContent || !dashboardContainer || !toggleIconContainer) return; // Ensure elements exist

        if (isCollapsed) {
            dashboardContainer.classList.add('sidebar-collapsed');
            toggleIconContainer.innerHTML = hamburgerIconSVG; // Show hamburger when collapsed
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            dashboardContainer.classList.remove('sidebar-collapsed');
            toggleIconContainer.innerHTML = closeIconSVG; // Show close when expanded
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    };

    // Check localStorage on page load
    const isInitiallyCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    setSidebarState(isInitiallyCollapsed); // Apply state and set correct icon

    // Add event listener to the toggle button
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const shouldCollapse = !dashboardContainer.classList.contains('sidebar-collapsed');
            setSidebarState(shouldCollapse);
        });
    }

    // Auto-hide alerts (moved inside DOMContentLoaded)
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Add active class to current nav item (moved inside DOMContentLoaded)
    const currentPage = window.location.pathname.split('/').pop() || 'index.php'; // Default to index.php if empty
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        // More robust check for active page, handles cases with parameters
        if (href && (href === currentPage || currentPage.startsWith(href + '?'))) {
            item.classList.add('active');
        } else {
            item.classList.remove('active'); // Ensure others are not active
        }
    });
});


// Prevent form resubmission on refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Global object (optional, but keeps things tidy)
window.BPC = {
    ...window.BPC, // Preserve existing functions if any
    formatDate,
    formatTime,
    showLoading,
    hideLoading,
    showToast,
    confirmAction,
    validateEmail,
    validatePhone,
    calculateHours
};