<?php
/**
 * DATABASE MIGRATION SCRIPT - ADD MISSING COLUMNS TO BARANG TABLE
 * Script ini menambahkan kolom yang hilang ke tabel barang
 */

require_once 'config/auth.php';

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Migrasi Tabel Barang - FIFO App</title>";
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
echo "<h1><i class='bi bi-arrow-repeat'></i> Migrasi Tabel Barang</h1>";
echo "<p class='text-muted'>Tambahkan kolom yang hilang ke tabel master barang</p>";
echo "</div>";

// ============ STEP 1: CEK KOLOM status_aktif ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-search icon'></i> Step 1: Cek Kolom status_aktif</h4>";

$check_status = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'status_aktif'");
$has_status = mysqli_num_rows($check_status) > 0;

if ($has_status) {
    echo "<p>✅ Kolom status_aktif sudah ada</p>";
    $step1_ok = true;
} else {
    echo "<p>⚠️ Kolom status_aktif tidak ditemukan, akan ditambahkan...</p>";
    $step1_ok = false;
}

echo "</div>";

// ============ STEP 2: TAMBAH KOLOM status_aktif ============
if (!$has_status) {
    echo "<div class='step'>";
    echo "<h4><i class='bi bi-plus icon'></i> Step 2: Tambah Kolom status_aktif</h4>";
    
    $add_status_query = "ALTER TABLE barang ADD COLUMN status_aktif TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif' AFTER deskripsi";
    if (mysqli_query($conn, $add_status_query)) {
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

// ============ STEP 3: CEK KOLOM created_at ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-search icon'></i> Step 3: Cek Kolom created_at</h4>";

$check_created = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'created_at'");
$has_created = mysqli_num_rows($check_created) > 0;

if ($has_created) {
    echo "<p>✅ Kolom created_at sudah ada</p>";
    $step3_ok = true;
} else {
    echo "<p>⚠️ Kolom created_at tidak ditemukan, akan ditambahkan...</p>";
    $step3_ok = false;
}

echo "</div>";

// ============ STEP 4: TAMBAH KOLOM created_at ============
if (!$has_created) {
    echo "<div class='step'>";
    echo "<h4><i class='bi bi-plus icon'></i> Step 4: Tambah Kolom created_at</h4>";
    
    $add_created_query = "ALTER TABLE barang ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pembuatan' AFTER status_aktif";
    if (mysqli_query($conn, $add_created_query)) {
        echo "<p>✅ Kolom created_at berhasil ditambahkan</p>";
        $step4_ok = true;
    } else {
        echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
        $step4_ok = false;
    }
    
    echo "</div>";
} else {
    $step4_ok = true;
}

// ============ STEP 5: CEK KOLOM updated_at ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-search icon'></i> Step 5: Cek Kolom updated_at</h4>";

$check_updated = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'updated_at'");
$has_updated = mysqli_num_rows($check_updated) > 0;

if ($has_updated) {
    echo "<p>✅ Kolom updated_at sudah ada</p>";
    $step5_ok = true;
} else {
    echo "<p>⚠️ Kolom updated_at tidak ditemukan, akan ditambahkan...</p>";
    $step5_ok = false;
}

echo "</div>";

// ============ STEP 6: TAMBAH KOLOM updated_at ============
if (!$has_updated) {
    echo "<div class='step'>";
    echo "<h4><i class='bi bi-plus icon'></i> Step 6: Tambah Kolom updated_at</h4>";
    
    $add_updated_query = "ALTER TABLE barang ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu pembaruan' AFTER created_at";
    if (mysqli_query($conn, $add_updated_query)) {
        echo "<p>✅ Kolom updated_at berhasil ditambahkan</p>";
        $step6_ok = true;
    } else {
        echo "<p class='text-danger'>❌ Error: " . mysqli_error($conn) . "</p>";
        $step6_ok = false;
    }
    
    echo "</div>";
} else {
    $step6_ok = true;
}

// ============ STEP 7: VERIFIKASI STRUKTUR FINAL ============
echo "<div class='step'>";
echo "<h4><i class='bi bi-check-circle icon'></i> Step 7: Verifikasi Struktur Final</h4>";

$describe = mysqli_query($conn, "DESCRIBE barang");
if ($describe && mysqli_num_rows($describe) > 0) {
    echo "<p>✅ Struktur tabel final:</p>";
    echo "<table class='table table-sm table-bordered' style='font-size: 12px; margin-top: 10px;'>";
    echo "<thead style='background: #667eea; color: white;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    while ($row = mysqli_fetch_assoc($describe)) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] == 'YES' ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($row['Key'] ?: '-') . "</td>";
        echo "<td>" . ($row['Default'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    $step7_ok = true;
} else {
    echo "<p class='text-danger'>❌ Error: Gagal membaca struktur tabel!</p>";
    $step7_ok = false;
}
echo "</div>";

// ============ FINAL STATUS ============
echo "<div style='margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 10px;'>";
if ($step1_ok && $step2_ok && $step3_ok && $step4_ok && $step5_ok && $step6_ok && $step7_ok) {
    echo "<h3 style='color: #51cf66;'><i class='bi bi-check-circle'></i> Migrasi Berhasil!</h3>";
    echo "<p>Semua kolom yang diperlukan sudah ada di tabel barang.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='master_barang.php' class='btn btn-success'>";
    echo "<i class='bi bi-arrow-right'></i> Lanjut ke Master Barang";
    echo "</a>";
    echo "</div>";
} else {
    echo "<h3 style='color: #ff6b6b;'><i class='bi bi-exclamation-circle'></i> Migrasi Tidak Lengkap</h3>";
    echo "<p>Silakan cek error message di atas dan ulangi migrasi.</p>";
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
