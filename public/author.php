<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/data.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$author = find_author_detail((string)($_GET['slug'] ?? ''));
$title = $author['pen_name'] ?? '找不到作者';
$image = $author ? (($author['profile_image'] ?? '') ?: ($author['avatar'] ?? '')) : '';
$siteUrl = external_url($author['site_url'] ?? '');
$site2Url = external_url($author['site2_url'] ?? '');

ob_start();
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">首頁</a></li>
        <li class="breadcrumb-item"><a href="/authors.php">作者專區</a></li>
        <li class="breadcrumb-item active"><?= h($title) ?></li>
    </ol>
</nav>
<?php if (!$author): ?>
    <div class="alert alert-warning">找不到這位作者。</div>
<?php else: ?>
    <section class="card shadow-sm mb-3">
        <div class="row g-0">
            <div class="col-md-3 text-center p-3">
                <img src="<?= h(asset($image)) ?>" class="img-fluid img-thumbnail author-detail-image" alt="<?= h($title) ?>" onerror="this.src='/images/logo_discuss.png'">
            </div>
            <div class="col-md-9">
                <div class="card-body">
                    <h1 class="h3 text-white bg-primary rounded-2 p-2"><?= h($title) ?></h1>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">出版作品</dt>
                        <dd class="col-sm-9"><?= h((string)($author['book_count'] ?? 0)) ?> 本</dd>
                        <?php if ($siteUrl !== ''): ?>
                            <dt class="col-sm-3">個人網站</dt>
                            <dd class="col-sm-9 author-link"><a href="<?= h($siteUrl) ?>" target="_blank" rel="noopener"><?= h($author['site_url'] ?? '') ?></a></dd>
                        <?php endif; ?>
                        <?php if ($site2Url !== ''): ?>
                            <dt class="col-sm-3">其他連結</dt>
                            <dd class="col-sm-9 author-link"><a href="<?= h($site2Url) ?>" target="_blank" rel="noopener"><?= h($author['site2_url'] ?? '') ?></a></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <section class="bg-white border rounded p-3 legacy-html mb-3 author-detail-text">
                <h2 class="h5">作者簡介</h2>
                <?= trim((string)($author['intro'] ?? '')) !== '' ? $author['intro'] : '<p class="text-muted mb-0">目前尚無作者簡介。</p>' ?>
            </section>
            <?php if (trim((string)($author['speak'] ?? '')) !== ''): ?>
                <section class="bg-white border rounded p-3 legacy-html mb-3 author-detail-text">
                    <h2 class="h5">作者的話</h2>
                    <?= $author['speak'] ?>
                </section>
            <?php endif; ?>
        </div>
        <div class="col-12 col-lg-4">
            <section class="bg-white border rounded p-3">
                <h2 class="h5">出版作品</h2>
                <?php if (empty($author['books'])): ?>
                    <p class="text-muted mb-0">目前尚無可顯示的作品。</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush <?= count($author['books']) > 10 ? 'author-books-scroll' : '' ?>">
                        <?php foreach ($author['books'] as $book): ?>
                            <li class="list-group-item px-0">
                                <a href="<?= h(book_href($book)) ?>"><?= h($book['title'] ?? '') ?></a>
                                <?php if (!empty($book['pubdate'])): ?><div class="text-muted small"><?= h(substr((string)$book['pubdate'], 0, 10)) ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/app/layout.php';

