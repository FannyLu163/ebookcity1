<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$dashboard = admin_dashboard();
ob_start();
?>
<h1 class="h3 mb-3">後台首頁</h1>
<div class="row g-3">
    <div class="col-12 col-md-4"><div class="bg-white border rounded p-4 shadow-sm"><div class="text-muted">全部書籍</div><div class="display-6"><?= h((string)$dashboard['total_books']) ?></div></div></div>
    <div class="col-12 col-md-4"><div class="bg-white border rounded p-4 shadow-sm"><div class="text-muted">前台顯示書籍</div><div class="display-6"><?= h((string)$dashboard['visible_books']) ?></div></div></div>
    <div class="col-12 col-md-4"><div class="bg-white border rounded p-4 shadow-sm"><div class="text-muted">作者</div><div class="display-6"><?= h((string)$dashboard['total_authors']) ?></div></div></div>
</div>
<div class="mt-4 d-flex flex-wrap gap-2">
    <a class="btn btn-success" href="/admin/books.php">管理書籍</a>
    <a class="btn btn-outline-success" href="/admin/authors.php">管理作者</a>
</div>
<?php admin_layout('後台首頁', ob_get_clean()); ?>

