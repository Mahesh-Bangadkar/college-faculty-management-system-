<?php
require_once __DIR__ . '/../config.php';

app_require_role('faculty');

if (!$pdo) {
    app_flash('error', 'Database connection is unavailable.');
    app_redirect('/index.php');
}

function faculty_ensure_profile(PDO $pdo, int $facultyId): array
{
    $statement = $pdo->prepare('SELECT * FROM faculty_profiles WHERE faculty_id = :faculty_id LIMIT 1');
    $statement->execute(['faculty_id' => $facultyId]);
    $profile = $statement->fetch();

    if ($profile) {
        return $profile;
    }

    $pdo->prepare('INSERT INTO faculty_profiles (faculty_id, qualification, experience, research_interests, achievements, certifications, publications, profile_photo, updated_at) VALUES (:faculty_id, \'\', \'\', \'\', \'\', \'\', \'\', NULL, NOW())')->execute(['faculty_id' => $facultyId]);

    $statement->execute(['faculty_id' => $facultyId]);

    return $statement->fetch() ?: [];
}

function faculty_relative_path(string $path): string
{
    return '/' . ltrim($path, '/');
}

$auth = app_current_user();
$facultyId = (int) ($auth['id'] ?? 0);

$facultyStatement = $pdo->prepare('SELECT * FROM faculty_users WHERE id = :id LIMIT 1');
$facultyStatement->execute(['id' => $facultyId]);
$faculty = $facultyStatement->fetch();

if (!$faculty) {
    app_flash('error', 'Faculty record was not found.');
    app_redirect('/logout.php');
}

$profile = faculty_ensure_profile($pdo, $facultyId);

