<?php
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();

// ====== baru: helper detection & statistik ======
function tableExists($conn, $table)
{
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    return $res && mysqli_num_rows($res) > 0;
}
function columnExists($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($conn, $table) . "` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
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
function findDateColumn($conn, $table)
{
    return findColumn($conn, $table, ['tanggal', 'created_at', 'date', 'date_created']);
}

// detect tables/cols
$barang_table = tableExists($conn, 'barang') ? 'barang' : null;
$kategori_table = tableExists($conn, 'kategori') ? 'kategori' : null;

$masuk_table = findTable($conn, ['stok_masuk', 'masuk', 'barang_masuk', 'penerimaan', 'pembelian']);
$masuk_qty_col = $masuk_table ? findColumn($conn, $masuk_table, ['jumlah', 'jumlah_masuk', 'qty', 'quantity']) : null;
$masuk_date_col = $masuk_table ? findDateColumn($conn, $masuk_table) : null;
$masuk_item_col = $masuk_table ? findColumn($conn, $masuk_table, ['barang_id', 'id_barang', 'id']) : null;

$keluar_table = findTable($conn, ['stok_keluar', 'keluar', 'barang_keluar', 'pengeluaran', 'penjualan']);
$keluar_qty_col = $keluar_table ? findColumn($conn, $keluar_table, ['jumlah_keluar', 'qty_keluar', 'quantity_keluar', 'jumlah', 'qty', 'quantity']) : null;
$keluar_date_col = $keluar_table ? findDateColumn($conn, $keluar_table) : null;
$keluar_item_col = $keluar_table ? findColumn($conn, $keluar_table, ['barang_id', 'id_barang', 'id']) : null;

// basic totals
$total_barang = 0;
$total_kategori = 0;
$input_hari_ini = 0;
$output_hari_ini = 0;

if ($barang_table) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM barang WHERE status_aktif = 1");
    if ($res) {
        $total_barang = (int)mysqli_fetch_assoc($res)['cnt'];
    }
}
if ($kategori_table) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM kategori");
    if ($res) {
        $total_kategori = (int)mysqli_fetch_assoc($res)['cnt'];
    }
}
if ($masuk_table && $masuk_qty_col) {
    if ($masuk_date_col) {
        $q = "SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) AS total FROM `{$masuk_table}` WHERE DATE(`{$masuk_date_col}`)=CURDATE()";
    } else {
        $q = "SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) AS total FROM `{$masuk_table}`";
    }
    $r = mysqli_query($conn, $q);
    if ($r) $input_hari_ini = (int)mysqli_fetch_assoc($r)['total'];
}
if ($keluar_table && $keluar_qty_col) {
    if ($keluar_date_col) {
        $q = "SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) AS total FROM `{$keluar_table}` WHERE DATE(`{$keluar_date_col}`)=CURDATE()";
    } else {
        $q = "SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) AS total FROM `{$keluar_table}`";
    }
    $r = mysqli_query($conn, $q);
    if ($r) $output_hari_ini = (int)mysqli_fetch_assoc($r)['total'];
}

// prepare data for charts: aggregate stok per kategori using total_masuk - total_keluar
$id_col = findColumn($conn, 'barang', ['id', 'id_barang']) ?: 'id';
$price_col = findColumn($conn, 'barang', ['harga', 'harga_beli', 'harga_jual']) ?: null;
$has_kategori_id = columnExists($conn, 'barang', 'kategori_id');
$has_kategori_text = columnExists($conn, 'barang', 'kategori');

$select_cols = [
    "b.`{$id_col}` AS id",
    "b.nama_barang",
    "b.status_aktif"
];
$select_cols[] = $price_col ? "b.`{$price_col}` AS harga" : "0 AS harga";
$masuk_item_match = $masuk_item_col ?: '0';
$keluar_item_match = $keluar_item_col ?: '0';
$select_cols[] = ($masuk_table && $masuk_qty_col) ? "(SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) FROM `{$masuk_table}` WHERE `{$masuk_item_match}` = b.`{$id_col}`) AS total_masuk" : "0 AS total_masuk";
$select_cols[] = ($keluar_table && $keluar_qty_col) ? "(SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) FROM `{$keluar_table}` WHERE `{$keluar_item_match}` = b.`{$id_col}`) AS total_keluar" : "0 AS total_keluar";
$select_cols[] = columnExists($conn, 'barang', 'stok_minimal') ? "COALESCE(b.`stok_minimal`,0) AS stok_minimal" : "0 AS stok_minimal";

