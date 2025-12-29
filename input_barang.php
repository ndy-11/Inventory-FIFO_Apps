<?php
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();

$success = '';
$error = '';

// ============ PROSES INPUT BARANG MASUK ============
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $barang_id = (int)$_POST['barang_id'];
    $jumlah_masuk = (int)$_POST['jumlah_masuk'];
    $harga_beli = (float)$_POST['harga_beli'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    
    // Validasi
    if ($barang_id <= 0) {
        $error = "Pilih barang terlebih dahulu!";
    } elseif ($jumlah_masuk <= 0) {
        $error = "Jumlah masuk harus lebih dari 0!";
    } elseif ($harga_beli < 0) {
        $error = "Harga beli tidak boleh negatif!";
    } else {
        // Insert ke stok_masuk (jika tabel ada)
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'stok_masuk'");
        if (mysqli_num_rows($check_table) > 0) {
            $query = "INSERT INTO stok_masuk (id_barang, tanggal_masuk, jumlah_masuk, harga_beli, keterangan) 
                      VALUES ($barang_id, NOW(), $jumlah_masuk, $harga_beli, '$keterangan')";
        } else {
            // Jika belum ada tabel stok_masuk, gunakan fifo_batches
            $check_fifo = mysqli_query($conn, "SHOW TABLES LIKE 'fifo_batches'");
            if (mysqli_num_rows($check_fifo) > 0) {
                $query = "INSERT INTO fifo_batches (barang_id, jumlah, harga_beli, tanggal_masuk, keterangan) 
                          VALUES ($barang_id, $jumlah_masuk, $harga_beli, NOW(), '$keterangan')";
            } else {
                $error = "Tabel stok_masuk atau fifo_batches belum ada!";
                $query = null;
            }
        }
        
        if ($query && mysqli_query($conn, $query)) {
            $success = "Barang masuk berhasil dicatat!";
        } elseif ($query) {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get daftar barang aktif
$query = "SELECT id_barang, kode, nama_barang, satuan, harga_beli FROM barang WHERE status_aktif = 1 ORDER BY nama_barang ASC";
$result = mysqli_query($conn, $query);
$barang_list = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Barang Masuk - FIFO App</title>
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

                    <?php if (isRole('admin')) { ?>
                    <a href="laporan.php" class="nav-link">
                        <i class="bi bi-bar-chart"></i> Laporan
                    </a>
                    <?php } ?>
                </nav>

            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-arrow-down-circle-fill"></i> Input Barang Masuk</h1>
                    <p>Catat barang yang masuk ke gudang</p>
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
                    <div class="col-lg-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-form-check"></i> Form Input Barang Masuk</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group mb-3">
                                        <label for="barang_id" class="form-label">Pilih Barang <span
                                                class="text-danger">*</span></label>
                                        <select id="barang_id" name="barang_id" class="form-select" required>
                                            <option value="">-- Pilih Barang --</option>
                                            <?php foreach ($barang_list as $brg): ?>
                                            <option value="<?php echo $brg['id_barang']; ?>"
                                                data-harga="<?php echo $brg['harga_beli']; ?>">
                                                <?php echo htmlspecialchars($brg['kode'] . ' - ' . $brg['nama_barang']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="jumlah_masuk" class="form-label">Jumlah Masuk <span
                                                class="text-danger">*</span></label>
                                        <input type="number" id="jumlah_masuk" name="jumlah_masuk" class="form-control"
                                            min="1" required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="harga_beli" class="form-label">Harga Beli Barang <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" id="harga_beli" name="harga_beli" class="form-control"
                                                min="0" step="0.01" required>
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="keterangan" class="form-label">Keterangan</label>
                                        <textarea id="keterangan" name="keterangan" class="form-control" rows="3"
                                            placeholder="Masukkan keterangan (opsional)"></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-check-lg"></i> Simpan Input Barang
                                        </button>
                                        <a href="input_barang.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Petunjuk</h5>
                            </div>
                            <div class="card-body" style="font-size: 13px; line-height: 1.8;">
                                <p><strong>Langkah Input Barang:</strong></p>
                                <ol>
                                    <li>Pilih barang dari daftar master barang</li>
                                    <li>Masukkan jumlah barang yang masuk</li>
                                    <li>Konfirmasi harga beli per unit</li>
                                    <li>Tambahkan keterangan jika diperlukan</li>
                                    <li>Klik Simpan untuk mencatat barang masuk</li>
                                </ol>
                                <hr>
                                <p><strong>⚠️ Catatan FIFO:</strong></p>
                                <p>Setiap input barang akan membuat batch baru berdasarkan tanggal masuk untuk
                                    perhitungan FIFO saat output.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-fill harga beli dari master barang
    document.getElementById('barang_id').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const harga = option.dataset.harga;
        if (harga) {
            document.getElementById('harga_beli').value = harga;
        }
    });
    </script>
</body>

</html>