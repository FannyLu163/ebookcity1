<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$result = admin_find_books($q, $page, $pageSize);
$books = $result['items'];
$total = (int)$result['total'];
$totalPages = max(1, (int)ceil($total / $pageSize));
ob_start();
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <div><h1 class="h3 mb-1">書籍管理</h1><div class="text-muted">共 <?= h((string)$total) ?> 本書</div></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="/admin/edit-book.php">新增書籍</a>
        <form class="d-flex gap-2" method="get">
            <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="搜尋書名、作者、ISBN">
            <button class="btn btn-outline-success" type="submit">搜尋</button>
        </form>
    </div>
</div>
<div class="table-responsive bg-white border rounded">
    <table class="table table-hover align-middle mb-0 admin-list-table">
        <thead><tr><th class="admin-list-image">圖片</th><th>書名</th><th>作者</th><th>出版日期</th><th>分類</th><th>狀態</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($books as $book): ?>
            <tr>
                <td><img class="admin-cover" src="<?= h(asset($book['cover_image'] ?? '')) ?>" alt="<?= h($book['title'] ?? '') ?>" onerror="this.src='/images/logo_discuss.png'"></td>
                <td><div class="fw-semibold"><?= h(plain_excerpt($book['title'] ?? '', 32)) ?></div><div class="text-muted small">ID <?= h((string)$book['id']) ?> <?= !empty($book['slug']) ? '/ ' . h($book['slug']) : '' ?></div></td>
                <td><?= h($book['author'] ?? '') ?></td>
                <td><?= h(!empty($book['pubdate']) ? substr((string)$book['pubdate'], 0, 10) : '') ?></td>
                <td><?= h($book['cat_name'] ?? '') ?></td>
                <td><?= !empty($book['is_visible']) ? '<span class="badge text-bg-success">顯示</span>' : '<span class="badge text-bg-secondary">隱藏</span>' ?></td>
                <td><a class="btn btn-outline-success btn-sm" href="/admin/edit-book.php?id=<?= h((string)$book['id']) ?>">編輯</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination"><li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(url('/admin/books.php', ['q' => $q, 'page' => $page - 1])) ?>">上一頁</a></li><li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li><li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(url('/admin/books.php', ['q' => $q, 'page' => $page + 1])) ?>">下一頁</a></li></ul></nav>
<?php endif; ?>
<?php admin_layout('書籍管理', ob_get_clean()); ?>

