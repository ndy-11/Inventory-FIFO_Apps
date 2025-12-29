<?php
include '../koneksi.php';

// Ambil data stok keluar dari database
$query = "
  SELECT sk.id_keluar, b.nama_barang, sk.jumlah_keluar, sk.tanggal_keluar
  FROM stok_keluar sk
  JOIN barang b ON k.id_barang = b.id_barang
  ORDER BY sk.tanggal_keluar DESC
";
$result = mysqli_query($koneksi, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Stok Keluar (FIFO)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="card shadow-lg border-0">
    <div class="card-header bg-dark text-white">
      <h4 class="mb-0">
        <i class="bi bi-box-arrow-up me-2"></i> Riwayat Stok Keluar (FIFO)
      </h4>
    </div>

    <div class="card-body">
      <?php if (mysqli_num_rows($result) > 0) { ?>
        <div class="table-responsive">
          <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th width="5%">No</th>
                <th>Nama Barang</th>
                <th>Jumlah Keluar</th>
                <th>Tanggal Keluar</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; while ($row = mysqli_fetch_assoc($result)) { ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                <td class="text-center"><?= $row['jumlah_keluar'] ?></td>
                <td class="text-center"><?= date('d-m-Y', strtotime($row['tanggal_keluar'])) ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } else { ?>
        <div class="alert alert-warning text-center">
          <strong>Belum ada data stok keluar.</strong>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<!-- Bootstrap Icon dan JS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
