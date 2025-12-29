<?php
// Koneksi Database
$host = 'localhost';
$user = 'root';
$password = 'root';
$database = 'fifo_app';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

// Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Small helper normalizer for roles (lowercase, trimmed)
 */
if (!function_exists('normalizeRole')) {
    function normalizeRole($r) {
        return $r !== null ? strtolower(trim($r)) : '';
    }
}

/**
 * Return role stored in session (raw)
 */
if (!function_exists('userRole')) {
    function userRole() {
        return $_SESSION['role'] ?? '';
    }
}

/**
 * Return effective role - real role or debug-overridden role (when active)
 */
if (!function_exists('getEffectiveRole')) {
    function getEffectiveRole() {
        if (!empty($_SESSION['debug_as_role'])) {
            return normalizeRole($_SESSION['debug_as_role']);
        }
        return normalizeRole(userRole());
    }
}

/**
 * Basic login check
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

/**
 * Require login
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['username'])) {
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Role wrappers (case-insensitive)
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isRole('admin');
    }
}
if (!function_exists('isKasir')) {
    function isKasir() {
        return isRole('kasir');
    }
}

/**
 * Check whether current user has role (supports string or array)
 */
if (!function_exists('isRole')) {
    function isRole($roleOrRoles) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $effective = getEffectiveRole();
        if ($effective === '') return false;
        $roles = is_array($roleOrRoles) ? $roleOrRoles : [$roleOrRoles];
        $rolesNorm = array_map('normalizeRole', $roles);
        return in_array($effective, $rolesNorm, true);
    }
}

/**
 * Require specific role(s) to access current page
 */
if (!function_exists('requireRole')) {
    function requireRole($roles) {
        requireLogin();
        if (!isRole($roles)) {
            // Redirect to dashboard with forbidden flag (or show 403)
            header('Location: index.php?error=forbidden');
            exit;
        }
    }
}

/**
 * Convenience to require admin role
 */
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireRole('admin');
    }
}

/**
 * ROLE DEBUGGING: enable switching effective role during development only (local IP)
 */

/* ipIsLocal: detect common local/private IPs */
if (!function_exists('ipIsLocal')) {
    function ipIsLocal($ip) {
        if (empty($ip)) return false;
        if ($ip === '::1' || $ip === '127.0.0.1') return true;
        if (strpos($ip, '::ffff:') === 0) {
            $ipv4 = substr($ip, 7);
            if ($ipv4 === '127.0.0.1') return true;
            $ip = $ipv4;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $a = (int)$parts[0];
            $b = (int)$parts[1];
            if ($a === 10) return true;
            if ($a === 172 && $b >= 16 && $b <= 31) return true;
            if ($a === 192 && $b === 168) return true;
        }
        return false;
    }
}

/* isDebugMode: debug only when ?debug=1 and local */
if (!function_exists('isDebugMode')) {
    function isDebugMode() {
        $isRequested = isset($_GET['debug']) && $_GET['debug'] === '1';
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return $isRequested && ipIsLocal($remote);
    }
}

/* Set debug role override (if ?as=... is provided). Only available on local debug mode */
if (isDebugMode() && isset($_GET['as'])) {
    $as = $_GET['as'];
    if ($as === '') {
        unset($_SESSION['debug_as_role']);
    } else {
        $_SESSION['debug_as_role'] = normalizeRole($as);
    }
    // Clean redirect to remove the 'as' param from URL, keep debug=1
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    // remove 'as' parameter occurrences
    $url = preg_replace('/([&?])as=[^&]*/', '$1', $url);
    // remove duplicate &/? if present at end
    $url = rtrim($url, '&?');
    header('Location: ' . $url);
    exit;
}

/* debugSession prints internal state for localhost + debug=1 */
if (!function_exists('debugSession')) {
    function debugSession($exit = false) {
        if (!isDebugMode()) return;
        echo '<div style="background:#fffbe6;color:#000;padding:10px;border:1px solid #ffd93d;margin:8px 0;font-family:monospace;">';
        echo '<strong>DEBUG INFO</strong><br>';
        echo '<pre style="white-space:pre-wrap;">';
        echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? '') . PHP_EOL;
        echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL . PHP_EOL;
        echo "GET: " . print_r($_GET, true) . PHP_EOL;
        echo "SESSION: " . print_r($_SESSION, true) . PHP_EOL;
        echo "userRole: " . (userRole() ?: ''); echo PHP_EOL;
        echo "getEffectiveRole: " . (getEffectiveRole() ?: ''); echo PHP_EOL;
        if (function_exists('isRole')) {
            echo "isRole('admin'): " . (isRole('admin') ? 'true' : 'false') . PHP_EOL;
            echo "isRole('kasir'): " . (isRole('kasir') ? 'true' : 'false') . PHP_EOL;
        }
        echo '</pre>';
        echo '</div>';
        if ($exit) exit;
    }
}

/* ...existing helper functions: logout(), getUser(), etc... */
if (!function_exists('logout')) {
    function logout() {
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

if (!function_exists('getUser')) {
    function getUser($id) {
        global $conn;
        $query = "SELECT * FROM users WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        return mysqli_fetch_assoc($result);
    }
}

?>