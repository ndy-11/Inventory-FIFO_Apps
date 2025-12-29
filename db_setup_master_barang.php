<?php
/**
 * DATABASE SETUP SCRIPT - MASTER BARANG TABLE
 * Setup otomatis untuk membuat tabel barang dengan struktur optimal
 */

require_once 'config/auth.php';

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Setup Master Barang - FIFO App</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>";
echo "<style>";
echo "body { background: #f5f5f5; padding: 20px; }";
echo ".setup-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }";
echo ".step { margin-bottom: 20px; padding: 15px; border-left: 4px solid #667eea; background: #f9f9f9; border-radius: 5px; }";
echo ".step.success { border-left-color: #51cf66; background: #f0fdf4; }";
echo ".step.error { border-left-color: #ff6b6b; background: #fef2f2; }";
echo ".step h4 { margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }";
echo ".step.success h4 { color: #51cf66; }";
echo ".step.error h4 { color: #ff6b6b; }";
echo ".icon { font-size: 20px; }";
echo ".progress-bar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='setup-container'>";
echo "<div style='text-align: center; margin-bottom: 30px;'>";
echo "<h1><i class='bi bi-tools'></i> Setup Master Barang</h1>";
echo "<p class='text-muted'>Konfigurasi otomatis tabel master barang untuk FIFO system</p>";
echo "</div>";

echo "<div class='progress mb-4' style='height: 25px;'>";
echo "<div class='progress-bar' role='progressbar' style='width: 100%'></div>";
echo "</div>";

// ============ STEP 0: CEK DAN REPAIR FOREIGN KEY LAMA ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-wrench icon'></i> Step 0: Perbaiki Foreign Key Lama</h4>";

