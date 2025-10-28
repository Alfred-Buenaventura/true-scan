// Simplified main.js - Common functions for BPC Attendance

// Format date (m/d/Y)
function formatDate(dateStr) {
    const d = new Date(dateStr);
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const year = d.getFullYear();
    return `${month}/${day}/${year}`;
}

// Format time (12h format)
function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Show loading
function showLoading(message = 'Loading...') {
    const loader = document.createElement('div');
    loader.id = 'loader';
    loader.className = 'modal active';
    loader.innerHTML = `
        <div style="text-align: center;">
            <div class="spinner"></div>
            <p style="color: white; margin-top: 16px; font-weight: 600;">${message}</p>
        </div>
    `;
    document.body.appendChild(loader);
}

// Hide loading
function hideLoading() {
    const loader = document.getElementById('loader');
    if (loader) loader.remove();
}

// Confirm action
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// Validate email
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Validate phone (PH format)
function validatePhone(phone) {
    return /^(09|\+639)\d{9}$/.test(phone.replace(/\s/g, ''));
}

// Calculate hours between times
function calculateHours(start, end) {
    const startTime = new Date(`2000-01-01 ${start}`);
    const endTime = new Date(`2000-01-01 ${end}`);
    const diff = (endTime - startTime) / (1000 * 60 * 60);
    return Math.round(diff * 100) / 100;
}

// Add toast styles
const style = document.createElement('style');
style.textContent = `
    .toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 16px 24px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 14px;
        z-index: 10000;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .toast.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-success {
        background: #059669;
    }
    
    .toast-error {
        background: #dc2626;
    }
    
    .toast-warning {
        background: #f59e0b;
        color: #111827;
    }
    
    .spinner {
        width: 48px;
        height: 48px;
        border: 4px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Add active class to current nav item
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        if (item.getAttribute('href') === currentPage) {
            item.classList.add('active');
        }
    });
});

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Global object for easy access
window.BPC = {
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
