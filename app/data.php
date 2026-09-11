<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }

    $config = app_config();
    $asp = asp_connection_config();
    $dsn = $config['db_dsn'] ?: ($asp['dsn'] ?? '');
    $user = $config['db_user'] ?: ($asp['user'] ?? '');
    $pass = $config['db_pass'] ?: ($asp['pass'] ?? '');

    if (!$dsn) {
        return $pdo = null;
    }

    try {
        return $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable) {
        return $pdo = null;
    }
}

function sample_books(): array
{
    return [
        [
            'id' => 1,
            'slug' => 'sample-php',
            'title' => 'PHP 網站建構範例',
            'subtitle' => '由原 ASP.NET MVC 專案轉出的新版入口',
            'author' => '明日書城編輯部',
            'pubdate' => '2026-09-10',
            'isbn' => '9780000000001',
            'thumb' => '/images/logo_discuss1.png',
            'picture' => '/images/logo_discuss1.png',
            'cat_name' => '網站範例',
            'short_intro' => '此範例會在尚未連接資料庫時顯示，連接原 ASP.NET 專案的 Azure SQL 後會改用實際書籍資料。',
            'legacy_description' => '<p>請設定 <code>.env</code> 中的資料庫連線，即可讀取既有書城資料表。</p>',
            'legacy_toc' => "設定環境\n啟動 PHP server\n連接資料庫",
            'author_intro' => '<p>明日書城 PHP 版示範資料。</p>',
        ],
    ];
}

function find_books(array $filters = []): array
{
    $pdo = db();
    if (!$pdo) {
        return ['items' => sample_books(), 'total' => 1, 'error' => '尚未連接資料庫，目前顯示範例資料。'];
    }

    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = 12;
    $offset = ($page - 1) * $perPage;
    $where = ['(bp.status = 1 OR bp.id IS NULL)', '(p.avail = 1 OR p.avail IS NULL)'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(p.prod_name LIKE :q_prod_name OR p.author LIKE :q_author OR p.isbn LIKE :q_isbn OR bp.display_title LIKE :q_display_title OR bp.display_author LIKE :q_display_author OR bp.short_intro LIKE :q_short_intro)';
        $keyword = '%' . trim((string)$filters['q']) . '%';
        $params[':q_prod_name'] = $keyword;
        $params[':q_author'] = $keyword;
        $params[':q_isbn'] = $keyword;
        $params[':q_display_title'] = $keyword;
        $params[':q_display_author'] = $keyword;
        $params[':q_short_intro'] = $keyword;
    }

    if (!empty($filters['tag'])) {
        $where[] = 'EXISTS (
            SELECT 1 FROM book_tag_map btm
            INNER JOIN book_tags bt ON bt.id = btm.tag_id
            WHERE btm.product_id = p.id AND bt.slug = :tag
        )';
        $params[':tag'] = (string)$filters['tag'];
    }

    $from = 'FROM products p LEFT JOIN book_profiles bp ON bp.product_id = p.id LEFT JOIN bookcats bc ON bc.id = p.cat_id WHERE ' . implode(' AND ', $where);

    $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
    foreach ($params as $name => $value) {
        $count->bindValue($name, $value);
    }
    $count->execute();

    $sql = 'SELECT p.id, COALESCE(bp.display_title, p.prod_name) AS title, bp.subtitle,
            COALESCE(bp.display_author, p.author) AS author, p.pubdate, p.isbn, p.thumb, p.picture,
            bc.cat_name, bp.slug, bp.short_intro ' . $from . '
            ORDER BY bp.featured_rank DESC, p.rank DESC, p.pubdate DESC, p.id DESC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['items' => $stmt->fetchAll(), 'total' => (int)$count->fetchColumn(), 'error' => null];
}

