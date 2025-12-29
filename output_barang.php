<?php
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();

$success = '';
$error = '';

// restore flash message (jika ada)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ====== mulai perubahan: deteksi kolom dan query barang ======
function columnExists($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && mysqli_num_rows($res) > 0;
}

// ====== baru: helper tableExists, findTable & findColumn untuk stok masuk/keluar ======
function tableExists($conn, $table)
{
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $res && mysqli_num_rows($res) > 0;
}
function findTable($conn, $candidates = [])
{
    foreach ($candidates as $t) {
        if (tableExists($conn, $t)) return $t;
    }
    return null;
}
function findColumn($conn, $table, $candidates = [])
{
    foreach ($candidates as $c) {
        if (columnExists($conn, $table, $c)) return $c;
    }
    return null;
}
// ====== baru: helper findNumericColumn untuk fallback kolom jumlah ======
function findNumericColumn($conn, $table, $exclude = [])
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}`");
    if (!$res) return null;
    while ($row = mysqli_fetch_assoc($res)) {
        $col = $row['Field'];
        $type = $row['Type'];
        if (in_array($col, $exclude)) continue;
        if (preg_match('/int|decimal|float|double/i', $type)) return $col;
    }
    return null;
}
// ====== akhir helper ======

// ambil kolom yang ada
$id_col = columnExists($conn, 'barang', 'id') ? 'id' : (columnExists($conn, 'barang', 'id_barang') ? 'id_barang' : 'id');
$qty_col = columnExists($conn, 'barang', 'jumlah') ? 'jumlah' : (columnExists($conn, 'barang', 'stok') ? 'stok' : null);
$price_col = columnExists($conn, 'barang', 'harga') ? 'harga' : (columnExists($conn, 'barang', 'harga_beli') ? 'harga_beli' : null);

// deteksi kolom kategori: bisa berupa kategori_id, atau kategori (text atau numeric id)
$has_kategori_id = columnExists($conn, 'barang', 'kategori_id');
$has_kategori_col = columnExists($conn, 'barang', 'kategori');

// helper: ambil tipe kolom
function getColumnType($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    if ($res && mysqli_num_rows($res)) {
        $row = mysqli_fetch_assoc($res);
        return $row['Type'] ?? null;
    }
    return null;
}

// jika hanya ada kolom 'kategori' tapi tidak 'kategori_id', cek apakah kolom kategori bertipe numeric (menyimpan id)
$kategori_is_id = false;
if ($has_kategori_col && !$has_kategori_id) {
    $ktype = getColumnType($conn, 'barang', 'kategori');
    if ($ktype && preg_match('/int|bigint|smallint|mediumint|tinyint/i', $ktype)) {
        $kategori_is_id = true;
    } else {
        // sample beberapa baris untuk mendeteksi apakah kolom teks menyimpan ID numerik
        $sampleQ = mysqli_query($conn, "SELECT `kategori` FROM `barang` WHERE `kategori` IS NOT NULL AND `kategori` <> '' LIMIT 200");
        $num = 0;
        $numeric = 0;
        if ($sampleQ) {
            while ($r = mysqli_fetch_assoc($sampleQ)) {
                $num++;
                if (preg_match('/^\d+$/', $r['kategori'])) $numeric++;
            }
            if ($num && ($numeric / $num) >= 0.6) {
                // >=60% entri numerik -> anggap menyimpan id
                $kategori_is_id = true;
            }
        }
    }
}

// ====== baru: deteksi kolom status_aktif ======
$has_status_aktif = columnExists($conn, 'barang', 'status_aktif');
// ====== akhir deteksi ======

// ====== baru: deteksi tabel/kolom pemasukan & pengeluaran ======
$masuk_table = findTable($conn, ['stok_masuk', 'masuk', 'barang_masuk', 'penerimaan', 'pembelian']);
$masuk_item_col = $masuk_table ? findColumn($conn, $masuk_table, ['id_barang', 'id_barang', 'id']) : null;
$masuk_qty_col = $masuk_table ? findColumn($conn, $masuk_table, ['jumlah', 'jumlah_masuk', 'qty', 'quantity']) : null;

$keluar_table = findTable($conn, ['stok_keluar', 'keluar', 'barang_keluar', 'pengeluaran', 'penjualan']);
$keluar_item_col = $keluar_table ? findColumn($conn, $keluar_table, ['id_barang', 'barang_id', 'id']) : null;
$keluar_qty_col = $keluar_table ? findColumn($conn, $keluar_table, ['jumlah_keluar', 'qty_keluar', 'quantity_keluar', 'jumlah', 'qty', 'quantity']) : null;
// ====== akhir deteksi ======
// handle POST: proses output (sederhana)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'], $_POST['jumlah_keluar'])) {
    $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
    $jumlah_keluar = (int)$_POST['jumlah_keluar'];

    if (!$qty_col && !($masuk_table && $masuk_qty_col)) {
        $error = 'Operasi tidak didukung pada skema database ini.';
    } elseif ($jumlah_keluar <= 0) {
        $error = 'Jumlah keluar harus lebih besar dari 0.';
    } else {
        // hitung stok dari total_masuk - total_keluar bila tabel tersedia
        $stok_now = 0;
        if ($masuk_table && $masuk_item_col && $masuk_qty_col) {
            $qmasuk = "SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) AS total FROM `{$masuk_table}` WHERE `{$masuk_item_col}` = '" . mysqli_real_escape_string($conn, $item_id) . "'";
            $rmasuk = mysqli_query($conn, $qmasuk);
            $total_masuk = $rmasuk && mysqli_num_rows($rmasuk) ? (int)mysqli_fetch_assoc($rmasuk)['total'] : 0;

            if ($keluar_table && $keluar_item_col && $keluar_qty_col) {
                $qkeluar = "SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) AS total FROM `{$keluar_table}` WHERE `{$keluar_item_col}` = '" . mysqli_real_escape_string($conn, $item_id) . "'";
                $rkeluar = mysqli_query($conn, $qkeluar);
                $total_keluar = $rkeluar && mysqli_num_rows($rkeluar) ? (int)mysqli_fetch_assoc($rkeluar)['total'] : 0;
            } else {
                $total_keluar = 0;
            }
            $stok_now = $total_masuk - $total_keluar;
        } elseif ($qty_col) {
            // fallback stok langsung dari barang
            $q = "SELECT `{$qty_col}` AS stok FROM barang WHERE `$id_col` = '" . mysqli_real_escape_string($conn, $item_id) . "' LIMIT 1";
            $res = mysqli_query($conn, $q);
            $stok_now = ($res && mysqli_num_rows($res)) ? (int)mysqli_fetch_assoc($res)['stok'] : 0;
        }

        if ($stok_now >= $jumlah_keluar) {
            // perform update barang.stok only if qty_col exists
            $update_ok = true;
            if ($qty_col) {
                $q2 = "UPDATE barang SET `{$qty_col}` = `{$qty_col}` - " . intval($jumlah_keluar) . " WHERE `$id_col` = '" . mysqli_real_escape_string($conn, $item_id) . "' AND `{$qty_col}` >= " . intval($jumlah_keluar);
                $update_ok = mysqli_query($conn, $q2);
            }

            // insert record output ke tabel pengeluaran (gunakan $keluar_table jika ada)
            $insert_ok = true;
            if ($keluar_table) {
                $tbl = $keluar_table;
                $col_item = findColumn($conn, $tbl, ['barang_id', 'id_barang', 'id']);
                $col_qty = findColumn($conn, $tbl, ['jumlah_keluar', 'qty_keluar', 'quantity_keluar', 'jumlah', 'qty', 'quantity']);
                $col_tujuan = findColumn($conn, $tbl, ['tujuan', 'tujuan_pengiriman', 'keterangan']);
                $col_user = findColumn($conn, $tbl, ['user', 'username', 'operator']);
                $col_tanggal = findColumn($conn, $tbl, ['tanggal', 'created_at', 'date']);

                // fallback, jika belum ada kolom qty yang cocok, cari kolom numeric yang tidak termasuk id/item/user/tanggal/tujuan
                if (!$col_qty) {
                    $exclude = array_filter([$col_item, $col_user, $col_tanggal, $col_tujuan]);
                    $col_qty = findNumericColumn($conn, $tbl, $exclude);
                    if ($col_qty) {
                        error_log("output_barang.php - Using numeric fallback column '{$col_qty}' for quantity in table '{$tbl}'");
                    } else {
                        error_log("output_barang.php - No quantity column found for table '{$tbl}'");
                    }
                }

                $cols = [];
                $vals = [];
                if ($col_item) {
                    $cols[] = "`{$col_item}`";
                    $vals[] = "'" . mysqli_real_escape_string($conn, $item_id) . "'";
                }
                if ($col_qty) {
                    $cols[] = "`{$col_qty}`";
                    $vals[] = intval($jumlah_keluar);
                }
                if ($col_tujuan && !empty($_POST['tujuan'])) {
                    $cols[] = "`{$col_tujuan}`";
                    $vals[] = "'" . mysqli_real_escape_string($conn, $_POST['tujuan']) . "'";
                }
                if ($col_user && isset($_SESSION['username'])) {
                    $cols[] = "`{$col_user}`";
                    $vals[] = "'" . mysqli_real_escape_string($conn, $_SESSION['username']) . "'";
                }
                if ($col_tanggal) {
                    $cols[] = "`{$col_tanggal}`";
                    $vals[] = "'" . mysqli_real_escape_string($conn, date('Y-m-d H:i:s')) . "'";
                }

                if (!empty($cols)) {
                    $insert_sql = "INSERT INTO `{$tbl}` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
                    if (!mysqli_query($conn, $insert_sql)) {
                        error_log("output_barang.php - Insert to {$tbl} failed: " . mysqli_error($conn));
                        $insert_ok = false;
                    }
                } else {
                    error_log("output_barang.php - No valid columns found for insert into {$tbl}");
                    $insert_ok = false;
                }
            }

            if ($update_ok && ($insert_ok || !$keluar_table)) {
                $_SESSION['success'] = "Berhasil memproses output barang.";
                header("Location: output_barang.php");
                exit;
            } else {
                // Buat pesan yang jelas
                if (!$update_ok) {
                    error_log("output_barang.php - Update Error: " . mysqli_error($conn));
                    $error = "Terjadi kesalahan saat memproses output (gagal mengurangi stok).";
                } elseif (!$insert_ok) {
                    // stok mungkin sudah dikurangi, tapi pencatatan keluar gagal
                    $_SESSION['success'] = "Berhasil memproses output barang. (Catatan: gagal menyimpan riwayat keluar.)";
                    header("Location: output_barang.php");
                    exit;
                } else {
                    $error = "Terjadi kesalahan yang tidak diketahui.";
                }
            }
        } else {
            $error = "Stok tidak mencukupi. Stok saat ini: {$stok_now}.";
        }
    }
}

// ambil daftar barang
// ====== tambahkan subquery total_masuk/total_keluar di SELECT ======
$select_cols = [];
$select_cols[] = "b.`{$id_col}` AS id";
$select_cols[] = "b.nama_barang";
$select_cols[] = $qty_col ? "b.`{$qty_col}` AS stok" : "0 AS stok";
$select_cols[] = $price_col ? "b.`{$price_col}` AS harga" : "0 AS harga";

if ($masuk_table && $masuk_item_col && $masuk_qty_col) {
    $select_cols[] = "(SELECT COALESCE(SUM(`{$masuk_qty_col}`),0) FROM `{$masuk_table}` WHERE `{$masuk_item_col}` = b.`{$id_col}`) AS total_masuk";
} else {
    $select_cols[] = "0 AS total_masuk";
}
if ($keluar_table && $keluar_item_col && $keluar_qty_col) {
    $select_cols[] = "(SELECT COALESCE(SUM(`{$keluar_qty_col}`),0) FROM `{$keluar_table}` WHERE `{$keluar_item_col}` = b.`{$id_col}`) AS total_keluar";
} else {
    $select_cols[] = "0 AS total_keluar";
}

// build FROM / JOIN: pilih mode sesuai struktur tabel barang
if ($has_kategori_id) {
    // explicit kategori_id column
    $select_cols[] = "COALESCE(k.nama_kategori, 'Tanpa Kategori') as nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id";
} elseif ($kategori_is_id) {
    // kolom 'kategori' menyimpan id numerik
    $select_cols[] = "COALESCE(k.nama_kategori, 'Tanpa Kategori') as nama_kategori";
    $from_join = "FROM barang b LEFT JOIN kategori k ON b.kategori = k.id";
} elseif ($has_kategori_col) {
    // kolom 'kategori' menyimpan nama teks -> lakukan join berdasarkan nama
    $select_cols[] = "COALESCE(k.nama_kategori, COALESCE(NULLIF(b.kategori,''), 'Tanpa Kategori')) as nama_kategori";
    // ambil collation database agar tidak terjadi Illegal mix of collations
    $collation = 'utf8mb4_general_ci';
    $qc = mysqli_query($conn, "SELECT @@collation_database AS coll");
    if ($qc) {
        $crow = mysqli_fetch_assoc($qc);
        if (!empty($crow['coll'])) $collation = $crow['coll'];
    }
    $from_join = "FROM barang b LEFT JOIN kategori k ON k.nama_kategori COLLATE {$collation} = b.kategori COLLATE {$collation}";
} else {
    $select_cols[] = "'Tanpa Kategori' as nama_kategori";
    $from_join = "FROM barang b";
}

// ====== ubah: tambahkan WHERE untuk menyembunyikan barang non-aktif jika ada kolom status_aktif ======
$where_clause = $has_status_aktif ? "WHERE b.status_aktif = 1" : "";
$query = "SELECT " . implode(", ", $select_cols) . " $from_join $where_clause ORDER BY b.nama_barang ASC";
// ====== akhir perubahan ======

$result = mysqli_query($conn, $query);
$barang_list = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
// debug: tampilkan mode deteksi kategori sebagai komentar HTML (hapus setelah verifikasi)
echo "<!-- kategori detection: has_kategori_id={$has_kategori_id}, has_kategori_col={$has_kategori_col}, kategori_is_id={$kategori_is_id} -->";
// ====== akhir perubahan ======
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Output Barang - FIFO App</title>
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
                    <h1><i class="bi bi-dash-circle-fill"></i> Output Barang</h1>
                    <p>Kelola pengeluaran barang dari gudang</p>
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
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Barang</h5>
                        <a href="input_barang.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg"></i> Tambah Barang
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Barang</th>
                                        <th class="text-center">Kategori</th>
                                        <th>Barang Masuk</th>
                                        <th>Barang Keluar</th>
                                        <th>Sisa Stock</th>
                                        <th class="text-center">Harga Aset</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($barang_list) === 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Tidak ada barang.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php $no = 1;
                                        foreach ($barang_list as $item):
                                            $total_masuk = isset($item['total_masuk']) ? (int)$item['total_masuk'] : 0;
                                            $total_keluar = isset($item['total_keluar']) ? (int)$item['total_keluar'] : 0;
                                            $stok = $total_masuk - $total_keluar;
                                            // fallback bila stok kolom ada dan tidak ada data masuk/keluar
                                            if (!isset($item['total_masuk']) && !isset($item['total_keluar'])) {
                                                $stok = isset($item['stok']) ? (int)$item['stok'] : 0;
                                            }
                                            $harga = isset($item['harga']) ? (float)$item['harga'] : 0;
                                            $status_badge = $stok > 0 ? 'success' : 'danger';                                        ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                                        <td class="text-center"><span
                                                class="badge badge-custom badge-success"><?php echo htmlspecialchars($item['nama_kategori']); ?></span>
                                        </td>
                                        <td class="text-center"><?php echo $total_masuk; ?></td>
                                        <td class="text-center"><?php echo $total_keluar; ?></td>
                                        <td class="text-center"><strong><?php echo $stok; ?></strong></td>
                                        <td>Rp <?php echo number_format($harga, 0, ',', '.'); ?></td>
                                        <td class="text-center"><span
                                                class="badge badge-custom badge-<?php echo $status_badge; ?>"><?php echo $stok > 0 ? 'Tersedia' : 'Habis'; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#outputModal"
                                                data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                                data-nama="<?php echo htmlspecialchars($item['nama_barang']); ?>"
                                                data-stok="<?php echo $stok; ?>"
                                                data-masuk="<?php echo $total_masuk; ?>"
                                                data-keluar="<?php echo $total_keluar; ?>"
                                                <?php echo $stok <= 0 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-arrow-up-right"></i> Keluar
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Output -->
    <div class="modal fade" id="outputModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-right"></i> Output Barang - <span
                            id="modal_item_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="item_id" id="modal_item_id" value="">
                    <div class="modal-body">
                        <div class="mb-2">
                            <small class="text-muted">Masuk: <span id="modal_total_masuk">0</span> &nbsp;|&nbsp; Keluar:
                                <span id="modal_total_keluar">0</span></small>
                        </div>
                        <p>Stok tersedia: <strong id="modal_current_stock">0</strong></p>
                        <div class="form-group mb-3">
                            <label for="jumlah_keluar" class="form-label">Jumlah Keluar</label>
                            <input type="number" id="jumlah_keluar" name="jumlah_keluar" class="form-control" required
                                min="1">
                            <div id="modal_stock_alert" class="text-danger small mt-1 d-none">Jumlah keluar tidak boleh
                                melebihi stok tersedia.</div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="tujuan" class="form-label">Tujuan</label>
                            <input type="text" id="tujuan" name="tujuan" class="form-control"
                                placeholder="Kemana barang ini dikirim?">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Proses Output
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var outputModalEl = document.getElementById('outputModal');
        outputModalEl.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var stok = button.getAttribute('data-stok');
            var totalMasuk = button.getAttribute('data-masuk') || 0;
            var totalKeluar = button.getAttribute('data-keluar') || 0;
            document.getElementById('modal_item_id').value = id;
            document.getElementById('modal_item_name').textContent = nama;
            document.getElementById('modal_current_stock').textContent = stok;
            document.getElementById('modal_total_masuk').textContent = totalMasuk;
            document.getElementById('modal_total_keluar').textContent = totalKeluar;
            var jumlahInput = document.getElementById('jumlah_keluar');
            var stockAlert = document.getElementById('modal_stock_alert');
            var modalForm = outputModalEl.querySelector('form');
            var submitBtn = modalForm.querySelector('button[type="submit"]');

            jumlahInput.value = '';
            jumlahInput.max = stok;

            function validateJumlah() {
                var val = parseInt(jumlahInput.value || '0', 10);
                var max = parseInt(jumlahInput.max || '0', 10);
                if (isNaN(val) || val < 1) {
                    stockAlert.classList.remove('d-none');
                    stockAlert.textContent = 'Jumlah keluar harus lebih besar dari 0.';
                    submitBtn.disabled = true;
                    return false;
                }
                if (max > 0 && val > max) {
                    stockAlert.classList.remove('d-none');
                    stockAlert.textContent = 'Jumlah keluar tidak boleh melebihi stok tersedia (' +
                        max + ').';
                    submitBtn.disabled = true;
                    return false;
                }
                stockAlert.classList.add('d-none');
                stockAlert.textContent = '';
                submitBtn.disabled = false;
                return true;
            }

            // validate while typing
            jumlahInput.addEventListener('input', function() {
                validateJumlah();
            });

            // Validate on submit and prevent submission if invalid
            modalForm.addEventListener('submit', function(e) {
                if (!validateJumlah()) {
                    e.preventDefault();
                    jumlahInput.focus();
                    jumlahInput.classList.add('is-invalid');
                    setTimeout(function() {
                        jumlahInput.classList.remove('is-invalid');
                    }, 700);
                }
            });
        });
    });
    </script>
</body>

</html>