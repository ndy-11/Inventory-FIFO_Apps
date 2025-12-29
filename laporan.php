<?php
session_start();
require_once 'config/auth.php';
requireLogin();

// require admin - non-admins will be redirected by requireAdmin()
requireAdmin();


$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Get kategori untuk filter
$query = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$result = mysqli_query($conn, $query);
$kategori_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// ====== mulai perubahan: deteksi kolom dan bangun query aman ======

// helper cek kolom
function columnExists($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && mysqli_num_rows($res) > 0;
}
// helper cari tabel & kolom
function tableExists($conn, $table)
{
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    return $res && mysqli_num_rows($res) > 0;
}
function findTable($conn, $candidates = [])
{
    foreach ($candidates as $t) if (tableExists($conn, $t)) return $t;
    return null;
}
function findColumn($conn, $table, $candidates = [])
{
    foreach ($candidates as $c) if (columnExists($conn, $table, $c)) return $c;
    return null;
}

// tentukan nama kolom id, qty, price, kategori
$id_col = columnExists($conn, 'barang', 'id') ? 'id' : (columnExists($conn, 'barang', 'id_barang') ? 'id_barang' : 'id');
$qty_col = columnExists($conn, 'barang', 'jumlah') ? 'jumlah' : (columnExists($conn, 'barang', 'stok') ? 'stok' : null);
$price_col = columnExists($conn, 'barang', 'harga') ? 'harga' : (columnExists($conn, 'barang', 'harga_beli') ? 'harga_beli' : null);
$has_kategori_id = columnExists($conn, 'barang', 'kategori_id');
$has_kategori_text = columnExists($conn, 'barang', 'kategori');

// deteksi tabel stok_masuk & stok_keluar
$masuk_table = findTable($conn, ['stok_masuk', 'masuk', 'barang_masuk', 'penerimaan', 'pembelian']);
$masuk_item_col = $masuk_table ? findColumn($conn, $masuk_table, ['barang_id', 'id_barang', 'id']) : null;
$masuk_qty_col  = $masuk_table ? findColumn($conn, $masuk_table, ['jumlah', 'jumlah_masuk', 'qty', 'quantity']) : null;

$keluar_table = findTable($conn, ['stok_keluar', 'keluar', 'barang_keluar', 'pengeluaran', 'penjualan']);
$keluar_item_col = $keluar_table ? findColumn($conn, $keluar_table, ['barang_id', 'id_barang', 'id']) : null;
$keluar_qty_col  = $keluar_table ? findColumn($conn, $keluar_table, ['jumlah', 'qty', 'quantity', 'jumlah_keluar', 'qty_keluar']) : null;

// build WHERE tetap seperti sebelumnya
$where = "WHERE 1=1";
if ($filter_kategori) {
    if ($has_kategori_id) {
        $where .= " AND b.kategori_id = '" . mysqli_real_escape_string($conn, $filter_kategori) . "'";
    } elseif ($has_kategori_text) {
        $where .= " AND b.kategori = '" . mysqli_real_escape_string($conn, $filter_kategori) . "'";
    }
}
// Sembunyikan barang non-aktif jika kolom status_aktif ada
if (columnExists($conn, 'barang', 'status_aktif')) {
    $where .= " AND b.status_aktif = 1";
}

// ====== mulai perubahan: deteksi kolom tambahan dan build SELECT/ORDER aman ======
// build SELECT: gunakan total_masuk dari tabel stok_masuk bila tersedia
$has_keterangan = columnExists($conn, 'barang', 'keterangan');
$has_created_at = columnExists($conn, 'barang', 'created_at');

