<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php';

const ADMIN_SESSION_KEY = 'ebookcity_admin_authenticated';

function admin_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_is_authenticated(): bool
{
    admin_start();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function admin_require(): void
{
    if (!admin_is_authenticated()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_login(string $user, string $pass): bool
{
    $expectedUser = env_value('ADMIN_USER', 'admin') ?: 'admin';
    $expectedPass = env_value('ADMIN_PASS', 'changeme') ?: 'changeme';
    if (hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass)) {
        admin_start();
        $_SESSION[ADMIN_SESSION_KEY] = true;
        return true;
    }
    return false;
}

function admin_logout(): void
{
    admin_start();
    unset($_SESSION[ADMIN_SESSION_KEY]);
}

function admin_layout(string $title, string $content): void
{
    require __DIR__ . '/admin_layout.php';
}

function post_bool(string $key): bool
{
    return isset($_POST[$key]) && ($_POST[$key] === '1' || $_POST[$key] === 'on');
}

function admin_save_book_image_upload(string $field, int $bookId, string $role, int $maxWidth, int $maxHeight, int $maxBytes): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(($role === 'thumb' ? '縮圖' : '圖片') . '上傳失敗');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException(($role === 'thumb' ? '縮圖' : '圖片') . '檔案大小不可超過 ' . admin_format_bytes($maxBytes));
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('只允許上傳 jpg、png、gif、webp 圖片');
    }

    $info = @getimagesize((string)$file['tmp_name']);
    if (!$info) {
        throw new RuntimeException('無法讀取圖片尺寸，請選擇有效圖片檔');
    }
    if ((int)$info[0] > $maxWidth || (int)$info[1] > $maxHeight) {
        throw new RuntimeException(sprintf('%s尺寸不可超過 %dx%d，目前為 %dx%d', $role === 'thumb' ? '縮圖' : '圖片', $maxWidth, $maxHeight, (int)$info[0], (int)$info[1]));
    }

    $folder = dirname(__DIR__) . '/public/imgs/pro';
    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException('無法建立圖片資料夾');
    }

    $prefix = $bookId > 0 ? (string)$bookId : 'new';
    for ($index = 0; $index < 100; $index++) {
        $suffix = $index === 0 ? '' : '-' . $index;
        $fileName = sprintf('%s-%s-%s%s.%s', $prefix, $role, date('YmdHisv'), $suffix, $extension);
        $target = $folder . '/' . $fileName;
        if (!file_exists($target)) {
            if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                throw new RuntimeException('無法儲存上傳圖片');
            }
            return '/imgs/pro/' . $fileName;
        }
    }

    throw new RuntimeException('無法建立不重複的圖片檔名，請再試一次');
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return (string)round($bytes / 1024 / 1024) . 'MB';
    }
    return (string)round($bytes / 1024) . 'KB';
}
