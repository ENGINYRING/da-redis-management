<?php
/**
 * DirectAdmin Redis Management Plugin Bootstrap
 * Enhanced with security features and proper initialization
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Ensure we're running in the correct environment
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 70400) {
    die('PHP 7.4 or higher is required');
}

// Initialize global variables securely
global $_POST, $_GET, $_SERVER;

// Parse environment variables safely
$queryString = getenv('QUERY_STRING');
$postData = getenv('POST');

if ($queryString !== false) {
    parse_str($queryString, $_GET);
} else {
    $_GET = array();
}

if ($postData !== false) {
    parse_str($postData, $_POST);
} else {
    $_POST = array();
}

// Sanitize all input data
function sanitizeInput(&$data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            sanitizeInput($data[$key]);
        }
    } else {
        // Remove null bytes and control characters except tab, newline, carriage return
        $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        // Limit length to prevent DoS
        if (strlen($data) > 4096) {
            $data = substr($data, 0, 4096);
        }
    }
}

sanitizeInput($_GET);
sanitizeInput($_POST);

// Set error reporting for security
if (getenv('DA_REDIS_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

// Set up error logging
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/php-error.log');

// Include the controller with error handling
$controllerPath = dirname(__DIR__) . '/php/Controllers/RedisController.php';
if (!file_exists($controllerPath)) {
    error_log('Redis Controller not found: ' . $controllerPath);
    die('System error: Required components not found');
}

try {
    require_once $controllerPath;
} catch (Exception $e) {
    error_log('Failed to load Redis Controller: ' . $e->getMessage());
    die('System error: Failed to initialize plugin');
}

// Verify we're running under DirectAdmin
$username = getenv('USERNAME');
$userLevel = getenv('LEVEL');

if (empty($username)) {
    error_log('Redis plugin accessed without valid DirectAdmin session');
    die('Access denied: Invalid session');
}

// Additional security check for admin functions
if (basename($_SERVER['SCRIPT_NAME']) === 'index.html' && 
    strpos($_SERVER['SCRIPT_FILENAME'], '/admin/') !== false) {
    if ($userLevel !== 'admin' && $userLevel !== 'reseller') {
        error_log('Non-admin user attempted to access admin interface: ' . $username);
        die('Access denied: Administrator privileges required');
    }
}

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validReferers = [
        $_SERVER['HTTP_HOST'],
        parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST)
    ];
    
    if (!in_array(parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST), $validReferers)) {
        error_log('CSRF attempt detected for user: ' . $username);
        die('Access denied: Invalid request origin');
    }
}
?>

<!-- Enhanced Bootstrap CSS with custom styling -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    /* Container for iframe context */
    #iframe-container {
        display: inline-block;
        padding: 1rem;
        width: 100vw;
        overflow: auto;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
    
    /* Enhanced card styling */
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border-radius: 0.5rem;
    }
    
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #dee2e6;
        border-radius: 0.5rem 0.5rem 0 0 !important;
    }
    
    /* Button enhancements */
    .btn {
        border-radius: 0.375rem;
        font-weight: 500;
        transition: all 0.15s ease-in-out;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
    }
    
    /* Code styling */
    code {
        background-color: #f8f9fa;
        color: #e83e8c;
        padding: 0.2rem 0.4rem;
        border-radius: 0.25rem;
        font-size: 0.875em;
        border: 1px solid #dee2e6;
    }
    
    pre code {
        background-color: transparent;
        color: inherit;
        padding: 0;
        border: none;
    }
    
    /* Alert styling */
    .alert {
        border-radius: 0.5rem;
        border: none;
    }
    
    /* Table enhancements */
    .table {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    .table thead th {
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.875rem;
        letter-spacing: 0.05em;
    }
    
    /* Modal enhancements */
    .modal-content {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    }
    
    .modal-header {
        border-bottom: 1px solid #dee2e6;
        border-radius: 0.75rem 0.75rem 0 0;
    }
    
    /* Custom utility classes */
    .text-monospace {
        font-family: 'Courier New', Courier, monospace;
    }
    
    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
    
    /* Responsive improvements */
    @media (max-width: 768px) {
        #iframe-container {
            padding: 0.5rem;
        }
        
        .card {
            margin-bottom: 1rem;
        }
        
        .btn-group .btn {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        
        .table-responsive {
            font-size: 0.875rem;
        }
        
        h2 {
            font-size: 1.5rem;
        }
    }
    
    /* Loading spinner */
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Toast notifications */
    .toast-container {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 9999;
    }
    
    /* User select utilities */
    .user-select-all {
        user-select: all;
        cursor: pointer;
    }
    
    .user-select-none {
        user-select: none;
    }
    
    /* Focus improvements for accessibility */
    .btn:focus,
    .form-control:focus,
    .form-select:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        border-color: #86b7fe;
    }
    
    /* Print styles */
    @media print {
        .btn, .btn-group {
            display: none !important;
        }
        
        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }
    }
</style>

<!-- Bootstrap JavaScript for interactive components -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Global JavaScript utilities for the Redis Management plugin
window.RedisManagement = {
    // Show loading state
    showLoading: function(element, text = 'Loading...') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.innerHTML = '<span class="loading-spinner"></span>' + text;
            element.disabled = true;
        }
    },
    
    // Hide loading state
    hideLoading: function(element, originalText = '') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.innerHTML = originalText;
            element.disabled = false;
        }
    },
    
    // Show toast notification
    showToast: function(message, type = 'info', duration = 4000) {
        const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
        const toastId = 'toast-' + Date.now();
        
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto-remove after duration
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, duration);
        
        return toast;
    },
    
    // Create toast container if it doesn't exist
    createToastContainer: function() {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    },
    
    // Copy text to clipboard
    copyToClipboard: function(text, successMessage = 'Copied to clipboard!') {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                this.showToast(successMessage, 'success');
            }).catch(() => {
                this.fallbackCopyTextToClipboard(text, successMessage);
            });
        } else {
            this.fallbackCopyTextToClipboard(text, successMessage);
        }
    },
    
    // Fallback copy method
    fallbackCopyTextToClipboard: function(text, successMessage) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                this.showToast(successMessage, 'success');
            } else {
                this.showToast('Failed to copy text', 'error');
            }
        } catch (err) {
            this.showToast('Copy not supported by your browser', 'error');
        }
        
        document.body.removeChild(textArea);
    },
    
    // Format timestamp
    formatDate: function(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },
    
    // Validate username format
    validateUsername: function(username) {
        const pattern = /^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/;
        return pattern.test(username) && username.length <= 32;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for copy buttons
    document.querySelectorAll('[data-copy]').forEach(function(button) {
        button.addEventListener('click', function() {
            const text = this.getAttribute('data-copy');
            window.RedisManagement.copyToClipboard(text);
        });
    });
    
    // Add confirmation to dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(event) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                event.preventDefault();
                return false;
            }
        });
    });
    
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Error handling
window.addEventListener('error', function(event) {
    console.error('JavaScript error:', event.error);
    // Don't show error toasts for every JS error as it could be spammy
});
</script>
