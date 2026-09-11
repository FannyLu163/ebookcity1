<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$saved = isset($_GET['saved']);
$book = $id > 0 ? admin_find_book($id) : [
    'id' => 0, 'title' => '', 'subtitle' => '', 'author' => '', 'pubdate' => date('Y-m-d'), 'isbn' => '', 'sn' => '',
    'picture' => '', 'thumb' => '', 'cat_id' => 0, 'short_intro' => '', 'toc' => '', 'preview_notice' => '', 'is_visible' => 1,
];
$categories = admin_category_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (trim((string)($_POST['title'] ?? '')) === '') {
            throw new RuntimeException('請輸入書名');
        }
        $newId = admin_save_book($_POST);
        header('Location: /admin/edit-book.php?id=' . $newId . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $book = $_POST + ['id' => $id];
    }
}

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1"><?= $id > 0 ? '編輯書籍' : '新增書籍' ?></h1><div class="text-muted"><?= $id > 0 ? 'ID ' . h((string)$id) : '建立新的書籍資料' ?></div></div>
    <a class="btn btn-outline-secondary" href="/admin/books.php">返回列表</a>
</div>
<?php if ($saved): ?><div class="alert alert-success">已儲存。</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="bg-white border rounded p-3">
    <input type="hidden" name="id" value="<?= h((string)($book['id'] ?? 0)) ?>">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">書名</label><input class="form-control" name="title" value="<?= h($book['title'] ?? '') ?>" required></div>
        <div class="col-md-4"><label class="form-label">副標題</label><input class="form-control" name="subtitle" value="<?= h($book['subtitle'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">作者</label><input class="form-control" name="author" value="<?= h($book['author'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">出版日期</label><input class="form-control" name="pubdate" type="date" value="<?= h(!empty($book['pubdate']) ? substr((string)$book['pubdate'], 0, 10) : '') ?>"></div>
        <div class="col-md-4"><label class="form-label">ISBN</label><input class="form-control" name="isbn" value="<?= h($book['isbn'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">書號</label><input class="form-control" name="sn" value="<?= h($book['sn'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">分類</label><select class="form-select" name="cat_id"><option value="0">未分類</option><?php foreach ($categories as $category): ?><option value="<?= h((string)$category['id']) ?>"<?= selected_attr($category['id'], $book['cat_id'] ?? 0) ?>><?= h($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_visible" value="1"<?= checked_attr(!empty($book['is_visible'])) ?>> 前台顯示</label></div>
        <div class="col-md-6"><label class="form-label">圖片路徑</label><input class="form-control" name="picture" value="<?= h($book['picture'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">縮圖路徑</label><input class="form-control" name="thumb" value="<?= h($book['thumb'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">簡介</label><textarea class="form-control" name="short_intro" rows="6"><?= h($book['short_intro'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">目錄</label><textarea class="form-control" name="toc" rows="6"><?= h($book['toc'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">試閱說明</label><input class="form-control" name="preview_notice" value="<?= h($book['preview_notice'] ?? '') ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-success" type="submit">儲存</button></div>
</form>
<?php admin_layout($id > 0 ? '編輯書籍' : '新增書籍', ob_get_clean()); ?>

