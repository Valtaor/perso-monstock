jQuery(document).ready(function ($) {
    const ajaxUrl = inventorySettings.ajaxUrl;
    const uploadsUrl = inventorySettings.uploadsUrl;
    const nonce = inventorySettings.nonce;

    const state = {
        categories: [],
        tags: [],
        products: [],
        activeCategoryFilter: [],
        activeTagFilter: [],
    };

    const $body = $('body');
    const $tableBody = $('#inventory-table-body');
    const $statsTotalArticles = $('#stat-total-articles');
    const $statsValeurAchat = $('#stat-valeur-achat');
    const $statsValeurVente = $('#stat-valeur-vente');
    const $statsMargeTotale = $('#stat-marge-totale');
    const $searchInput = $('#inventory-search');
    const $toastContainer = $('.inventory-toast-stack');
    const $categoryForm = $('#inventory-category-form');
    const $tagForm = $('#inventory-tag-form');
    const $categoryList = $('#inventory-category-list');
    const $tagList = $('#inventory-tag-list');
    const $productCategories = $('#product-categories');
    const $productTags = $('#product-tags');
    const $filterCategories = $('#filter-categories');
    const $filterTags = $('#filter-tags');
    const $modal = $('#inventory-term-modal');
    const $modalCategories = $('#modal-categories');
    const $modalTags = $('#modal-tags');
    const $modalForm = $('#inventory-term-form');
    const $modalTitle = $('#inventory-term-modal-title');
    const baseModalTitle = $modalTitle.text();

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function sanitizeColor(color) {
        if (typeof color !== 'string') {
            return '#c47b83';
        }
        const trimmed = color.trim();
        if (/^#([0-9a-f]{3}){1,2}$/i.test(trimmed)) {
            return trimmed;
        }
        return '#c47b83';
    }

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
        });
    }

    function renderCategoryBadge(category) {
        const safeName = escapeHtml(category.nom || '');
        const safeColor = sanitizeColor(category.couleur);
        const icon = category.icone ? `<span class="category-emoji">${escapeHtml(category.icone)}</span>` : '';
        return `<span class="category-badge" style="--badge-color: ${safeColor};">${icon}<span>${safeName}</span></span>`;
    }

    function renderTagBadge(tag) {
        return `<span class="tag-badge">#${escapeHtml(tag.nom || '')}</span>`;
    }

    function buildCategoryBadges(product) {
        if (!product.categories || product.categories.length === 0) {
            return '<span class="meta-placeholder">' + escapeHtml('Aucune catégorie') + '</span>';
        }
        return product.categories.map(renderCategoryBadge).join('');
    }

    function buildTagBadges(product) {
        if (!product.tags || product.tags.length === 0) {
            return '<span class="meta-placeholder">' + escapeHtml('Aucun tag') + '</span>';
        }
        return product.tags.map(renderTagBadge).join('');
    }

    function buildRow(product) {
        const imageCell = product.image
            ? `<img src="${uploadsUrl}${product.image}" alt="${escapeHtml(product.nom || '')}" class="inventory-thumb">`
            : '<div class="inventory-thumb placeholder">✨</div>';
        const marge = (Number(product.prix_vente) - Number(product.prix_achat)).toFixed(2);

        return `
            <tr data-id="${product.id}">
                <td class="cell-image">${imageCell}</td>
                <td class="cell-title">
                    <div class="item-name">${escapeHtml(product.nom || '')}</div>
                    <div class="item-reference">${escapeHtml(product.reference || '')}</div>
                    <div class="item-categories">${buildCategoryBadges(product)}</div>
                    <div class="item-tags">${buildTagBadges(product)}</div>
                </td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_achat">${Number(product.prix_achat || 0).toFixed(2)}</td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_vente">${Number(product.prix_vente || 0).toFixed(2)}</td>
                <td class="cell-stock inventory-editable" contenteditable="true" data-field="stock">${parseInt(product.stock, 10)}</td>
                <td class="cell-marge">${formatCurrency(marge)}</td>
                <td class="cell-actions">
                    <button class="btn-icon manage-terms" title="Catégories & tags" aria-label="Catégories et tags">🏷️</button>
                    <button class="btn-icon delete-product" title="Supprimer" aria-label="Supprimer">✖</button>
                </td>
            </tr>
        `;
    }

    function showEmptyState() {
        state.products = [];
        $tableBody.html(`
            <tr class="empty-state">
                <td colspan="7">
                    <div class="empty-wrapper">
                        <span class="empty-icon">💎</span>
                        <p>Aucun bijou dans l'inventaire pour le moment.</p>
                    </div>
                </td>
            </tr>
        `);
    }

    function showToast(message, type = 'success') {
        const toastId = `toast-${Date.now()}`;
        const $toast = $(`
            <div class="inventory-toast ${type}" id="${toastId}">
                <span>${escapeHtml(message)}</span>
            </div>
        `);

        $toastContainer.append($toast);
        requestAnimationFrame(() => {
            $toast.addClass('visible');
        });

        setTimeout(() => {
            $toast.removeClass('visible');
            setTimeout(() => $toast.remove(), 300);
        }, 3000);
    }

    function responseMessage(response, fallback) {
        if (response && response.data && typeof response.data.message !== 'undefined') {
            return response.data.message;
        }
        if (response && typeof response.message !== 'undefined') {
            return response.message;
        }
        return fallback;
    }
    function updateSearchFilter() {
        const query = ($searchInput.val() || '').toLowerCase();
        let visibleRows = 0;

        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state') || $row.hasClass('empty-search')) {
                return;
            }
            const text = $row.text().toLowerCase();
            const matches = text.includes(query);
            $row.toggle(matches);
            if (matches) {
                visibleRows += 1;
            }
        });

        $tableBody.find('tr.empty-search').remove();
        if (visibleRows === 0 && state.products.length > 0) {
            $tableBody.append(`
                <tr class="empty-search">
                    <td colspan="7">
                        <div class="empty-wrapper">
                            <span class="empty-icon">🔍</span>
                            <p>${escapeHtml('Aucun résultat ne correspond à votre recherche.')}</p>
                        </div>
                    </td>
                </tr>
            `);
        }
    }

    function sanitizeSelection(values, items) {
        const available = new Set(items.map((item) => String(item.id)));
        return (values || []).map(String).filter((value) => available.has(value));
    }

    function rebuildCategorySelect($select, selectedValues) {
        const sanitized = sanitizeSelection(selectedValues, state.categories);
        $select.empty();
        state.categories.forEach((category) => {
            const option = $('<option></option>')
                .attr('value', category.id)
                .text(category.nom || '');
            $select.append(option);
        });
        $select.val(sanitized);
        return sanitized;
    }

    function rebuildTagSelect($select, selectedValues) {
        const sanitized = sanitizeSelection(selectedValues, state.tags);
        $select.empty();
        state.tags.forEach((tag) => {
            const option = $('<option></option>')
                .attr('value', tag.id)
                .text(tag.nom || '');
            $select.append(option);
        });
        $select.val(sanitized);
        return sanitized;
    }

    function refreshSelects() {
        const productCategorySelected = rebuildCategorySelect($productCategories, $productCategories.val());
        const productTagSelected = rebuildTagSelect($productTags, $productTags.val());
        const modalCategorySelected = rebuildCategorySelect($modalCategories, $modalCategories.val());
        const modalTagSelected = rebuildTagSelect($modalTags, $modalTags.val());
        state.activeCategoryFilter = rebuildCategorySelect($filterCategories, state.activeCategoryFilter);
        state.activeTagFilter = rebuildTagSelect($filterTags, state.activeTagFilter);

        $productCategories.val(productCategorySelected);
        $productTags.val(productTagSelected);
        $modalCategories.val(modalCategorySelected);
        $modalTags.val(modalTagSelected);
        $filterCategories.val(state.activeCategoryFilter);
        $filterTags.val(state.activeTagFilter);
    }

    function renderTaxonomyLists() {
        $categoryList.empty();
        if (!state.categories.length) {
            $categoryList.append('<li class="taxonomy-empty">' + escapeHtml('Aucune catégorie définie pour le moment.') + '</li>');
        } else {
            state.categories.forEach((category) => {
                const $item = $(`
                    <li data-id="${category.id}">
                        <div class="taxonomy-item">
                            <span class="taxonomy-color" style="--taxonomy-color: ${sanitizeColor(category.couleur)}"></span>
                            <span class="taxonomy-name">${category.icone ? `<span class="category-emoji">${escapeHtml(category.icone)}</span>` : ''}<span>${escapeHtml(category.nom || '')}</span></span>
                        </div>
                        <div class="taxonomy-item-actions">
                            <button type="button" class="btn-inline edit-category">${escapeHtml('Modifier')}</button>
                            <button type="button" class="btn-inline danger delete-category">${escapeHtml('Supprimer')}</button>
                        </div>
                    </li>
                `);
                $item.data('category', category);
                $categoryList.append($item);
            });
        }

        $tagList.empty();
        if (!state.tags.length) {
            $tagList.append('<li class="taxonomy-empty">' + escapeHtml('Aucun tag défini pour le moment.') + '</li>');
        } else {
            state.tags.forEach((tag) => {
                const $item = $(`
                    <li data-id="${tag.id}">
                        <div class="taxonomy-item">
                            <span class="taxonomy-name">#${escapeHtml(tag.nom || '')}</span>
                        </div>
                        <div class="taxonomy-item-actions">
                            <button type="button" class="btn-inline edit-tag">${escapeHtml('Modifier')}</button>
                            <button type="button" class="btn-inline danger delete-tag">${escapeHtml('Supprimer')}</button>
                        </div>
                    </li>
                `);
                $item.data('tag', tag);
                $tagList.append($item);
            });
        }
    }

    function loadTaxonomies() {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_get_taxonomies', nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success && response.data) {
                state.categories = response.data.categories || [];
                state.tags = response.data.tags || [];
                renderTaxonomyLists();
                refreshSelects();
            } else {
                showToast(responseMessage(response, 'Impossible de charger les catégories et tags.'), 'error');
            }
        }).fail(() => {
            showToast('Impossible de charger les catégories et tags.', 'error');
        });
    }

    function renderProducts(products) {
        state.products = products || [];
        if (!state.products.length) {
            showEmptyState();
            return;
        }

        const rows = state.products.map((product) => buildRow(product)).join('');
        $tableBody.html(rows);
        state.products.forEach((product) => {
            const $row = $tableBody.find(`tr[data-id="${product.id}"]`);
            $row.data('product', product);
        });
        updateSearchFilter();
    }

    function loadProducts() {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'inventory_get_products',
                nonce,
                categories: state.activeCategoryFilter,
                tags: state.activeTagFilter,
            },
            dataType: 'json',
            traditional: true,
        }).done((response) => {
            if (response.success) {
                renderProducts(response.data || []);
                return;
            }

            showToast(responseMessage(response, 'Impossible de charger les produits.'), 'error');
        }).fail(() => {
            showToast('Impossible de charger les produits.', 'error');
        });
    }

    function loadStats() {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_get_stats', nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success && response.data) {
                $statsTotalArticles.text(response.data.total_articles);
                $statsValeurAchat.text(formatCurrency(response.data.valeur_achat));
                $statsValeurVente.text(formatCurrency(response.data.valeur_vente));
                $statsMargeTotale.text(formatCurrency(response.data.marge_totale));
            }
        });
    }

    function resetCategoryForm() {
        $('#category-id').val('');
        $('#category-name').val('');
        $('#category-color').val('#c47b83');
        $('#category-icon').val('');
    }

    function resetTagForm() {
        $('#tag-id').val('');
        $('#tag-name').val('');
    }

    function openTermModal(product) {
        const categoryIds = (product.categories || []).map((item) => String(item.id));
        const tagIds = (product.tags || []).map((item) => String(item.id));
        $('#term-product-id').val(product.id);
        $modalTitle.text(`${baseModalTitle} – ${product.nom || ''}`);
        refreshSelects();
        $modalCategories.val(categoryIds);
        $modalTags.val(tagIds);
        $modal.attr('aria-hidden', 'false').addClass('is-open');
        $body.addClass('inventory-modal-open');
    }

    function closeTermModal() {
        $modal.attr('aria-hidden', 'true').removeClass('is-open');
        $body.removeClass('inventory-modal-open');
        $('#term-product-id').val('');
        $modalCategories.val([]);
        $modalTags.val([]);
        $modalTitle.text(baseModalTitle);
    }
    $('#inventory-form').on('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'inventory_add_product');
        formData.append('nonce', nonce);
        formData.append('categories', JSON.stringify($productCategories.val() || []));
        formData.append('tags', JSON.stringify($productTags.val() || []));

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                this.reset();
                $('#image-preview').attr('src', '').addClass('is-empty');
                showToast(responseMessage(response, 'Produit ajouté.'));
                loadProducts();
                loadStats();
            } else {
                showToast(responseMessage(response, 'Erreur lors de l\'ajout.'), 'error');
            }
        }).fail(() => {
            showToast('Erreur lors de l\'ajout du produit.', 'error');
        });
    });

    $('#product-image').on('change', function () {
        const file = this.files[0];
        if (!file) {
            $('#image-preview').attr('src', '').addClass('is-empty');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            $('#image-preview').attr('src', e.target.result).removeClass('is-empty');
        };
        reader.readAsDataURL(file);
    });

    $(document).on('click', '.delete-product', function () {
        const $row = $(this).closest('tr');
        const id = $row.data('id');

        if (!confirm('Supprimer cet article ?')) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_delete_product', id, nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Article supprimé.'));
                loadProducts();
                loadStats();
            } else {
                showToast(responseMessage(response, 'Suppression impossible.'), 'error');
            }
        }).fail(() => {
            showToast('Suppression impossible.', 'error');
        });
    });

    $(document).on('focus', '.inventory-editable', function () {
        $(this).data('original', $(this).text().trim());
    });

    $(document).on('keypress', '.inventory-editable', function (event) {
        if (event.which === 13) {
            event.preventDefault();
            $(this).blur();
        }
    });

    $(document).on('blur', '.inventory-editable', function () {
        const $cell = $(this);
        const $row = $cell.closest('tr');
        const id = $row.data('id');
        const field = $cell.data('field');
        let value = $cell.text().trim().replace(',', '.');
        const original = $cell.data('original');

        if (field === 'stock') {
            value = parseInt(value, 10);
            if (Number.isNaN(value) || value < 0) {
                $cell.text(original);
                return;
            }
        } else {
            value = parseFloat(value);
            if (Number.isNaN(value) || value < 0) {
                $cell.text(original);
                return;
            }
            value = value.toFixed(2);
            $cell.text(value);
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_update_product', id, field, value, nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Valeur mise à jour.'));
                const prixAchat = parseFloat($row.find('[data-field="prix_achat"]').text().replace(',', '.')) || 0;
                const prixVente = parseFloat($row.find('[data-field="prix_vente"]').text().replace(',', '.')) || 0;
                const marge = prixVente - prixAchat;
                $row.find('.cell-marge').text(formatCurrency(marge));
                loadStats();
            } else {
                $cell.text(original);
                showToast(responseMessage(response, 'Mise à jour impossible.'), 'error');
            }
        }).fail(() => {
            $cell.text(original);
            showToast('Mise à jour impossible.', 'error');
        });
    });

    $searchInput.on('input', function () {
        updateSearchFilter();
    });

    $('#export-csv').on('click', function () {
        const rows = [];
        rows.push(['Nom', 'Référence', 'Catégories', 'Tags', 'Prix achat', 'Prix vente', 'Stock', 'Marge']);

        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state') || $row.hasClass('empty-search') || !$row.is(':visible')) {
                return;
            }

            const nom = $row.find('.item-name').text().trim();
            const reference = $row.find('.item-reference').text().trim();
            const categoriesText = $row.find('.item-categories').text().replace(/\s+/g, ' ').trim();
            const tagsText = $row.find('.item-tags').text().replace(/\s+/g, ' ').trim();
            const prixAchat = $row.find('[data-field="prix_achat"]').text().trim();
            const prixVente = $row.find('[data-field="prix_vente"]').text().trim();
            const stock = $row.find('[data-field="stock"]').text().trim();
            const marge = $row.find('.cell-marge').text().trim();

            rows.push([nom, reference, categoriesText, tagsText, prixAchat, prixVente, stock, marge]);
        });

        const csvContent = rows
            .map((cols) => cols.map((value) => `"${value.replace(/"/g, '""')}"`).join(';'))
            .join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'inventaire-bijoux.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        showToast('Export CSV généré.');
    });

    $filterCategories.on('change', function () {
        state.activeCategoryFilter = $(this).val() || [];
        loadProducts();
    });

    $filterTags.on('change', function () {
        state.activeTagFilter = $(this).val() || [];
        loadProducts();
    });

    $('#reset-filters').on('click', function () {
        state.activeCategoryFilter = [];
        state.activeTagFilter = [];
        $filterCategories.val([]);
        $filterTags.val([]);
        loadProducts();
    });

    $('#reset-category-form').on('click', resetCategoryForm);
    $('#reset-tag-form').on('click', resetTagForm);

    $categoryForm.on('submit', function (event) {
        event.preventDefault();
        const id = $('#category-id').val();
        const nom = $('#category-name').val();
        const couleur = $('#category-color').val();
        const icone = $('#category-icon').val();

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'inventory_save_category',
                nonce,
                id,
                nom,
                couleur,
                icone,
            },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Catégorie enregistrée.'));
                resetCategoryForm();
                loadTaxonomies();
                loadProducts();
            } else {
                showToast(responseMessage(response, 'Impossible d\'enregistrer la catégorie.'), 'error');
            }
        }).fail(() => {
            showToast('Impossible d\'enregistrer la catégorie.', 'error');
        });
    });

    $tagForm.on('submit', function (event) {
        event.preventDefault();
        const id = $('#tag-id').val();
        const nom = $('#tag-name').val();

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'inventory_save_tag',
                nonce,
                id,
                nom,
            },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Tag enregistré.'));
                resetTagForm();
                loadTaxonomies();
                loadProducts();
            } else {
                showToast(responseMessage(response, 'Impossible d\'enregistrer le tag.'), 'error');
            }
        }).fail(() => {
            showToast('Impossible d\'enregistrer le tag.', 'error');
        });
    });

    $(document).on('click', '.edit-category', function () {
        const category = $(this).closest('li').data('category');
        if (!category) {
            return;
        }
        $('#category-id').val(category.id);
        $('#category-name').val(category.nom);
        $('#category-color').val(sanitizeColor(category.couleur));
        $('#category-icon').val(category.icone || '');
    });

    $(document).on('click', '.delete-category', function () {
        const category = $(this).closest('li').data('category');
        if (!category) {
            return;
        }
        if (!confirm(`Supprimer la catégorie "${category.nom}" ?`)) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_delete_category', nonce, id: category.id },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Catégorie supprimée.'));
                resetCategoryForm();
                loadTaxonomies();
                loadProducts();
            } else {
                showToast(responseMessage(response, 'Suppression impossible.'), 'error');
            }
        }).fail(() => {
            showToast('Suppression impossible.', 'error');
        });
    });

    $(document).on('click', '.edit-tag', function () {
        const tag = $(this).closest('li').data('tag');
        if (!tag) {
            return;
        }
        $('#tag-id').val(tag.id);
        $('#tag-name').val(tag.nom);
    });

    $(document).on('click', '.delete-tag', function () {
        const tag = $(this).closest('li').data('tag');
        if (!tag) {
            return;
        }
        if (!confirm(`Supprimer le tag "${tag.nom}" ?`)) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_delete_tag', nonce, id: tag.id },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Tag supprimé.'));
                resetTagForm();
                loadTaxonomies();
                loadProducts();
            } else {
                showToast(responseMessage(response, 'Suppression impossible.'), 'error');
            }
        }).fail(() => {
            showToast('Suppression impossible.', 'error');
        });
    });

    $(document).on('click', '.manage-terms', function () {
        const product = $(this).closest('tr').data('product');
        if (!product) {
            return;
        }
        openTermModal(product);
    });
    $('#close-term-modal, #cancel-term-modal').on('click', function (event) {
        event.preventDefault();
        closeTermModal();
    });

    $modal.on('click', function (event) {
        if ($(event.target).hasClass('inventory-modal-overlay')) {
            closeTermModal();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $modal.hasClass('is-open')) {
            closeTermModal();
        }
    });

    $modalForm.on('submit', function (event) {
        event.preventDefault();
        const productId = Number($('#term-product-id').val());
        const categories = $modalCategories.val() || [];
        const tags = $modalTags.val() || [];

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'inventory_assign_terms',
                nonce,
                id: productId,
                categories,
                tags,
            },
            dataType: 'json',
            traditional: true,
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Associations mises à jour.'));
                closeTermModal();
                loadProducts();
            } else {
                showToast(responseMessage(response, 'Impossible de mettre à jour les associations.'), 'error');
            }
        }).fail(() => {
            showToast('Impossible de mettre à jour les associations.', 'error');
        });
    });

    loadTaxonomies();
    loadProducts();
    loadStats();
});
