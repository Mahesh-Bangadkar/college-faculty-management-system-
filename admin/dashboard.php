<?php
require_once __DIR__ . '/../config.php';

app_require_role('admin');

if (!$pdo) {
    app_flash('error', 'Database connection is unavailable.');
    app_redirect('/index.php');
}

function admin_sync_department_counts(PDO $pdo): void
{
    $pdo->exec("UPDATE departments d LEFT JOIN (SELECT department, COUNT(*) AS faculty_total FROM faculty_users GROUP BY department) f ON f.department = d.department_name SET d.faculty_count = COALESCE(f.faculty_total, 0)");
}

function admin_remove_faculty_files(int $facultyId, PDO $pdo): void
{
    $statement = $pdo->prepare('SELECT profile_photo FROM faculty_profiles WHERE faculty_id = :faculty_id LIMIT 1');
    $statement->execute(['faculty_id' => $facultyId]);
    $profile = $statement->fetch();

    if (!empty($profile['profile_photo'])) {
        $photoPath = __DIR__ . '/../' . ltrim((string) $profile['profile_photo'], '/');
        if (is_file($photoPath)) {
            unlink($photoPath);
        }
    }

    $documentStatement = $pdo->prepare('SELECT file_path FROM faculty_documents WHERE faculty_id = :faculty_id');
    $documentStatement->execute(['faculty_id' => $facultyId]);

    foreach ($documentStatement->fetchAll() as $document) {
        $documentPath = __DIR__ . '/../' . ltrim((string) $document['file_path'], '/');
        if (is_file($documentPath)) {
            unlink($documentPath);
        }
    }

    $deleteDocuments = $pdo->prepare('DELETE FROM faculty_documents WHERE faculty_id = :faculty_id');
    $deleteDocuments->execute(['faculty_id' => $facultyId]);
}

