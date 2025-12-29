<?php
include '../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$id_barang = $_POST['id_barang'];
$jumlah_keluar = $_POST['jumlah'];
$harga_jual = $_POST['harga'];

$koneksi->begin_transaction();

try {
    // Hitung total stok tersedia
    $stok_tersedia = 0;
    $q_stok = mysqli_query($koneksi, "SELECT * FROM stok_masuk WHERE id_barang='$id_barang' AND sisa > 0 ORDER BY tanggal_masuk ASC");
    while ($row = mysqli_fetch_assoc($q_stok)) {
        $stok_tersedia += $row['sisa'];
    }

    if ($stok_tersedia < $jumlah_keluar) {
        throw new Exception("Stok tidak mencukupi");
    }

    // Buat transaksi penjualan utama
    mysqli_query($koneksi, "INSERT INTO penjualan (total_harga) VALUES (0)");
    $id_penjualan = mysqli_insert_id($koneksi);

    $sisa_keluar = $jumlah_keluar;
    $total_harga = 0;

    // FIFO logic
    $stok_fifo = mysqli_query($koneksi, "SELECT * FROM stok_masuk WHERE id_barang='$id_barang' AND sisa > 0 ORDER BY tanggal_masuk ASC");
    while ($row = mysqli_fetch_assoc($stok_fifo)) {
        if ($sisa_keluar == 0) break;

        $pakai = min($row['sisa'], $sisa_keluar);
        $subtotal = $pakai * $harga_jual;
        $total_harga += $subtotal;

        // Kurangi stok
        mysqli_query($koneksi, "UPDATE stok_masuk SET sisa = sisa - $pakai WHERE id_masuk = '{$row['id_masuk']}'");

        // Catat ke stok_keluar
        mysqli_query($koneksi, "INSERT INTO stok_keluar (id_barang, jumlah, tanggal_keluar) 
                             VALUES ('$id_barang', '$pakai', NOW())");

        // Catat ke detail penjualan
        mysqli_query($koneksi, "INSERT INTO penjualan_detail (id_penjualan, id_barang, jumlah, harga_satuan, subtotal) 
                             VALUES ('$id_penjualan', '$id_barang', '$pakai', '$harga_jual', '$subtotal')");

        $sisa_keluar -= $pakai;
    }

    // Update total harga penjualan
    mysqli_query($koneksi, "UPDATE penjualan SET total_harga='$total_harga' WHERE id_penjualan='$id_penjualan'");

    $koneksi->commit();
    header("Location: penjualan.php?status=success");
} catch (Exception $e) {
    $koneksi->rollback();
    header("Location: penjualan.php?status=failed");
}
?>
