<?php
session_start();
require_once 'config/auth.php';
require_once 'koneksi.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FIFO App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <?php require_once 'includes/navbar.php'; ?>

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
                    <?php if (isRole('admin')) { ?>
                        <a href="laporan.php" class="nav-link">
                            <i class="bi bi-bar-chart"></i> Laporan
                        </a>
                    <?php } ?>
                </nav>
            </div>
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-house-door-fill"></i> Dashboard</h1>
                    <p>Selamat datang di FIFO App - Sistem Manajemen Barang</p>
                </div>

                <!-- Stats Grid -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h6>Total Barang</h6>
                        <h3>0</h3>
                        <small class="text-muted">Barang di gudang</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #51cf66;">
                        <h6>Input Hari Ini</h6>
                        <h3>0</h3>
                        <small class="text-muted">Barang masuk</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ff6b6b;">
                        <h6>Output Hari Ini</h6>
                        <h3>0</h3>
                        <small class="text-muted">Barang keluar</small>
                    </div>
                    <div class="stat-card" style="border-left-color: #ffd93d;">
                        <h6>Total Kategori</h6>
                        <h3>0</h3>
                        <small class="text-muted">Kategori barang</small>
                    </div>
                </div>

                <!-- Menu Cards -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <a href="master_barang.php" class="text-decoration-none">
                            <div class="card card-custom">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-collection" style="font-size: 48px; color: #4ecdc4;"></i>
                                    <h5 class="card-title mt-3">Master Barang</h5>
                                    <p class="card-text text-muted">Kelola data produk</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="input_barang.php" class="text-decoration-none">
                            <div class="card card-custom">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-plus-circle" style="font-size: 48px; color: #667eea;"></i>
                                    <h5 class="card-title mt-3">Input Barang</h5>
                                    <p class="card-text text-muted">Masukkan barang masuk</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="output_barang.php" class="text-decoration-none">
                            <div class="card card-custom">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-dash-circle" style="font-size: 48px; color: #51cf66;"></i>
                                    <h5 class="card-title mt-3">Output Barang</h5>
                                    <p class="card-text text-muted">Keluarkan barang dari gudang</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="kategori.php" class="text-decoration-none">
                            <div class="card card-custom">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-tags" style="font-size: 48px; color: #4ecdc4;"></i>
                                    <h5 class="card-title mt-3">Kategori</h5>
                                    <p class="card-text text-muted">Kelola kategori barang</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php if (isRole('admin')) { ?>
                    <div class="col-md-4">
                        <a href="laporan.php" class="text-decoration-none">
                            <div class="card card-custom">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-bar-chart" style="font-size: 48px; color: #ffd93d;"></i>
                                    <h5 class="card-title mt-3">Laporan</h5>
                                    <p class="card-text text-muted">Lihat laporan dan analisis barang</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>