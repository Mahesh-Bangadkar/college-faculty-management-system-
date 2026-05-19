<?php
declare(strict_types=1);

function app_clean(?string $value): string
{
    return trim((string) $value);
}

function app_hash_password(string $context, string $password): string
{
    $contextClean = app_clean($context);

    if (strpos($contextClean, '@') !== false) {
        $contextClean = mb_strtolower($contextClean);
    }

    return hash('sha256', APP_SECRET . '|' . $contextClean . '|' . $password);
}

function app_verify_password(string $context, string $password, string $storedHash): bool
{
    return hash_equals($storedHash, app_hash_password($context, $password));
}

function app_flash(string $key, ?string $message = null)
{
    if ($message === null) {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }

        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);

        return $value;
    }

    $_SESSION['flash'][$key] = $message;

    return null;
}

function app_redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function app_url(string $path = ''): string
{
    $normalizedBase = rtrim(BASE_URL, '/');
    $normalizedPath = ltrim($path, '/');

    if ($normalizedBase === '') {
        return '/' . $normalizedPath;
    }

    return $normalizedPath === '' ? $normalizedBase : $normalizedBase . '/' . $normalizedPath;
}

function app_is_logged_in(): bool
{
    return isset($_SESSION['auth']) && is_array($_SESSION['auth']);
}

function app_current_user(): ?array
{
    return app_is_logged_in() ? $_SESSION['auth'] : null;
}

function app_require_login(): void
{
    if (!app_is_logged_in()) {
        app_flash('error', 'Please log in to continue.');
        app_redirect('/index.php');
    }
}

function app_require_role(string $role): void
{
    app_require_login();

    $user = app_current_user();
    if (($user['role'] ?? '') !== $role) {
        app_flash('error', 'You do not have permission to access that page.');
        app_redirect('/dashboard.php');
    }
}

function app_old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function app_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function app_uploaded_file(array $file, string $directory, array $allowedExtensions, string $prefix = 'file'): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $safeName = sprintf('%s_%s.%s', $prefix, bin2hex(random_bytes(8)), $extension);
    $targetPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('File upload failed.');
    }

    return [
        'name' => $safeName,
        'path' => $targetPath,
        'relative' => str_replace(__DIR__ . '/..' . DIRECTORY_SEPARATOR, '', $targetPath),
        'extension' => $extension,
    ];
}

function app_format_date(?string $dateTime): string
{
    if (!$dateTime) {
        return '-';
    }

    return date('d M Y, h:i A', strtotime($dateTime));
}

function app_safe_string(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_count(array $rows): int
{
    return count($rows);
}

function app_generate_simple_pdf(string $title, array $headers, array $rows): string
{
    $lines = [];
    $lines[] = $title;
    $lines[] = str_repeat('-', 92);
    $lines[] = implode(' | ', $headers);
    $lines[] = str_repeat('-', 92);

    foreach ($rows as $row) {
        $lines[] = implode(' | ', array_map(static function ($value): string {
            $text = trim((string) $value);
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            return substr($text, 0, 42);
        }, $row));
    }

    $text = implode("\n", $lines);
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $text = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $text) ?? $text;

    $content = "BT /F1 10 Tf 40 790 Td 12 TL (";
    $content .= str_replace("\n", ") Tj T* (", $text);
    $content .= ") Tj ET";
    $length = strlen($content);

    $pdf = "%PDF-1.4\n";
    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj\n";
    $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier >> endobj\n";
    $objects[] = "5 0 obj << /Length {$length} >> stream\n{$content}\nendstream endobj\n";

    $offsets = [];
    $offsets[] = 0;

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($index = 1; $index <= count($objects); $index++) {
        $pdf .= sprintf('%010d 00000 n %s', $offsets[$index], "\n");
    }

    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

    return $pdf;
}

function app_send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
