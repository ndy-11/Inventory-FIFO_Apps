<?php
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();

$success = '';
$error = '';

// Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    if (empty($nama_kategori)) {
        $error = "Nama kategori tidak boleh kosong!";
    } else {
        $query = "INSERT INTO kategori (nama_kategori, deskripsi) VALUES ('$nama_kategori', '$deskripsi')";
        if (mysqli_query($conn, $query)) {
            $success = "Kategori berhasil ditambahkan!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Edit Kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    if (empty($nama_kategori)) {
        $error = "Nama kategori tidak boleh kosong!";
    } else {
        $query = "UPDATE kategori SET nama_kategori='$nama_kategori', deskripsi='$deskripsi' WHERE id='$id'";
        if (mysqli_query($conn, $query)) {
            $success = "Kategori berhasil diperbarui!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Hapus Kategori
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    $query = "DELETE FROM kategori WHERE id='$id'";
    if (mysqli_query($conn, $query)) {
        $success = "Kategori berhasil dihapus!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get all kategori
$query = "SELECT * FROM kategori ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$kategori_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get kategori untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = mysqli_real_escape_string($conn, $_GET['edit']);
    $query = "SELECT * FROM kategori WHERE id='$id'";
    $result = mysqli_query($conn, $query);
    $edit_data = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Barang - FIFO App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-box-seam"></i> FIFO App
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="user-badge">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?>
                </span>
                <a href="logout.php" class="btn btn-sm btn-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row" style="min-height: calc(100vh - 70px);">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar d-none d-md-block">
                <nav class="nav flex-column">
                    <a href="index.php" class="nav-link">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>

                    <a href="master_barang.php" class="nav-link">
                        <i class="bi bi-box-seam"></i> Master Barang
                    </a>

                    <a href="input_barang.php" class="nav-link">
                        <i class="bi bi-plus-circle"></i> Input Barang
                    </a>

                    <a href="output_barang.php" class="nav-link">
                        <i class="bi bi-dash-circle"></i> Output Barang
                    </a>

                    <a href="kategori.php" class="nav-link">
                        <i class="bi bi-tags"></i> Kategori
                    </a>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                        <a href="laporan.php" class="nav-link">
                            <i class="bi bi-bar-chart"></i> Laporan
                        </a>
                    <?php } ?>
                </nav>

            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-tags-fill"></i> Manajemen Kategori</h1>
                    <p>Kelola kategori barang untuk input dan output</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-custom alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-custom alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form Tambah/Edit -->
                    <div class="col-lg-4">
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-plus-circle"></i> 
                                    <?php echo $edit_data ? 'Edit Kategori' : 'Tambah Kategori'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?php echo $edit_data ? 'edit' : 'tambah'; ?>">
                                    <?php if ($edit_data): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                                    <?php endif; ?>

                                    <div class="form-group mb-3">
                                        <label for="nama_kategori" class="form-label">Nama Kategori</label>
                                        <input type="text" id="nama_kategori" name="nama_kategori" class="form-control" 
                                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_kategori']) : ''; ?>" 
                                               required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="deskripsi" class="form-label">Deskripsi</label>
                                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="4" 
                                                  placeholder="Jelaskan kategori ini..."><?php echo $edit_data ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="bi bi-<?php echo $edit_data ? 'pencil' : 'plus-lg'; ?>"></i>
                                            <?php echo $edit_data ? 'Update' : 'Tambah'; ?>
                                        </button>
                                        <?php if ($edit_data): ?>
                                            <a href="kategori.php" class="btn btn-secondary">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Kategori -->
                    <div class="col-lg-8">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Kategori (<?php echo count($kategori_list); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($kategori_list) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>Nama Kategori</th>
                                                    <th>Deskripsi</th>
                                                    <th>Dibuat</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = 1; foreach ($kategori_list as $kat): ?>
                                                    <tr>
                                                        <td><?php echo $no++; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($kat['nama_kategori']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <small><?php echo htmlspecialchars(substr($kat['deskripsi'], 0, 50)) . (strlen($kat['deskripsi']) > 50 ? '...' : ''); ?></small>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($kat['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <a href="kategori.php?edit=<?php echo $kat['id']; ?>" class="btn btn-sm btn-warning">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="kategori.php?delete=<?php echo $kat['id']; ?>" class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Yakin hapus kategori ini?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="text-muted mt-3">Belum ada kategori. Tambahkan kategori baru di form sebelah!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
