<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/data.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$result = find_books(['q' => $_GET['q'] ?? '', 'tag' => $_GET['tag'] ?? '', 'page' => $page]);
$books = $result['items'];
$totalPages = max(1, (int)ceil($result['total'] / 12));
$title = '書籍列表';

ob_start();
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">首頁</li></ol></nav>
<?php if ($result['error']): ?><div class="alert alert-warning"><?= h($result['error']) ?></div><?php endif; ?>
<?php if (!$books): ?>
    <div class="alert alert-info">目前沒有符合條件的書籍。</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($books as $book): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card book-card shadow-sm">
                    <div class="row g-0 h-100">
                        <div class="col-4 p-2 d-flex align-items-start justify-content-center">
                            <a href="<?= h(book_href($book)) ?>"><img src="<?= h(asset($book['thumb'] ?? $book['picture'] ?? '')) ?>" class="img-thumbnail book-cover" alt="<?= h($book['title'] ?? '') ?>" onerror="this.src='/images/logo_discuss.png'"></a>
                        </div>
                        <div class="col-8"><div class="card-body">
                            <h2 class="card-title h5"><a href="<?= h(book_href($book)) ?>"><?= h($book['title'] ?? '') ?></a></h2>
                            <?php if (!empty($book['subtitle'])): ?><div class="text-muted small mb-1"><?= h($book['subtitle']) ?></div><?php endif; ?>
                            <p class="card-text small mb-2">
                                <?php if (!empty($book['pubdate'])): ?>出版日期：<?= h(substr((string)$book['pubdate'], 0, 10)) ?><br><?php endif; ?>
                                <?php if (!empty($book['authors'])): ?>
                                    作者：
                                    <?php foreach ($book['authors'] as $index => $author): ?>
                                        <?php if ($index > 0): ?><span>、</span><?php endif; ?>
                                        <a href="<?= h(author_href($author)) ?>"><?= h($author['pen_name']) ?></a>
                                    <?php endforeach; ?>
                                    <br>
                                <?php elseif (!empty($book['author'])): ?>
                                    作者：<?= h($book['author']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($book['cat_name'])): ?>分類：<?= h($book['cat_name']) ?><?php endif; ?>
                            </p>
                            <?php $summary = plain_excerpt($book['short_intro'] ?? $book['legacy_description'] ?? '', 120); ?>
                            <?php if ($summary !== ''): ?><p class="book-summary mb-0"><?= h($summary) ?></p><?php endif; ?>
                        </div></div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="書籍分頁">
            <ul class="pagination justify-content-center flex-wrap gap-1">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(url('/', ['q' => $_GET['q'] ?? '', 'tag' => $_GET['tag'] ?? '', 'page' => max(1, $page - 1)])) ?>" aria-label="上一頁">上一頁</a>
                </li>
            <?php foreach (pagination_pages($page, $totalPages) as $item): ?>
                <?php if ($item === 'ellipsis'): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php else: ?>
                    <li class="page-item <?= $item === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(url('/', ['q' => $_GET['q'] ?? '', 'tag' => $_GET['tag'] ?? '', 'page' => $item])) ?>"><?= $item ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(url('/', ['q' => $_GET['q'] ?? '', 'tag' => $_GET['tag'] ?? '', 'page' => min($totalPages, $page + 1)])) ?>" aria-label="下一頁">下一頁</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/app/layout.php';
