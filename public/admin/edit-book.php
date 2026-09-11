<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$saved = isset($_GET['saved']);
$book = $id > 0 ? admin_find_book($id) : [
    'id' => 0, 'title' => '', 'subtitle' => '', 'author' => '', 'pubdate' => date('Y-m-d'), 'isbn' => '', 'sn' => '',
    'picture' => '', 'thumb' => '', 'cat_id' => 0, 'short_intro' => '', 'toc' => '', 'preview_notice' => '', 'is_visible' => 1,
];
$categories = admin_category_options();
$categoryTree = admin_category_tree_options();
$authorOptions = admin_author_options();
$selectedAuthorIds = array_map('intval', $book['selected_author_ids'] ?? []);
$selectedParentId = (int)($book['parent_cat_id'] ?? 0);
if ($selectedParentId <= 0 && !empty($book['cat_id'])) {
    foreach ($categoryTree as $root) {
        if ((int)$root['id'] === (int)$book['cat_id']) {
            $selectedParentId = (int)$root['id'];
            break;
        }
        foreach (($root['children'] ?? []) as $child) {
            if ((int)$child['id'] === (int)$book['cat_id']) {
                $selectedParentId = (int)$root['id'];
                break 2;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titleValue = trim((string)($_POST['title'] ?? ''));
        $subtitleValue = trim((string)($_POST['subtitle'] ?? ''));
        if ($titleValue === '') {
            throw new RuntimeException('請輸入書名');
        }
        if (mb_strlen($titleValue, 'UTF-8') > 50) {
            throw new RuntimeException('書名最多 50 個字');
        }
        if (mb_strlen($subtitleValue, 'UTF-8') > 100) {
            throw new RuntimeException('副標題最多 100 個字');
        }
        $newId = admin_save_book($_POST);
        header('Location: /admin/edit-book.php?id=' . $newId . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $book = $_POST + ['id' => $id, 'selected_author_ids' => $_POST['selected_author_ids'] ?? []];
        $selectedAuthorIds = normalize_author_ids($_POST['selected_author_ids'] ?? []);
        $selectedParentId = (int)($_POST['parent_cat_id'] ?? 0);
    }
}

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1"><?= $id > 0 ? '編輯書籍' : '新增書籍' ?></h1><div class="text-muted"><?= $id > 0 ? 'ID ' . h((string)$id) : '建立新的書籍資料' ?></div></div>
    <a class="btn btn-outline-secondary" href="/admin/books.php">返回列表</a>
</div>
<?php if ($saved): ?><div class="alert alert-success">已儲存。</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="bg-white border rounded p-3">
    <input type="hidden" name="id" value="<?= h((string)($book['id'] ?? 0)) ?>">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">書名</label><input class="form-control" name="title" value="<?= h($book['title'] ?? '') ?>" maxlength="50" required><div class="form-text">最多 50 個字</div></div>
        <div class="col-md-4"><label class="form-label">副標題</label><input class="form-control" name="subtitle" value="<?= h($book['subtitle'] ?? '') ?>" maxlength="100"><div class="form-text">最多 100 個字</div></div>
        <input type="hidden" id="Author" name="author" value="<?= h($book['author'] ?? '') ?>">
        <div class="col-md-4"><label class="form-label">出版日期</label><input class="form-control" name="pubdate" type="date" value="<?= h(!empty($book['pubdate']) ? substr((string)$book['pubdate'], 0, 10) : '') ?>"></div>
        <div class="col-md-4"><label class="form-label">ISBN</label><input class="form-control" name="isbn" value="<?= h($book['isbn'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">書號</label><input class="form-control" name="sn" value="<?= h($book['sn'] ?? '') ?>"></div>
        <div class="col-md-6">
            <label class="form-label">主分類</label>
            <select class="form-select" id="ParentCategoryId" name="parent_cat_id">
                <option value="0">請選擇主分類</option>
                <?php foreach ($categoryTree as $category): ?>
                    <option value="<?= h((string)$category['id']) ?>"<?= selected_attr($category['id'], $selectedParentId) ?>><?= h($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">分類</label>
            <select class="form-select" id="CategoryId" name="cat_id">
                <option value="0">請選擇子分類</option>
                <?php foreach ($categoryTree as $category): ?>
                    <option value="<?= h((string)$category['id']) ?>" data-parent="<?= h((string)$category['id']) ?>"<?= selected_attr($category['id'], $book['cat_id'] ?? 0) ?>>全部<?= h($category['name']) ?></option>
                    <?php foreach (($category['children'] ?? []) as $child): ?>
                        <option value="<?= h((string)$child['id']) ?>" data-parent="<?= h((string)$category['id']) ?>"<?= selected_attr($child['id'], $book['cat_id'] ?? 0) ?>><?= h($child['name']) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">關聯作者</label>
            <input class="form-control mb-2" id="AuthorFilter" type="search" placeholder="輸入作者關鍵字" autocomplete="off">
            <div class="input-group">
                <select class="form-select" id="AuthorProfileId">
                    <option value="" data-name="">請選擇作者</option>
                    <?php foreach ($authorOptions as $author): ?>
                        <option value="<?= h((string)$author['id']) ?>" data-name="<?= h($author['pen_name']) ?>"><?= h($author['pen_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-success" type="button" id="AddSelectedAuthor">加入</button>
            </div>
            <div class="mt-2" id="SelectedAuthors">
                <?php foreach ($authorOptions as $author): ?>
                    <?php if (in_array((int)$author['id'], $selectedAuthorIds, true)): ?>
                        <span class="badge text-bg-light border text-dark me-1 mb-1 selected-author" data-id="<?= h((string)$author['id']) ?>" data-name="<?= h($author['pen_name']) ?>">
                            <?= h($author['pen_name']) ?>
                            <button class="btn-close ms-1 remove-author" type="button" aria-label="移除作者"></button>
                            <input type="hidden" name="selected_author_ids[]" value="<?= h((string)$author['id']) ?>">
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-outline-secondary btn-sm mt-2" type="button" id="ShowNewAuthor">新增作者</button>
            <div class="mt-2 d-none" id="NewAuthorPanel">
                <label class="form-label small mb-1">新增作者</label>
                <input class="form-control" id="NewAuthorName" name="new_author_name" value="<?= h($book['new_author_name'] ?? '') ?>" placeholder="輸入新作者名稱">
                <div class="form-text">儲存後會建立作者資料，並與這本書關聯。</div>
            </div>
        </div>
        <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_visible" value="1"<?= checked_attr(!empty($book['is_visible'])) ?>> 前台顯示</label></div>
        <div class="col-md-6"><label class="form-label">圖片路徑</label><input class="form-control" name="picture" value="<?= h($book['picture'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">縮圖路徑</label><input class="form-control" name="thumb" value="<?= h($book['thumb'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">簡介</label><textarea class="form-control" name="short_intro" rows="6"><?= h($book['short_intro'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">目錄</label><textarea class="form-control" name="toc" rows="6"><?= h($book['toc'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">試閱說明</label><input class="form-control" name="preview_notice" value="<?= h($book['preview_notice'] ?? '') ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-success" type="submit">儲存</button></div>
</form>
<script>
(function () {
    var parentCategory = document.getElementById('ParentCategoryId');
    var category = document.getElementById('CategoryId');
    if (parentCategory && category) {
        var originalOptions = Array.prototype.map.call(category.options, function (option) {
            return { value: option.value, text: option.text, parent: option.getAttribute('data-parent') || '', selected: option.selected };
        });
        function renderCategories(reset) {
            var parentId = parentCategory.value;
            var currentValue = reset ? '' : category.value;
            category.innerHTML = '';
            originalOptions.forEach(function (item) {
                if (item.value !== '0' && item.parent !== parentId) return;
                var option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                if (item.parent) option.setAttribute('data-parent', item.parent);
                if (item.value === currentValue || (!reset && item.selected)) option.selected = true;
                category.appendChild(option);
            });
            if (category.selectedIndex < 0 && category.options.length) category.selectedIndex = 0;
        }
        parentCategory.addEventListener('change', function () { renderCategories(true); });
        renderCategories(false);
    }
})();
(function () {
    var select = document.getElementById('AuthorProfileId');
    var filter = document.getElementById('AuthorFilter');
    var selectedAuthors = document.getElementById('SelectedAuthors');
    var addButton = document.getElementById('AddSelectedAuthor');
    var authorInput = document.getElementById('Author');
    var newAuthorButton = document.getElementById('ShowNewAuthor');
    var newAuthorPanel = document.getElementById('NewAuthorPanel');
    var newAuthorInput = document.getElementById('NewAuthorName');
    if (!select || !filter || !selectedAuthors || !authorInput) return;
    var options = Array.prototype.map.call(select.options, function (option) {
        return { value: option.value, text: option.text, name: option.getAttribute('data-name') || '' };
    });
    function selectedIds() {
        return Array.prototype.map.call(selectedAuthors.querySelectorAll('input[name="selected_author_ids[]"]'), function (input) { return input.value; });
    }
    function syncAuthorName() {
        var names = Array.prototype.map.call(selectedAuthors.querySelectorAll('.selected-author'), function (item) {
            return item.getAttribute('data-name');
        }).filter(Boolean);
        if (newAuthorInput && newAuthorInput.value.trim()) names.push(newAuthorInput.value.trim());
        authorInput.value = names.join('、');
    }
    function addAuthor(id, name) {
        if (!id || !name || selectedIds().indexOf(id) !== -1) return;
        var item = document.createElement('span');
        item.className = 'badge text-bg-light border text-dark me-1 mb-1 selected-author';
        item.setAttribute('data-id', id);
        item.setAttribute('data-name', name);
        item.appendChild(document.createTextNode(name + ' '));
        var remove = document.createElement('button');
        remove.className = 'btn-close ms-1 remove-author';
        remove.type = 'button';
        remove.setAttribute('aria-label', '移除作者');
        item.appendChild(remove);
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_author_ids[]';
        hidden.value = id;
        item.appendChild(hidden);
        selectedAuthors.appendChild(item);
        syncAuthorName();
    }
    function renderOptions(keyword) {
        var currentValue = select.value;
        var normalized = (keyword || '').trim().toLowerCase();
        select.innerHTML = '';
        options.forEach(function (item) {
            if (normalized && item.name.toLowerCase().indexOf(normalized) === -1) return;
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            if (item.name) option.setAttribute('data-name', item.name);
            if (item.value === currentValue) option.selected = true;
            select.appendChild(option);
        });
    }
    filter.addEventListener('input', function () { renderOptions(filter.value); });
    addButton.addEventListener('click', function () {
        var selected = select.options[select.selectedIndex];
        if (selected) addAuthor(selected.value, selected.getAttribute('data-name') || selected.textContent.trim());
    });
    selectedAuthors.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-author')) {
            event.target.closest('.selected-author').remove();
            syncAuthorName();
        }
    });
    if (newAuthorButton && newAuthorPanel && newAuthorInput) {
        newAuthorButton.addEventListener('click', function () {
            newAuthorPanel.classList.remove('d-none');
            newAuthorInput.focus();
        });
        newAuthorInput.addEventListener('input', syncAuthorName);
        if (newAuthorInput.value.trim()) newAuthorPanel.classList.remove('d-none');
    }
    syncAuthorName();
})();
</script>
<?php admin_layout($id > 0 ? '編輯書籍' : '新增書籍', ob_get_clean()); ?>
