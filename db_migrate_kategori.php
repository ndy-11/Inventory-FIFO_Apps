<?php
/**
 * DATABASE MIGRATION SCRIPT - ADD STATUS_AKTIF TO KATEGORI TABLE
 */

require_once 'config/auth.php';

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Migrasi Tabel Kategori - FIFO App</title>";
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
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='setup-container'>";
echo "<div style='text-align: center; margin-bottom: 30px;'>";
echo "<h1><i class='bi bi-arrow-repeat'></i> Migrasi Tabel Kategori</h1>";
echo "<p class='text-muted'>Tambahkan kolom status_aktif ke tabel kategori</p>";
echo "</div>";

// ============ STEP 1: CEK KOLOM status_aktif ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-search icon'></i> Step 1: Cek Kolom status_aktif</h4>";

$check_status = mysqli_query($conn, "SHOW COLUMNS FROM kategori LIKE 'status_aktif'");
$has_status = mysqli_num_rows($check_status) > 0;

if ($has_status) {
    echo "<p>✅ Kolom status_aktif sudah ada</p>";
    $step1_ok = true;
} else {
    echo "<p>⚠️ Kolom status_aktif tidak ditemukan</p>";
    $step1_ok = false;
}

echo "</div>";

// ============ STEP 2: TAMBAH KOLOM status_aktif ============
if (!$has_status) {
    echo "<div class='step'>";
    echo "<h4><i class='bi bi-plus icon'></i> Step 2: Tambah Kolom status_aktif</h4>";
    
    $add_query = "ALTER TABLE kategori ADD COLUMN status_aktif TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif'";
    if (mysqli_query($conn, $add_query)) {
        echo "<p>✅ Kolom status_aktif berhasil ditambahkan</p>";
        $step2_ok = true;
    } else {
        echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
        $step2_ok = false;
    }
    
    echo "</div>";
} else {
    $step2_ok = true;
}

// ============ FINAL ============
echo "<div style='margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 10px;'>";
if ($step1_ok && $step2_ok) {
    echo "<h3 style='color: #51cf66;'><i class='bi bi-check-circle'></i> Migrasi Berhasil!</h3>";
    echo "<p>Kolom status_aktif sudah ditambahkan ke tabel kategori.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='master_barang.php' class='btn btn-success'>";
    echo "<i class='bi bi-arrow-right'></i> Lanjut ke Master Barang";
    echo "</a>";
    echo "</div>";
} else {
    echo "<h3 style='color: #ff6b6b;'><i class='bi bi-exclamation-circle'></i> Migrasi Gagal</h3>";
    echo "<div style='margin-top: 20px;'>";
    echo "<button onclick='location.reload()' class='btn btn-warning'>";
    echo "<i class='bi bi-arrow-clockwise'></i> Ulangi Migrasi";
    echo "</button>";
    echo "</div>";
}
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