if (app_is_post()) {
    $action = app_clean($_POST['action'] ?? '');

    try {
        if ($action === 'update_profile') {
            $fullName = app_clean($_POST['full_name'] ?? '');
            $email = app_clean($_POST['email'] ?? '');
            $phone = app_clean($_POST['phone'] ?? '');
            $qualification = app_clean($_POST['qualification'] ?? '');
            $experience = app_clean($_POST['experience'] ?? '');
            $researchInterests = app_clean($_POST['research_interests'] ?? '');
            $achievements = app_clean($_POST['achievements'] ?? '');
            $certifications = app_clean($_POST['certifications'] ?? '');
            $publications = app_clean($_POST['publications'] ?? '');

            if ($fullName === '' || $email === '' || $phone === '') {
                throw new RuntimeException('Please fill the required profile fields.');
            }

            $pdo->prepare('UPDATE faculty_users SET full_name = :full_name, email = :email, phone = :phone WHERE id = :id')->execute([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'id' => $facultyId,
            ]);

            $pdo->prepare('UPDATE faculty_profiles SET qualification = :qualification, experience = :experience, research_interests = :research_interests, achievements = :achievements, certifications = :certifications, publications = :publications, updated_at = NOW() WHERE faculty_id = :faculty_id')->execute([
                'qualification' => $qualification,
                'experience' => $experience,
                'research_interests' => $researchInterests,
                'achievements' => $achievements,
                'certifications' => $certifications,
                'publications' => $publications,
                'faculty_id' => $facultyId,
            ]);

            app_flash('success', 'Profile updated successfully.');
            app_redirect('/faculty/dashboard.php');
        }

        if ($action === 'upload_photo') {
            $upload = app_uploaded_file($_FILES['profile_photo'], UPLOAD_PATH . '/faculty/' . $facultyId . '/photo', ['jpg', 'jpeg', 'png', 'webp'], 'photo');

            if ($upload) {
                $previous = $profile['profile_photo'] ?? '';
                if ($previous) {
                    $previousPath = __DIR__ . '/../' . ltrim((string) $previous, '/');
                    if (is_file($previousPath)) {
                        unlink($previousPath);
                    }
                }

                $relative = 'uploads/faculty/' . $facultyId . '/photo/' . $upload['name'];
                $pdo->prepare('UPDATE faculty_profiles SET profile_photo = :profile_photo, updated_at = NOW() WHERE faculty_id = :faculty_id')->execute([
                    'profile_photo' => $relative,
                    'faculty_id' => $facultyId,
                ]);

                app_flash('success', 'Profile photo uploaded successfully.');
            }

            app_redirect('/faculty/dashboard.php');
        }

        if ($action === 'upload_document') {
            $upload = app_uploaded_file($_FILES['document_file'], UPLOAD_PATH . '/faculty/' . $facultyId . '/documents', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], 'doc');

            if ($upload) {
                $relative = 'uploads/faculty/' . $facultyId . '/documents/' . $upload['name'];
                $original = app_clean($_FILES['document_file']['name'] ?? '');
                $pdo->prepare('INSERT INTO faculty_documents (faculty_id, original_name, file_path, file_type, uploaded_at) VALUES (:faculty_id, :original_name, :file_path, :file_type, NOW())')->execute([
                    'faculty_id' => $facultyId,
                    'original_name' => $original,
                    'file_path' => $relative,
                    'file_type' => $upload['extension'],
                ]);

                app_flash('success', 'Document uploaded successfully.');
            }

            app_redirect('/faculty/dashboard.php');
        }

        if ($action === 'delete_document') {
            $documentId = (int) ($_POST['document_id'] ?? 0);
            $statement = $pdo->prepare('SELECT * FROM faculty_documents WHERE id = :id AND faculty_id = :faculty_id LIMIT 1');
            $statement->execute(['id' => $documentId, 'faculty_id' => $facultyId]);
            $document = $statement->fetch();

            if ($document) {
                $documentPath = __DIR__ . '/../' . ltrim((string) $document['file_path'], '/');
                if (is_file($documentPath)) {
                    unlink($documentPath);
                }

                $pdo->prepare('DELETE FROM faculty_documents WHERE id = :id')->execute(['id' => $documentId]);
                app_flash('success', 'Document removed successfully.');
            }

            app_redirect('/faculty/dashboard.php');
        }

        if ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Please complete all password fields.');
            }

            if (!app_verify_password($faculty['employee_id'], $currentPassword, $faculty['password'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }

            $pdo->prepare('UPDATE faculty_users SET password = :password WHERE id = :id')->execute([
                'password' => app_hash_password($faculty['employee_id'], $newPassword),
                'id' => $facultyId,
            ]);

            app_flash('success', 'Password changed successfully.');
            app_redirect('/faculty/dashboard.php');
        }

        if ($action === 'add_schedule') {
            $subjectName = app_clean($_POST['subject_name'] ?? '');
            $subjectId = 0;
            if ($subjectName !== '') {
                $sFind = $pdo->prepare('SELECT id FROM subjects WHERE faculty_id = :faculty_id AND subject_name = :subject_name LIMIT 1');
                $sFind->execute(['faculty_id' => $facultyId, 'subject_name' => $subjectName]);
                $sRow = $sFind->fetch();
                if ($sRow) {
                    $subjectId = (int) $sRow['id'];
                } else {
                    $pdo->prepare('INSERT INTO subjects (faculty_id, subject_name, subject_code, semester, credits, created_at) VALUES (:faculty_id, :subject_name, "", "", 0, NOW())')->execute([
                        'faculty_id' => $facultyId,
                        'subject_name' => $subjectName,
                    ]);
                    $subjectId = (int) $pdo->lastInsertId();
                }
            }
            $lectureDay = app_clean($_POST['lecture_day'] ?? '');
            $startTime = app_clean($_POST['start_time'] ?? '');
            $endTime = app_clean($_POST['end_time'] ?? '');
            $roomNumber = app_clean($_POST['room_number'] ?? '');
            $departmentField = app_clean($_POST['department'] ?? $faculty['department']);

            if ($lectureDay === '' || $startTime === '' || $endTime === '' || $roomNumber === '') {
                throw new RuntimeException('Please complete all schedule fields.');
            }

            $pdo->prepare('INSERT INTO lecture_schedule (faculty_id, subject_id, lecture_day, start_time, end_time, room_number, department, created_at) VALUES (:faculty_id, :subject_id, :lecture_day, :start_time, :end_time, :room_number, :department, NOW())')->execute([
                'faculty_id' => $facultyId,
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'lecture_day' => $lectureDay,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_number' => $roomNumber,
                'department' => $departmentField,
            ]);

            app_flash('success', 'Lecture schedule added.');
            app_redirect('/faculty/dashboard.php#schedule');
        }

        if ($action === 'edit_schedule') {
            $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
            $subjectName = app_clean($_POST['subject_name'] ?? '');
            $subjectId = 0;
            if ($subjectName !== '') {
                $sFind = $pdo->prepare('SELECT id FROM subjects WHERE faculty_id = :faculty_id AND subject_name = :subject_name LIMIT 1');
                $sFind->execute(['faculty_id' => $facultyId, 'subject_name' => $subjectName]);
                $sRow = $sFind->fetch();
                if ($sRow) {
                    $subjectId = (int) $sRow['id'];
                } else {
                    $pdo->prepare('INSERT INTO subjects (faculty_id, subject_name, subject_code, semester, credits, created_at) VALUES (:faculty_id, :subject_name, "", "", 0, NOW())')->execute([
                        'faculty_id' => $facultyId,
                        'subject_name' => $subjectName,
                    ]);
                    $subjectId = (int) $pdo->lastInsertId();
                }
            }

            $lectureDay = app_clean($_POST['lecture_day'] ?? '');
            $startTime = app_clean($_POST['start_time'] ?? '');
            $endTime = app_clean($_POST['end_time'] ?? '');
            $roomNumber = app_clean($_POST['room_number'] ?? '');

            if ($scheduleId <= 0) {
                throw new RuntimeException('Invalid schedule selected.');
            }

            $pdo->prepare('UPDATE lecture_schedule SET subject_id = :subject_id, lecture_day = :lecture_day, start_time = :start_time, end_time = :end_time, room_number = :room_number WHERE id = :id AND faculty_id = :faculty_id')->execute([
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'lecture_day' => $lectureDay,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_number' => $roomNumber,
                'id' => $scheduleId,
                'faculty_id' => $facultyId,
            ]);

            app_flash('success', 'Lecture schedule updated.');
            app_redirect('/faculty/dashboard.php#schedule');
        }

        if ($action === 'delete_schedule') {
            $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
            if ($scheduleId > 0) {
                $pdo->prepare('DELETE FROM lecture_schedule WHERE id = :id AND faculty_id = :faculty_id')->execute([
                    'id' => $scheduleId,
                    'faculty_id' => $facultyId,
                ]);
                app_flash('success', 'Lecture schedule removed.');
            }

            app_redirect('/faculty/dashboard.php#schedule');
        }
    } catch (Throwable $exception) {
        app_flash('error', $exception->getMessage());
        app_redirect('/faculty/dashboard.php');
    }
}

