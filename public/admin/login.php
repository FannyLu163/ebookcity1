<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_login(trim((string)($_POST['user'] ?? '')), (string)($_POST['password'] ?? ''))) {
        header('Location: /admin/');
        exit;
    }
    $error = '帳號或密碼不正確';
}

ob_start();
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-xl-4">
        <section class="bg-white border rounded p-4 shadow-sm">
            <h1 class="h4 mb-3">後台登入</h1>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">帳號</label>
                    <input class="form-control" name="user" autocomplete="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">密碼</label>
                    <input class="form-control" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-success w-100" type="submit">登入</button>
            </form>
        </section>
    </div>
</div>
<?php admin_layout('後台登入', ob_get_clean()); ?>

