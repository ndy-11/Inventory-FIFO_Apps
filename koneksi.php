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

// Session Management: start only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

// Ambil role user
if (!function_exists('userRole')) {
    function userRole()
    {
        return $_SESSION['role'] ?? '';
    }
}

// Cek admin
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return userRole() === 'admin';
    }
}

// Cek kasir
if (!function_exists('isKasir')) {
    function isKasir()
    {
        return userRole() === 'kasir';
    }
}

// Redirect jika belum login
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
    }
}

// Khusus halaman laporan (kasir dilarang)
if (!function_exists('requireAdminForLaporan')) {
    function requireAdminForLaporan()
    {
        if (!isAdmin()) {
            echo "<script>
                    alert('Akses ditolak! Menu laporan hanya untuk Admin');
                    window.location='dashboard.php';
                  </script>";
            exit;
        }
    }
}

// Logout
if (!function_exists('logout')) {
    function logout()
    {
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Get user info
if (!function_exists('getUser')) {
    function getUser($id)
    {
        global $conn;
        $query = "SELECT * FROM users WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        return mysqli_fetch_assoc($result);
    }
}
