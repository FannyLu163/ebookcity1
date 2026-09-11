<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
admin_require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$fieldErrors = [];
$firstErrorField = '';
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
            $fieldErrors['title'] = '請輸入書名';
        }
        if (mb_strlen($titleValue, 'UTF-8') > 50) {
            $fieldErrors['title'] = '書名最多 50 個字';
        }
        if (mb_strlen($subtitleValue, 'UTF-8') > 100) {
            $fieldErrors['subtitle'] = '副標題最多 100 個字';
        }
        if (mb_strlen(trim((string)($_POST['isbn'] ?? '')), 'UTF-8') > 20) {
            $fieldErrors['isbn'] = 'ISBN 最多 20 個字';
        }
        if (mb_strlen(trim((string)($_POST['sn'] ?? '')), 'UTF-8') > 20) {
            $fieldErrors['sn'] = '書號最多 20 個字';
        }
        if (mb_strlen(trim((string)($_POST['preview_notice'] ?? '')), 'UTF-8') > 500) {
            $fieldErrors['preview_notice'] = '試閱說明最多 500 個字';
        }
        if (mb_strlen(trim((string)($_POST['picture'] ?? '')), 'UTF-8') > 255) {
            $fieldErrors['picture'] = '圖片路徑最多 255 個字';
        }
        if (mb_strlen(trim((string)($_POST['thumb'] ?? '')), 'UTF-8') > 255) {
            $fieldErrors['thumb'] = '縮圖路徑最多 255 個字';
        }
        if ($fieldErrors) {
            throw new RuntimeException('請修正欄位錯誤');
        }
        try {
            $uploadedPicture = admin_save_book_image_upload('picture_file', $id, 'cover', 500, 500, 1024 * 1024);
            if ($uploadedPicture !== null) {
                $_POST['picture'] = $uploadedPicture;
            }
        } catch (Throwable $uploadError) {
            $fieldErrors['picture_file'] = $uploadError->getMessage();
            throw new RuntimeException('請修正欄位錯誤');
        }
        try {
            $uploadedThumb = admin_save_book_image_upload('thumb_file', $id, 'thumb', 250, 250, 500 * 1024);
            if ($uploadedThumb !== null) {
                $_POST['thumb'] = $uploadedThumb;
            }
        } catch (Throwable $uploadError) {
            $fieldErrors['thumb_file'] = $uploadError->getMessage();
            throw new RuntimeException('請修正欄位錯誤');
        }
        $newId = admin_save_book($_POST);
        header('Location: /admin/edit-book.php?id=' . $newId . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $fieldErrors ? '' : $e->getMessage();
        $firstErrorField = $fieldErrors ? (string)array_key_first($fieldErrors) : '';
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
<form method="post" class="bg-white border rounded p-3" enctype="multipart/form-data" data-first-error="<?= h($firstErrorField) ?>">
    <input type="hidden" name="id" value="<?= h((string)($book['id'] ?? 0)) ?>">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">書名</label><input class="form-control<?= isset($fieldErrors['title']) ? ' is-invalid' : '' ?>" name="title" value="<?= h($book['title'] ?? '') ?>" maxlength="50" required><?php if (isset($fieldErrors['title'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['title']) ?></div><?php endif; ?><div class="form-text">最多 50 個字</div></div>
        <div class="col-md-4"><label class="form-label">副標題</label><input class="form-control<?= isset($fieldErrors['subtitle']) ? ' is-invalid' : '' ?>" name="subtitle" value="<?= h($book['subtitle'] ?? '') ?>" maxlength="100"><?php if (isset($fieldErrors['subtitle'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['subtitle']) ?></div><?php endif; ?><div class="form-text">最多 100 個字</div></div>
        <input type="hidden" id="Author" name="author" value="<?= h($book['author'] ?? '') ?>">
        <div class="col-md-4"><label class="form-label">出版日期</label><input class="form-control" name="pubdate" type="date" value="<?= h(!empty($book['pubdate']) ? substr((string)$book['pubdate'], 0, 10) : '') ?>"></div>
        <div class="col-md-4"><label class="form-label">ISBN</label><input class="form-control<?= isset($fieldErrors['isbn']) ? ' is-invalid' : '' ?>" name="isbn" value="<?= h($book['isbn'] ?? '') ?>" maxlength="20"><?php if (isset($fieldErrors['isbn'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['isbn']) ?></div><?php endif; ?><div class="form-text">最多 20 個字</div></div>
        <div class="col-md-4"><label class="form-label">書號</label><input class="form-control<?= isset($fieldErrors['sn']) ? ' is-invalid' : '' ?>" name="sn" value="<?= h($book['sn'] ?? '') ?>" maxlength="20"><?php if (isset($fieldErrors['sn'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['sn']) ?></div><?php endif; ?><div class="form-text">最多 20 個字</div></div>
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
        <div class="col-md-6">
            <label class="form-label">圖片路徑</label>
            <div class="admin-image-preview mb-2">
                <img id="PicturePreview" src="<?= h(asset($book['picture'] ?? '')) ?>" alt="圖片預覽" onerror="this.src='/images/logo_discuss.png'">
            </div>
            <input class="form-control image-path-input<?= isset($fieldErrors['picture']) ? ' is-invalid' : '' ?>" id="PicturePath" name="picture" value="<?= h($book['picture'] ?? '') ?>" maxlength="255" data-preview="PicturePreview">
            <?php if (isset($fieldErrors['picture'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['picture']) ?></div><?php endif; ?>
            <input class="form-control mt-2 image-file-input<?= isset($fieldErrors['picture_file']) ? ' is-invalid' : '' ?>" type="file" name="picture_file" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="PicturePreview" data-max-width="500" data-max-height="500" data-max-bytes="1048576" data-label="圖片">
            <?php if (isset($fieldErrors['picture_file'])): ?><div class="invalid-feedback d-block image-file-error"><?= h($fieldErrors['picture_file']) ?></div><?php endif; ?>
            <div class="form-text">圖片路徑最多 255 個字。上傳後會存到 /imgs/pro/，系統會自動改檔名避免重複。限制：500x500 內，1MB 以內。</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">縮圖路徑</label>
            <div class="admin-image-preview mb-2">
                <img id="ThumbPreview" src="<?= h(asset($book['thumb'] ?? '')) ?>" alt="縮圖預覽" onerror="this.src='/images/logo_discuss.png'">
            </div>
            <input class="form-control image-path-input<?= isset($fieldErrors['thumb']) ? ' is-invalid' : '' ?>" id="ThumbPath" name="thumb" value="<?= h($book['thumb'] ?? '') ?>" maxlength="255" data-preview="ThumbPreview">
            <?php if (isset($fieldErrors['thumb'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['thumb']) ?></div><?php endif; ?>
            <input class="form-control mt-2 image-file-input<?= isset($fieldErrors['thumb_file']) ? ' is-invalid' : '' ?>" type="file" name="thumb_file" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="ThumbPreview" data-max-width="250" data-max-height="250" data-max-bytes="512000" data-label="縮圖">
            <?php if (isset($fieldErrors['thumb_file'])): ?><div class="invalid-feedback d-block image-file-error"><?= h($fieldErrors['thumb_file']) ?></div><?php endif; ?>
            <div class="form-text">縮圖路徑最多 255 個字。上傳後會存到 /imgs/pro/，系統會自動改檔名避免重複。限制：250x250 內，500KB 以內。</div>
        </div>
        <div class="col-12">
            <label class="form-label">簡介</label>
            <textarea class="visually-hidden" id="ShortIntro" name="short_intro"><?= h($book['short_intro'] ?? '') ?></textarea>
            <div class="rich-editor border rounded">
                <div class="rich-editor-toolbar border-bottom bg-body-tertiary p-2 d-flex flex-wrap gap-2" role="toolbar" aria-label="簡介文字工具列">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-command="bold" title="粗體"><strong>B</strong></button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-command="italic" title="斜體"><em>I</em></button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-command="underline" title="底線"><u>U</u></button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-block="p">段落</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-block="h3">標題</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-command="insertUnorderedList">清單</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-command="insertOrderedList">編號</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-action="link">連結</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-action="clear">清除格式</button>
                    <select class="form-select form-select-sm rich-editor-font" id="ShortIntroFontName" aria-label="字型">
                        <option value="">字型</option>
                        <option value="Microsoft JhengHei">微軟正黑體</option>
                        <option value="PMingLiU">新細明體</option>
                        <option value="MingLiU">細明體</option>
                        <option value="DFKai-SB">標楷體</option>
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                    </select>
                </div>
                <div id="ShortIntroEditor" class="rich-editor-surface p-3 bg-white" contenteditable="true" aria-label="簡介內容"><?= $book['short_intro'] ?? '' ?></div>
            </div>
            <div class="form-text">此欄位會儲存為 HTML，並在前台書籍頁直接渲染。</div>
        </div>
        <div class="col-12"><label class="form-label">目錄</label><textarea class="form-control" name="toc" rows="6"><?= h($book['toc'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">試閱說明</label><input class="form-control<?= isset($fieldErrors['preview_notice']) ? ' is-invalid' : '' ?>" name="preview_notice" value="<?= h($book['preview_notice'] ?? '') ?>" maxlength="500"><?php if (isset($fieldErrors['preview_notice'])): ?><div class="invalid-feedback d-block"><?= h($fieldErrors['preview_notice']) ?></div><?php endif; ?><div class="form-text">最多 500 個字</div></div>
    </div>
    <div class="mt-3"><button class="btn btn-success" type="submit">儲存</button></div>
</form>
<script>
(function () {
    var form = document.querySelector('form[data-first-error]');
    if (!form) return;
    var firstError = form.getAttribute('data-first-error');
    if (!firstError) return;
    var escapedName = window.CSS && CSS.escape ? CSS.escape(firstError) : firstError.replace(/"/g, '\\"');
    var field = form.querySelector('[name="' + escapedName + '"]');
    if (!field) return;
    window.setTimeout(function () {
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus({ preventScroll: true });
    }, 80);
})();
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
(function () {
    var editor = document.getElementById('ShortIntroEditor');
    var field = document.getElementById('ShortIntro');
    if (!editor || !field) return;

    function sync() {
        field.value = editor.innerHTML;
    }
    function focusEditor() {
        editor.focus();
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-command]'), function (button) {
        button.addEventListener('click', function () {
            focusEditor();
            document.execCommand(button.getAttribute('data-command'), false, null);
            sync();
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-block]'), function (button) {
        button.addEventListener('click', function () {
            focusEditor();
            document.execCommand('formatBlock', false, button.getAttribute('data-block'));
            sync();
        });
    });
    var linkButton = document.querySelector('[data-action="link"]');
    if (linkButton) {
        linkButton.addEventListener('click', function () {
            focusEditor();
            var url = window.prompt('請輸入連結網址', 'https://');
            if (url && url !== 'https://') {
                document.execCommand('createLink', false, url);
                sync();
            }
        });
    }
    var clearButton = document.querySelector('[data-action="clear"]');
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            focusEditor();
            document.execCommand('removeFormat', false, null);
            sync();
        });
    }
    var fontSelect = document.getElementById('ShortIntroFontName');
    if (fontSelect) {
        fontSelect.addEventListener('change', function () {
            if (!fontSelect.value) return;
            focusEditor();
            document.execCommand('fontName', false, fontSelect.value);
            fontSelect.value = '';
            sync();
        });
    }
    editor.addEventListener('input', sync);
    var form = editor.closest('form');
    if (form) {
        form.addEventListener('submit', sync);
    }
    sync();
})();
(function () {
    function previewImage(id, src) {
        var image = document.getElementById(id);
        if (!image) return;
        image.src = src && src.trim() ? src.trim() : '/images/logo_discuss.png';
    }
    Array.prototype.forEach.call(document.querySelectorAll('.image-path-input'), function (input) {
        input.addEventListener('input', function () {
            previewImage(input.getAttribute('data-preview'), input.value);
        });
        input.addEventListener('change', function () {
            previewImage(input.getAttribute('data-preview'), input.value);
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('.image-file-input'), function (input) {
        function findError() {
            var next = input.nextElementSibling;
            if (next && next.classList.contains('image-file-error')) return next;
            var error = document.createElement('div');
            error.className = 'invalid-feedback d-block image-file-error';
            input.insertAdjacentElement('afterend', error);
            return error;
        }
        function clearError() {
            input.classList.remove('is-invalid');
            input.setCustomValidity('');
            var error = findError();
            error.textContent = '';
            error.classList.add('d-none');
        }
        function showError(message) {
            input.classList.add('is-invalid');
            input.setCustomValidity(message);
            var error = findError();
            error.textContent = message;
            error.classList.remove('d-none');
        }
        function formatBytes(bytes) {
            if (bytes >= 1024 * 1024) return Math.round(bytes / 1024 / 1024) + 'MB';
            return Math.round(bytes / 1024) + 'KB';
        }
        function validateSelectedFile() {
            clearError();
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) return;

            var label = input.getAttribute('data-label') || '圖片';
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            var maxBytes = parseInt(input.getAttribute('data-max-bytes') || '0', 10);
            var maxWidth = parseInt(input.getAttribute('data-max-width') || '0', 10);
            var maxHeight = parseInt(input.getAttribute('data-max-height') || '0', 10);
            if (allowedTypes.indexOf(file.type) === -1) {
                showError('只允許上傳 jpg、png、gif、webp 圖片');
                return;
            }
            if (maxBytes > 0 && file.size > maxBytes) {
                showError(label + '檔案大小不可超過 ' + formatBytes(maxBytes));
                return;
            }

            input.setCustomValidity('正在檢查圖片尺寸');
            var url = URL.createObjectURL(file);
            var probe = new Image();
            probe.onload = function () {
                URL.revokeObjectURL(url);
                if ((maxWidth > 0 && probe.naturalWidth > maxWidth) || (maxHeight > 0 && probe.naturalHeight > maxHeight)) {
                    showError(label + '尺寸不可超過 ' + maxWidth + 'x' + maxHeight + '，目前為 ' + probe.naturalWidth + 'x' + probe.naturalHeight);
                    return;
                }
                clearError();
            };
            probe.onerror = function () {
                URL.revokeObjectURL(url);
                showError('無法讀取圖片尺寸，請選擇有效圖片檔');
            };
            probe.src = url;
        }
        input.addEventListener('change', function () {
            var imageId = input.getAttribute('data-preview');
            clearError();
            if (!input.files || !input.files[0] || !imageId) return;
            var url = URL.createObjectURL(input.files[0]);
            var image = document.getElementById(imageId);
            if (!image) {
                URL.revokeObjectURL(url);
                validateSelectedFile();
                return;
            }
            image.onload = function () { URL.revokeObjectURL(url); };
            image.src = url;
            validateSelectedFile();
        });
        input.addEventListener('invalid', function () {
            window.setTimeout(function () {
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                input.focus({ preventScroll: true });
            }, 0);
        });
    });
    var form = document.querySelector('form[enctype="multipart/form-data"]');
    if (form) {
        form.addEventListener('submit', function (event) {
            var invalidFile = form.querySelector('.image-file-input:invalid');
            if (!invalidFile) return;
            event.preventDefault();
            invalidFile.scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalidFile.focus({ preventScroll: true });
        });
    }
})();
</script>
<?php admin_layout($id > 0 ? '編輯書籍' : '新增書籍', ob_get_clean()); ?>
