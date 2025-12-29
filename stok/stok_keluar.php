<?php
include "../koneksi.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Stok Keluar (FIFO)</title>
  <link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 12px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); }
    .btn { border-radius: 8px; }
  </style>
</head>
<body>

<div class="container mt-4">
  <div class="card">
    <div class="card-header bg-danger text-white">
      <h4 class="mb-0"><i class="bi bi-box-arrow-up"></i> Stok Keluar (Metode FIFO)</h4>
    </div>

    <div class="card-body">
      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label">Nama Barang</label>
          <select name="id_barang" class="form-select" required>
            <option value="">-- Pilih Barang --</option>
            <?php
            $barang = mysqli_query($koneksi, "SELECT * FROM barang");
            while ($b = mysqli_fetch_assoc($barang)) {
              echo "<option value='{$b['id_barang']}'>{$b['nama_barang']} (Stok: {$b['stok_total']})</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Tanggal Keluar</label>
          <input type="date" name="tanggal_keluar" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Jumlah Keluar / Terjual</label>
          <input type="number" name="jumlah_keluar" class="form-control" min="1" required>
        </div>

        <button type="submit" name="simpan" class="btn btn-danger">
          <i class="bi bi-save"></i> Simpan Transaksi
        </button>
        <a href="stok_keluar_list.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </form>

      <?php
      if (isset($_POST['simpan'])) {
        $id_barang = $_POST['id_barang'];
        $tanggal_keluar = $_POST['tanggal_keluar'];
        $jumlah_keluar = $_POST['jumlah_keluar'];
        $sisa = $jumlah_keluar;

        // Ambil stok masuk paling lama (FIFO)
        $stokMasuk = mysqli_query($koneksi, "
          SELECT * FROM stok_masuk 
          WHERE id_barang = '$id_barang' AND jumlah_masuk > 0 
          ORDER BY tanggal_masuk ASC
        ");

        mysqli_begin_transaction($koneksi);

        try {
          while ($row = mysqli_fetch_assoc($stokMasuk)) {
            if ($sisa <= 0) break;

            $stokTersedia = $row['jumlah_masuk'];
            $ambil = min($stokTersedia, $sisa);

            // Catat ke tabel stok_keluar
            mysqli_query($koneksi, "
              INSERT INTO stok_keluar (id_barang, tanggal_keluar, jumlah_keluar, id_stok_masuk)
              VALUES ('$id_barang', '$tanggal_keluar', '$ambil', '{$row['id_stok_masuk']}')
            ");

            // Update stok_masuk (kurangi stok batch)
            mysqli_query($koneksi, "
              UPDATE stok_masuk SET jumlah_masuk = jumlah_masuk - $ambil 
              WHERE id_stok_masuk = '{$row['id_stok_masuk']}'
            ");

            $sisa -= $ambil;
          }

          // Kurangi stok_total di tabel barang
          mysqli_query($koneksi, "
            UPDATE barang SET stok_total = stok_total - $jumlah_keluar 
            WHERE id_barang = '$id_barang'
          ");

          mysqli_commit($koneksi);
          echo "<div class='alert alert-success mt-4'><i class='bi bi-check-circle'></i> Transaksi stok keluar berhasil disimpan!</div>";

        } catch (Exception $e) {
          mysqli_rollback($koneksi);
          echo "<div class='alert alert-danger mt-4'><i class='bi bi-x-circle'></i> Terjadi kesalahan: " . $e->getMessage() . "</div>";
        }
      }
      ?>
    </div>
  </div>
</div>

</body>
</html>
