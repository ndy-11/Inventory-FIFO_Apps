<?php
$host = 'localhost';
$user = 'root';
$password = '';

// Koneksi tanpa database
$conn = mysqli_connect($host, $user, $password);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Buat database
$sql = "CREATE DATABASE IF NOT EXISTS fifo_app";
if (mysqli_query($conn, $sql)) {
    echo "✅ Database 'fifo_app' berhasil dibuat.<br>";
} else {
    die("❌ Error membuat database: " . mysqli_error($conn));
}

// Pilih database
mysqli_select_db($conn, 'fifo_app');

// Buat tabel users
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Tabel 'users' berhasil dibuat.<br>";
} else {
    die("❌ Error membuat tabel: " . mysqli_error($conn));
}

// Insert user default
$sql = "INSERT IGNORE INTO users (username, password, email) VALUES 
('admin', '21232f297a57a5a743894a0e4a801fc3', 'admin@fifo.com')";

if (mysqli_query($conn, $sql)) {
    echo "✅ User admin berhasil ditambahkan.<br>";
} else {
    echo "⚠️ User admin sudah ada atau gagal ditambahkan.<br>";
}

mysqli_close($conn);

echo "<br><a href='login.php'><button>Login Sekarang</button></a>";
?>