if ($has_kategori_id) {
    // standar: ada kolom kategori_id -> join kategori untuk nama
    $select_cols[] = "COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id";
} elseif (columnExists($conn, 'barang', 'kategori') && tableExists($conn, 'kategori')) {
    // kolom 'kategori' bisa berisi foreign key -> join ke kategori untuk nama,
    // fallback ke teks di b.kategori kalau join tidak menemukan nama
    $select_cols[] = "COALESCE(k.nama_kategori, b.kategori, 'Tanpa Kategori') AS nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori = k.id";
} elseif ($has_kategori_text) {
    // kolom 'kategori' berisi teks nama langsung
    $select_cols[] = "COALESCE(b.kategori,'Tanpa Kategori') AS nama_kategori";
    $from_join = "FROM barang b";
} else {
    $select_cols[] = "'Tanpa Kategori' AS nama_kategori";
    $from_join = "FROM barang b";
}

$query = "SELECT " . implode(", ", $select_cols) . " $from_join";
$res = mysqli_query($conn, $query);
$barang_list = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
$low_stock = []; // kumpulkan barang dengan stok <= stok_minimal

// { changed code }
// Hitung total masuk/keluar per item dengan query yang pasti menggunakan nama kolom di tabel masing-masing,
// lalu agregasi stok/nilai per kategori untuk chart.
$category_stats = [];
foreach ($barang_list as $it) {
    $itemId = $it['id'];

    $total_masuk = 0;
    if ($masuk_table && $masuk_item_col && $masuk_qty_col) {
        $q1 = "SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) AS total FROM `{$masuk_table}` WHERE `{$masuk_item_col}` = '" . mysqli_real_escape_string($conn, $itemId) . "'";
        $r1 = mysqli_query($conn, $q1);
        $total_masuk = $r1 ? (int)mysqli_fetch_assoc($r1)['total'] : 0;
    }

    $total_keluar = 0;
    if ($keluar_table && $keluar_item_col && $keluar_qty_col) {
        $q2 = "SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) AS total FROM `{$keluar_table}` WHERE `{$keluar_item_col}` = '" . mysqli_real_escape_string($conn, $itemId) . "'";
        $r2 = mysqli_query($conn, $q2);
        $total_keluar = $r2 ? (int)mysqli_fetch_assoc($r2)['total'] : 0;
    }

    // Hitung stok berdasarkan masuk - keluar. Jika tabel masuk/keluar tidak tersedia dan ada kolom stok, gunakan itu.
    if (($masuk_table && $masuk_item_col && $masuk_qty_col) || ($keluar_table && $keluar_item_col && $keluar_qty_col)) {
        $stok = $total_masuk - $total_keluar;
    } else {
        $stok = isset($it['stok']) ? (int)$it['stok'] : 0;
    }

    // Cek apakah stok sudah dibawah atau sama dengan stok_minimal
    $itemStatus = isset($it['status_aktif']) ? (int)$it['status_aktif'] : 0;

    // HANYA BARANG AKTIF (status_aktif = 1)
    if ($itemStatus === 1) {
        $stok_min_value = isset($it['stok_minimal']) ? (int)$it['stok_minimal'] : 0;

        if ($stok_min_value > 0 && $stok <= $stok_min_value) {
            $low_stock[] = [
                'id'           => $itemId,
                'nama_barang'  => $it['nama_barang'],
                'stok'         => $stok,
                'stok_minimal' => $stok_min_value
            ];
        }
    }

    $cat = $it['nama_kategori'] ?? 'Tanpa Kategori';
    $harga = isset($it['harga']) ? (float)$it['harga'] : 0;

    if (!isset($category_stats[$cat])) $category_stats[$cat] = ['stok' => 0, 'nilai' => 0];
    $category_stats[$cat]['stok'] += $stok;
    $category_stats[$cat]['nilai'] += ($stok * $harga);
}

$low_count = count($low_stock);

// Siapkan array chart dari hasil agregasi
$category_items = [];
foreach ($category_stats as $k => $v) {
    $category_items[] = ['kategori' => $k, 'stok' => $v['stok'], 'nilai' => $v['nilai']];
}
usort($category_items, function ($a, $b) {
    return $b['stok'] <=> $a['stok'];
});
$top5 = array_slice($category_items, 0, 5);
$chart_labels = array_column($top5, 'kategori');
$chart_data_qty = array_column($top5, 'stok');
$all_labels = array_column($category_items, 'kategori');
$all_values = array_column($category_items, 'nilai');

// --- NEW: build a color palette for categories ---
$base_palette = ['#4ecdc4', '#51cf66', '#ffd93d', '#ff6b6b', '#667eea', '#845ef7', '#ff922b', '#20c997', '#e64980', '#495057'];
$palette = [];
$cnt = count($all_labels);
for ($i = 0; $i < $cnt; $i++) {
    $palette[] = $base_palette[$i % count($base_palette)];
}
$pie_colors = array_slice($palette, 0, count($chart_labels)); // colors for top5 pie
$point_colors = $palette; // colors for all categories (line points)
// --- end new ---

