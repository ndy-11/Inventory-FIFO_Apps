<?php
include '../koneksi.php';

// Jika form disubmit
if (isset($_POST['simpan'])) {
    $id_barang = $_POST['id_barang'];
    $tanggal_penjualan = $_POST['tanggal_penjualan'];
    $jumlah_terjual = $_POST['jumlah_terjual'];
    $harga_jual = $_POST['harga_jual'];

    // Ambil stok total barang
    $cek = mysqli_query($conn, "SELECT stok_total FROM barang WHERE id_barang = '$id_barang'");
    $barang = mysqli_fetch_assoc($cek);
    $stok_total = $barang['stok_total'];

    if ($jumlah_terjual > $stok_total) {
        echo "<div class='alert alert-danger text-center m-3'>
                Stok tidak mencukupi! Stok tersedia hanya <b>$stok_total</b> unit.
              </div>";
    } else {
        // Hitung total harga
        $total_harga = $jumlah_terjual * $harga_jual;

        // Catat ke tabel penjualan
        mysqli_query($conn, "INSERT INTO penjualan (id_barang, tanggal_penjualan, jumlah_terjual, harga_jual, total_harga)
                             VALUES ('$id_barang', '$tanggal_penjualan', '$jumlah_terjual', '$harga_jual', '$total_harga')");

        // Kurangi stok_total di tabel barang
        mysqli_query($conn, "UPDATE barang SET stok_total = stok_total - $jumlah_terjual WHERE id_barang = '$id_barang'");

        // Catat juga ke stok_keluar (FIFO)
        mysqli_query($conn, "INSERT INTO stok_keluar (id_barang, jumlah, tanggal_keluar)
                             VALUES ('$id_barang', '$jumlah_terjual', '$tanggal_penjualan')");

        echo "<div class='alert alert-success text-center m-3'>
                Transaksi penjualan berhasil dicatat! 🎉<br>
                Total harga: <b>Rp " . number_format($total_harga, 0, ',', '.') . "</b>
              </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f6f9fc; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .btn-primary { background-color: #007bff; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../index.php">🛒 Penjualan - Toko Zahra</a>
  </div>
</nav>

<div class="container py-4">
  <div class="card">
    <div class="card-header bg-success text-white">
      <h4 class="mb-0"><i class="bi bi-cash-coin"></i> Catat Transaksi Penjualan</h4>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Nama Barang</label>
          <select name="id_barang" class="form-select" required>
            <option value="">-- Pilih Barang --</option>
            <?php
            $barang = mysqli_query($conn, "SELECT * FROM barang ORDER BY nama_barang ASC");
            while ($b = mysqli_fetch_assoc($barang)) {
                echo "<option value='{$b['id_barang']}'>{$b['nama_barang']} (Stok: {$b['stok_total']})</option>";
            }
            ?>
          </select>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Tanggal Penjualan</label>
            <input type="date" name="tanggal_penjualan" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Jumlah Terjual</label>
            <input type="number" name="jumlah_terjual" class="form-control" placeholder="Masukkan jumlah" required min="1">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Harga Jual Satuan (Rp)</label>
          <input type="number" step="0.01" name="harga_jual" class="form-control" placeholder="Contoh: 15000" required>
        </div>

        <button type="submit" name="simpan" class="btn btn-success">
          <i class="bi bi-save"></i> Simpan Transaksi
        </button>
        <a href="../index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
