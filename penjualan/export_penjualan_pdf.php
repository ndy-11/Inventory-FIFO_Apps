<?php
include '../koneksi.php';
header("Content-Type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=laporan_penjualan.xls");

$query = mysqli_query($conn, "
    SELECT p.*, b.nama_barang 
    FROM penjualan p 
    JOIN barang b ON p.id_barang = b.id_barang 
    ORDER BY p.tanggal_penjualan DESC
");
?>
<table border="1" cellpadding="5">
<tr>
  <th>No</th><th>Tanggal</th><th>Nama Barang</th><th>Jumlah</th><th>Harga</th><th>Total</th>
</tr>
<?php
$no=1;
while($r=mysqli_fetch_assoc($query)){
  echo "<tr>
    <td>$no</td>
    <td>{$r['tanggal_penjualan']}</td>
    <td>{$r['nama_barang']}</td>
    <td>{$r['jumlah_terjual']}</td>
    <td>{$r['harga_jual']}</td>
    <td>{$r['total_harga']}</td>
  </tr>";
  $no++;
}
?>
</table>
