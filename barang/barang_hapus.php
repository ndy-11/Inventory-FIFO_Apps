<?php
include "../koneksi.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "DELETE FROM barang WHERE id_barang='$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: barang_list.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
