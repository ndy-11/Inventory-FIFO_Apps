<?php
require_once 'config/auth.php';

echo "<h2>Perbaikan Database FIFO App</h2>";

// 1. Cek struktur tabel barang
$query = "DESCRIBE barang";
$result = mysqli_query($conn, $query);

echo "<h3>Struktur Tabel Barang:</h3>";
echo "<table border='1' cellpadding='10'>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Drop tabel barang dan buat ulang dengan struktur benar
echo "<h3>Memperbaiki Tabel Barang...</h3>";

$sql = "DROP TABLE IF EXISTS barang";
if (mysqli_query($conn, $sql)) {
    echo "✅ Tabel barang dihapus<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

$sql = "CREATE TABLE barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_barang VARCHAR(100) NOT NULL,
    kategori_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 0,
    harga INT NOT NULL DEFAULT 0,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Tabel barang berhasil dibuat ulang<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

echo "<h3>Verifikasi Struktur Tabel:</h3>";
$query = "DESCRIBE barang";
$result = mysqli_query($conn, $query);

echo "<table border='1' cellpadding='10'>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><strong>✅ Perbaikan selesai!</strong><br>";
echo "<a href='input_barang.php'><button style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Ke Input Barang</button></a>";
?>
