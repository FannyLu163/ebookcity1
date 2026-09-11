<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function plain_excerpt(?string $html, int $length = 120): string
{
    $text = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length, 'UTF-8') . '...';
}

function url(string $path, array $query = []): string
{
    $base = $path;
    if ($query) {
        $base .= '?' . http_build_query(array_filter($query, static fn($v) => $v !== null && $v !== ''));
    }
    return $base;
}

function asset(?string $path): string
{
    $config = app_config();
    $path = trim((string) $path);
    if ($path === '') {
        return '/images/logo_discuss.png';
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    $path = preg_replace('/^~?\//', '', $path);
    return ($config['asset_base'] ?: '') . '/' . ltrim($path, '/');
}

function book_href(array $book): string
{
    return !empty($book['slug'])
        ? url('/book.php', ['slug' => $book['slug']])
        : url('/book.php', ['id' => $book['id'] ?? null]);
}

function author_href(array $author): string
{
    return url('/author.php', ['slug' => $author['slug'] ?? '']);
}

function external_url(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/https?:\/\/[^\s，,、]+/iu', $raw, $matches)) {
        return $matches[0];
    }

    if (preg_match('/www\.[^\s，,、]+/iu', $raw, $matches)) {
        return 'https://' . $matches[0];
    }

    if (str_contains($raw, '.') && !preg_match('/[\s，,、]/u', $raw)) {
        return 'https://' . $raw;
    }

    return '';
}

function page_title(string $title): string
{
    return $title . ' - 明日書城';
}

function category_url(array $category): string
{
    return url('/', ['tag' => $category['slug'] ?? '']);
}

function render_category_nav_item(array $category): string
{
    $name = (string)($category['name'] ?? '');
    $children = array_values(array_filter($category['children'] ?? [], static function (array $child): bool {
        return (int)($child['product_count'] ?? 0) > 0;
    }));

    if (!$children || $name === '其它服務') {
        return '<li class="nav-item"><a class="nav-link" href="' . h(category_url($category)) . '">' . h($name) . '</a></li>';
    }

    $html = '<li class="nav-item dropdown">';
    $html .= '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">' . h($name) . '</a>';
    $html .= '<ul class="dropdown-menu category-dropdown">';
    $html .= '<li><a class="dropdown-item" href="' . h(category_url($category)) . '">全部' . h($name) . '</a></li>';
    $html .= '<li><hr class="dropdown-divider"></li>';
    foreach ($children as $child) {
        $html .= '<li><a class="dropdown-item" href="' . h(category_url($child)) . '">' . h((string)$child['name']) . '</a></li>';
    }
    $html .= '</ul></li>';

    return $html;
}

function pagination_pages(int $currentPage, int $totalPages, int $window = 2): array
{
    if ($totalPages <= 1) {
        return [];
    }

    $pages = [1, $totalPages];
    for ($page = max(1, $currentPage - $window); $page <= min($totalPages, $currentPage + $window); $page++) {
        $pages[] = $page;
    }

    $pages = array_values(array_unique($pages));
    sort($pages);

    $items = [];
    $previous = 0;
    foreach ($pages as $page) {
        if ($previous > 0 && $page > $previous + 1) {
            $items[] = 'ellipsis';
        }
        $items[] = $page;
        $previous = $page;
    }

    return $items;
}

function checked_attr(bool $value): string
{
    return $value ? ' checked' : '';
}

function selected_attr($left, $right): string
{
    return (string)$left === (string)$right ? ' selected' : '';
}