$select_cols = [];
$select_cols[] = "b.`{$id_col}` AS id";
$select_cols[] = "b.nama_barang";
// kategori field selection
$select_cols[] = $has_kategori_id ? "b.kategori_id" : ($has_kategori_text ? "b.kategori" : "NULL AS kategori");
// jumlah -> total_masuk subquery when possible
if ($masuk_table && $masuk_item_col && $masuk_qty_col) {
    $select_cols[] = "(SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) FROM `{$masuk_table}` WHERE `{$masuk_item_col}` = b.`{$id_col}`) AS total_masuk";
} else {
    $select_cols[] = $qty_col ? "b.`{$qty_col}` AS total_masuk" : "0 AS total_masuk";
}
// ===== added: total_keluar subquery =====
if ($keluar_table && $keluar_item_col && $keluar_qty_col) {
    $select_cols[] = "(SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) FROM `{$keluar_table}` WHERE `{$keluar_item_col}` = b.`{$id_col}`) AS total_keluar";
} else {
    $select_cols[] = "0 AS total_keluar";
}
$select_cols[] = $price_col ? "b.`{$price_col}` AS harga" : "0 AS harga";
$select_cols[] = $has_keterangan ? "b.keterangan" : "'' AS keterangan";
if ($has_created_at) {
    $select_cols[] = "b.created_at";
    $order_by = "b.created_at DESC";
} else {
    $select_cols[] = "NULL AS created_at";
    $order_by = "b.`{$id_col}` DESC";
}
// nama kategori representation
if ($has_kategori_id) {
    // standar: ada kolom kategori_id -> join kategori
    $select_cols[] = "COALESCE(k.nama_kategori, 'Tanpa Kategori') as nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id";
} elseif (columnExists($conn, 'barang', 'kategori') && tableExists($conn, 'kategori')) {
    // kolom bernama 'kategori' tapi nilainya mungkin foreign key -> join ke kategori untuk nama
    $select_cols[] = "COALESCE(k.nama_kategori, b.kategori, 'Tanpa Kategori') as nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori = k.id";
} elseif ($has_kategori_text) {
    // kolom 'kategori' berisi teks nama langsung
    $select_cols[] = "COALESCE(b.kategori, 'Tanpa Kategori') as nama_kategori";
    $from_join = "FROM barang b";
} else {
    $select_cols[] = "'Tanpa Kategori' as nama_kategori";
    $from_join = "FROM barang b";
}

$query = "SELECT " . implode(", ", $select_cols) . " $from_join $where ORDER BY $order_by";