function find_book(string $key): ?array
{
    $pdo = db();
    if (!$pdo) {
        foreach (sample_books() as $book) {
            if ((string)$book['id'] === $key || $book['slug'] === $key) {
                return $book + ['tags' => [['name' => 'PHP', 'slug' => 'php']], 'links' => [], 'previews' => []];
            }
        }
        return null;
    }

    $byId = ctype_digit($key);
    $stmt = $pdo->prepare('SELECT p.id, COALESCE(bp.display_title, p.prod_name) AS title, bp.subtitle,
        COALESCE(bp.display_author, p.author) AS author, p.editor, bp.illustrator, p.pubdate, p.isbn,
        p.cat_id, bc.cat_name, ISNULL(bc.parent, 0) AS parent_cat_id, parent_bc.cat_name AS parent_cat_name,
        p.thumb, p.picture, p.content AS legacy_description, p.[index] AS legacy_toc, bp.slug,
        bp.author_intro, bp.preview_notice
        FROM products p
        LEFT JOIN book_profiles bp ON bp.product_id = p.id
        LEFT JOIN dbo.categories AS bc ON bc.id = p.cat_id
        LEFT JOIN dbo.categories AS parent_bc ON parent_bc.id = bc.parent
        WHERE ' . ($byId ? 'p.id = :key' : 'bp.slug = :key') . ' AND (bp.status = 1 OR bp.id IS NULL)
        ORDER BY p.id
        OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY');
    $stmt->bindValue(':key', $byId ? (int)$key : $key, $byId ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $book = $stmt->fetch();
    if (!$book) {
        return null;
    }

    $book['tags'] = rows($pdo, 'SELECT bt.name, bt.slug FROM book_tag_map btm INNER JOIN book_tags bt ON bt.id = btm.tag_id WHERE btm.product_id = :id ORDER BY bt.sort_order ASC, bt.name ASC', (int)$book['id']);
    $book['links'] = rows($pdo, 'SELECT label, url FROM book_links WHERE product_id = :id AND is_public = 1 ORDER BY sort_order ASC, id ASC', (int)$book['id']);
    $book['previews'] = rows($pdo, 'SELECT title, excerpt_label, content FROM book_previews WHERE product_id = :id AND is_public = 1 ORDER BY sort_order ASC, id ASC', (int)$book['id']);
    $book['authors'] = find_book_authors($pdo, (int)$book['id']);
    $book['category_path'] = book_category_path($book);
    return $book;
}

function book_category_path(array $book): array
{
    $path = [];

    if (!empty($book['parent_cat_id']) && !empty($book['parent_cat_name'])) {
        $path[] = [
            'name' => (string)$book['parent_cat_name'],
            'slug' => 'legacy-category-' . (int)$book['parent_cat_id'],
        ];
    }

    if (!empty($book['cat_id']) && !empty($book['cat_name'])) {
        $isSameAsParent = !empty($book['parent_cat_id']) && (int)$book['cat_id'] === (int)$book['parent_cat_id'];
        if (!$isSameAsParent) {
            $path[] = [
                'name' => (string)$book['cat_name'],
                'slug' => 'legacy-category-' . (int)$book['cat_id'],
            ];
        }
    }

    return $path;
}

function find_book_authors(PDO $pdo, int $productId): array
{
    try {
        $stmt = $pdo->prepare('SELECT author.pen_name, author.slug
            FROM dbo.author_book_map AS map
            INNER JOIN dbo.author_profiles AS author ON author.id = map.author_profile_id
            WHERE map.product_id = :product_id
              AND author.[status] = 1
            ORDER BY map.created_at ASC, author.sort_order ASC, author.id ASC');
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return array_values(array_filter($stmt->fetchAll(), static function (array $author): bool {
            return trim((string)($author['pen_name'] ?? '')) !== '' && trim((string)($author['slug'] ?? '')) !== '';
        }));
    } catch (Throwable) {
        return [];
    }
}

function rows(PDO $pdo, string $sql, int $id): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function sample_category_menu(): array
{
    return [
        ['id' => 1, 'parent' => 0, 'sort_order' => 10, 'name' => '明日便利書', 'slug' => 'legacy-category-1', 'product_count' => 1, 'children' => []],
        ['id' => 2, 'parent' => 0, 'sort_order' => 20, 'name' => '明日名家/武俠', 'slug' => 'legacy-category-2', 'product_count' => 1, 'children' => []],
        ['id' => 3, 'parent' => 0, 'sort_order' => 30, 'name' => '多媒體書', 'slug' => 'legacy-category-3', 'product_count' => 1, 'children' => []],
        ['id' => 4, 'parent' => 0, 'sort_order' => 40, 'name' => '未來書城', 'slug' => 'legacy-category-4', 'product_count' => 1, 'children' => []],
        ['id' => 5, 'parent' => 0, 'sort_order' => 50, 'name' => '其它服務', 'slug' => 'legacy-category-5', 'product_count' => 1, 'children' => []],
    ];
}

function find_category_menu(): array
{
    $pdo = db();
    if (!$pdo) {
        return sample_category_menu();
    }

    $sql = "SELECT c.id,
            ISNULL(c.parent, 0) AS parent,
            ISNULL(c.[rank], 0) AS sort_order,
            c.cat_name AS name,
            bt.slug,
            CAST(COUNT(DISTINCT btm.product_id) AS INT) AS product_count
        FROM dbo.categories AS c
        LEFT JOIN dbo.book_tags AS bt ON bt.slug = CONCAT(N'legacy-category-', c.id)
        LEFT JOIN dbo.book_tag_map AS btm ON btm.tag_id = bt.id
        WHERE ISNULL(c.hide, 0) = 0
          AND ISNULL(c.is_delete, 0) = 0
          AND NULLIF(LTRIM(RTRIM(c.cat_name)), N'') IS NOT NULL
        GROUP BY c.id, c.parent, c.[rank], c.cat_name, bt.slug
        ORDER BY ISNULL(c.parent, 0), ISNULL(c.[rank], 0), c.id";

    try {
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable) {
        return sample_category_menu();
    }

    $nodes = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $nodes[$id] = [
            'id' => $id,
            'parent' => (int)$row['parent'],
            'sort_order' => (int)$row['sort_order'],
            'name' => (string)$row['name'],
            'slug' => $row['slug'] ?: 'legacy-category-' . $id,
            'product_count' => (int)$row['product_count'],
            'children' => [],
        ];
    }

    uasort($nodes, 'compare_category_menu');
    $roots = [];
    foreach (array_keys($nodes) as $id) {
        $parentId = $nodes[$id]['parent'];
        if ($parentId !== 0 && isset($nodes[$parentId])) {
            $nodes[$parentId]['children'][] = &$nodes[$id];
        } else {
            $roots[] = &$nodes[$id];
        }
    }

    foreach ($roots as &$root) {
        usort($root['children'], 'compare_category_menu');
    }

    return $roots;
}

function compare_category_menu(array $left, array $right): int
{
    $rank = ((int)$left['sort_order']) <=> ((int)$right['sort_order']);
    if ($rank !== 0) {
        return $rank;
    }

    return ((int)$left['id']) <=> ((int)$right['id']);
}

function find_authors(string $q = '', int $page = 1, int $pageSize = 48): array
{
    $pdo = db();
    if (!$pdo) {
        return ['items' => [['pen_name' => '明日書城編輯部', 'slug' => 'editorial', 'intro' => '範例作者資料', 'avatar' => '/images/logo_discuss.png', 'book_count' => 1]], 'total' => 1];
    }

    $page = max(1, $page);
    $pageSize = max(1, $pageSize);
    $offset = ($page - 1) * $pageSize;
    $where = 'a.[status] = 1';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (a.pen_name LIKE :keyword_count_name OR a.intro LIKE :keyword_count_intro OR a.speak LIKE :keyword_count_speak)';
        $params[':keyword_count_name'] = '%' . $q . '%';
        $params[':keyword_count_intro'] = '%' . $q . '%';
        $params[':keyword_count_speak'] = '%' . $q . '%';
    }

    try {
        $count = $pdo->prepare('SELECT COUNT(1) FROM dbo.author_profiles AS a WHERE ' . $where);
        foreach ($params as $name => $value) {
            $count->bindValue($name, $value);
        }
        $count->execute();
        $total = (int)$count->fetchColumn();

        $keywordSql = $q !== ''
            ? ' AND (a.pen_name LIKE :keyword OR a.intro LIKE :keyword_intro OR a.speak LIKE :keyword_speak)'
            : '';
        $stmt = $pdo->prepare('SELECT a.id, a.slug, a.pen_name, a.site_url, a.avatar_image AS avatar, a.profile_image, a.intro, a.is_recommended,
                ISNULL(book_counts.book_count, 0) AS book_count
            FROM dbo.author_profiles AS a
            LEFT JOIN (
                SELECT author_profile_id, COUNT(*) AS book_count
                FROM dbo.author_book_map
                GROUP BY author_profile_id
            ) AS book_counts ON book_counts.author_profile_id = a.id
            WHERE a.[status] = 1' . $keywordSql . '
            ORDER BY a.is_recommended DESC, ISNULL(book_counts.book_count, 0) DESC, a.sort_order ASC, a.pen_name ASC
            OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY');
        if ($q !== '') {
            $keyword = '%' . $q . '%';
            $stmt->bindValue(':keyword', $keyword);
            $stmt->bindValue(':keyword_intro', $keyword);
            $stmt->bindValue(':keyword_speak', $keyword);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => $total];
    } catch (Throwable) {
        return find_authors_without_book_count($pdo, $q, $page, $pageSize);
    }
}

function find_authors_without_book_count(PDO $pdo, string $q = '', int $page = 1, int $pageSize = 48): array
{
    $offset = (max(1, $page) - 1) * max(1, $pageSize);
    $where = 'a.[status] = 1';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (a.pen_name LIKE :keyword_count_name OR a.intro LIKE :keyword_count_intro OR a.speak LIKE :keyword_count_speak)';
        $params[':keyword_count_name'] = '%' . $q . '%';
        $params[':keyword_count_intro'] = '%' . $q . '%';
        $params[':keyword_count_speak'] = '%' . $q . '%';
    }

    $count = $pdo->prepare('SELECT COUNT(1) FROM dbo.author_profiles AS a WHERE ' . $where);
    foreach ($params as $name => $value) {
        $count->bindValue($name, $value);
    }
    $count->execute();
    $total = (int)$count->fetchColumn();

    $keywordSql = $q !== ''
        ? ' AND (a.pen_name LIKE :keyword OR a.intro LIKE :keyword_intro OR a.speak LIKE :keyword_speak)'
        : '';
    $stmt = $pdo->prepare('SELECT a.id, a.slug, a.pen_name, a.site_url, a.avatar_image AS avatar, a.profile_image, a.intro, a.is_recommended, CAST(0 AS INT) AS book_count
        FROM dbo.author_profiles AS a
        WHERE a.[status] = 1' . $keywordSql . '
        ORDER BY a.is_recommended DESC, a.sort_order ASC, a.pen_name ASC
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY');
    if ($q !== '') {
        $keyword = '%' . $q . '%';
        $stmt->bindValue(':keyword', $keyword);
        $stmt->bindValue(':keyword_intro', $keyword);
        $stmt->bindValue(':keyword_speak', $keyword);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $stmt->execute();
    return ['items' => $stmt->fetchAll(), 'total' => $total];
}

function find_author_detail(string $slug): ?array
{
    $pdo = db();
    if (!$pdo || $slug === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT TOP (1)
                a.id, a.legacy_blog_id, a.slug, a.pen_name, a.site_url, a.site2_url,
                a.intro, a.speak, a.avatar_image AS avatar, a.profile_image,
                ISNULL(book_counts.book_count, 0) AS book_count
            FROM dbo.author_profiles AS a
            LEFT JOIN (
                SELECT author_profile_id, COUNT(*) AS book_count
                FROM dbo.author_book_map
                GROUP BY author_profile_id
            ) AS book_counts ON book_counts.author_profile_id = a.id
            WHERE a.slug = :slug AND a.[status] = 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $author = $stmt->fetch();
    } catch (Throwable) {
        $stmt = $pdo->prepare('SELECT TOP (1)
                a.id, a.legacy_blog_id, a.slug, a.pen_name, a.site_url, a.site2_url,
                a.intro, a.speak, a.avatar_image AS avatar, a.profile_image,
                CAST(0 AS INT) AS book_count
            FROM dbo.author_profiles AS a
            WHERE a.slug = :slug AND a.[status] = 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $author = $stmt->fetch();
    }

    if (!$author) {
        return null;
    }

    $author['books'] = find_author_books($pdo, (int)$author['id']);
    return $author;
}

function find_author_books(PDO $pdo, int $authorId): array
{
    try {
        $stmt = $pdo->prepare('SELECT p.id, bp.slug,
                COALESCE(bp.display_title, p.prod_name) AS title,
                bp.subtitle,
                COALESCE(bp.display_author, p.author) AS author,
                p.pubdate,
                p.isbn,
                COALESCE(NULLIF(p.thumb, N\'\'), NULLIF(p.picture, N\'\')) AS cover_image,
                bc.cat_name,
                bp.short_intro,
                p.content AS legacy_description
            FROM dbo.author_book_map AS map
            INNER JOIN dbo.products AS p ON p.id = map.product_id
            LEFT JOIN dbo.book_profiles AS bp ON bp.product_id = p.id
            LEFT JOIN dbo.categories AS bc ON bc.id = p.cat_id
            WHERE map.author_profile_id = :author_id
              AND (bp.[status] = 1 OR bp.id IS NULL)
              AND (p.avail = 1 OR p.avail IS NULL)
              AND ISNULL(p.is_delete, 0) = 0
            ORDER BY p.pubdate DESC, p.id DESC');
        $stmt->bindValue(':author_id', $authorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable) {
        return [];
    }
}