// ====== akhir statistik ======
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FIFO App</title>
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
    <!-- Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-box-seam"></i> FIFO App
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-sm btn-light position-relative" id="lowStockDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if (!empty($low_count)) { ?>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $low_count; ?></span>
                        <?php } ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="lowStockDropdown"
                        style="min-width: 280px;">
                        <?php if (empty($low_stock)) { ?>
                        <li class="px-3 text-muted">Tidak ada barang hampir habis</li>
                        <?php } else {
                            foreach ($low_stock as $ls) { ?>
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center"
                                href="master_barang.php?edit=<?php echo $ls['id']; ?>">
                                <span><?php echo htmlspecialchars($ls['nama_barang']); ?></span>
                                <span
                                    class="badge bg-danger"><?php echo $ls['stok']; ?>/<?php echo $ls['stok_minimal']; ?></span>
                            </a>
                        </li>
                        <?php } ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-center" href="master_barang.php">Lihat semua</a></li>
                        <?php } ?>
                    </ul>
                </div>
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

                    <a href="input_barang.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Input Barang
                    </a>

                    <a href="master_barang.php" class="nav-link">
                        <i class="bi bi-box-seam"></i> Master Barang
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
                    <h1><i class="bi bi-house-door-fill"></i> Dashboard</h1>
                    <p>Selamat datang di FIFO App - Sistem Manajemen Barang</p>
                </div>

                <?php if (!empty($low_stock)) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>
                        <strong>Terdapat <?php echo $low_count; ?> barang hampir habis.</strong> <a
                            href="master_barang.php" class="alert-link">Cek daftar barang</a>
                    </div>
                </div>
                <?php } ?>

                <!-- Stats Grid -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h6>Total Barang</h6>
                        <h3><span class="count" data-target="<?php echo (int)$total_barang; ?>">0</span></h3>
                        <small class="text-muted">Barang di gudang</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #51cf66;">
                        <h6>Input Hari Ini</h6>
                        <h3><span class="count" data-target="<?php echo (int)$input_hari_ini; ?>">0</span></h3>
                        <small class="text-muted">Barang masuk</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ff6b6b;">
                        <h6>Output Hari Ini</h6>
                        <h3><span class="count" data-target="<?php echo (int)$output_hari_ini; ?>">0</span></h3>
                        <small class="text-muted">Barang keluar</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ffd93d;">
                        <h6>Total Kategori</h6>
                        <h3><span class="count" data-target="<?php echo (int)$total_kategori; ?>">0</span></h3>
                        <small class="text-muted">Kategori barang</small>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row g-4 mt-3">
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
        // reveal stat cards
        document.querySelectorAll('.stat-card').forEach(function(el, i) {
            setTimeout(function() {
                el.classList.add('visible');
            }, 120 * (i + 1));
        });

        // simple count-up
        function fmt(num, locale) {
            return locale ? Number(num).toLocaleString(locale) : String(num);
        }

        function animateCount(el, duration) {
            var target = parseInt(el.getAttribute('data-target') || '0', 10);
            var prefix = el.getAttribute('data-prefix') || '';
            var fmtLocale = el.getAttribute('data-format') || '';
            var startTime = null;
            duration = duration || 1000;

            function step(ts) {
                if (!startTime) startTime = ts;
                var prog = Math.min((ts - startTime) / duration, 1);
                var val = Math.floor(prog * target);
                el.textContent = prefix + (fmtLocale ? fmt(val, fmtLocale) : val);
                if (prog < 1) requestAnimationFrame(step);
                else el.textContent = prefix + (fmtLocale ? fmt(target, fmtLocale) : target);
            }
            requestAnimationFrame(step);
        }
        setTimeout(function() {
            document.querySelectorAll('.count').forEach(function(el) {
                animateCount(el, 1000 + Math.random() * 700);
            });
        }, 300);
        const labelsTop = <?php echo json_encode($chart_labels ?: []); ?>;
        const dataTop = <?php echo json_encode($chart_data_qty ?: []); ?>;
        const el1 = document.getElementById('chartTop5');

        // colors from PHP
        const piePalette = <?php echo json_encode($pie_colors ?: []); ?>;
        const pointPalette = <?php echo json_encode($point_colors ?: []); ?>;

        function ensureLen(arr, n, fallback) {
            if (!arr || arr.length === 0) return Array.from({
                length: n
            }, () => fallback || '#667eea');
            const out = [];
            for (let i = 0; i < n; i++) out.push(arr[i % arr.length]);
            return out;
        }

        if (el1 && window.Chart) {
            new Chart(el1.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: labelsTop,
                    datasets: [{
                        data: dataTop,
                        backgroundColor: ensureLen(piePalette, labelsTop.length, '#667eea')
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
                                    return ctx.label + ': Rp ' + Number(v).toLocaleString('id-ID');
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
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        // per-point colors to differentiate categories
                        pointBackgroundColor: ensureLen(pointPalette, labels2.length, '#667eea'),
                        pointBorderColor: '#ffffff',
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