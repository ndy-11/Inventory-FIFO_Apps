<?php
include '../koneksi.php';

$id_barang      = $_POST['id_barang'];
$jumlah_masuk   = $_POST['jumlah_masuk'];
$harga_beli     = $_POST['harga_beli'];
$tanggal_masuk  = $_POST['tanggal_masuk'];

// Simpan ke tabel stok_masuk
mysqli_query($conn, "INSERT INTO stok_masuk (id_barang, jumlah_masuk, harga_beli, tanggal_masuk)
                     VALUES ('$id_barang', '$jumlah_masuk', '$harga_beli', '$tanggal_masuk')");

// Update stok di tabel barang
mysqli_query($conn, "UPDATE barang SET stok = stok + $jumlah_masuk WHERE id_barang = '$id_barang'");

// Kembali ke halaman list
header("Location: stok_masuk_list.php");
exit;
?>
