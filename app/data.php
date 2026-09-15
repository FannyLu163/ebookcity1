<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_error(?string $message = null): ?string
{
    static $error = null;

    if ($message !== null) {
        $error = $message;
    }

    return $error;
}

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
        db_error('找不到資料庫連線設定，請確認 .env 或原 ASP 專案 Web.config。');
        return $pdo = null;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        db_error('');
        return $pdo;
    } catch (Throwable $e) {
        db_error($e->getMessage());
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

    $fromBase = 'FROM products p LEFT JOIN book_profiles bp ON bp.product_id = p.id LEFT JOIN bookcats bc ON bc.id = p.cat_id';
    $from = $fromBase . ' WHERE ' . implode(' AND ', $where);

    $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
    foreach ($params as $name => $value) {
        $count->bindValue($name, $value);
    }
    $count->execute();

    $sql = "SELECT p.id, COALESCE(bp.display_title, p.prod_name) AS title, bp.subtitle,
            COALESCE(bp.display_author, p.author) AS author, author_links.authors AS linked_authors,
            p.pubdate, p.isbn, p.thumb, p.picture,
            bc.cat_name, bp.slug, bp.short_intro " . $fromBase . "
            OUTER APPLY (
                SELECT STRING_AGG(CONCAT(author.pen_name, N'|', author.slug), N';;') WITHIN GROUP (ORDER BY map.created_at ASC, author.sort_order ASC, author.id ASC) AS authors
                FROM dbo.author_book_map AS map
                INNER JOIN dbo.author_profiles AS author ON author.id = map.author_profile_id
                WHERE map.product_id = p.id
                  AND author.[status] = 1
                  AND NULLIF(LTRIM(RTRIM(author.pen_name)), N'') IS NOT NULL
                  AND NULLIF(LTRIM(RTRIM(author.slug)), N'') IS NOT NULL
            ) AS author_links
            WHERE " . implode(' AND ', $where) . "
            ORDER BY bp.featured_rank DESC, p.rank DESC, p.pubdate DESC, p.id DESC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = array_map(static function (array $book): array {
        $book['authors'] = parse_linked_authors((string)($book['linked_authors'] ?? ''));
        return $book;
    }, $stmt->fetchAll());

    return ['items' => $items, 'total' => (int)$count->fetchColumn(), 'error' => null];
}

