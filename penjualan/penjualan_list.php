<?php
include '../koneksi.php';

// Filter tanggal (harian / bulanan)
$filter = "";
if (isset($_GET['filter'])) {
    if ($_GET['filter'] == 'harian') {
        $tanggal = $_GET['tanggal'];
        $filter = "WHERE tanggal_penjualan = '$tanggal'";
    } elseif ($_GET['filter'] == 'bulanan') {
        $bulan = $_GET['bulan'];
        $tahun = $_GET['tahun'];
        $filter = "WHERE MONTH(tanggal_penjualan) = '$bulan' AND YEAR(tanggal_penjualan) = '$tahun'";
    }
}

// Query data penjualan
$query = mysqli_query($conn, "
    SELECT p.*, b.nama_barang 
    FROM penjualan p 
    JOIN barang b ON p.id_barang = b.id_barang 
    $filter 
    ORDER BY p.tanggal_penjualan DESC
");

// Hitung total keseluruhan
$total_semua = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT SUM(total_harga) AS total FROM penjualan $filter")
)['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f7f9fb; }
        .card { border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        table th { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../index.php">📊 Laporan Penjualan - Toko Zahra</a>
  </div>
</nav>

<div class="container py-4">
  <div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Penjualan</h5>
      <a href="penjualan.php" class="btn btn-light btn-sm"><i class="bi bi-plus-circle"></i> Tambah Penjualan</a>
    </div>

    <div class="card-body">
      <!-- Filter -->
      <form class="row g-3 mb-4">
        <div class="col-md-3">
          <label class="form-label">Filter Harian</label>
          <input type="date" name="tanggal" class="form-control">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" name="filter" value="harian" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Harian</button>
        </div>

        <div class="col-md-2">
          <label class="form-label">Bulan</label>
          <select name="bulan" class="form-select">
            <option value="">-- Bulan --</option>
            <?php for($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>"><?= date('F', mktime(0,0,0,$i,1)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Tahun</label>
          <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" name="filter" value="bulanan" class="btn btn-success w-100"><i class="bi bi-calendar3"></i> Bulanan</button>
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <a href="penjualan_list.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a>
        </div>
      </form>

      <!-- Tabel Riwayat -->
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead>
            <tr class="text-center">
              <th>No</th>
              <th>Tanggal</th>
              <th>Nama Barang</th>
              <th>Jumlah Terjual</th>
              <th>Harga Jual</th>
              <th>Total Harga</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            if (mysqli_num_rows($query) > 0):
              while ($row = mysqli_fetch_assoc($query)):
            ?>
            <tr>
              <td class="text-center"><?= $no++ ?></td>
              <td><?= date('d-m-Y', strtotime($row['tanggal_penjualan'])) ?></td>
              <td><?= $row['nama_barang'] ?></td>
              <td class="text-center"><?= $row['jumlah_terjual'] ?></td>
              <td>Rp <?= number_format($row['harga_jual'],0,',','.') ?></td>
              <td>Rp <?= number_format($row['total_harga'],0,',','.') ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center text-muted">Belum ada data penjualan</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold bg-light">
              <td colspan="5" class="text-end">Total Penjualan</td>
              <td>Rp <?= number_format($total_semua, 0, ',', '.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Tombol Export -->
      <div class="mt-3 text-end">
        <a href="export_penjualan_excel.php" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <a href="export_penjualan_pdf.php" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