if (app_is_post()) {
    $action = app_clean($_POST['action'] ?? '');

    try {
        if ($action === 'save_faculty') {
            $facultyId = (int) ($_POST['faculty_id'] ?? 0);
            $fullName = app_clean($_POST['full_name'] ?? '');
            $employeeId = app_clean($_POST['employee_id'] ?? '');
            $email = app_clean($_POST['email'] ?? '');
            $department = app_clean($_POST['department'] ?? '');
            $designation = app_clean($_POST['designation'] ?? '');
            $phone = app_clean($_POST['phone'] ?? '');
            $temporaryPassword = (string) ($_POST['temporary_password'] ?? '');

            if ($fullName === '' || $employeeId === '' || $email === '' || $department === '' || $designation === '' || $phone === '') {
                throw new RuntimeException('Please fill all faculty fields.');
            }

            if ($facultyId > 0) {
                $statement = $pdo->prepare('SELECT * FROM faculty_users WHERE id = :id LIMIT 1');
                $statement->execute(['id' => $facultyId]);
                $existingFaculty = $statement->fetch();

                if (!$existingFaculty) {
                    throw new RuntimeException('Faculty record not found.');
                }

                $passwordHash = $existingFaculty['password'];
                if ($temporaryPassword !== '') {
                    $passwordHash = app_hash_password($employeeId, $temporaryPassword);
                }

                $update = $pdo->prepare('UPDATE faculty_users SET full_name = :full_name, email = :email, department = :department, designation = :designation, phone = :phone, password = :password WHERE id = :id');
                $update->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'department' => $department,
                    'designation' => $designation,
                    'phone' => $phone,
                    'password' => $passwordHash,
                    'id' => $facultyId,
                ]);
            } else {
                if ($temporaryPassword === '') {
                    throw new RuntimeException('A temporary password is required for new faculty accounts.');
                }

                $insert = $pdo->prepare('INSERT INTO faculty_users (full_name, employee_id, email, password, department, designation, phone, created_at) VALUES (:full_name, :employee_id, :email, :password, :department, :designation, :phone, NOW())');
                $insert->execute([
                    'full_name' => $fullName,
                    'employee_id' => $employeeId,
                    'email' => $email,
                    'password' => app_hash_password($employeeId, $temporaryPassword),
                    'department' => $department,
                    'designation' => $designation,
                    'phone' => $phone,
                ]);

                $newFacultyId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO faculty_profiles (faculty_id, qualification, experience, research_interests, achievements, certifications, publications, profile_photo, updated_at) VALUES (:faculty_id, \'\', \'\', \'\', \'\', \'\', \'\', NULL, NOW())')->execute(['faculty_id' => $newFacultyId]);
            }

            admin_sync_department_counts($pdo);
            app_flash('success', 'Faculty record saved successfully.');
            app_redirect('/admin/dashboard.php');
        }

        if ($action === 'delete_faculty') {
            $facultyId = (int) ($_POST['faculty_id'] ?? 0);
            if ($facultyId > 0) {
                admin_remove_faculty_files($facultyId, $pdo);
                $pdo->prepare('DELETE FROM faculty_profiles WHERE faculty_id = :faculty_id')->execute(['faculty_id' => $facultyId]);
                $pdo->prepare('DELETE FROM faculty_users WHERE id = :id')->execute(['id' => $facultyId]);
                admin_sync_department_counts($pdo);
                app_flash('success', 'Faculty record deleted successfully.');
            }
            app_redirect('/admin/dashboard.php');
        }

        if ($action === 'save_department') {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $departmentName = app_clean($_POST['department_name'] ?? '');
            $hodName = app_clean($_POST['hod_name'] ?? '');

            if ($departmentName === '') {
                throw new RuntimeException('Department name is required.');
            }

            if ($departmentId > 0) {
                $pdo->prepare('UPDATE departments SET department_name = :department_name, hod_name = :hod_name WHERE id = :id')->execute([
                    'department_name' => $departmentName,
                    'hod_name' => $hodName,
                    'id' => $departmentId,
                ]);
            } else {
                $pdo->prepare('INSERT INTO departments (department_name, hod_name, faculty_count) VALUES (:department_name, :hod_name, 0)')->execute([
                    'department_name' => $departmentName,
                    'hod_name' => $hodName,
                ]);
            }

            admin_sync_department_counts($pdo);
            app_flash('success', 'Department saved successfully.');
            app_redirect('/admin/dashboard.php');
        }

        if ($action === 'delete_department') {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            if ($departmentId > 0) {
                $pdo->prepare('DELETE FROM departments WHERE id = :id')->execute(['id' => $departmentId]);
                admin_sync_department_counts($pdo);
                app_flash('success', 'Department removed successfully.');
            }
            app_redirect('/admin/dashboard.php');
        }
    } catch (Throwable $exception) {
        app_flash('error', $exception->getMessage());
        app_redirect('/admin/dashboard.php');
    }
}

$departmentEditId = (int) ($_GET['edit_department'] ?? 0);
$facultyEditId = (int) ($_GET['edit_faculty'] ?? 0);
$search = app_clean($_GET['q'] ?? '');
$departmentFilter = app_clean($_GET['department'] ?? '');
$export = app_clean($_GET['export'] ?? '');

$departmentEdit = null;
$facultyEdit = null;

