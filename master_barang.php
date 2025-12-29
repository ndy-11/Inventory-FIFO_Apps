<?php

/**
 * MASTER BARANG MODULE - FIFO INVENTORY SYSTEM
 * ============================================================
 * File ini mengelola seluruh proses master data barang,
 * termasuk pembuatan, pembaruan, penghapusan, dan penampilan
 * daftar produk. Data ini menjadi dasar utama pengelolaan stok
 * dengan metode FIFO.
 *
 * FUNGSI UTAMA:
 * 1. createItem()       - Menambahkan barang baru ke database.
 * 2. updateItem()       - Mengubah data barang yang sudah ada.
 * 3. deactivateItem()   - Soft delete (nonaktifkan barang).
 * 4. getAllItems()      - Mengambil semua data barang aktif.
 * 5. getItemById()      - Mengambil detail satu barang.
 * 6. searchItem()       - Pencarian barang berdasarkan nama/.
 * 7. validateItem()     - Validasi input barang sebelum disimpan.
 * 8. countItems()       - Hitung total barang aktif.
 *
 * FIELD DATA MASTER BARANG:
 * - _barang      :  unik barang (PRIMARY INDEX)
 * - nama_barang      : Nama lengkap produk
 * - kategori      : FK ke tabel kategori
 * - satuan           : Unit (pcs, box, pack, kg, liter, meter)
 * - harga_beli       : Harga beli standar (DECIMAL 12,2)
 * - stok_minimal     : Indikator stok hampir habis (INT)
 * - deskripsi        : Deskripsi produk (TEXT)
 * - status_aktif     : 1=aktif, 0=nonaktif (TINYINT, soft delete)
 * - created_at       : Timestamp pembuatan
 * - updated_at       : Timestamp pembaruan terakhir
 *
 * CATATAN PENTING UNTUK FIFO:
 * ⚠️  Master Barang TIDAK menyimpan stok aktual!
 * ⚠️  Stok disimpan dalam tabel batch terpisah (fifo_batches / stok_masuk)
 * ⚠️  Setiap barang masuk membuat batch baru berdasarkan tanggal masuk
 * ⚠️  Master Barang hanya sebagai acuan identitas barang
 *
 * BEST PRACTICE:
 * ✓ Validasi ketat pada _barang (UNIQUE, max 50 char)
 * ✓ Gunakan soft delete (status_aktif=0) agar transaksi historis aman
 * ✓ Jangan taruh logika FIFO di module ini
 * ✓ Modul harus aman, ringan, tanpa proses berat
 * ✓ Selalu gunakan prepared statement jika diperlukan
 *
 * RELASI DATABASE:
 * barang.kategori -> kategori.id (LEFT JOIN, ON DELETE SET NULL)
 *
 * AUTHOR: FIFO Inventory System
 * VERSION: 1.0.0
 * LAST UPDATED: 2025
 */
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();


$success = '';
$error = '';
$field_errors = []; // associative array field => message for inline display
$search_query = '';

// ============ CEK TABEL BARANG EXISTENCE ============
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'barang'");
if (!$check_table || mysqli_num_rows($check_table) == 0) {
    // Tabel belum ada, redirect ke setup database
    header("Location: db_setup_master_barang.php");
    exit;
}

// ============ CEK KOLOM PENTING ============
$check_status = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'status_aktif'");
if (!$check_status || mysqli_num_rows($check_status) == 0) {
    // Kolom belum ada, redirect ke migrasi
    header("Location: db_migrate_barang.php");
    exit;
}

// ============ FUNGSI MASTER BARANG ============

/**
 * Validasi input barang sebelum disimpan
 * @param array $data - Data dari form POST
 * @param mysqli $conn - Koneksi database
 * @param int|null $edit_id - ID barang jika edit (untuk cek duplikasi )
 * @return array ['valid' => bool, 'messages' => array]
 */
