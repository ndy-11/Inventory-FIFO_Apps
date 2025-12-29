<?php
$host = 'localhost';
$user = 'root';
$password = '';

// Koneksi tanpa database
$conn = mysqli_connect($host, $user, $password);

if (!$conn) {
    die("❌ Koneksi gagal: " . mysqli_connect_error());
}

// Buat database
$sql = "CREATE DATABASE IF NOT EXISTS db_fifo";
if (mysqli_query($conn, $sql)) {
    echo "✅ Database 'db_fifo' berhasil dibuat.<br>";
} else {
    die("❌ Error: " . mysqli_error($conn));
}

// Pilih database
mysqli_select_db($conn, 'db_fifo');

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
    die("❌ Error: " . mysqli_error($conn));
}

// Tabel Kategori
$sql = "CREATE TABLE IF NOT EXISTS kategori (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) UNIQUE NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Tabel 'kategori' berhasil dibuat.<br>";
} else {
    die("❌ Error: " . mysqli_error($conn));
}

// Tabel Barang
$sql = "CREATE TABLE IF NOT EXISTS barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_barang VARCHAR(100) NOT NULL,
    kategori_id INT,
    jumlah INT NOT NULL,
    harga INT NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Tabel 'barang' berhasil dibuat.<br>";
} else {
    die("❌ Error: " . mysqli_error($conn));
}

// Cek apakah kolom kategori_id sudah ada
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'kategori_id'");
if (mysqli_num_rows($check_column) == 0) {
    $sql = "ALTER TABLE barang ADD COLUMN kategori_id INT AFTER nama_barang";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Kolom 'kategori_id' berhasil ditambahkan.<br>";
    } else {
        echo "⚠️ Gagal menambah kolom kategori_id.<br>";
    }
} else {
    echo "✅ Kolom 'kategori_id' sudah ada.<br>";
}

// Insert user default
$sql = "INSERT IGNORE INTO users (username, password, email) VALUES 
('admin', '21232f297a57a5a743894a0e4a801fc3', 'admin@fifo.com')";

if (mysqli_query($conn, $sql)) {
    echo "✅ User admin berhasil ditambahkan (Username: admin, Password: admin).<br>";
} else {
    echo "⚠️ User admin sudah ada atau gagal ditambahkan.<br>";
}

// Insert kategori default
$sql = "INSERT IGNORE INTO kategori (nama_kategori, deskripsi) VALUES 
('Elektronik', 'Barang-barang elektronik'),
('Pakaian', 'Barang pakaian dan tekstil'),
('Makanan', 'Barang makanan dan minuman'),
('Peralatan', 'Peralatan dan tools')";

if (mysqli_query($conn, $sql)) {
    echo "✅ Kategori default berhasil ditambahkan.<br>";
} else {
    echo "⚠️ Kategori sudah ada atau gagal ditambahkan.<br>";
}

mysqli_close($conn);

echo "<br><strong>Setup selesai!</strong><br>";
echo "<a href='login.php'><button style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Ke Halaman Login</button></a>";
?>