$disable_fk = "SET FOREIGN_KEY_CHECKS = 0";
if (mysqli_query($conn, $disable_fk)) {
    echo "<p>✅ Foreign key checks dinonaktifkan</p>";
} else {
    echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Drop foreign key lama dari stok_keluar jika ada
$drop_fk_query = "ALTER TABLE stok_keluar DROP FOREIGN KEY stok_keluar_ibfk_1";
mysqli_query($conn, $drop_fk_query); // Ignore error jika FK tidak ada
echo "<p>✅ Foreign key lama dihapus (jika ada)</p>";

echo "</div>";

// ============ STEP 1: DROP TABEL LAMA ============
echo "<div class='step success'>";
echo "<h4><i class='bi bi-check-circle icon'></i> Step 1: Persiapan Tabel</h4>";

$drop_query = "DROP TABLE IF EXISTS barang";
if (mysqli_query($conn, $drop_query)) {
    echo "<p>✅ Tabel barang lama dihapus (jika ada)</p>";
    $step1_ok = true;
} else {
    echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
    $step1_ok = false;
}

echo "</div>";

// ============ STEP 2: CREATE TABLE BARU ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-table icon'></i> Step 2: Membuat Tabel Barang</h4>";

$create_query = "CREATE TABLE barang (
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'ID unik barang',
    id_barang INT UNIQUE COMMENT 'Alias untuk kompatibilitas dengan tabel lama',
    kode_barang VARCHAR(50) UNIQUE NOT NULL COMMENT 'Kode unik barang',
    nama_barang VARCHAR(100) NOT NULL COMMENT 'Nama lengkap produk',
    kategori_id INT COMMENT 'FK ke tabel kategori',
    satuan VARCHAR(20) NOT NULL COMMENT 'Unit (pcs, box, kg, dll)',
    harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Harga beli standar',
    harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Harga jual standar',
    stok_minimal INT NOT NULL DEFAULT 0 COMMENT 'Indikator stok hampir habis',
    deskripsi TEXT COMMENT 'Deskripsi produk',
    status_aktif TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL,
    INDEX idx_kode (kode_barang),
    INDEX idx_nama (nama_barang),
    INDEX idx_kategori (kategori_id),
    INDEX idx_status (status_aktif),
    INDEX idx_id_barang (id_barang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (mysqli_query($conn, $create_query)) {
    echo "<p>✅ Tabel barang berhasil dibuat dengan struktur optimal</p>";
    echo "<div class='step success' style='margin-top: 10px; border-left: none; padding: 10px;'>";
    echo "<strong>Fields yang dibuat:</strong>";
    echo "<ul style='font-size: 13px; margin: 10px 0;'>";
    echo "<li>id (INT, PRIMARY KEY)</li>";
    echo "<li>id_barang (INT, kompatibilitas)</li>";
    echo "<li>kode_barang (VARCHAR 50, UNIQUE)</li>";
    echo "<li>nama_barang (VARCHAR 100)</li>";
    echo "<li>kategori_id (INT, FOREIGN KEY)</li>";
    echo "<li>satuan (VARCHAR 20)</li>";
    echo "<li>harga_beli, harga_jual (DECIMAL 12,2)</li>";
    echo "<li>stok_minimal (INT)</li>";
    echo "<li>deskripsi (TEXT)</li>";
    echo "<li>status_aktif (TINYINT, soft delete)</li>";
    echo "<li>created_at, updated_at (TIMESTAMP)</li>";
    echo "</ul>";
    echo "</div>";
    $step2_ok = true;
} else {
    echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
    $step2_ok = false;
}
echo "</div>";

// ============ STEP 3: ADD NEW FOREIGN KEY ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-link icon'></i> Step 3: Buat Foreign Key Baru</h4>";

$add_fk_query = "ALTER TABLE stok_keluar ADD CONSTRAINT stok_keluar_ibfk_1_new FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE";
if (mysqli_query($conn, $add_fk_query)) {
    echo "<p>✅ Foreign key stok_keluar -> barang berhasil dibuat</p>";
    $step3_ok = true;
} else {
    echo "<p class='text-warning'>⚠️ Foreign key tidak perlu dibuat atau sudah ada: " . mysqli_error($conn) . "</p>";
    $step3_ok = true; // Tidak fatal
}
echo "</div>";

// ============ STEP 4: ENABLE FOREIGN KEY CHECKS ============
echo "<div class='step success'>";
echo "<h4><i class='bi bi-check-circle icon'></i> Step 4: Reaktifkan Foreign Key</h4>";

$enable_fk = "SET FOREIGN_KEY_CHECKS = 1";
if (mysqli_query($conn, $enable_fk)) {
    echo "<p>✅ Foreign key checks diaktifkan kembali</p>";
    $step4_ok = true;
} else {
    echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
    $step4_ok = false;
}
echo "</div>";

// ============ STEP 5: VERIFICATION ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-search icon'></i> Step 5: Verifikasi Struktur</h4>";

$describe = mysqli_query($conn, "DESCRIBE barang");
if ($describe && mysqli_num_rows($describe) > 0) {
    echo "<p>✅ Struktur tabel berhasil diverifikasi</p>";
    echo "<table class='table table-sm table-bordered' style='font-size: 12px; margin-top: 10px;'>";
    echo "<thead style='background: #667eea; color: white;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    while ($row = mysqli_fetch_assoc($describe)) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] == 'YES' ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($row['Key'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    $step5_ok = true;
} else {
    echo "<p class='text-danger'>❌ Error: Gagal membaca struktur tabel!</p>";
    $step5_ok = false;
}
echo "</div>";

// ============ FINAL STATUS ============
echo "<div style='margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 10px;'>";
if ($step1_ok && $step2_ok && $step3_ok && $step4_ok && $step5_ok) {
    echo "<h3 style='color: #51cf66;'><i class='bi bi-check-circle'></i> Setup Berhasil!</h3>";
    echo "<p>Tabel master barang sudah siap digunakan dengan struktur optimal untuk FIFO system.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='master_barang.php' class='btn btn-success'>";
    echo "<i class='bi bi-arrow-right'></i> Lanjut ke Master Barang";
    echo "</a>";
    echo "</div>";
} else {
    echo "<h3 style='color: #ff6b6b;'><i class='bi bi-exclamation-circle'></i> Setup Tidak Lengkap</h3>";
    echo "<p>Silakan cek error message di atas dan ulangi setup.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<button onclick='location.reload()' class='btn btn-warning'>";
    echo "<i class='bi bi-arrow-clockwise'></i> Ulangi Setup";
    echo "</button>";
    echo "</div>";
}
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
