<?php
require_once 'config/auth.php';

echo "<h2>Migrasi Database FIFO App</h2><hr>";

// 1. Cek dan tambah kolom kategori_id jika belum ada
echo "<h3>Step 1: Cek Kolom kategori_id</h3>";
$check = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'kategori_id'");

if (mysqli_num_rows($check) == 0) {
    echo "⚠️ Kolom kategori_id tidak ditemukan. Menambahkan...<br>";
    
    if (mysqli_query($conn, "ALTER TABLE barang ADD COLUMN kategori_id INT AFTER nama_barang")) {
        echo "✅ Kolom kategori_id berhasil ditambahkan<br>";
    } else {
        echo "❌ Error: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "✅ Kolom kategori_id sudah ada<br>";
}

// 2. Tambah Foreign Key jika belum ada
echo "<h3>Step 2: Cek Foreign Key</h3>";
$fk_check = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                                 WHERE TABLE_NAME='barang' AND COLUMN_NAME='kategori_id' AND REFERENCED_TABLE_NAME='kategori'");

if (mysqli_num_rows($fk_check) == 0) {
    echo "⚠️ Foreign Key tidak ditemukan. Menambahkan...<br>";
    
    if (mysqli_query($conn, "ALTER TABLE barang ADD CONSTRAINT fk_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE")) {
        echo "✅ Foreign Key berhasil ditambahkan<br>";
    } else {
        // Ignore error jika constraint sudah ada
        echo "⚠️ Foreign Key tidak perlu ditambahkan (mungkin sudah ada)<br>";
    }
} else {
    echo "✅ Foreign Key sudah ada<br>";
}

// 3. Assign kategori default untuk barang yang belum memiliki kategori
echo "<h3>Step 3: Assign Kategori Default</h3>";
$default_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM kategori LIMIT 1"));

if ($default_kategori) {
    $updated = mysqli_query($conn, "UPDATE barang SET kategori_id = " . $default_kategori['id'] . " WHERE kategori_id IS NULL");
    $rows_affected = mysqli_affected_rows($conn);
    echo "✅ $rows_affected barang diassign ke kategori default<br>";
} else {
    echo "⚠️ Tidak ada kategori default. Pastikan ada kategori terlebih dahulu.<br>";
}

// 4. Verifikasi Struktur
echo "<h3>Step 4: Verifikasi Struktur Tabel</h3>";
$describe = mysqli_query($conn, "DESCRIBE barang");
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #667eea; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = mysqli_fetch_assoc($describe)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>✅ Migrasi Database Selesai!</h3>";
echo "<a href='laporan.php' style='display: inline-block; margin-top: 20px;'><button style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Ke Halaman Laporan</button></a>";
?>
