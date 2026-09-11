<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$result = admin_find_authors($q, $page, $pageSize);
$authors = $result['items'];
$total = (int)$result['total'];
$totalPages = max(1, (int)ceil($total / $pageSize));
ob_start();
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <div><h1 class="h3 mb-1">作者管理</h1><div class="text-muted">共 <?= h((string)$total) ?> 位作者</div></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="/admin/edit-author.php">新增作者</a>
        <form class="d-flex gap-2" method="get">
            <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="搜尋作者、網站、簡介">
            <button class="btn btn-outline-success" type="submit">搜尋</button>
        </form>
    </div>
</div>
<div class="table-responsive bg-white border rounded">
    <table class="table table-hover align-middle mb-0 admin-list-table">
        <thead><tr><th class="admin-list-image">圖片</th><th>作者</th><th>作品</th><th>排序</th><th>推薦</th><th>狀態</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($authors as $author): ?>
            <tr>
                <td><img class="admin-cover" src="<?= h(asset($author['avatar'] ?? '')) ?>" alt="<?= h($author['pen_name'] ?? '') ?>" onerror="this.src='/images/logo_discuss.png'"></td>
                <td><div class="fw-semibold"><?= h($author['pen_name'] ?? '') ?></div><div class="text-muted small">ID <?= h((string)$author['id']) ?> <?= !empty($author['slug']) ? '/ ' . h($author['slug']) : '' ?></div></td>
                <td><?= h((string)($author['book_count'] ?? 0)) ?></td>
                <td><?= h((string)($author['sort_order'] ?? 0)) ?></td>
                <td><?= !empty($author['is_recommended']) ? '<span class="badge text-bg-info">推薦</span>' : '' ?></td>
                <td><?= !empty($author['status']) ? '<span class="badge text-bg-success">顯示</span>' : '<span class="badge text-bg-secondary">隱藏</span>' ?></td>
                <td><a class="btn btn-outline-success btn-sm" href="/admin/edit-author.php?id=<?= h((string)$author['id']) ?>">編輯</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination"><li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(url('/admin/authors.php', ['q' => $q, 'page' => $page - 1])) ?>">上一頁</a></li><li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li><li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h(url('/admin/authors.php', ['q' => $q, 'page' => $page + 1])) ?>">下一頁</a></li></ul></nav>
<?php endif; ?>
<?php admin_layout('作者管理', ob_get_clean()); ?>

