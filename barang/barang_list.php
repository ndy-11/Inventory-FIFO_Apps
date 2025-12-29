<?php
require_once "../config/auth.php";
require_once "../koneksi.php";
requireLogin();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Master Barang</title>
    <link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container py-4">
    <h3>Master Barang</h3>
    <a href="barang_tambah.php" class="btn btn-primary mb-3">+ Tambah Barang</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Harga Jual</th>
                <th>Stok Total</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $query = "SELECT b.*, COALESCE(k.nama_kategori, '') AS nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id ORDER BY b.id_barang ASC";
        $data = mysqli_query($conn, $query);
        while ($d = mysqli_fetch_assoc($data)) { ?>
            <tr>
                <td><?= htmlspecialchars($d['id_barang']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($d['kode_barang'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($d['nama_barang']) ?></td>
                <td><?= htmlspecialchars($d['nama_kategori'] ?? '') ?></td>
                <td>Rp <?= number_format($d['harga_jual'] ?? 0, 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($d['stok'] ?? 0) ?></td>
                <td>
                    <a href="barang_edit.php?id=<?= $d['id_barang'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="barang_hapus.php?id=<?= $d['id_barang'] ?>" 
                       onclick="return confirm('Yakin ingin menghapus data ini?')" 
                       class="btn btn-danger btn-sm">Hapus</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <a href="../index.php" class="btn btn-secondary">Kembali</a>
</div>

</body>
</html>