function validateItem($data, $conn, $edit_id = null)
{
    $messages = [];
    $field_errors = [];

    // Validasi  Barang - REQUIRED, UNIQUE, MAX 50
    if (empty($data['kode'])) {
        $msg = "Kode barang tidak boleh kosong!";
        $messages[] = $msg;
        $field_errors['kode'] = $msg;
    } elseif (strlen($data['kode']) > 8) {
        $msg = "Kode barang maksimal 8 karakter!";
        $messages[] = $msg;
        $field_errors['kode'] = $msg;
    } else {
        // Cek duplikasi kode
        $query = "SELECT id_barang FROM barang WHERE kode = '" . mysqli_real_escape_string($conn, $data['kode']) . "'";
        if ($edit_id) {
            $query .= " AND id_barang != '$edit_id'";
        }
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $msg = "Kode barang sudah digunakan!";
            $messages[] = $msg;
            $field_errors['kode'] = $msg;
        }
    }

    // Validasi Nama Barang - REQUIRED, MAX 100
    if (empty($data['nama_barang'])) {
        $msg = "Nama barang tidak boleh kosong!";
        $messages[] = $msg;
        $field_errors['nama_barang'] = $msg;
    } elseif (strlen($data['nama_barang']) > 100) {
        $msg = "Nama barang maksimal 100 karakter!";
        $messages[] = $msg;
        $field_errors['nama_barang'] = $msg;
    }

    // Validasi Kategori - REQUIRED
    if (empty($data['kategori'])) {
        $msg = "Kategori barang harus dipilih!";
        $messages[] = $msg;
        $field_errors['kategori'] = $msg;
    }

    // Validasi Satuan - REQUIRED
    if (empty($data['satuan'])) {
        $msg = "Satuan barang tidak boleh kosong!";
        $messages[] = $msg;
        $field_errors['satuan'] = $msg;
    }

    // Validasi Harga Beli - REQUIRED, NON-NEGATIVE
    if (!isset($data['harga_beli']) || $data['harga_beli'] === '' || !is_numeric($data['harga_beli']) || $data['harga_beli'] < 0) {
        $msg = "Harga beli harus diisi dan tidak boleh negatif!";
        $messages[] = $msg;
        $field_errors['harga_beli'] = $msg;
    }
    // Validasi Stok Minimal - NON-NEGATIVE
    if (isset($data['stok_minimal']) && $data['stok_minimal'] !== '' && (!is_numeric($data['stok_minimal']) || $data['stok_minimal'] < 0)) {
        $msg = "Stok minimal tidak boleh negatif!";
        $messages[] = $msg;
        $field_errors['stok_minimal'] = $msg;
    }

    return [
        'valid' => count($messages) === 0,
        'messages' => $messages,
        'field_errors' => $field_errors
    ];
}

/**
 * Tambah barang baru ke master
 * @param array $data - Data dari form
 * @param mysqli $conn - Koneksi database
 * @return array ['success' => bool, 'message/messages' => string/array, 'id' => int]
 */
function createItem($data, $conn)
{
    $validation = validateItem($data, $conn);
    if (!$validation['valid']) {
        return ['success' => false, 'messages' => $validation['messages'], 'field_errors' => $validation['field_errors']];
    }

    $kode = mysqli_real_escape_string($conn, $data['kode']);
    $nama_barang = mysqli_real_escape_string($conn, $data['nama_barang']);
    $kategori = (int)$data['kategori'];
    $satuan = mysqli_real_escape_string($conn, $data['satuan']);
    $harga_beli = (float)$data['harga_beli'];
    $stok_minimal = (int)($data['stok_minimal'] ?? 0);
    $deskripsi = mysqli_real_escape_string($conn, $data['deskripsi'] ?? '');

    $query = "INSERT INTO barang 
              (kode, nama_barang, kategori, satuan, harga_beli, stok_minimal, deskripsi, status_aktif, created_at) 
              VALUES ('$kode', '$nama_barang', $kategori, '$satuan', $harga_beli, $stok_minimal, '$deskripsi', 1, NOW())";

    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'message' => 'Barang berhasil ditambahkan!', 'id' => mysqli_insert_id($conn)];
    } else {
        return ['success' => false, 'messages' => ['Error: ' . mysqli_error($conn)]];
    }
}

