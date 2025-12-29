<?php
include "../koneksi.php";
$id = $_GET['id'];

// ambil jumlah dan id_barang
$get = mysqli_query($conn, "SELECT * FROM stok_masuk WHERE id_masuk = $id");
$data = mysqli_fetch_assoc($get);

$id_barang = $data['id_barang'];
$jumlah = $data['jumlah_masuk'];

// hapus stok masuk
mysqli_query($conn, "DELETE FROM stok_masuk WHERE id_masuk = $id");

// kurangi stok total barang
mysqli_query($conn, "UPDATE barang SET stok_total = stok_total - $jumlah WHERE id_barang = $id_barang");

header("Location: stok_masuk_list.php");
?>
