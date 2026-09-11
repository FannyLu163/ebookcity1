<?php require_once __DIR__ . '/helpers.php'; ?>
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
            <a class="navbar-brand" href="/admin/"><img src="/images/logo_discuss1.png" alt="明日書城" height="50"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="切換導覽">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/admin/">後台首頁</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/books.php">書籍管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/authors.php">作者管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/">前台</a></li>
                </ul>
                <?php if (admin_is_authenticated()): ?>
                    <a class="btn btn-outline-secondary" href="/admin/logout.php">登出</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?= $content ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

