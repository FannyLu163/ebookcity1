<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$saved = isset($_GET['saved']);
$author = $id > 0 ? admin_find_author($id) : [
    'id' => 0, 'slug' => '', 'pen_name' => '', 'site_url' => '', 'site2_url' => '', 'intro' => '', 'speak' => '',
    'avatar_image' => '', 'profile_image' => '', 'sort_order' => 0, 'is_recommended' => 0, 'status' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (trim((string)($_POST['pen_name'] ?? '')) === '') {
            throw new RuntimeException('請輸入作者筆名');
        }
        $newId = admin_save_author($_POST);
        header('Location: /admin/edit-author.php?id=' . $newId . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $author = $_POST + ['id' => $id];
    }
}

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1"><?= $id > 0 ? '編輯作者' : '新增作者' ?></h1><div class="text-muted"><?= $id > 0 ? 'ID ' . h((string)$id) : '建立新的作者資料' ?></div></div>
    <a class="btn btn-outline-secondary" href="/admin/authors.php">返回列表</a>
</div>
<?php if ($saved): ?><div class="alert alert-success">已儲存。</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="bg-white border rounded p-3">
    <input type="hidden" name="id" value="<?= h((string)($author['id'] ?? 0)) ?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">作者筆名</label><input class="form-control" name="pen_name" value="<?= h($author['pen_name'] ?? '') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" value="<?= h($author['slug'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">個人網站</label><input class="form-control" name="site_url" value="<?= h($author['site_url'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">第二網站</label><input class="form-control" name="site2_url" value="<?= h($author['site2_url'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">頭像路徑</label><input class="form-control" name="avatar_image" value="<?= h($author['avatar_image'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">照片路徑</label><input class="form-control" name="profile_image" value="<?= h($author['profile_image'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">排序</label><input class="form-control" name="sort_order" type="number" value="<?= h((string)($author['sort_order'] ?? 0)) ?>"></div>
        <div class="col-md-9 d-flex align-items-end gap-4">
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_recommended" value="1"<?= checked_attr(!empty($author['is_recommended'])) ?>> 推薦作者</label>
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="status" value="1"<?= checked_attr(!empty($author['status'])) ?>> 前台顯示</label>
        </div>
        <div class="col-12"><label class="form-label">作者簡介</label><textarea class="form-control" name="intro" rows="8"><?= h($author['intro'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">作者的話</label><textarea class="form-control" name="speak" rows="6"><?= h($author['speak'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-3"><button class="btn btn-success" type="submit">儲存</button></div>
</form>
<?php admin_layout($id > 0 ? '編輯作者' : '新增作者', ob_get_clean()); ?>

