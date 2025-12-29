<?php
require_once "../config/auth.php";
require_once "../koneksi.php";
requireLogin();

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kode_barang = mysqli_real_escape_string($conn, $_POST['kode_barang'] ?? '');
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan'] ?? '');
    $harga_beli = (float)($_POST['harga_beli'] ?? 0);
    $harga_jual = (float)($_POST['harga_jual'] ?? 0);
    $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');

    $sql = "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan, harga_beli, harga_jual, stok_minimal, deskripsi, status_aktif, created_at) 
            VALUES ('$kode_barang', '$nama_barang', $kategori_id, '$satuan', $harga_beli, $harga_jual, $stok_minimal, '$deskripsi', 1, NOW())";

    if (mysqli_query($conn, $sql)) {
        header("Location: barang_list.php");
        exit;
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Ambil list kategori untuk dropdown (jika ada tabel kategori)
$kategori_list = [];
$check = mysqli_query($conn, "SHOW TABLES LIKE 'kategori'");
if ($check && mysqli_num_rows($check) > 0) {
    $r = mysqli_query($conn, "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
    if ($r) $kategori_list = mysqli_fetch_all($r, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tambah Barang</title>
    <link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h3>Tambah Barang</h3>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" class="card p-4 shadow-sm">
        <div class="mb-3">
            <label>Kode Barang</label>
            <input type="text" name="kode_barang" class="form-control" required placeholder="Misal: BR001">
        </div>
        <div class="mb-3">
            <label>Nama Barang</label>
            <input type="text" name="nama_barang" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Kategori</label>
            <select name="kategori_id" class="form-select">
                <option value="0">-- Tidak ada/Default --</option>
                <?php foreach ($kategori_list as $kat): ?>
                    <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['nama_kategori']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Satuan</label>
            <input type="text" name="satuan" class="form-control" placeholder="pcs/box/kg">
        </div>

        <div class="mb-3 row">
            <div class="col-md-6">
                <label>Harga Beli</label>
                <input type="number" name="harga_beli" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="col-md-6">
                <label>Harga Jual</label>
                <input type="number" name="harga_jual" class="form-control" min="0" step="0.01" value="0">
            </div>
        </div>

        <div class="mb-3">
            <label>Stok Minimal</label>
            <input type="number" name="stok_minimal" class="form-control" min="0" value="0">
        </div>

        <div class="mb-3">
            <label>Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">Simpan</button>
    </form>
    <a href="barang_list.php" class="btn btn-secondary mt-3">Kembali</a>
</div>
</body>
</html>