/**
 * Update barang yang sudah ada
 * @param int $id - ID barang
 * @param array $data - Data baru
 * @param mysqli $conn - Koneksi database
 * @return array ['success' => bool, 'message/messages' => string/array]
 */
function updateItem($id, $data, $conn)
{
    $validation = validateItem($data, $conn, $id);
    if (!$validation['valid']) {
        return ['success' => false, 'messages' => $validation['messages'], 'field_errors' => $validation['field_errors']];
    }

    $id = (int)$id;
    $kode = mysqli_real_escape_string($conn, $data['kode']);
    $nama_barang = mysqli_real_escape_string($conn, $data['nama_barang']);
    $kategori = (int)$data['kategori'];
    $satuan = mysqli_real_escape_string($conn, $data['satuan']);
    $harga_beli = (float)$data['harga_beli'];
    $stok_minimal = (int)($data['stok_minimal'] ?? 0);
    $deskripsi = mysqli_real_escape_string($conn, $data['deskripsi'] ?? '');
    $status_aktif = isset($data['status_aktif']) ? 1 : 0;

    $query = "UPDATE barang SET 
              kode = '$kode',
              nama_barang = '$nama_barang',
              kategori = $kategori,
              satuan = '$satuan',
              harga_beli = $harga_beli,
              stok_minimal = $stok_minimal,
              deskripsi = '$deskripsi',
              status_aktif = $status_aktif,
              updated_at = NOW()
              WHERE id_barang = $id";

    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'message' => 'Barang berhasil diperbarui!'];
    } else {
        return ['success' => false, 'messages' => ['Error: ' . mysqli_error($conn)]];
    }
}

/**
 * Soft delete - tandai barang tidak aktif
 * Transaksi historis tetap aman, data tidak dihapus dari DB
 * @param int $id - ID barang
 * @param mysqli $conn - Koneksi database
 * @return array ['success' => bool, 'message/messages' => string/array]
 */
function deactivateItem($id, $conn)
{
    $id = (int)$id;
    $query = "UPDATE barang SET status_aktif = '0', updated_at = NOW() WHERE id_barang = $id";

    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'message' => 'Barang berhasil dinonaktifkan!'];
    } else {
        return ['success' => false, 'messages' => ['Error: ' . mysqli_error($conn)]];
    }
}

/**
 * Ambil semua barang aktif dengan pagination
 * @param mysqli $conn - Koneksi database
 * @param int|null $limit - Jumlah data per halaman
 * @param int|null $offset - Offset untuk pagination
 * @return array Daftar barang
 */
function getAllItems($conn, $limit = null, $offset = null)
{
    $query = "SELECT b.*, k.nama_kategori 
              FROM barang b 
              LEFT JOIN kategori k ON b.kategori = k.id 
              WHERE b.status_aktif = 1
              ORDER BY b.created_at DESC";

    if ($limit && $offset !== null) {
        $query .= " LIMIT $limit OFFSET $offset";
    }

    $result = mysqli_query($conn, $query);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * Ambil detail barang berdasarkan ID
 * @param int $id - ID barang
 * @param mysqli $conn - Koneksi database
 * @return array|null Detail barang atau null jika tidak ditemukan
 */
function getItemById($id, $conn)
{
    $id = (int)$id;
    $query = "SELECT b.*, k.nama_kategori 
              FROM barang b 
              LEFT JOIN kategori k ON b.kategori = k.id 
              WHERE b.id_barang = $id";

    $result = mysqli_query($conn, $query);
    return $result ? mysqli_fetch_assoc($result) : null;
}

/**
 * Cari barang berdasarkan nama atau kode
 * Case-insensitive, LIKE search
 * @param string $keyword - Kata kunci pencarian
 * @param mysqli $conn - Koneksi database
 * @return array Hasil pencarian barang
 */
function searchItem($keyword, $conn)
{
    $keyword = mysqli_real_escape_string($conn, $keyword);
    $query = "SELECT b.*, k.nama_kategori 
              FROM barang b 
              LEFT JOIN kategori k ON b.kategori = k.id 
              WHERE b.status_aktif = 1 AND (b.nama_barang LIKE '%$keyword%' OR b.kode LIKE '%$keyword%')
              ORDER BY b.nama_barang ASC";

    $result = mysqli_query($conn, $query);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * Hitung total barang aktif
 * @param mysqli $conn - Koneksi database
 * @return int Total barang aktif
 */
function countItems($conn)
{
    $query = "SELECT COUNT(*) as total FROM barang WHERE status_aktif = 1";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// ============ PROSES FORM ============

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'tambah') {
        $result = createItem($_POST, $conn);
        if ($result['success']) {
            $success = $result['message'];
            header("Location: master_barang.php");
            exit;
        } else {
            $error = implode('<br>', $result['messages']);
            $field_errors = $result['field_errors'] ?? [];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $result = updateItem($_POST['id'], $_POST, $conn);
        if ($result['success']) {
            $success = $result['message'];
            header("Location: master_barang.php");
            exit;
        } else {
            $error = implode('<br>', $result['messages']);
            $field_errors = $result['field_errors'] ?? [];
        }
    }
}

// Hapus barang (soft delete)
if (isset($_GET['deactivate'])) {
    $result = deactivateItem($_GET['deactivate'], $conn);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = implode('<br>', $result['messages']);
    }
}

// Get data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_data = getItemById($_GET['edit'], $conn);
    if (!$edit_data) {
        $error = "Barang tidak ditemukan!";
    }
}

