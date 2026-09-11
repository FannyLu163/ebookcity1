<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/data.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 48;
$result = find_authors($q, $page, $pageSize);
$authors = $result['items'];
$totalItems = (int)$result['total'];
$totalPages = max(1, (int)ceil($totalItems / $pageSize));
$title = '作者專區';

ob_start();
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">首頁</a></li><li class="breadcrumb-item active">作者專區</li></ol></nav>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div><h1 class="h3 mb-1">作者專區</h1><div class="text-muted">共 <?= h((string)$totalItems) ?> 位作者</div></div>
    <form class="d-flex flex-grow-1 justify-content-md-end gap-2" method="get" action="/authors.php">
        <input class="form-control" style="max-width: 520px; min-width: 240px;" name="q" type="search" value="<?= h($q) ?>" placeholder="搜尋作者或簡介">
        <button class="btn btn-primary" type="submit">搜尋</button>
        <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="/authors.php">清除</a><?php endif; ?>
    </form>
</div>
<div class="row g-3">
    <?php foreach ($authors as $author): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <article class="card h-100 shadow-sm">
                <div class="card-body d-flex gap-3">
                    <a href="<?= h(author_href($author)) ?>" class="flex-shrink-0">
                        <img src="<?= h(asset($author['avatar'] ?? '')) ?>" class="author-avatar" alt="<?= h($author['pen_name'] ?? '') ?>" onerror="this.src='/images/logo_discuss.png'">
                    </a>
                    <div class="author-card-body">
                        <h2 class="h5 mb-1 author-name"><a href="<?= h(author_href($author)) ?>"><?= h($author['pen_name'] ?? '') ?></a></h2>
                        <div class="text-muted small mb-2">出版作品 <?= h((string)($author['book_count'] ?? 0)) ?> 本</div>
                        <?php $intro = plain_excerpt($author['intro'] ?? '', 110); ?>
                        <?php if ($intro !== ''): ?><p class="book-summary author-summary mb-0"><?= h($intro) ?></p><?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="作者分頁">
        <ul class="pagination justify-content-center flex-wrap gap-1">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h(url('/authors.php', ['q' => $q, 'page' => max(1, $page - 1)])) ?>">上一頁</a>
            </li>
            <?php foreach (pagination_pages($page, $totalPages) as $item): ?>
                <?php if ($item === 'ellipsis'): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php else: ?>
                    <li class="page-item <?= $item === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(url('/authors.php', ['q' => $q, 'page' => $item])) ?>"><?= $item ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h(url('/authors.php', ['q' => $q, 'page' => min($totalPages, $page + 1)])) ?>">下一頁</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/app/layout.php';