if ($departmentEditId > 0) {
    $statement = $pdo->prepare('SELECT * FROM departments WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $departmentEditId]);
    $departmentEdit = $statement->fetch();
}

if ($facultyEditId > 0) {
    $statement = $pdo->prepare('SELECT * FROM faculty_users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $facultyEditId]);
    $facultyEdit = $statement->fetch();
}

admin_sync_department_counts($pdo);

$facultyWhere = [];
$facultyParams = [];
if ($search !== '') {
    $facultyWhere[] = '(f.full_name LIKE :search OR f.department LIKE :search)';
    $facultyParams['search'] = '%' . $search . '%';
}
if ($departmentFilter !== '') {
    $facultyWhere[] = 'f.department = :department';
    $facultyParams['department'] = $departmentFilter;
}

$facultySql = 'SELECT f.*, p.qualification, p.experience, p.research_interests, p.achievements, p.certifications, p.publications, p.profile_photo, p.updated_at FROM faculty_users f LEFT JOIN faculty_profiles p ON p.faculty_id = f.id';
if ($facultyWhere) {
    $facultySql .= ' WHERE ' . implode(' AND ', $facultyWhere);
}
$facultySql .= ' ORDER BY f.created_at DESC';

$facultyStatement = $pdo->prepare($facultySql);
$facultyStatement->execute($facultyParams);
$facultyRows = $facultyStatement->fetchAll();

if ($export === 'csv' || $export === 'pdf') {
    $exportHeaders = ['Full Name', 'Employee ID', 'Email', 'Department', 'Designation', 'Phone', 'Qualification', 'Experience', 'Achievements', 'Certifications', 'Publications'];
    $exportRows = [];
    foreach ($facultyRows as $row) {
        $exportRows[] = [
            $row['full_name'],
            $row['employee_id'],
            $row['email'],
            $row['department'],
            $row['designation'],
            $row['phone'],
            $row['qualification'] ?? '',
            $row['experience'] ?? '',
            $row['achievements'] ?? '',
            $row['certifications'] ?? '',
            $row['publications'] ?? '',
        ];
    }

    if ($export === 'csv') {
        app_send_csv('faculty-report.csv', $exportHeaders, $exportRows);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="faculty-report.pdf"');
    echo app_generate_simple_pdf('Faculty Report', $exportHeaders, $exportRows);
    exit;
}

$departmentRows = $pdo->query('SELECT * FROM departments ORDER BY department_name ASC')->fetchAll();
$documentRows = $pdo->query('SELECT d.*, f.full_name, f.employee_id FROM faculty_documents d INNER JOIN faculty_users f ON f.id = d.faculty_id ORDER BY d.uploaded_at DESC')->fetchAll();

$facultyCount = (int) $pdo->query('SELECT COUNT(*) FROM faculty_users')->fetchColumn();
$departmentCount = (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn();
$documentCount = (int) $pdo->query('SELECT COUNT(*) FROM faculty_documents')->fetchColumn();
$achievementCount = (int) $pdo->query("SELECT COUNT(*) FROM faculty_profiles WHERE COALESCE(achievements, '') <> ''")->fetchColumn();

$loginUser = app_current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | <?php echo app_safe_string(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 sidebar p-4 text-white">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="../assets/images/college-logo.svg" alt="Logo" width="48" height="48">
                <div>
                    <div class="fw-bold">Admin Panel</div>
                    <small class="text-white-50">College Portal</small>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link active" href="#overview"><i class="bi bi-speedometer2 me-2"></i>Dashboard Overview</a>
                <a class="nav-link" href="#faculty"><i class="bi bi-people me-2"></i>Faculty Management</a>
                <a class="nav-link" href="#departments"><i class="bi bi-diagram-3 me-2"></i>Department Management</a>
                <a class="nav-link" href="#reports"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a>
                <a class="nav-link" href="#settings"><i class="bi bi-gear me-2"></i>Settings</a>
                <a class="nav-link mt-3" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </aside>

        <main class="col-lg-10 p-0">
            <nav class="navbar navbar-expand-lg bg-white border-bottom px-3 px-lg-4 shadow-sm">
                <div class="container-fluid">
                    <span class="navbar-brand mb-0 h1 text-navy">College Faculty Management System</span>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <span class="badge badge-soft">Admin</span>
                        <span class="muted-text small"><?php echo app_safe_string($loginUser['display_name'] ?? ''); ?></span>
                    </div>
                </div>
            </nav>

            <div class="p-3 p-lg-4">
                <?php if ($message = app_flash('success')): ?>
                    <div class="alert alert-success border-0 shadow-sm"><?php echo app_safe_string($message); ?></div>
                <?php endif; ?>
                <?php if ($message = app_flash('error')): ?>
                    <div class="alert alert-danger border-0 shadow-sm"><?php echo app_safe_string($message); ?></div>
                <?php endif; ?>

                <section id="overview" class="mb-4">
                    <div class="page-shell p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <h1 class="h4 section-title mb-1">Dashboard Overview</h1>
                                <p class="muted-text mb-0">Quick summary of faculty records and department status.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-outline-navy btn-sm" href="?export=csv<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?><?php echo $departmentFilter !== '' ? '&department=' . urlencode($departmentFilter) : ''; ?>">Export CSV</a>
                                <a class="btn btn-navy btn-sm" href="?export=pdf<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?><?php echo $departmentFilter !== '' ? '&department=' . urlencode($departmentFilter) : ''; ?>">Download PDF</a>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="card stat-card h-100 p-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="muted-text small">Total Faculty</div>
                                            <div class="h3 mb-0 text-navy"><?php echo $facultyCount; ?></div>
                                        </div>
                                        <i class="bi bi-people fs-2 text-navy"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card h-100 p-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="muted-text small">Departments</div>
                                            <div class="h3 mb-0 text-navy"><?php echo $departmentCount; ?></div>
                                        </div>
                                        <i class="bi bi-diagram-3 fs-2 text-navy"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card h-100 p-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="muted-text small">Documents</div>
                                            <div class="h3 mb-0 text-navy"><?php echo $documentCount; ?></div>
                                        </div>
                                        <i class="bi bi-folder2-open fs-2 text-navy"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card h-100 p-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="muted-text small">Profiles with Achievements</div>
                                            <div class="h3 mb-0 text-navy"><?php echo $achievementCount; ?></div>
                                        </div>
                                        <i class="bi bi-award fs-2 text-navy"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="faculty" class="mb-4">
                    <div class="page-shell p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <div>
                                <h2 class="h5 section-title mb-1">Faculty Management</h2>
                                <p class="muted-text mb-0">Add, edit, search, and delete faculty records.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="text" class="form-control" placeholder="Search by name or department" data-table-search style="max-width: 280px;">
                            </div>
                        </div>

                        <form method="get" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="text" name="q" class="form-control" placeholder="Search faculty by name or department" value="<?php echo app_safe_string($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departmentRows as $department): ?>
                                        <option value="<?php echo app_safe_string($department['department_name']); ?>" <?php echo $departmentFilter === $department['department_name'] ? 'selected' : ''; ?>>
                                            <?php echo app_safe_string($department['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 d-flex gap-2">
                                <button class="btn btn-navy">Filter</button>
                                <a href="dashboard.php" class="btn btn-outline-navy">Reset</a>
                            </div>
                        </form>

                        <div class="table-responsive mb-4" data-filter-table>
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Faculty</th>
                                        <th>Department</th>
                                        <th>Designation</th>
                                        <th>Contact</th>
                                        <th>Achievements</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($facultyRows as $faculty): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($faculty['profile_photo'])): ?>
                                                <img src="../<?php echo app_safe_string(ltrim((string) $faculty['profile_photo'], '/')); ?>" alt="Profile Photo" class="avatar">
                                            <?php else: ?>
                                                <div class="avatar d-flex align-items-center justify-content-center bg-light text-navy">N/A</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo app_safe_string($faculty['full_name']); ?></div>
                                            <small class="muted-text"><?php echo app_safe_string($faculty['employee_id']); ?> | <?php echo app_safe_string($faculty['email']); ?></small>
                                        </td>
                                        <td><?php echo app_safe_string($faculty['department']); ?></td>
                                        <td><?php echo app_safe_string($faculty['designation']); ?></td>
                                        <td><?php echo app_safe_string($faculty['phone']); ?></td>
                                        <td>
                                            <span class="badge badge-soft"><?php echo $faculty['achievements'] ? 'Updated' : 'Pending'; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a class="btn btn-sm btn-outline-navy" href="?edit_faculty=<?php echo (int) $faculty['id']; ?>#faculty-form">Edit</a>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_faculty">
                                                    <input type="hidden" name="faculty_id" value="<?php echo (int) $faculty['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this faculty record?">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="faculty-form" class="row g-4">
                            <div class="col-lg-7">
                                <div class="border rounded-4 p-4 bg-white">
                                    <h3 class="h6 text-navy mb-3"><?php echo $facultyEdit ? 'Edit Faculty Member' : 'Add New Faculty'; ?></h3>
                                    <form method="post" class="row g-3">
                                        <input type="hidden" name="action" value="save_faculty">
                                        <input type="hidden" name="faculty_id" value="<?php echo (int) ($facultyEdit['id'] ?? 0); ?>">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="full_name" class="form-control" required value="<?php echo app_safe_string($facultyEdit['full_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Employee ID</label>
                                            <input type="text" name="employee_id" class="form-control" required value="<?php echo app_safe_string($facultyEdit['employee_id'] ?? ''); ?>" <?php echo $facultyEdit ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" required value="<?php echo app_safe_string($facultyEdit['email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="text" name="phone" class="form-control" required value="<?php echo app_safe_string($facultyEdit['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Department</label>
                                            <select name="department" class="form-select" required>
                                                <option value="">Select Department</option>
                                                <?php foreach ($departmentRows as $department): ?>
                                                    <option value="<?php echo app_safe_string($department['department_name']); ?>" <?php echo (($facultyEdit['department'] ?? '') === $department['department_name']) ? 'selected' : ''; ?>>
                                                        <?php echo app_safe_string($department['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Designation</label>
                                            <input type="text" name="designation" class="form-control" required value="<?php echo app_safe_string($facultyEdit['designation'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Temporary Password <?php echo $facultyEdit ? '<small class="text-muted">(leave blank to keep current password)</small>' : ''; ?></label>
                                            <input type="text" name="temporary_password" class="form-control" <?php echo $facultyEdit ? '' : 'required'; ?> placeholder="<?php echo $facultyEdit ? 'Optional for updates' : 'Create initial password'; ?>">
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button type="submit" class="btn btn-navy"><?php echo $facultyEdit ? 'Update Faculty' : 'Save Faculty'; ?></button>
                                            <?php if ($facultyEdit): ?>
                                                <a href="dashboard.php#faculty-form" class="btn btn-outline-navy">Cancel</a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="border rounded-4 p-4 bg-white h-100">
                                    <h3 class="h6 text-navy mb-3">Uploaded Profiles and Documents</h3>
                                    <div class="small text-muted mb-3">Latest documents submitted by faculty members.</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Faculty</th>
                                                    <th>Document</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($documentRows as $document): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo app_safe_string($document['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo app_safe_string($document['employee_id']); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="../<?php echo app_safe_string(ltrim((string) $document['file_path'], '/')); ?>" target="_blank" class="text-navy"><?php echo app_safe_string($document['original_name']); ?></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="departments" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Department Management</h2>
                        <p class="muted-text mb-3">Maintain department names, heads of department, and faculty counts.</p>

                        <div class="row g-4">
                            <div class="col-lg-5">
                                <form method="post" class="border rounded-4 p-4 bg-white">
                                    <input type="hidden" name="action" value="save_department">
                                    <input type="hidden" name="department_id" value="<?php echo (int) ($departmentEdit['id'] ?? 0); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Department Name</label>
                                        <input type="text" name="department_name" class="form-control" required value="<?php echo app_safe_string($departmentEdit['department_name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">HOD Name</label>
                                        <input type="text" name="hod_name" class="form-control" value="<?php echo app_safe_string($departmentEdit['hod_name'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-navy"><?php echo $departmentEdit ? 'Update Department' : 'Save Department'; ?></button>
                                    <?php if ($departmentEdit): ?>
                                        <a href="dashboard.php#departments" class="btn btn-outline-navy ms-2">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="col-lg-7">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>HOD</th>
                                                <th>Faculty Count</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($departmentRows as $department): ?>
                                            <tr>
                                                <td><?php echo app_safe_string($department['department_name']); ?></td>
                                                <td><?php echo app_safe_string($department['hod_name']); ?></td>
                                                <td><span class="badge badge-soft"><?php echo (int) $department['faculty_count']; ?></span></td>
                                                <td class="d-flex gap-2">
                                                    <a href="?edit_department=<?php echo (int) $department['id']; ?>#departments" class="btn btn-sm btn-outline-navy">Edit</a>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="delete_department">
                                                        <input type="hidden" name="department_id" value="<?php echo (int) $department['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this department?">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="reports" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Reports</h2>
                        <p class="muted-text mb-3">Download filtered faculty data in PDF or CSV format.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-navy" href="?export=pdf<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?><?php echo $departmentFilter !== '' ? '&department=' . urlencode($departmentFilter) : ''; ?>">Download Faculty PDF</a>
                            <a class="btn btn-outline-navy" href="?export=csv<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?><?php echo $departmentFilter !== '' ? '&department=' . urlencode($departmentFilter) : ''; ?>">Download Faculty CSV</a>
                        </div>
                    </div>
                </section>

                <section id="settings" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Settings</h2>
                        <p class="muted-text mb-0">Session security, password hashing, and file uploads are handled through the shared PHP backend.</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