// --- MOVED: Baca parameter filter dari query string (pindah ke sini supaya tersedia sebelum build WHERE)
$filter_kategori = isset($_GET['filter_kategori']) ? $_GET['filter_kategori'] : '';
$filter_satuan = isset($_GET['filter_satuan']) ? $_GET['filter_satuan'] : '';
$search_query = isset($_GET['cari']) ? $_GET['cari'] : '';

// Build filtered query (search + kategori + satuan)
$where = ["b.status_aktif = 1"];
if ($search_query !== '') {
    $kw = mysqli_real_escape_string($conn, $search_query);
    $where[] = "(b.nama_barang LIKE '%$kw%' OR b.kode LIKE '%$kw%')";
}
if ($filter_kategori !== '') {
    // support both kategori and kategori_id storage
    $fk = intval($filter_kategori);
    $where[] = "(b.kategori = '$fk' OR b.kategori = '$fk')";
}
if ($filter_satuan !== '') {
    $fs = mysqli_real_escape_string($conn, $filter_satuan);
    $where[] = "b.satuan = '$fs'";
}
$where_clause = implode(' AND ', $where);
$query = "SELECT b.*, k.nama_kategori 
          FROM barang b 
          LEFT JOIN kategori k ON b.kategori = k.id 
          WHERE $where_clause 
          ORDER BY b.created_at DESC";
$result = mysqli_query($conn, $query);
$barang_list = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

// ============ GET KATEGORI UNTUK DROPDOWN ============
// Cek apakah kategori punya kolom status_aktif
$check_kategori_status = mysqli_query($conn, "SHOW COLUMNS FROM kategori LIKE 'status_aktif'");
$has_kategori_status = mysqli_num_rows($check_kategori_status) > 0;

// Query kategori dengan atau tanpa filter status_aktif
if ($has_kategori_status) {
    $query = "SELECT id, nama_kategori FROM kategori WHERE status_aktif = 1 ORDER BY nama_kategori ASC";
} else {
    $query = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC";
}

$result = mysqli_query($conn, $query);
$kategori_list = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

// Ambil daftar satuan unik untuk filter
$satuan_res = mysqli_query($conn, "SELECT DISTINCT COALESCE(satuan,'') AS satuan FROM barang WHERE COALESCE(satuan,'') <> '' ORDER BY satuan ASC");
$satuan_list = $satuan_res ? mysqli_fetch_all($satuan_res, MYSQLI_ASSOC) : [];

