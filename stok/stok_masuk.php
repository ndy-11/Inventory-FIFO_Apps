<?php
include '../koneksi.php';
$query_barang = mysqli_query($conn, "SELECT * FROM barang ORDER BY nama_barang ASC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Tambah Stok Masuk</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <h3>Form Tambah Stok Masuk</h3>
  <form action="stok_masuk_proses.php" method="POST">

    <div class="mb-3">
      <label>Nama Barang</label>
      <select name="id_barang" class="form-select" required>
        <option value="">-- Pilih Barang --</option>
        <?php while($b = mysqli_fetch_assoc($query_barang)) { ?>
          <option value="<?= $b['id_barang'] ?>"><?= $b['nama_barang'] ?></option>
        <?php } ?>
      </select>
    </div>

    <div class="mb-3">
      <label>Jumlah Masuk</label>
      <input type="number" name="jumlah_masuk" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Harga Beli (Rp)</label>
      <input type="number" name="harga_beli" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Tanggal Masuk</label>
      <input type="date" name="tanggal_masuk" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="stok_masuk_list.php" class="btn btn-secondary">Kembali</a>

  </form>
</div>
</body>
</html>
