<?php
/**
 * DATABASE UPDATE SCRIPT - MASTER BARANG TABLE
 * Script ini membuat atau memperbarui struktur tabel barang
 * dengan field lengkap dan indeks yang optimal untuk FIFO system
 */

require_once 'config/auth.php';

echo "<h2>Database Update - Master Barang Table</h2><hr>";

// ============ PERSIAPAN: NONAKTIFKAN FOREIGN KEY CHECKS ============
echo "<h3>Step 1: Persiapan Tabel</h3>";

// Nonaktifkan foreign key checks agar bisa drop tabel
$disable_fk = "SET FOREIGN_KEY_CHECKS = 0";
if (mysqli_query($conn, $disable_fk)) {
    echo "✅ Foreign key checks dinonaktifkan<br>";
} else {
    echo "⚠️ Warning: " . mysqli_error($conn) . "<br>";
}

// Drop tabel lama jika ada
$drop_query = "DROP TABLE IF EXISTS barang";
if (mysqli_query($conn, $drop_query)) {
    echo "✅ Tabel barang lama dihapus (jika ada)<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

// ============ BUAT TABEL BARU ============
echo "<h3>Step 2: Membuat Tabel Barang dengan Struktur Optimal</h3>";

$create_query = "CREATE TABLE barang (
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'ID unik barang',
    kode_barang VARCHAR(50) UNIQUE NOT NULL COMMENT 'Kode unik barang (PRIMARY INDEX)',
    nama_barang VARCHAR(100) NOT NULL COMMENT 'Nama lengkap produk',
    kategori_id INT COMMENT 'FK ke tabel kategori',
    satuan VARCHAR(20) NOT NULL COMMENT 'Unit (pcs, box, kg, dll)',
    harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Harga beli standar',
    harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Harga jual standar',
    stok_minimal INT NOT NULL DEFAULT 0 COMMENT 'Indikator stok hampir habis',
    deskripsi TEXT COMMENT 'Deskripsi produk',
    status_aktif TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif (soft delete)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pembuatan',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu pembaruan',
    
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL,
    
    INDEX idx_kode (kode_barang),
    INDEX idx_nama (nama_barang),
    INDEX idx_kategori (kategori_id),
    INDEX idx_status (status_aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master data barang untuk FIFO system'";

if (mysqli_query($conn, $create_query)) {
    echo "✅ Tabel barang berhasil dibuat dengan struktur optimal<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

// ============ AKTIFKAN KEMBALI FOREIGN KEY CHECKS ============
echo "<h3>Step 3: Reaktifkan Foreign Key Checks</h3>";

$enable_fk = "SET FOREIGN_KEY_CHECKS = 1";
if (mysqli_query($conn, $enable_fk)) {
    echo "✅ Foreign key checks diaktifkan kembali<br>";
} else {
    echo "⚠️ Warning: " . mysqli_error($conn) . "<br>";
}

// ============ VERIFIKASI STRUKTUR ============
echo "<h3>Step 4: Verifikasi Struktur Tabel Barang</h3>";

$describe = mysqli_query($conn, "DESCRIBE barang");
if ($describe && mysqli_num_rows($describe) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = mysqli_fetch_assoc($describe)) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] == 'YES' ? '✓' : '-') . "</td>";
        echo "<td>" . ($row['Key'] ?: '-') . "</td>";
        echo "<td>" . ($row['Default'] ?: '-') . "</td>";
        echo "<td>" . ($row['Extra'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error: Gagal membaca struktur tabel!<br>";
}

// ============ INFO INDEX ============
echo "<h3>Step 5: Index Information</h3>";

$indexes = mysqli_query($conn, "SHOW INDEXES FROM barang");
if ($indexes && mysqli_num_rows($indexes) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>Key Name</th><th>Column Name</th><th>Cardinality</th><th>Seq in Index</th>";
    echo "</tr>";
    
    while ($row = mysqli_fetch_assoc($indexes)) {
        echo "<tr>";
        echo "<td><strong>" . $row['Key_name'] . "</strong></td>";
        echo "<td>" . $row['Column_name'] . "</td>";
        echo "<td>" . ($row['Cardinality'] ?? '-') . "</td>";
        echo "<td>" . $row['Seq_in_index'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ============ FOREIGN KEY ============
echo "<h3>Step 6: Foreign Key Information</h3>";

$fks = mysqli_query($conn, "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                             WHERE TABLE_NAME='barang' AND REFERENCED_TABLE_NAME IS NOT NULL");

if ($fks && mysqli_num_rows($fks) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>Constraint</th><th>Column</th><th>References</th>";
    echo "</tr>";
    
    while ($row = mysqli_fetch_assoc($fks)) {
        echo "<tr>";
        echo "<td><strong>" . $row['CONSTRAINT_NAME'] . "</strong></td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #999;'>Tidak ada foreign key atau belum ada referensi.</p>";
}

echo "<br><h3 style='color: #51cf66;'>✅ Update Database Selesai!</h3>";
echo "<p style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Struktur master barang sudah optimal untuk FIFO system.</strong><br>";
echo "Indeks pada kode_barang, nama_barang, kategori_id, dan status_aktif sudah dibuat.";
echo "</p>";

// Tombol untuk lanjut ke Master Barang
echo "<div style='margin-top: 30px;'>";
echo "<a href='master_barang.php' style='display: inline-block;'>";
echo "<button style='padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>";
echo "→ Ke Master Barang</button></a>";
echo "</div>";
?>
