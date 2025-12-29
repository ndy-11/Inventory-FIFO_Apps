<?php
require_once "../config/auth.php";
require_once "../koneksi.php";
requireLogin();

$id = $_GET['id'];
$res = mysqli_query($conn, "SELECT * FROM barang WHERE id_barang='" . mysqli_real_escape_string($conn, $id) . "'");
$d = $res ? mysqli_fetch_assoc($res) : null;

// ambil list kategori
$kategori_list = [];
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'kategori'");
if ($chk && mysqli_num_rows($chk) > 0) {
    $r = mysqli_query($conn, "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
    if ($r) $kategori_list = mysqli_fetch_all($r, MYSQLI_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kode_barang = mysqli_real_escape_string($conn, $_POST['kode_barang'] ?? '');
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan'] ?? '');
    $harga_beli = (float)($_POST['harga_beli'] ?? 0);
    $harga_jual = (float)($_POST['harga_jual'] ?? 0);
    $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');

    $sql = "UPDATE barang SET kode_barang='$kode_barang', nama_barang='$nama_barang', kategori_id=$kategori_id, satuan='$satuan', harga_beli=$harga_beli, harga_jual=$harga_jual, stok_minimal=$stok_minimal, deskripsi='$deskripsi' WHERE id_barang='" . mysqli_real_escape_string($conn, $id) . "'";
    if (mysqli_query($conn, $sql)) {
        header("Location: barang_list.php");
        exit;
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Barang</title>
    <link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h3>Edit Barang</h3>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" class="card p-4 shadow-sm">
        <div class="mb-3">
            <label>Nama Barang</label>
            <input type="text" name="nama_barang" class="form-control" value="<?= htmlspecialchars($d['nama_barang'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Kode Barang</label>
            <input type="text" name="kode_barang" class="form-control" value="<?= htmlspecialchars($d['kode_barang'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label>Stok (field tetap untuk informasi)</label>
            <input type="number" name="stok" class="form-control" value="<?= htmlspecialchars($d['stok'] ?? 0) ?>" disabled>
        </div>

        <div class="mb-3">
            <label>Kategori</label>
            <select name="kategori_id" class="form-select">
                <option value="0">-- Tidak ada/Default --</option>
                <?php foreach ($kategori_list as $kat): ?>
                    <option value="<?php echo $kat['id']; ?>" <?php echo (($d['kategori_id'] ?? 0) == $kat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($kat['nama_kategori']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Satuan</label>
            <input type="text" name="satuan" class="form-control" value="<?= htmlspecialchars($d['satuan'] ?? '') ?>">
        </div>

        <div class="mb-3 row">
            <div class="col-md-6">
                <label>Harga Beli</label>
                <input type="number" name="harga_beli" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($d['harga_beli'] ?? 0) ?>">
            </div>
            <div class="col-md-6">
                <label>Harga Jual</label>
                <input type="number" name="harga_jual" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($d['harga_jual'] ?? 0) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Stok Minimal</label>
            <input type="number" name="stok_minimal" class="form-control" min="0" value="<?= htmlspecialchars($d['stok_minimal'] ?? 0) ?>">
        </div>

        <div class="mb-3">
            <label>Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($d['deskripsi'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">Update</button>
    </form>
    <a href="barang_list.php" class="btn btn-secondary mt-3">Kembali</a>
</div>
</body>
</html>
