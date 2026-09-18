<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Accounting/ExpenseService.php';

use Delegacje\Accounting\ExpenseService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$file = $_FILES['document'] ?? null;
$attachment = null;
$storedPath = null;
$expenseCreated = false;

try {
    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Nie udało się przesłać dokumentu.');
        }
        if (!is_int($file['size']) && !ctype_digit((string) $file['size'])) {
            throw new RuntimeException('Nieprawidłowy rozmiar pliku.');
        }
        $size = (int) $file['size'];
        if ($size < 1 || $size > 10 * 1024 * 1024) {
            throw new RuntimeException('Dokument może mieć maksymalnie 10 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new RuntimeException('Dozwolone są wyłącznie dokumenty JPG, PNG i PDF.');
        }
        $directory = dirname(__DIR__, 3) . '/storage/expenses';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można przygotować bezpiecznego magazynu dokumentów.');
        }
        $storedName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        $storedPath = $directory . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            throw new RuntimeException('Nie można zapisać dokumentu.');
        }
        @chmod($storedPath, 0640);
        $attachment = [
            'original_name' => mb_substr(basename((string) ($file['name'] ?? 'dokument')), 0, 255),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $storedPath),
        ];
    }

    $id = (new ExpenseService($pdo))->create($user, $_POST, $attachment);
    $expenseCreated = true;
    api_audit($pdo, (int) $user['id'], 'expense.create', 'expense', $id, ['has_attachment' => $attachment !== null]);
    api_response(['ok' => true, 'id' => $id], 201);
} catch (RuntimeException $e) {
    if (!$expenseCreated && $storedPath !== null && is_file($storedPath)) {
        @unlink($storedPath);
    }
    api_exception($e, 422);
} catch (\Throwable $e) {
    if (!$expenseCreated && $storedPath !== null && is_file($storedPath)) {
        @unlink($storedPath);
    }
    api_error('Nie udało się zapisać kosztu.', 500);
}
