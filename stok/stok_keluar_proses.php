<?php
include '../koneksi.php';

// Ambil data dari form
$id_barang = $_POST['id_barang'];
$jumlah_keluar = $_POST['jumlah'];
$tanggal_keluar = $_POST['tanggal_keluar'];

// Validasi input
if ($id_barang == "" || $jumlah_keluar == "" || $tanggal_keluar == "") {
    echo "<div class='alert alert-danger'>Semua field wajib diisi!</div>";
    exit;
}

// FIFO: Ambil stok_masuk paling lama
$query_stok = mysqli_query($koneksi, "SELECT * FROM stok_masuk WHERE id_barang='$id_barang' AND jumlah > 0 ORDER BY tanggal_masuk ASC");

if (!$query_stok || mysqli_num_rows($query_stok) == 0) {
    echo "<div class='alert alert-danger'>Stok barang tidak tersedia!</div>";
    exit;
}

$sisa_keluar = $jumlah_keluar;
$total_keluar = 0;

// Proses FIFO
while ($row = mysqli_fetch_assoc($query_stok)) {
    $id_masuk = $row['id_masuk'];
    $stok_tersedia = $row['jumlah'];

    if ($sisa_keluar <= 0) break;

    if ($stok_tersedia >= $sisa_keluar) {
        // Kurangi stok_masuk
        $new_stok = $stok_tersedia - $sisa_keluar;
        mysqli_query($koneksi, "UPDATE stok_masuk SET jumlah='$new_stok' WHERE id_masuk='$id_masuk'");

        // Catat stok_keluar
        mysqli_query($koneksi, "INSERT INTO stok_keluar (id_barang, tanggal_keluar, jumlah) VALUES ('$id_barang', '$tanggal_keluar', '$sisa_keluar')");
        $total_keluar += $sisa_keluar;
        $sisa_keluar = 0;
    } else {
        // Jika stok masuk tidak cukup, habiskan stok itu dulu
        mysqli_query($koneksi, "UPDATE stok_masuk SET jumlah=0 WHERE id_masuk='$id_masuk'");

        // Catat stok_keluar sebagian
        mysqli_query($koneksi, "INSERT INTO stok_keluar (id_barang, tanggal_keluar, jumlah) VALUES ('$id_barang', '$tanggal_keluar', '$stok_tersedia')");
        $total_keluar += $stok_tersedia;
        $sisa_keluar -= $stok_tersedia;
    }
}

// Update total stok di tabel barang
mysqli_query($koneksi, "UPDATE barang SET stok = stok - $total_keluar WHERE id_barang='$id_barang'");

if ($sisa_keluar > 0) {
    echo "<div class='alert alert-warning'>Sebagian stok tidak cukup. Hanya $total_keluar unit yang berhasil dikeluarkan.</div>";
} else {
    echo "<div class='alert alert-success'>Stok keluar berhasil dicatat sebanyak $total_keluar unit!</div>";
}

// Redirect kembali ke list
echo "<meta http-equiv='refresh' content='2; url=stok_keluar_list.php'>";
?>
