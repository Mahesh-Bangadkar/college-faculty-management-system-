<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo app_safe_string(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg topbar navbar-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="assets/images/college-logo.svg" alt="College Logo" width="36" height="36">
            <span><?php echo app_safe_string(APP_NAME); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="admin-login.php">Admin Login</a></li>
                <li class="nav-item"><a class="nav-link" href="faculty-login.php">Faculty Login</a></li>
            </ul>
        </div>
    </div>
</nav>

<main id="home" class="py-5">
    <div class="container">
        <?php if ($message = app_flash('success')): ?>
            <div class="alert alert-success border-0 shadow-sm"><?php echo app_safe_string($message); ?></div>
        <?php endif; ?>
        <?php if ($message = app_flash('error')): ?>
            <div class="alert alert-danger border-0 shadow-sm"><?php echo app_safe_string($message); ?></div>
        <?php endif; ?>

        <div class="hero-panel p-4 p-lg-5 mb-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="badge badge-soft rounded-pill mb-3 px-3 py-2">Official Faculty Management Portal</span>
                    <h1 class="display-6 fw-bold text-navy mb-3">College Faculty Management Website</h1>
                    <div class="accent-line mb-4"></div>
                    <p class="lead muted-text mb-4">
                        A clean and secure portal for faculty records, department administration, and profile management.
                        Built in a professional university style with responsive Bootstrap 5 layouts.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="admin-login.php" class="btn btn-navy btn-lg px-4">Admin Login</a>
                        <a href="faculty-login.php" class="btn btn-outline-navy btn-lg px-4">Faculty Login</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card login-card p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="assets/images/college-logo.svg" alt="College Logo" width="64" height="64">
                            <div>
                                <h2 class="h5 mb-1 brand-title"><?php echo app_safe_string(APP_NAME); ?></h2>
                                <p class="muted-text mb-0">Secure access for authorized users only</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light">
                                    <i class="bi bi-shield-lock text-navy me-2"></i> Separate login routes for Admin and Faculty
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light">
                                    <i class="bi bi-table text-navy me-2"></i> Department, profile, and report management
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light">
                                    <i class="bi bi-phone text-navy me-2"></i> Fully responsive on desktop and mobile
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <a class="text-decoration-none" href="admin-login.php">
                    <div class="card login-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge badge-soft mb-2">Portal Access</span>
                                <h3 class="h4 text-navy mb-1">Admin Login</h3>
                            </div>
                            <i class="bi bi-person-gear fs-2 text-navy"></i>
                        </div>
                        <p class="muted-text mb-0">Manage faculty records, departments, reports, and uploads from a secure dashboard.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a class="text-decoration-none" href="faculty-login.php">
                    <div class="card login-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge badge-soft mb-2">Portal Access</span>
                                <h3 class="h4 text-navy mb-1">Faculty Login</h3>
                            </div>
                            <i class="bi bi-mortarboard fs-2 text-navy"></i>
                        </div>
                        <p class="muted-text mb-0">Update your own profile, add research details, upload documents, and change your password.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</main>

<footer class="footer-bar py-4 mt-5">
    <div class="container">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <strong><?php echo app_safe_string(APP_NAME); ?></strong><br>
                <small>Address: College Road, Education City, State, India | Phone: +91 98765 43210 | Email: info@college.edu</small>
            </div>
            <div class="col-md-4 text-md-end">
                <small>&copy; <?php echo date('Y'); ?> College Faculty Management System</small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
