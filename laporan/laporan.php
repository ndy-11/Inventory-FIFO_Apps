<?php
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>
            alert('Akses ditolak! Menu laporan hanya untuk admin');
            window.location='index.php';
          </script>";
    exit;
}

$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Get kategori untuk filter
$query = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$result = mysqli_query($conn, $query);
$kategori_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get barang dengan query yang lebih aman
$where = "WHERE 1=1";
if ($filter_kategori) {
    $where .= " AND b.kategori_id = '$filter_kategori'";
}

// Query yang lebih aman dengan LEFT JOIN
$query = "SELECT b.id, b.nama_barang, b.kategori_id, b.jumlah, b.harga, b.keterangan, b.created_at,
          COALESCE(k.nama_kategori, 'Tanpa Kategori') as nama_kategori 
          FROM barang b 
          LEFT JOIN kategori k ON b.kategori_id = k.id 
          $where 
          ORDER BY b.created_at DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error Query: " . mysqli_error($conn));
}

$barang_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Hitung statistik
$total_barang = count($barang_list);
$total_nilai = 0;
foreach ($barang_list as $brg) {
    $total_nilai += ($brg['jumlah'] * $brg['harga']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - FIFO App</title>
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
                    <h1><i class="bi bi-bar-chart-fill"></i> Laporan Barang</h1>
                    <p>Analisis dan laporan inventori gudang</p>
                </div>

                <!-- Stats -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h6>Total Jenis Barang</h6>
                        <h3><?php echo $total_barang; ?></h3>
                        <small class="text-muted">Barang aktif</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #51cf66;">
                        <h6>Total Unit</h6>
                        <h3><?php echo array_sum(array_column($barang_list, 'jumlah')); ?></h3>
                        <small class="text-muted">Unit barang</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #4ecdc4;">
                        <h6>Total Nilai</h6>
                        <h3>Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></h3>
                        <small class="text-muted">Nilai inventori</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ffd93d;">
                        <h6>Kategori</h6>
                        <h3><?php echo count($kategori_list); ?></h3>
                        <small class="text-muted">Kategori aktif</small>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Laporan</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select id="kategori" name="kategori" class="form-select">
                                    <option value="">-- Semua Kategori --</option>
                                    <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?php echo $kat['id']; ?>" <?php echo $filter_kategori == $kat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="bulan" class="form-label">Bulan</label>
                                <select id="bulan" name="bulan" class="form-select">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?php echo $filter_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tahun" class="form-label">Tahun</label>
                                <select id="tahun" name="tahun" class="form-select">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $filter_tahun == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="laporan.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabel Laporan -->
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Detail Barang</h5>
                        <button onclick="window.print()" class="btn btn-sm btn-info">
                            <i class="bi bi-printer"></i> Cetak
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($barang_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Barang</th>
                                            <th>Kategori</th>
                                            <th>Jumlah</th>
                                            <th>Harga Satuan</th>
                                            <th>Total Nilai</th>
                                            <th>Tanggal Input</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($barang_list as $brg): 
                                            $total = $brg['jumlah'] * $brg['harga'];
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($brg['nama_barang']); ?></strong></td>
                                                <td>
                                                    <span class="badge badge-custom badge-success">
                                                        <?php echo htmlspecialchars($brg['nama_kategori']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $brg['jumlah']; ?></td>
                                                <td>Rp <?php echo number_format($brg['harga'], 0, ',', '.'); ?></td>
                                                <td><strong>Rp <?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($brg['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background: #f8f9fa; font-weight: bold;">
                                            <td colspan="5" class="text-end">TOTAL NILAI INVENTORI:</td>
                                            <td>Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                <p class="text-muted mt-3">Tidak ada barang yang sesuai dengan filter.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart Placeholder -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Top 5 Kategori</h5>
                            </div>
                            <div class="card-body text-center" style="padding: 60px 20px;">
                                <p class="text-muted">Visualisasi data kategori</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Nilai Stok per Kategori</h5>
                            </div>
                            <div class="card-body text-center" style="padding: 60px 20px;">
                                <p class="text-muted">Visualisasi nilai inventori</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @media print {
            .navbar, .sidebar, .page-header, .btn, form { display: none !important; }
            body { margin: 0; padding: 0; }
        }
    </style>
</body>
</html>
