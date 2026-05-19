<?php
require_once __DIR__ . '/config.php';

if (app_is_post()) {
    $username = app_clean($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        app_flash('error', 'Please enter both username and password.');
    } elseif (!$pdo) {
        app_flash('error', 'Database connection is unavailable.');
    } else {
        $statement = $pdo->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();

        if ($admin && app_verify_password($admin['username'], $password, $admin['password'])) {
            $_SESSION['auth'] = [
                'role' => 'admin',
                'id' => (int) $admin['id'],
                'username' => $admin['username'],
                'display_name' => ucfirst($admin['username']),
            ];

            app_flash('success', 'Welcome back, Admin.');
            app_redirect('/admin/dashboard.php');
        }

        app_flash('error', 'Invalid admin credentials.');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?php echo app_safe_string(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card login-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <img src="assets/images/college-logo.svg" alt="College Logo" width="70" height="70" class="mb-3">
                    <h1 class="h3 text-navy mb-2">Admin Login</h1>
                    <p class="muted-text mb-0">Access the admin dashboard to manage faculty records and departments.</p>
                </div>

                <?php if ($message = app_flash('error')): ?>
                    <div class="alert alert-danger"><?php echo app_safe_string($message); ?></div>
                <?php endif; ?>
                <?php if ($message = app_flash('success')): ?>
                    <div class="alert alert-success"><?php echo app_safe_string($message); ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" required value="<?php echo app_safe_string(app_old('username')); ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-navy w-100 py-2">Sign In</button>
                </form>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-decoration-none text-navy">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