function parse_linked_authors(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $authors = [];
    foreach (explode(';;', $value) as $item) {
        [$name, $slug] = array_pad(explode('|', $item, 2), 2, '');
        $name = trim($name);
        $slug = trim($slug);
        if ($name !== '' && $slug !== '') {
            $authors[] = ['pen_name' => $name, 'slug' => $slug];
        }
    }

    return $authors;
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

function db_value(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function admin_dashboard(): array
{
    $pdo = db();
    if (!$pdo) {
        return [
            'total_books' => 0,
            'visible_books' => 0,
            'total_authors' => 0,
            'error' => db_error() ?: '資料庫尚未連線',
        ];
    }

    $stmt = $pdo->query('SELECT
        (SELECT COUNT(1) FROM dbo.products) AS total_books,
        (SELECT COUNT(1) FROM dbo.products WHERE avail = 1 OR avail IS NULL) AS visible_books,
        (SELECT COUNT(1) FROM dbo.author_profiles WHERE [status] = 1 OR [status] IS NULL) AS total_authors');
    $dashboard = $stmt->fetch() ?: ['total_books' => 0, 'visible_books' => 0, 'total_authors' => 0];
    $dashboard['error'] = null;
    return $dashboard;
}

function admin_find_books(string $q = '', int $page = 1, int $pageSize = 20): array
{
    $pdo = db();
    if (!$pdo) {
        return ['items' => [], 'total' => 0];
    }
    $page = max(1, $page);
    $offset = ($page - 1) * $pageSize;
    $where = '1 = 1';
    $params = [];
    if ($q !== '') {
        $where = '(p.prod_name LIKE :q_title OR p.author LIKE :q_author OR p.isbn LIKE :q_isbn OR bp.display_title LIKE :q_display_title OR bp.display_author LIKE :q_display_author)';
        foreach ([':q_title', ':q_author', ':q_isbn', ':q_display_title', ':q_display_author'] as $name) {
            $params[$name] = '%' . $q . '%';
        }
    }
    $count = $pdo->prepare('SELECT COUNT(1) FROM dbo.products AS p LEFT JOIN dbo.book_profiles AS bp ON bp.product_id = p.id WHERE ' . $where);
    foreach ($params as $name => $value) {
        $count->bindValue($name, $value);
    }
    $count->execute();

    $stmt = $pdo->prepare('SELECT p.id, COALESCE(bp.display_title, p.prod_name) AS title, bp.subtitle,
            COALESCE(bp.display_author, p.author) AS author, p.pubdate,
            COALESCE(NULLIF(p.thumb, N\'\'), NULLIF(p.picture, N\'\')) AS cover_image,
            bc.cat_name, bp.slug,
            CAST(CASE WHEN (p.avail = 1 OR p.avail IS NULL) AND (bp.[status] = 1 OR bp.id IS NULL) THEN 1 ELSE 0 END AS INT) AS is_visible
        FROM dbo.products AS p
        LEFT JOIN dbo.book_profiles AS bp ON bp.product_id = p.id
        LEFT JOIN dbo.categories AS bc ON bc.id = p.cat_id
        WHERE ' . $where . '
        ORDER BY p.id DESC
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY');
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $stmt->execute();
    return ['items' => $stmt->fetchAll(), 'total' => (int)$count->fetchColumn()];
}

function admin_find_authors(string $q = '', int $page = 1, int $pageSize = 20): array
{
    $pdo = db();
    if (!$pdo) {
        return ['items' => [], 'total' => 0];
    }
    $page = max(1, $page);
    $offset = ($page - 1) * $pageSize;
    $where = '1 = 1';
    $params = [];
    if ($q !== '') {
        $where = '(a.pen_name LIKE :q_name OR a.site_url LIKE :q_site OR a.site2_url LIKE :q_site2 OR a.intro LIKE :q_intro OR a.speak LIKE :q_speak)';
        foreach ([':q_name', ':q_site', ':q_site2', ':q_intro', ':q_speak'] as $name) {
            $params[$name] = '%' . $q . '%';
        }
    }
    $count = $pdo->prepare('SELECT COUNT(1) FROM dbo.author_profiles AS a WHERE ' . $where);
    foreach ($params as $name => $value) {
        $count->bindValue($name, $value);
    }
    $count->execute();

    $stmt = $pdo->prepare('SELECT a.id, a.slug, a.pen_name, a.site_url,
            COALESCE(NULLIF(a.avatar_image, N\'\'), NULLIF(a.profile_image, N\'\')) AS avatar,
            a.sort_order, a.is_recommended, a.[status],
            ISNULL(book_counts.book_count, 0) AS book_count
        FROM dbo.author_profiles AS a
        LEFT JOIN (
            SELECT author_profile_id, COUNT(*) AS book_count
            FROM dbo.author_book_map
            GROUP BY author_profile_id
        ) AS book_counts ON book_counts.author_profile_id = a.id
        WHERE ' . $where . '
        ORDER BY a.id DESC
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY');
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $stmt->execute();
    return ['items' => $stmt->fetchAll(), 'total' => (int)$count->fetchColumn()];
}

function admin_find_author(int $id): ?array
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT TOP (1) id, slug, pen_name, site_url, site2_url, intro, speak, avatar_image, profile_image, sort_order, is_recommended, [status] FROM dbo.author_profiles WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

function admin_save_author(array $data): int
{
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException('資料庫尚未連線');
    }
    $id = (int)($data['id'] ?? 0);
    $slug = db_value($data['slug'] ?? '') ?: create_slug($data['pen_name'] ?? 'author');
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE dbo.author_profiles SET slug = :slug, pen_name = :pen_name, site_url = :site_url, site2_url = :site2_url,
            intro = :intro, speak = :speak, avatar_image = :avatar_image, profile_image = :profile_image, sort_order = :sort_order,
            is_recommended = :is_recommended, [status] = :status WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare('INSERT INTO dbo.author_profiles
            (legacy_blog_id, slug, pen_name, site_url, site2_url, intro, speak, avatar_image, profile_image, sort_order, is_recommended, [status], source_note, created_at)
            OUTPUT INSERTED.id
            VALUES (:legacy_blog_id, :slug, :pen_name, :site_url, :site2_url, :intro, :speak, :avatar_image, :profile_image, :sort_order, :is_recommended, :status, N\'php-admin\', SYSUTCDATETIME())');
        $stmt->bindValue(':legacy_blog_id', (int)($data['legacy_blog_id'] ?? next_php_author_legacy_blog_id($pdo)), PDO::PARAM_INT);
    }
    bind_author_form($stmt, $data, $slug);
    $stmt->execute();
    return $id > 0 ? $id : (int)$stmt->fetchColumn();
}

function bind_author_form(PDOStatement $stmt, array $data, string $slug): void
{
    $stmt->bindValue(':slug', $slug);
    $stmt->bindValue(':pen_name', db_value($data['pen_name'] ?? ''));
    $stmt->bindValue(':site_url', db_value($data['site_url'] ?? ''));
    $stmt->bindValue(':site2_url', db_value($data['site2_url'] ?? ''));
    $stmt->bindValue(':intro', db_value($data['intro'] ?? ''));
    $stmt->bindValue(':speak', db_value($data['speak'] ?? ''));
    $stmt->bindValue(':avatar_image', db_value($data['avatar_image'] ?? ''));
    $stmt->bindValue(':profile_image', db_value($data['profile_image'] ?? ''));
    $stmt->bindValue(':sort_order', (int)($data['sort_order'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':is_recommended', !empty($data['is_recommended']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':status', !empty($data['status']) ? 1 : 0, PDO::PARAM_INT);
}

function admin_find_book(int $id): ?array
{
    $pdo = db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT TOP (1) p.id, COALESCE(bp.display_title, p.prod_name) AS title, bp.subtitle,
        COALESCE(bp.display_author, p.author) AS author, p.pubdate, p.isbn, p.sn, p.picture, p.thumb,
        p.cat_id, ISNULL(bc.parent, 0) AS parent_cat_id, bp.slug, bp.short_intro, bp.toc, bp.preview_notice,
        CAST(CASE WHEN (p.avail = 1 OR p.avail IS NULL) AND (bp.[status] = 1 OR bp.id IS NULL) THEN 1 ELSE 0 END AS INT) AS is_visible
        FROM dbo.products AS p
        LEFT JOIN dbo.book_profiles AS bp ON bp.product_id = p.id
        LEFT JOIN dbo.categories AS bc ON bc.id = p.cat_id
        WHERE p.id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $book = $stmt->fetch();
    if (!$book) {
        return null;
    }
    $book['selected_author_ids'] = admin_book_author_ids($id);
    return $book;
}

function admin_save_book(array $data): int
{
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException('資料庫尚未連線');
    }
    $title = trim((string)($data['title'] ?? ''));
    $subtitle = trim((string)($data['subtitle'] ?? ''));
    $isbn = trim((string)($data['isbn'] ?? ''));
    $bookNumber = trim((string)($data['sn'] ?? ''));
    $previewNotice = trim((string)($data['preview_notice'] ?? ''));
    $picture = trim((string)($data['picture'] ?? ''));
    $thumb = trim((string)($data['thumb'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('請輸入書名');
    }
    if (mb_strlen($title, 'UTF-8') > 50) {
        throw new RuntimeException('書名最多 50 個字');
    }
    if (mb_strlen($subtitle, 'UTF-8') > 100) {
        throw new RuntimeException('副標題最多 100 個字');
    }
    if (mb_strlen($isbn, 'UTF-8') > 20) {
        throw new RuntimeException('ISBN 最多 20 個字');
    }
    if (mb_strlen($bookNumber, 'UTF-8') > 20) {
        throw new RuntimeException('書號最多 20 個字');
    }
    if (mb_strlen($previewNotice, 'UTF-8') > 500) {
        throw new RuntimeException('試閱說明最多 500 個字');
    }
    if (mb_strlen($picture, 'UTF-8') > 255) {
        throw new RuntimeException('圖片路徑最多 255 個字');
    }
    if (mb_strlen($thumb, 'UTF-8') > 255) {
        throw new RuntimeException('縮圖路徑最多 255 個字');
    }
    $id = (int)($data['id'] ?? 0);
    $selectedAuthorIds = normalize_author_ids($data['selected_author_ids'] ?? []);
    if (!empty($data['new_author_name'])) {
        $newAuthorId = admin_create_author_name((string)$data['new_author_name']);
        if (!in_array($newAuthorId, $selectedAuthorIds, true)) {
            $selectedAuthorIds[] = $newAuthorId;
        }
    }
    if ($selectedAuthorIds) {
        $data['author'] = admin_author_display_name($selectedAuthorIds, (string)($data['author'] ?? ''));
    }
    $visible = !empty($data['is_visible']) ? 1 : 0;
    if ($id <= 0) {
        $next = $pdo->query('SELECT ISNULL(MAX(id), 0) + 1 FROM dbo.products WITH (UPDLOCK, HOLDLOCK)');
        $id = (int)$next->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO dbo.products
            (id, mall, cat_id, [rank], prod_name, author, sn, pubdate, isbn, thumb, picture, avail, stock, has_editor, has_preview, content, [index], cdate, add_time, is_delete)
            VALUES (:id, 0, :cat_id, 0, :title, :author, :sn, :pubdate, :isbn, :thumb, :picture, :visible, 0, 0, 0, :short_intro, :toc, SYSUTCDATETIME(), SYSUTCDATETIME(), 0)');
    } else {
        $stmt = $pdo->prepare('UPDATE dbo.products SET prod_name = :title, author = :author, pubdate = :pubdate, isbn = :isbn, sn = :sn,
            cat_id = :cat_id, picture = :picture, thumb = :thumb, avail = :visible WHERE id = :id');
    }
    bind_book_product_form($stmt, $id, $data, $visible);
    $stmt->execute();

    $exists = $pdo->prepare('SELECT COUNT(1) FROM dbo.book_profiles WHERE product_id = :id');
    $exists->bindValue(':id', $id, PDO::PARAM_INT);
    $exists->execute();
    if ((int)$exists->fetchColumn() > 0) {
        $profile = $pdo->prepare('UPDATE dbo.book_profiles SET display_title = :title, subtitle = :subtitle, display_author = :author,
            short_intro = :short_intro, toc = :toc, preview_notice = :preview_notice, [status] = :visible WHERE product_id = :id');
    } else {
        $profile = $pdo->prepare('INSERT INTO dbo.book_profiles (product_id, slug, display_title, subtitle, display_author, short_intro, toc, preview_notice, [status])
            VALUES (:id, :slug, :title, :subtitle, :author, :short_intro, :toc, :preview_notice, :visible)');
        $profile->bindValue(':slug', 'book-' . $id);
    }
    bind_book_profile_form($profile, $id, $data, $visible);
    $profile->execute();
    sync_admin_book_authors($pdo, $id, $selectedAuthorIds);
    sync_admin_book_category_tags($pdo, $id, (int)($data['cat_id'] ?? 0));
    return $id;
}

function bind_book_product_form(PDOStatement $stmt, int $id, array $data, int $visible): void
{
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':title', db_value($data['title'] ?? ''));
    $stmt->bindValue(':author', db_value($data['author'] ?? ''));
    $stmt->bindValue(':pubdate', db_value($data['pubdate'] ?? ''));
    $stmt->bindValue(':isbn', db_value($data['isbn'] ?? ''));
    $stmt->bindValue(':sn', db_value($data['sn'] ?? ''));
    $stmt->bindValue(':cat_id', (int)($data['cat_id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':picture', db_value($data['picture'] ?? ''));
    $stmt->bindValue(':thumb', db_value($data['thumb'] ?? ''));
    $stmt->bindValue(':visible', $visible, PDO::PARAM_INT);
    if (str_contains($stmt->queryString, ':short_intro')) {
        $stmt->bindValue(':short_intro', db_value($data['short_intro'] ?? ''));
        $stmt->bindValue(':toc', db_value($data['toc'] ?? ''));
    }
}

function bind_book_profile_form(PDOStatement $stmt, int $id, array $data, int $visible): void
{
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':title', db_value($data['title'] ?? ''));
    $stmt->bindValue(':subtitle', db_value($data['subtitle'] ?? ''));
    $stmt->bindValue(':author', db_value($data['author'] ?? ''));
    $stmt->bindValue(':short_intro', db_value($data['short_intro'] ?? ''));
    $stmt->bindValue(':toc', db_value($data['toc'] ?? ''));
    $stmt->bindValue(':preview_notice', db_value($data['preview_notice'] ?? ''));
    $stmt->bindValue(':visible', $visible, PDO::PARAM_INT);
}

function sync_admin_book_category_tags(PDO $pdo, int $productId, int $categoryId): void
{
    try {
        $pdo->prepare("DELETE FROM dbo.book_tag_map WHERE product_id = :id AND tag_id IN (SELECT id FROM dbo.book_tags WHERE slug LIKE N'legacy-category-%')")
            ->execute([':id' => $productId]);
        if ($categoryId <= 0) {
            return;
        }
        $stmt = $pdo->prepare("INSERT INTO dbo.book_tag_map (product_id, tag_id)
            SELECT :product_id, bt.id FROM dbo.book_tags AS bt
            WHERE bt.slug = CONCAT(N'legacy-category-', :category_id)
              AND NOT EXISTS (SELECT 1 FROM dbo.book_tag_map AS existing WHERE existing.product_id = :product_id2 AND existing.tag_id = bt.id)");
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id2', $productId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable) {
    }
}

function admin_category_options(): array
{
    $items = [];
    foreach (find_category_menu() as $root) {
        $items[] = ['id' => $root['id'], 'name' => $root['name']];
        foreach (($root['children'] ?? []) as $child) {
            $items[] = ['id' => $child['id'], 'name' => '　' . $child['name']];
        }
    }
    return $items;
}

function admin_category_tree_options(): array
{
    return find_category_menu();
}

function admin_author_options(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    $stmt = $pdo->query('SELECT a.id, a.pen_name, a.slug, ISNULL(book_counts.book_count, 0) AS book_count
        FROM dbo.author_profiles AS a
        LEFT JOIN (
            SELECT author_profile_id, COUNT(*) AS book_count
            FROM dbo.author_book_map
            GROUP BY author_profile_id
        ) AS book_counts ON book_counts.author_profile_id = a.id
        WHERE a.[status] = 1
        ORDER BY a.pen_name ASC');
    return $stmt->fetchAll();
}

function admin_book_author_ids(int $productId): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare('SELECT author_profile_id FROM dbo.author_book_map WHERE product_id = :id ORDER BY created_at ASC, author_profile_id ASC');
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('intval', array_column($stmt->fetchAll(), 'author_profile_id'));
    } catch (Throwable) {
        return [];
    }
}

function normalize_author_ids($ids): array
{
    if (!is_array($ids)) {
        $ids = [$ids];
    }
    $normalized = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0 && !in_array($id, $normalized, true)) {
            $normalized[] = $id;
        }
    }
    return $normalized;
}

function admin_create_author_name(string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException('資料庫尚未連線');
    }
    $existing = $pdo->prepare('SELECT TOP (1) id FROM dbo.author_profiles WHERE pen_name = :pen_name ORDER BY [status] DESC, id ASC');
    $existing->bindValue(':pen_name', $name);
    $existing->execute();
    $existingId = (int)$existing->fetchColumn();
    if ($existingId > 0) {
        return $existingId;
    }

    return admin_save_author([
        'pen_name' => $name,
        'slug' => create_unique_author_slug($pdo, $name),
        'legacy_blog_id' => next_php_author_legacy_blog_id($pdo),
        'site_url' => '',
        'site2_url' => '',
        'intro' => '',
        'speak' => '',
        'avatar_image' => '',
        'profile_image' => '',
        'sort_order' => 0,
        'is_recommended' => 0,
        'status' => 1,
    ]);
}

function next_php_author_legacy_blog_id(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT ISNULL(MIN(legacy_blog_id), 0) - 1 FROM dbo.author_profiles WITH (UPDLOCK, HOLDLOCK)');
    return min(-1, (int)$stmt->fetchColumn());
}

function create_author_slug(string $name): string
{
    $base = 'author-' . create_slug($name);
    return $base === 'author-' ? 'author-new' : $base;
}

function create_unique_author_slug(PDO $pdo, string $name): string
{
    $base = create_author_slug($name);
    $slug = $base;
    for ($index = 2; $index < 1000; $index++) {
        $stmt = $pdo->prepare('SELECT COUNT(1) FROM dbo.author_profiles WHERE slug = :slug');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $index;
    }

    return $base . '-' . time();
}

function is_duplicate_author_key_error(Throwable $e): bool
{
    $message = $e->getMessage();
    return str_contains($message, 'author_profiles')
        && (str_contains($message, 'UX_author_profiles_legacy_blog_id') || str_contains($message, 'UX_author_profiles_slug'));
}

function admin_author_display_name(array $ids, string $fallback): string
{
    $pdo = db();
    if (!$pdo || !$ids) {
        return $fallback;
    }
    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $name = ':id' . $index;
        $placeholders[] = $name;
        $params[$name] = $id;
    }
    $stmt = $pdo->prepare('SELECT id, pen_name FROM dbo.author_profiles WHERE id IN (' . implode(',', $placeholders) . ')');
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $namesById = [];
    foreach ($stmt->fetchAll() as $row) {
        $namesById[(int)$row['id']] = (string)$row['pen_name'];
    }
    $names = [];
    foreach ($ids as $id) {
        if (!empty($namesById[$id])) {
            $names[] = $namesById[$id];
        }
    }
    return $names ? implode('、', $names) : $fallback;
}

function sync_admin_book_authors(PDO $pdo, int $productId, array $authorIds): void
{
    try {
        $delete = $pdo->prepare('DELETE FROM dbo.author_book_map WHERE product_id = :id');
        $delete->bindValue(':id', $productId, PDO::PARAM_INT);
        $delete->execute();
        foreach ($authorIds as $index => $authorId) {
            $insert = $pdo->prepare('INSERT INTO dbo.author_book_map (author_profile_id, product_id, source_note, created_at)
                VALUES (:author_id, :product_id, N\'php-admin\', DATEADD(SECOND, :sort_order, SYSUTCDATETIME()))');
            $insert->bindValue(':author_id', $authorId, PDO::PARAM_INT);
            $insert->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $insert->bindValue(':sort_order', $index, PDO::PARAM_INT);
            $insert->execute();
        }
    } catch (Throwable) {
    }
}

function create_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug !== '' ? substr($slug, 0, 120) : 'item-' . time();
}
