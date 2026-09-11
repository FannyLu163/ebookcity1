<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';
$title = $title ?? '書籍列表';
$categoryMenus = find_category_menu();
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(page_title($title)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/site.css" rel="stylesheet">
</head>
<body>
<div class="container site-shell py-4">
    <nav class="navbar navbar-expand-lg bg-body-tertiary rounded border mb-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="/"><img src="/images/logo_discuss1.png" alt="明日書城" height="50"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="切換導覽">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/">全部書籍</a></li>
                    <li class="nav-item"><a class="nav-link" href="/authors.php">作者專區</a></li>
                    <?php foreach ($categoryMenus as $category): ?>
                        <?= render_category_nav_item($category) ?>
                    <?php endforeach; ?>
                </ul>
                <form class="d-flex" role="search" method="get" action="/">
                    <input class="form-control me-2" name="q" type="search" value="<?= h($_GET['q'] ?? '') ?>" placeholder="請輸入書名或作者">
                    <button class="btn btn-outline-success" type="submit">搜尋</button>
                </form>
            </div>
        </div>
    </nav>

    <?= $content ?>

    <footer class="site-footer mt-5 pt-4">
        <div>明日工作室股份有限公司 / 未來書城股份有限公司 版權所有</div>
        <div>Tomorrow Studio Corp. / EbookCity Corp. All Rights Reserved.</div>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