// ====== ubah: jangan tampilkan error query mentah, log saja dan beri pesan ramah ======
$query_error = false;
$result = mysqli_query($conn, $query);
if (!$result) {
    error_log("laporan.php - Query Error: " . mysqli_error($conn)); // log detail di server
    $query_error = true;
    $barang_list = [];
} else {
    $barang_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// ====== akhir perubahan ======

// Hitung statistik
$total_barang = count($barang_list);
$total_nilai = 0;
foreach ($barang_list as $brg) {
    $jumlah_masuk = isset($brg['total_masuk']) ? (int)$brg['total_masuk'] : 0;
    $jumlah_keluar = isset($brg['total_keluar']) ? (int)$brg['total_keluar'] : 0;
    $stok_netto = max(0, $jumlah_masuk - $jumlah_keluar);
    $harga = isset($brg['harga']) ? (float)$brg['harga'] : 0;
    $total_nilai += ($stok_netto * $harga);
}

// ====== baru: siapkan data untuk chart berdasarkan total_masuk per kategori ======
$category_stats = [];
foreach ($barang_list as $brg) {
    $cat = $brg['nama_kategori'] ?? 'Tanpa Kategori';
    $jm = isset($brg['total_masuk']) ? (int)$brg['total_masuk'] : 0;
    $jk = isset($brg['total_keluar']) ? (int)$brg['total_keluar'] : 0;
    $stok = max(0, $jm - $jk);
    $harga = isset($brg['harga']) ? (float)$brg['harga'] : 0;
    if (!isset($category_stats[$cat])) $category_stats[$cat] = ['masuk' => 0, 'nilai' => 0];
    $category_stats[$cat]['masuk'] += $stok; // gunakan stok netto untuk grafik jumlah
    $category_stats[$cat]['nilai'] += ($stok * $harga);
}
$cat_items = [];
foreach ($category_stats as $k => $v) $cat_items[] = ['kategori' => $k, 'masuk' => $v['masuk'], 'nilai' => $v['nilai']];
usort($cat_items, fn($a, $b) => $b['masuk'] <=> $a['masuk']);
$top5 = array_slice($cat_items, 0, 5);
$chart_labels = array_column($top5, 'kategori');
$chart_data_qty = array_column($top5, 'masuk');
$all_labels = array_column($cat_items, 'kategori');
$all_values = array_column($cat_items, 'nilai');
// build export query (preserve current filters)
$export_params = [];
if ($filter_kategori !== '') $export_params['kategori'] = $filter_kategori;
if ($filter_bulan !== '') $export_params['bulan'] = $filter_bulan;
if ($filter_tahun !== '') $export_params['tahun'] = $filter_tahun;
$export_params['export'] = 'pdf';
$export_query = http_build_query($export_params);

// ====== baru: handler export PDF (render minimal page + watermark + tanda tangan) ======
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // nicer CSS + header + watermark + generated date
    $app_name = 'FIFO App';
    $generated_at = date('d/m/Y H:i');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Laporan PDF - Detail Barang</title>
    <style>
        body{font-family: Arial, Helvetica, sans-serif; color:#111; margin:18px;}
        .header-wrap{display:flex;align-items:center;gap:12px;margin-bottom:6px;}
        .logo{width:64px;height:64px;background:#eee;display:inline-block;border-radius:6px;text-align:center;line-height:64px;color:#999;font-weight:bold;}
        h2{margin:0;font-size:18px;}
        .meta{font-size:12px;color:#666;margin-top:4px;}
        .table{width:100%;border-collapse:collapse;margin-top:12px;}
        .table th,.table td{border:1px solid #e6e6e6;padding:8px;font-size:12px;}
        .table th{background:#f7f7f7;text-align:left;}
        .watermark{position:fixed;top:40%;left:0;width:100%;text-align:center;font-size:88px;color:#000;opacity:0.04;transform:rotate(-30deg);pointer-events:none;z-index:0;}
        .signature{margin-top:36px;text-align:right;font-size:13px;}
        .footer{margin-top:18px;font-size:12px;color:#444;}
        @media print { .no-print{display:none;} }
    </style>
    </head><body>
    <div class="watermark">' . htmlspecialchars($app_name) . '</div>
    <div class="header-wrap">
        <div class="logo">LOGO</div>
        <div>
            <h2>' . htmlspecialchars($app_name) . ' - Laporan Detail Barang</h2>
            <div class="meta">Dicetak: ' . htmlspecialchars($generated_at) . '</div>
            <div class="meta">';
    if ($filter_kategori) echo 'Kategori: ' . htmlspecialchars($filter_kategori) . ' &nbsp; ';
    if ($filter_bulan) echo 'Bulan: ' . htmlspecialchars($filter_bulan) . ' &nbsp; ';
    if ($filter_tahun) echo 'Tahun: ' . htmlspecialchars($filter_tahun) . ' &nbsp; ';
    echo '</div></div></div>';

    // table header
    echo '<table class="table"><thead><tr>
        <th style="width:40px;">No</th>
        <th>Nama Barang</th>
        <th style="width:140px;">Kategori</th>
        <th style="width:100px;text-align:right;">Jumlah Masuk</th>
        <th style="width:100px;text-align:right;">Jumlah Keluar</th>
        <th style="width:140px;text-align:right;">Sisa Barang</th>
        <th style="width:160px;text-align:right;">Total Nilai Aset</th>
    </tr></thead><tbody>';

    $no = 1;
    $total_nilai_pdf = 0;
    foreach ($barang_list as $brg) {
        $jm = isset($brg['total_masuk']) ? (int)$brg['total_masuk'] : 0;
        $jk = isset($brg['total_keluar']) ? (int)$brg['total_keluar'] : 0;
        $harga = isset($brg['harga']) ? (float)$brg['harga'] : 0;
        $stok_netto = max(0, $jm - $jk);
        $total = $stok_netto * $harga;
        $total_nilai_pdf += $total;

        echo '<tr>';
        echo '<td style="text-align:center;">' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($brg['nama_barang']) . '</td>';
        echo '<td>' . htmlspecialchars($brg['nama_kategori']) . '</td>';
        echo '<td style="text-align:right;">' . number_format($jm) . '</td>';
        echo '<td style="text-align:right;">' . number_format($jk) . '</td>';
        // tampilkan stok netto (sisa barang) di PDF, bukan harga
        echo '<td style="text-align:right;">' . number_format($stok_netto) . '</td>';
        echo '<td style="text-align:right;">Rp ' . number_format($total, 0, ',', '.') . '</td>';
        echo '</tr>';
    }

    // footer total
    echo '</tbody><tfoot><tr>
        <td colspan="6" style="text-align:right;font-weight:bold;">TOTAL NILAI ASET :</td>
        <td style="text-align:right;font-weight:bold;">Rp ' . number_format($total_nilai_pdf, 0, ',', '.') . '</td>
    </tr></tfoot></table>';

    // signature area
    echo '<div class="signature">
        <p>Mengetahui,</p>
        <p><strong>Sandy Irawan</strong></p>
        <br><br>
        <p>__________________________</p>
    </div>';

    // nicer footer
    echo '<div class="footer">Generated by ' . htmlspecialchars($app_name) . ' - ' . htmlspecialchars($generated_at) . '</div>';

    // auto print then close (open in new tab)
    echo '<script>window.onload = function(){ window.print(); };</script>';
    echo '</body></html>';
    exit;
}
// ====== akhir handler ======
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <!-- ...existing head... -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - FIFO App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- added: stat card animation + count styling -->
    <style>
    .stat-card {
        transition: transform .5s ease, opacity .5s ease;
        transform: translateY(8px);
        opacity: 0;
    }

    .stat-card.visible {
        transform: translateY(0);
        opacity: 1;
    }

    .stat-card h3 {
        font-weight: 700;
        margin: .25rem 0;
        font-size: 1.8rem;
    }

    .count {
        display: inline-block;
        min-width: 70px;
        text-align: right;
    }
    </style>
</head>

<body>
    <!-- Navbar is included above -->
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
                    <h1><i class="bi bi-bar-chart-fill"></i> Laporan Barang</h1>
                    <p>Analisis dan laporan inventori gudang</p>
                </div>

                <!-- Stats -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h6>Total Jenis Barang</h6>
                        <h3><span class="count" data-target="<?php echo (int)$total_barang; ?>">0</span></h3>
                        <small class="text-muted">Barang aktif</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #51cf66;">
                        <h6>Total Unit (Masuk)</h6>
                        <h3><span class="count"
                                data-target="<?php echo (int)array_sum(array_column($barang_list, 'total_masuk')); ?>">0</span>
                        </h3>
                        <small class="text-muted">Jumlah masuk</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #4ecdc4;">
                        <h6>Total Nilai Aset</h6>
                        <h3><span class="count" data-target="<?php echo (int)$total_nilai; ?>" data-prefix="Rp "
                                data-format="id">0</span></h3>
                        <small class="text-muted">Nilai inventori</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ffd93d;">
                        <h6>Kategori</h6>
                        <h3><span class="count" data-target="<?php echo (int)count($kategori_list); ?>">0</span></h3>
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
                                    <option value="<?php echo $kat['id']; ?>"
                                        <?php echo $filter_kategori == $kat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="bulan" class="form-label">Bulan</label>
                                <select id="bulan" name="bulan" class="form-select">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"
                                        <?php echo $filter_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tahun" class="form-label">Tahun</label>
                                <select id="tahun" name="tahun" class="form-select">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>"
                                        <?php echo $filter_tahun == $i ? 'selected' : ''; ?>>
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
                        <a href="?<?php echo $export_query; ?>" target="_blank" class="btn btn-sm btn-info">
                            <i class="bi bi-file-earmark-pdf"></i> Cetak PDF
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($query_error): ?>
                        <div class="alert alert-danger">Terjadi kesalahan saat memuat data. Silakan coba lagi atau
                            hubungi admin.</div>
                        <?php endif; ?>
                        <?php if (count($barang_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-custom table-hover">
                                <thead>
                                    <tr>
                                        <th class="text-center">No</th>
                                        <th>Nama Barang</th>
                                        <th class="text-center">Kategori</th>
                                        <th class="text-center">Jumlah Masuk</th>
                                        <th class="text-center">Jumlah Keluar</th>
                                        <th class="text-center">Sisa Barang</th>
                                        <th class="text-center">Total Nilai Aset</th>
                                        <th class="text-center">Tanggal Input</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                        foreach ($barang_list as $brg):
                                            $jm = isset($brg['total_masuk']) ? (int)$brg['total_masuk'] : 0;
                                            $jk = isset($brg['total_keluar']) ? (int)$brg['total_keluar'] : 0;
                                            $stok_netto = max(0, $jm - $jk);
                                            $total = $stok_netto * (isset($brg['harga']) ? (float)$brg['harga'] : 0);
                                        ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($brg['nama_barang']); ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-custom badge-success">
                                                <?php echo htmlspecialchars($brg['nama_kategori']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?php echo $jm; ?></td>
                                        <td class="text-center"><?php echo $jk ?? 0; ?></td>
                                        <!-- Ubah header & isi: tampilkan Sisa Barang (stok netto) -->
                                        <td class="text-center"><?php echo number_format($stok_netto); ?></td>
                                        <td class="text-center"><strong>Rp
                                                <?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                                        <td class="text-center">
                                            <?php echo !empty($brg['created_at']) ? date('d/m/Y', strtotime($brg['created_at'])) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f8f9fa; font-weight: bold;">
                                        <td colspan="6" class="text-end">TOTAL NILAI ASET INVENTORI:</td>
                                        <td>Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?>
                                        </td>
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

                <!-- Charts -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Top 5 Kategori</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartTop5" style="max-height:300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Nilai Stok per Kategori</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartNilaiKategori" style="max-height:300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        // reveal stat cards with delay
        document.querySelectorAll('.stat-card').forEach(function(el, i) {
            setTimeout(function() {
                el.classList.add('visible');
            }, 120 * (i + 1));
        });

        // simple count-up
        function formatNumber(val, locale) {
            if (!locale) return String(val);
            return Number(val).toLocaleString(locale);
        }

        function animateCount(el, duration) {
            var target = parseInt(el.getAttribute('data-target') || '0', 10);
            var prefix = el.getAttribute('data-prefix') || '';
            var fmt = el.getAttribute('data-format') || '';
            var start = 0,
                startTime = null;
            duration = duration || 1100;

            function step(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                var value = Math.floor(progress * (target - start) + start);
                el.textContent = prefix + (fmt ? formatNumber(value, fmt) : value);
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = prefix + (fmt ? formatNumber(target, fmt) : target);
            }
            requestAnimationFrame(step);
        }

        // run count-up after small delay
        setTimeout(function() {
            document.querySelectorAll('.count').forEach(function(el) {
                animateCount(el, 1000 + Math.random() * 600);
            });
        }, 350);

        // enhanced chart init (animated pie + animated line with gradient)
        const labelsTop = <?php echo json_encode($chart_labels ?: []); ?>;
        const dataTop = <?php echo json_encode($chart_data_qty ?: []); ?>;
        const el1 = document.getElementById('chartTop5');
        if (el1 && window.Chart) {
            new Chart(el1.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: labelsTop,
                    datasets: [{
                        data: dataTop,
                        backgroundColor: ['#4ecdc4', '#51cf66', '#ffd93d', '#ff6b6b', '#667eea']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 900,
                        easing: 'easeOutBack'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const v = ctx.parsed || 0;
                                    return ctx.label + ': ' + Number(v).toLocaleString('id-ID') +
                                        ' unit';
                                }
                            }
                        }
                    }
                }
            });
        }

        const labels2 = <?php echo json_encode($all_labels ?: []); ?>;
        const vals2 = <?php echo json_encode($all_values ?: []); ?>;
        const el2 = document.getElementById('chartNilaiKategori');
        if (el2 && window.Chart) {
            const ctx2 = el2.getContext('2d');
            // create gradient fill
            const grad = ctx2.createLinearGradient(0, 0, 0, el2.height || 300);
            grad.addColorStop(0, 'rgba(102,126,234,0.45)');
            grad.addColorStop(1, 'rgba(102,126,234,0.05)');

            let delayed = false;
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: labels2,
                    datasets: [{
                        label: 'Nilai Stok (Rp)',
                        data: vals2,
                        borderColor: '#667eea',
                        backgroundColor: grad,
                        fill: true,
                        tension: 0.36,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const v = ctx.parsed.y || 0;
                                    return 'Rp ' + Number(v).toLocaleString('id-ID');
                                }
                            }
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + Number(value).toLocaleString('id-ID');
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.04)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.02)'
                            }
                        }
                    },
                    animation: {
                        onComplete: () => {
                            delayed = true;
                        },
                        delay: (ctx) => {
                            let delay = 0;
                            if (ctx.type === 'data' && ctx.mode === 'default' && !delayed) {
                                delay = ctx.dataIndex * 40 + ctx.datasetIndex * 100;
                            }
                            return delay;
                        },
                        easing: 'easeOutQuart',
                        duration: 900
                    }
                }
            });
        }
    })();
    </script>

</body>

</html>