// Baca parameter filter dari query string
$filter_kategori = isset($_GET['filter_kategori']) ? $_GET['filter_kategori'] : '';
$filter_satuan = isset($_GET['filter_satuan']) ? $_GET['filter_satuan'] : '';
$search_query = isset($_GET['cari']) ? $_GET['cari'] : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Barang - FIFO App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-box-seam"></i> FIFO App
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="user-badge">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?>
                </span>
                <a href="logout.php" class="btn btn-sm btn-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row" style="min-height: calc(100vh - 70px);">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar d-none d-md-block">
                <nav class="nav flex-column">
                    <a href="index.php" class="nav-link">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>

                    <a href="master_barang.php" class="nav-link">
                        <i class="bi bi-box-seam"></i> Master Barang
                    </a>

                    <a href="input_barang.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Input Barang
                    </a>

                    <a href="output_barang.php" class="nav-link">
                        <i class="bi bi-dash-circle"></i> Output Barang
                    </a>

                    <a href="kategori.php" class="nav-link">
                        <i class="bi bi-tags"></i> Kategori
                    </a>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                        <a href="laporan.php" class="nav-link">
                            <i class="bi bi-bar-chart"></i> Laporan
                        </a>
                    <?php } ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-collection-fill"></i> Master Barang</h1>
                    <p>Kelola data master produk untuk FIFO inventory system</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-custom alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-custom alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form Tambah/Edit -->
                    <div class="col-lg-4">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-<?php echo $edit_data ? 'pencil-square' : 'plus-circle'; ?>"></i>
                                    <?php echo $edit_data ? 'Edit Barang' : 'Tambah Barang'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="itemForm" novalidate>
                                    <input type="hidden" name="action"
                                        value="<?php echo $edit_data ? 'edit' : 'tambah'; ?>">
                                    <?php if ($edit_data): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_data['id_barang']; ?>">
                                    <?php endif; ?>

                                    <div class="form-group mb-3">
                                        <label for="kode" class="form-label">Kode Barang <span
                                                class="text-danger">*</span></label>
                                        <input type="text" id="kode" name="kode" class="form-control"
                                            value="<?php echo isset($_POST['kode']) ? htmlspecialchars($_POST['kode']) : ($edit_data ? htmlspecialchars($edit_data['kode']) : ''); ?>"
                                            placeholder="Misal: BR001" required>
                                        <small class="text-muted">Kode unik identitas barang (max 50 karakter)</small>
                                        <div id="err_kode" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['kode'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="nama_barang" class="form-label">Nama Barang <span
                                                class="text-danger">*</span></label>
                                        <input type="text" id="nama_barang" name="nama_barang" class="form-control"
                                            value="<?php echo isset($_POST['nama_barang']) ? htmlspecialchars($_POST['nama_barang']) : ($edit_data ? htmlspecialchars($edit_data['nama_barang']) : ''); ?>"
                                            placeholder="Masukkan nama produk" required>
                                        <div id="err_nama_barang" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['nama_barang'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="kategori" class="form-label">Kategori <span
                                                class="text-danger">*</span></label>
                                        <select id="kategori" name="kategori" class="form-select" required>
                                            <option value="">-- Pilih Kategori --</option>
                                            <?php foreach ($kategori_list as $kat): ?>
                                                <option value="<?php echo $kat['id']; ?>"
                                                    <?php echo ((isset($_POST['kategori']) && $_POST['kategori'] == $kat['id']) || ($edit_data && $edit_data['kategori'] == $kat['id'])) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="err_kategori" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['kategori'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="satuan" class="form-label">Satuan <span
                                                class="text-danger">*</span></label>
                                        <select id="satuan" name="satuan" class="form-select" required>
                                            <option value="">-- Pilih Satuan --</option>
                                            <option value="pcs"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'pcs') || ($edit_data && $edit_data['satuan'] == 'pcs')) ? 'selected' : ''; ?>>
                                                Piece (pcs)</option>
                                            <option value="box"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'box') || ($edit_data && $edit_data['satuan'] == 'box')) ? 'selected' : ''; ?>>
                                                Box</option>
                                            <option value="pack"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'pack') || ($edit_data && $edit_data['satuan'] == 'pack')) ? 'selected' : ''; ?>>
                                                Pack</option>
                                            <option value="kg"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'kg') || ($edit_data && $edit_data['satuan'] == 'kg')) ? 'selected' : ''; ?>>
                                                Kilogram (kg)</option>
                                            <option value="liter"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'liter') || ($edit_data && $edit_data['satuan'] == 'liter')) ? 'selected' : ''; ?>>
                                                Liter</option>
                                            <option value="meter"
                                                <?php echo ((isset($_POST['satuan']) && $_POST['satuan'] == 'meter') || ($edit_data && $edit_data['satuan'] == 'meter')) ? 'selected' : ''; ?>>
                                                Meter</option>
                                        </select>
                                        <div id="err_satuan" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['satuan'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="harga_beli" class="form-label">Harga Beli <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" id="harga_beli" name="harga_beli" class="form-control"
                                                value="<?php echo isset($_POST['harga_beli']) ? htmlspecialchars($_POST['harga_beli']) : ($edit_data ? htmlspecialchars($edit_data['harga_beli']) : ''); ?>"
                                                min="0" step="0.01" required>
                                        </div>
                                        <div id="err_harga_beli" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['harga_beli'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="stok_minimal" class="form-label">Stok Minimal</label>
                                        <input type="number" id="stok_minimal" name="stok_minimal" class="form-control"
                                            value="<?php echo isset($_POST['stok_minimal']) ? htmlspecialchars($_POST['stok_minimal']) : ($edit_data ? htmlspecialchars($edit_data['stok_minimal']) : '0'); ?>"
                                            min="0" placeholder="Indikator stok hampir habis">
                                        <div id="err_stok_minimal" class="text-danger small mt-1">
                                            <?php echo htmlspecialchars($field_errors['stok_minimal'] ?? ''); ?></div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="deskripsi" class="form-label">Deskripsi</label>
                                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="2"
                                            placeholder="Deskripsi produk (opsional)"><?php echo isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : ($edit_data ? htmlspecialchars($edit_data['deskripsi']) : ''); ?></textarea>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input type="checkbox" id="status_aktif" name="status_aktif"
                                            class="form-check-input"
                                            <?php echo (isset($_POST['status_aktif']) ? 'checked' : ((!$edit_data || $edit_data['status_aktif']) ? 'checked' : '')); ?>>
                                        <label class="form-check-label" for="status_aktif">
                                            Status Aktif
                                        </label>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-<?php echo $edit_data ? 'pencil' : 'plus-lg'; ?>"></i>
                                            <?php echo $edit_data ? 'Update' : 'Tambah'; ?>
                                        </button>
                                        <?php if ($edit_data): ?>
                                            <a href="master_barang.php" class="btn btn-secondary">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var form = document.getElementById('itemForm');
                                if (!form) return;

                                function setErr(id, msg) {
                                    var el = document.getElementById('err_' + id);
                                    if (el) el.textContent = msg || '';
                                }

                                form.addEventListener('submit', function(e) {
                                    // clear previous client errors (allow server errors to remain)
                                    ['kode', 'nama_barang', 'kategori', 'satuan', 'harga_beli',
                                        'stok_minimal'
                                    ].forEach(function(f) {
                                        setErr(f, '');
                                    });

                                    var errors = {};
                                    var kode = (form.kode.value || '').trim();
                                    if (!kode) errors.kode = 'Kode barang tidak boleh kosong!';
                                    else if (kode.length > 50) errors.kode =
                                        'Kode barang maksimal 50 karakter!';

                                    var nama = (form.nama_barang.value || '').trim();
                                    if (!nama) errors.nama_barang = 'Nama barang tidak boleh kosong!';
                                    else if (nama.length > 100) errors.nama_barang =
                                        'Nama barang maksimal 100 karakter!';

                                    var kategori = form.kategori.value;
                                    if (!kategori) errors.kategori = 'Kategori barang harus dipilih!';

                                    var satuan = form.satuan.value;
                                    if (!satuan) errors.satuan = 'Satuan barang tidak boleh kosong!';

                                    var harga = form.harga_beli.value;
                                    if (harga === '' || isNaN(harga) || Number(harga) < 0) errors
                                        .harga_beli = 'Harga beli harus diisi dan tidak boleh negatif!';

                                    var stokmin = form.stok_minimal.value;
                                    if (stokmin !== '' && (isNaN(stokmin) || Number(stokmin) < 0)) errors
                                        .stok_minimal = 'Stok minimal tidak boleh negatif!';

                                    // display
                                    Object.keys(errors).forEach(function(k) {
                                        setErr(k, errors[k]);
                                    });

                                    if (Object.keys(errors).length > 0) {
                                        e.preventDefault();
                                        // focus first invalid field
                                        var first = Object.keys(errors)[0];
                                        var fld = form[first];
                                        if (fld) fld.focus();
                                        if (form.scrollIntoView) form.scrollIntoView({
                                            behavior: 'smooth',
                                            block: 'center'
                                        });
                                    }
                                });
                            });
                        </script>
                    </div>
                    <!-- Daftar Barang -->
                    <div class="col-lg-8">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Master Barang</h5>
                            </div>
                            <div class="card-body">
                                <!-- Search & Filter -->
                                <form method="GET" class="mb-4">
                                    <div class="row g-2">
                                        <!-- Search bar (full width) -->
                                        <div class="col-12">
                                            <div class="input-group">
                                                <input type="text" name="cari" class="form-control"
                                                    placeholder="Cari barang berdasarkan nama atau kode"
                                                    value="<?php echo htmlspecialchars($search_query); ?>">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                                <?php if ($search_query || $filter_kategori || $filter_satuan): ?>
                                                    <a href="master_barang.php" class="btn btn-secondary">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Filters (side-by-side on md+) -->
                                        <div class="col-12 col-md-6 mt-2">
                                            <select name="filter_kategori" class="form-select">
                                                <option value="">Semua Kategori</option>
                                                <?php foreach ($kategori_list as $kat): ?>
                                                    <option value="<?php echo $kat['id']; ?>"
                                                        <?php if ($filter_kategori == $kat['id']) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6 mt-2">
                                            <div class="input-group">
                                                <select name="filter_satuan" class="form-select">
                                                    <option value="">Semua Satuan</option>
                                                    <?php foreach ($satuan_list as $s): ?>
                                                        <option value="<?php echo htmlspecialchars($s['satuan']); ?>"
                                                            <?php if ($filter_satuan == $s['satuan']) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars($s['satuan']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-outline-primary" type="submit">Filter</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <!-- Table -->
                                <?php if (count($barang_list) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>Kode Barang</th>
                                                    <th>Nama Barang</th>
                                                    <th>Kategori</th>
                                                    <th>Satuan</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = 1;
                                                foreach ($barang_list as $brg): ?>
                                                    <tr>
                                                        <td><?php echo $no++; ?></td>
                                                        <td>
                                                            <span
                                                                class="badge bg-secondary"><?php echo htmlspecialchars($brg['kode']); ?></span>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($brg['nama_barang']); ?></strong><br>
                                                            <small
                                                                class="text-muted"><?php echo htmlspecialchars(substr($brg['deskripsi'], 0, 50)); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-custom badge-success">
                                                                <?php echo htmlspecialchars($brg['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($brg['satuan']); ?></td>
                                                        <td>
                                                            <a href="master_barang.php?edit=<?php echo $brg['id_barang']; ?>"
                                                                class="btn btn-sm btn-warning">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="master_barang.php?deactivate=<?php echo $brg['id_barang']; ?>"
                                                                class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Nonaktifkan barang ini?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="text-muted mt-3">
                                            <?php echo $search_query ? 'Tidak ada barang yang cocok dengan pencarian.' : 'Belum ada master barang. Silakan tambahkan barang baru!'; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Info Box -->
                        <div class="card card-custom mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Catatan Penting</h5>
                            </div>
                            <div class="card-body" style="font-size: 13px; line-height: 1.8;">
                                <p><strong>⚠️ FIFO System</strong></p>
                                <p>Master Barang <strong>BUKAN</strong> menyimpan stok aktual. Stok disimpan dalam
                                    sistem FIFO yang terpisah.</p>
                                <ul style="padding-left: 20px;">
                                    <li>Stok masuk → fifo_batches</li>
                                    <li>Stok keluar → mengikuti FIFO order</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>