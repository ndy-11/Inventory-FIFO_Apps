<?php include "../koneksi.php"; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>📦 Stok Masuk | FIFO Inventory</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap Icon -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            font-family: "Poppins", sans-serif;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background-color: #1976d2;
            border: none;
        }
        .btn-primary:hover {
            background-color: #1565c0;
        }
        footer {
            text-align: center;
            margin-top: 40px;
            color: #555;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-primary text-white d-flex align-items-center">
            <i class="bi bi-box-seam me-2"></i>
            <h5 class="mb-0">Tambah Stok Masuk</h5>
        </div>
        <div class="card-body bg-light">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nama Barang</label>
                    <select name="id_barang" class="form-select" required>
                        <option value="">-- Pilih Barang --</option>
                        <?php
                        $barang = mysqli_query($koneksi, "SELECT * FROM barang");
                        while ($b = mysqli_fetch_assoc($barang)) {
                            echo "<option value='{$b['id_barang']}'>{$b['nama_barang']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-calendar-event"></i> Tanggal Masuk</label>
                    <input type="date" name="tanggal_masuk" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-box-arrow-in-down"></i> Jumlah Masuk</label>
                    <input type="number" name="jumlah_masuk" class="form-control" placeholder="Masukkan jumlah barang" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-cash-stack"></i> Harga Beli (Rp)</label>
                    <input type="number" step="0.01" name="harga_beli" class="form-control" placeholder="Masukkan harga beli per item" required>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="stok_masuk_list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left-circle"></i> Kembali
                    </a>
                    <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer>
    <p class="mt-4">&copy; 2025 <strong>FIFO Inventory</strong> | Sistem Manajemen Stok</p>
</footer>

<?php
if (isset($_POST['simpan'])) {
    $id_barang = $_POST['id_barang'];
    $tanggal_masuk = $_POST['tanggal_masuk'];
    $jumlah_masuk = $_POST['jumlah_masuk'];
    $harga_beli = $_POST['harga_beli'];

    // Simpan ke tabel stok_masuk
    $sql = "INSERT INTO stok_masuk (id_barang, tanggal_masuk, jumlah_masuk, harga_beli)
            VALUES ('$id_barang', '$tanggal_masuk', '$jumlah_masuk', '$harga_beli')";
    if (mysqli_query($koneksi, $sql)) {
        // Update stok_total di tabel barang
        mysqli_query($koneksi, "UPDATE barang SET stok_total = stok_total + $jumlah_masuk WHERE id_barang = $id_barang");
        echo "<script>
            alert('Stok masuk berhasil disimpan!');
            window.location='stok_masuk_list.php';
        </script>";
    } else {
        echo "<div class='alert alert-danger text-center mt-3'>Error: " . mysqli_error($koneksi) . "</div>";
    }
}
?>
</body>
</html>
