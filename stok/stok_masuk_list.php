<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Stok Masuk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0">📦 Daftar Stok Masuk</h4>
    </div>
    <div class="card-body">
      <a href="stok_masuk_tambah.php" class="btn btn-success mb-3">+ Tambah Stok Masuk</a>
      <table class="table table-bordered table-striped">
        <thead class="table-dark">
          <tr>
            <th>No</th>
            <th>Nama Barang</th>
            <th>Jumlah</th>
            <th>Harga Beli</th>
            <th>Tanggal Masuk</th>
          </tr>
        </thead>
        <tbody>
          <?php
          include '../koneksi.php';
          $no = 1;
          $data = mysqli_query($koneksi,"  SELECT s.*, b.nama_barang 
    FROM stok_masuk AS s
    LEFT JOIN barang AS b ON s.id_barang = b.id_barang
    ORDER BY s.tanggal_masuk DESC");
          while($d = mysqli_fetch_array($data)){
            echo
              "<tr>
                <td>$no</td>
                <td>{$d['nama_barang']}</td>
                <td>{$d['jumlah_masuk']}</td>
                <td>Rp " . number_format($d['harga_beli'], 0, ',', '.') . "</td>
                <td>{$d['tanggal_masuk']}</td>
              </tr>";
            $no++;
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>