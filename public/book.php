<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/data.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$book = find_book((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
$title = $book['title'] ?? '找不到書籍';

ob_start();
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">首頁</a></li>
        <?php foreach (($book['category_path'] ?? []) as $category): ?>
            <li class="breadcrumb-item"><a href="<?= h(url('/', ['tag' => $category['slug']])) ?>"><?= h($category['name']) ?></a></li>
        <?php endforeach; ?>
        <li class="breadcrumb-item active"><?= h($title) ?></li>
    </ol>
</nav>
<?php if (!$book): ?>
    <div class="alert alert-warning">找不到這本書。</div>
<?php else: ?>
    <section class="card shadow-sm mb-3">
        <div class="row g-0">
            <div class="col-md-3 text-center p-3"><img src="<?= h(asset($book['picture'] ?? $book['thumb'] ?? '')) ?>" class="img-fluid img-thumbnail detail-cover" alt="<?= h($title) ?>" onerror="this.src='/images/logo_discuss.png'"></div>
            <div class="col-md-9"><div class="card-body">
                <h1 class="h3 text-white bg-primary rounded-2 p-2"><?= h($title) ?></h1>
                <?php if (!empty($book['subtitle'])): ?><p class="lead"><?= h($book['subtitle']) ?></p><?php endif; ?>
                <dl class="row mb-0">
                    <?php if (!empty($book['authors'])): ?>
                        <dt class="col-sm-3">作者</dt>
                        <dd class="col-sm-9">
                            <?php foreach ($book['authors'] as $index => $author): ?>
                                <?php if ($index > 0): ?><span>、</span><?php endif; ?>
                                <a href="<?= h(author_href(['slug' => $author['slug']])) ?>"><?= h($author['pen_name']) ?></a>
                            <?php endforeach; ?>
                        </dd>
                    <?php elseif (!empty($book['author'])): ?>
                        <dt class="col-sm-3">作者</dt><dd class="col-sm-9"><?= h($book['author']) ?></dd>
                    <?php endif; ?>
                    <?php foreach ([['封面繪者','illustrator'], ['編輯','editor'], ['ISBN','isbn']] as [$label, $key]): ?>
                        <?php if (!empty($book[$key])): ?><dt class="col-sm-3"><?= h($label) ?></dt><dd class="col-sm-9"><?= h($book[$key]) ?></dd><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($book['pubdate'])): ?><dt class="col-sm-3">出版日期</dt><dd class="col-sm-9"><?= h(substr((string)$book['pubdate'], 0, 10)) ?></dd><?php endif; ?>
                </dl>
                <?php if (!empty($book['tags'])): ?><div class="mt-2 d-flex flex-wrap gap-2"><?php foreach ($book['tags'] as $tag): ?><a class="badge text-bg-light border text-decoration-none" href="<?= h(url('/', ['tag' => $tag['slug']])) ?>"><?= h($tag['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($book['links'])): ?><div class="mt-3 d-flex flex-wrap gap-2"><?php foreach ($book['links'] as $link): ?><a class="btn btn-sm btn-outline-success" href="<?= h($link['url']) ?>" target="_blank" rel="noopener"><?= h($link['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
            </div></div>
        </div>
    </section>
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#intro" type="button">內容簡介</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#author" type="button">作者簡介</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#toc" type="button">目錄</button></li>
        <li class="nav-item"><button class="nav-link <?= empty($book['previews']) ? 'disabled' : '' ?>" data-bs-toggle="tab" data-bs-target="#preview" type="button">試閱</button></li>
    </ul>
    <div class="tab-content border border-top-0 bg-white p-3 legacy-html">
        <div class="tab-pane fade show active book-tab-scroll" id="intro"><?= $book['legacy_description'] ?? '<p class="text-muted mb-0">目前尚無內容簡介。</p>' ?></div>
        <div class="tab-pane fade book-tab-scroll" id="author"><?= $book['author_intro'] ?? '<p class="text-muted mb-0">目前尚無作者簡介。</p>' ?></div>
        <div class="tab-pane fade book-tab-scroll" id="toc" style="white-space: pre-wrap;"><?= $book['legacy_toc'] ?? '<p class="text-muted mb-0">目前尚無目錄資料。</p>' ?></div>
        <div class="tab-pane fade" id="preview"><?php foreach (($book['previews'] ?? []) as $preview): ?><article class="mb-4"><h2 class="h5"><?= h($preview['title'] ?: '試閱') ?></h2><?= $preview['content'] ?></article><?php endforeach; ?></div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/app/layout.php';