$documentsStatement = $pdo->prepare('SELECT * FROM faculty_documents WHERE faculty_id = :faculty_id ORDER BY uploaded_at DESC');
$documentsStatement->execute(['faculty_id' => $facultyId]);
$documents = $documentsStatement->fetchAll();

$subjectsStatement = $pdo->prepare('SELECT * FROM subjects WHERE faculty_id = :faculty_id');
$subjectsStatement->execute(['faculty_id' => $facultyId]);
$subjects = $subjectsStatement->fetchAll();

$schedulesStatement = $pdo->prepare('SELECT ls.*, s.subject_name FROM lecture_schedule ls LEFT JOIN subjects s ON ls.subject_id = s.id WHERE ls.faculty_id = :faculty_id ORDER BY FIELD(ls.lecture_day, "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), ls.start_time');
$schedulesStatement->execute(['faculty_id' => $facultyId]);
$schedules = $schedulesStatement->fetchAll();

$profile = faculty_ensure_profile($pdo, $facultyId);
$photoPath = !empty($profile['profile_photo']) ? faculty_relative_path((string) $profile['profile_photo']) : '';
$achievementItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($profile['achievements'] ?? '')) ?: [])));
$certificationItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($profile['certifications'] ?? '')) ?: [])));
$publicationItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($profile['publications'] ?? '')) ?: [])));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faculty Dashboard | <?php echo app_safe_string(APP_NAME); ?></title>
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
                    <div class="fw-bold">Faculty Panel</div>
                    <small class="text-white-50">My Profile</small>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link active" href="#profile"><i class="bi bi-person-badge me-2"></i>My Profile</a>
                <a class="nav-link" href="#documents"><i class="bi bi-folder2-open me-2"></i>Documents</a>
                <a class="nav-link" href="#schedule"><i class="bi bi-calendar-event me-2"></i>Lecture Schedule</a>
                <a class="nav-link" href="#password"><i class="bi bi-shield-lock me-2"></i>Change Password</a>
                <a class="nav-link mt-3" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </aside>

        <main class="col-lg-10 p-0">
            <nav class="navbar navbar-expand-lg bg-white border-bottom px-3 px-lg-4 shadow-sm">
                <div class="container-fluid">
                    <span class="navbar-brand mb-0 h1 text-navy">Faculty Dashboard</span>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <span class="badge badge-soft"><?php echo app_safe_string($faculty['employee_id']); ?></span>
                        <span class="muted-text small"><?php echo app_safe_string($faculty['full_name']); ?></span>
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

                <section id="profile" class="mb-4">
                    <div class="page-shell p-4">
                        <div class="row g-4 align-items-start">
                            <div class="col-lg-4">
                                <div class="border rounded-4 p-4 bg-white text-center">
                                    <?php if ($photoPath !== ''): ?>
                                        <img src="../<?php echo app_safe_string(ltrim($photoPath, '/')); ?>" alt="Profile Photo" class="avatar mb-3" style="width:110px;height:110px;">
                                    <?php else: ?>
                                        <div class="avatar mb-3 mx-auto d-flex align-items-center justify-content-center bg-light text-navy" style="width:110px;height:110px;">No Photo</div>
                                    <?php endif; ?>
                                    <h1 class="h5 text-navy mb-1"><?php echo app_safe_string($faculty['full_name']); ?></h1>
                                    <div class="muted-text mb-1"><?php echo app_safe_string($faculty['designation']); ?></div>
                                    <div class="badge badge-soft mb-3"><?php echo app_safe_string($faculty['department']); ?></div>
                                    <div class="text-start small">
                                        <div class="mb-2"><strong>Employee ID:</strong> <?php echo app_safe_string($faculty['employee_id']); ?></div>
                                        <div class="mb-2"><strong>Email:</strong> <?php echo app_safe_string($faculty['email']); ?></div>
                                        <div class="mb-2"><strong>Phone:</strong> <?php echo app_safe_string($faculty['phone']); ?></div>
                                        <div class="mb-2"><strong>Qualification:</strong> <?php echo app_safe_string($profile['qualification'] ?? '-'); ?></div>
                                        <div class="mb-2"><strong>Experience:</strong> <?php echo app_safe_string($profile['experience'] ?? '-'); ?></div>
                                        <div class="mb-2"><strong>Research Interests:</strong> <?php echo app_safe_string($profile['research_interests'] ?? '-'); ?></div>
                                    </div>
                                    <form method="post" enctype="multipart/form-data" class="mt-3">
                                        <input type="hidden" name="action" value="upload_photo">
                                        <input type="file" name="profile_photo" class="form-control form-control-sm mb-2" accept=".jpg,.jpeg,.png,.webp" required>
                                        <button type="submit" class="btn btn-outline-navy btn-sm w-100">Upload Profile Photo</button>
                                    </form>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="border rounded-4 p-4 bg-white">
                                    <h2 class="h5 text-navy mb-3">Edit Personal Profile</h2>
                                    <form method="post" class="row g-3">
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?php echo app_safe_string($faculty['full_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo app_safe_string($faculty['email']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" name="phone" class="form-control" value="<?php echo app_safe_string($faculty['phone']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Department</label>
                                            <input type="text" class="form-control" value="<?php echo app_safe_string($faculty['department']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Designation</label>
                                            <input type="text" class="form-control" value="<?php echo app_safe_string($faculty['designation']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Qualification</label>
                                            <input type="text" name="qualification" class="form-control" value="<?php echo app_safe_string($profile['qualification'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Experience</label>
                                            <input type="text" name="experience" class="form-control" value="<?php echo app_safe_string($profile['experience'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Research Interests</label>
                                            <textarea name="research_interests" class="form-control" rows="3"><?php echo app_safe_string($profile['research_interests'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Achievements</label>
                                            <textarea name="achievements" class="form-control" rows="3"><?php echo app_safe_string($profile['achievements'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Certifications</label>
                                            <textarea name="certifications" class="form-control" rows="3"><?php echo app_safe_string($profile['certifications'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Publications</label>
                                            <textarea name="publications" class="form-control" rows="4"><?php echo app_safe_string($profile['publications'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-navy">Save Profile</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-4">
                                <div class="card stat-card p-3 h-100">
                                    <div class="muted-text small">Achievements</div>
                                    <div class="h5 text-navy mb-0"><?php echo count($achievementItems); ?> entries</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card p-3 h-100">
                                    <div class="muted-text small">Certifications</div>
                                    <div class="h5 text-navy mb-0"><?php echo count($certificationItems); ?> entries</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card p-3 h-100">
                                    <div class="muted-text small">Publications</div>
                                    <div class="h5 text-navy mb-0"><?php echo count($publicationItems); ?> entries</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="schedule" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Lecture Schedule</h2>
                        <p class="muted-text mb-3">Manage your weekly lectures: add, edit, or remove schedule entries.</p>

                        <form method="post" class="row g-3 align-items-end mb-4">
                            <input type="hidden" name="action" value="add_schedule">
                            <div class="col-md-3">
                                <label class="form-label">Subject (optional)</label>
                                <input type="text" name="subject_name" class="form-control" placeholder="Enter subject name">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Day</label>
                                <select name="lecture_day" class="form-select" required>
                                    <option value="">Select</option>
                                    <option>Monday</option>
                                    <option>Tuesday</option>
                                    <option>Wednesday</option>
                                    <option>Thursday</option>
                                    <option>Friday</option>
                                    <option>Saturday</option>
                                    <option>Sunday</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Room</label>
                                <input type="text" name="room_number" class="form-control" required>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-navy w-100">Add</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Room</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($schedules as $sch): ?>
                                    <tr>
                                        <td><?php echo app_safe_string($sch['lecture_day']); ?></td>
                                        <td><?php echo app_safe_string(substr((string)$sch['start_time'],0,5) . ' - ' . substr((string)$sch['end_time'],0,5)); ?></td>
                                        <td><?php echo app_safe_string($sch['subject_name'] ?? '-'); ?></td>
                                        <td><?php echo app_safe_string($sch['room_number'] ?? '-'); ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                                                <form method="post" class="row g-2 mt-2">
                                                    <input type="hidden" name="action" value="edit_schedule">
                                                    <input type="hidden" name="schedule_id" value="<?php echo (int)$sch['id']; ?>">
                                                    <div class="col-12">
                                                        <label class="form-label">Subject (optional)</label>
                                                        <input type="text" name="subject_name" class="form-control" value="<?php echo app_safe_string($sch['subject_name'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Day</label>
                                                        <select name="lecture_day" class="form-select" required>
                                                            <option value="">Select</option>
                                                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                                                                <option <?php echo ($d === $sch['lecture_day']) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Start</label>
                                                        <input type="time" name="start_time" class="form-control" value="<?php echo app_safe_string(substr((string)$sch['start_time'],0,5)); ?>" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">End</label>
                                                        <input type="time" name="end_time" class="form-control" value="<?php echo app_safe_string(substr((string)$sch['end_time'],0,5)); ?>" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Room</label>
                                                        <input type="text" name="room_number" class="form-control" value="<?php echo app_safe_string($sch['room_number']); ?>" required>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                    </div>
                                                </form>
                                                <form method="post" class="d-inline ms-2 mt-2">
                                                    <input type="hidden" name="action" value="delete_schedule">
                                                    <input type="hidden" name="schedule_id" value="<?php echo (int)$sch['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove this schedule?">Delete</button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="documents" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Upload Documents</h2>
                        <p class="muted-text mb-3">Upload academic documents, certificates, and supporting files.</p>
                        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mb-4">
                            <input type="hidden" name="action" value="upload_document">
                            <div class="col-md-8">
                                <label class="form-label">Choose File</label>
                                <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-navy w-100">Upload Document</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($documents as $document): ?>
                                    <tr>
                                        <td>
                                            <a href="../<?php echo app_safe_string(ltrim((string) $document['file_path'], '/')); ?>" target="_blank" class="text-navy"><?php echo app_safe_string($document['original_name']); ?></a>
                                        </td>
                                        <td><?php echo app_safe_string(strtoupper((string) $document['file_type'])); ?></td>
                                        <td><?php echo app_safe_string(app_format_date($document['uploaded_at'])); ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="document_id" value="<?php echo (int) $document['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove this document?">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="password" class="mb-4">
                    <div class="page-shell p-4">
                        <h2 class="h5 section-title mb-1">Change Password</h2>
                        <p class="muted-text mb-3">Change your account password at any time.</p>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="change_password">
                            <div class="col-md-4">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-navy">Update Password</button>
                            </div>
                        </form>
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
