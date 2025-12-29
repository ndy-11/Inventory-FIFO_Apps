<?php
echo "<h1>Debug FIFO App</h1>";

echo "<h2>1. Cek File yang Ada:</h2>";
$files = [
    'login.php',
    'index.php',
    'logout.php',
    'input_barang.php',
    'output_barang.php',
    'kategori.php',
    'laporan.php',
    'config/auth.php',
    'assets/css/style.css'
];

foreach ($files as $file) {
    $exists = file_exists($file) ? '✅' : '❌';
    echo "$exists $file<br>";
}

echo "<h2>2. Cek Koneksi Database:</h2>";
require_once 'config/auth.php';
echo "Koneksi: ✅ OK<br>";

$query = "SHOW TABLES";
$result = mysqli_query($conn, $query);
echo "Tabel yang ada:<br>";
while ($row = mysqli_fetch_row($result)) {
    echo "- " . $row[0] . "<br>";
}

echo "<h2>3. Akses File:</h2>";
echo "<a href='login.php'>Login</a><br>";
echo "<a href='index.php'>Dashboard</a><br>";
echo "<a href='kategori.php'>Kategori</a><br>";
?>
