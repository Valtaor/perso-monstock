jQuery(document).ready(function ($) {
    const settings = window.inventorySettings || {};
    const ajaxUrl = settings.ajaxUrl || '';
    const uploadsUrl = settings.uploadsUrl || '';
    const i18n = settings.i18n || {};

    const $form = $('#inventory-form');
    const $tableBody = $('#inventory-table-body');
    const $statsTotalArticles = $('#stat-total-articles');
    const $statsValeurAchat = $('#stat-valeur-achat');
    const $statsValeurVente = $('#stat-valeur-vente');
    const $statsMargeTotale = $('#stat-marge-totale');
    const $searchInput = $('#inventory-search');
    const $statLowStock = $('#stat-low-stock');
    const $statOutOfStock = $('#stat-out-of-stock');
    const $statAverageMargin = $('#stat-average-margin');
    const $statIncomplete = $('#stat-incomplete');
    const $toastContainer = $('.inventory-toast-stack');
    const $followUpSection = $('.inventory-follow-up');
    const $incompleteToggle = $('#product-incomplete');

    function t(key, fallback) {
        if (Object.prototype.hasOwnProperty.call(i18n, key)) {
            return i18n[key];
        }
        return fallback || key;
    }

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
        });
    }

    function escapeHtml(value) {
        return (value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(message, type = 'success') {
        if (!message) {
            return;
        }
        const toastId = `toast-${Date.now()}`;
        const $toast = $(`
            <div class="inventory-toast ${type}" id="${toastId}">
                <span>${message}</span>
            </div>
        `);

        $toastContainer.append($toast);
        requestAnimationFrame(() => {
            $toast.addClass('visible');
        });

        setTimeout(() => {
            $toast.removeClass('visible');
            setTimeout(() => $toast.remove(), 300);
        }, 3200);
    }

    function buildRow(product) {
        const imageCell = product.image
            ? `<img src="${uploadsUrl}${product.image}" alt="${escapeHtml(product.nom)}" class="inventory-thumb">`
            : '<div class="inventory-thumb placeholder">✨</div>';
        const isIncomplete = Number(product.a_completer) === 1;
        const statusBadge = isIncomplete
            ? '<span class="status-badge badge-incomplete">À compléter</span>'
            : '<span class="status-badge badge-complete">Complet</span>';
        const toggleTitle = isIncomplete ? t('toggleComplete', 'Marquer comme complet') : t('toggleIncomplete', 'Marquer comme à compléter');
        const toggleIcon = isIncomplete ? '☑️' : '⏳';
        const prixAchat = Number(product.prix_achat || 0);
        const prixVente = Number(product.prix_vente || 0);
        const marge = prixVente - prixAchat;
        const reference = escapeHtml(product.reference);
        const emplacement = escapeHtml(product.emplacement);
        const notes = escapeHtml(product.notes);
        const dateAchat = product.date_achat ? escapeHtml(product.date_achat) : '';

        return `
            <tr data-id="${product.id}" data-incomplete="${isIncomplete ? '1' : '0'}">
                <td class="cell-image">${imageCell}</td>
                <td class="cell-title">
                    <div class="item-name">${escapeHtml(product.nom)}</div>
                    <div class="item-meta">
                        ${notes ? `<span class="item-note">${notes}</span>` : ''}
                        ${dateAchat ? `<span class="item-date">${dateAchat}</span>` : ''}
                    </div>
                </td>
                <td class="cell-reference">${reference || '—'}</td>
                <td class="cell-location">${emplacement || '—'}</td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_achat">${prixAchat.toFixed(2)}</td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_vente">${prixVente.toFixed(2)}</td>
                <td class="cell-stock inventory-editable" contenteditable="true" data-field="stock">${parseInt(product.stock, 10) || 0}</td>
                <td class="cell-marge">${formatCurrency(marge)}</td>
                <td class="cell-status">${statusBadge}</td>
                <td class="cell-actions">
                    <button class="btn-icon toggle-incomplete" data-incomplete="${isIncomplete ? '1' : '0'}" title="${toggleTitle}">${toggleIcon}</button>
                    <button class="btn-icon delete-product" title="Supprimer">✖</button>
                </td>
            </tr>
        `;
    }

    function showEmptyState() {
        $tableBody.html(`
            <tr class="empty-state">
                <td colspan="10">
                    <div class="empty-wrapper">
                        <span class="empty-icon">💎</span>
                        <p>${t('emptySearch', "Aucun bijou dans l'inventaire pour le moment.")}</p>
                    </div>
                </td>
            </tr>
        `);
        updateDerivedStats([]);
    }

    function updateDerivedStats(products) {
        if (!Array.isArray(products) || products.length === 0) {
            $statLowStock.text(0);
            $statOutOfStock.text(0);
            $statAverageMargin.text(formatCurrency(0));
            $statIncomplete.text(0);
            return;
        }

        let lowStock = 0;
        let outOfStock = 0;
        let totalMargin = 0;
        let marginCount = 0;
        let incompleteCount = 0;

        products.forEach((product) => {
            const stockValue = parseInt(product.stock, 10) || 0;
            if (stockValue <= 0) {
                outOfStock += 1;
            } else if (stockValue <= 3) {
                lowStock += 1;
            }

            const prixAchat = parseFloat(product.prix_achat) || 0;
            const prixVente = parseFloat(product.prix_vente) || 0;
            totalMargin += prixVente - prixAchat;
            marginCount += 1;

            if (Number(product.a_completer) === 1) {
                incompleteCount += 1;
            }
        });

        $statLowStock.text(lowStock);
        $statOutOfStock.text(outOfStock);
        $statAverageMargin.text(formatCurrency(marginCount ? totalMargin / marginCount : 0));
        $statIncomplete.text(incompleteCount);
    }

    function updateSearchFilter() {
        const query = ($searchInput.val() || '').toLowerCase();
        let visibleRows = 0;
        const $existingSearchEmpty = $tableBody.find('tr.search-empty');
        $existingSearchEmpty.remove();
        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state')) {
                return;
            }
            const text = $row.text().toLowerCase();
            const isVisible = text.includes(query);
            $row.toggle(isVisible);
            if (isVisible) {
                visibleRows += 1;
            }
        });

        if (visibleRows === 0) {
            $tableBody.append(`
                <tr class="empty-state search-empty">
                    <td colspan="10">
                        <div class="empty-wrapper">
                            <span class="empty-icon">🔍</span>
                            <p>${t('emptySearch', "Aucun bijou ne correspond à votre recherche.")}</p>
                        </div>
                    </td>
                </tr>
            `);
        }
    }

    function refreshTable(products) {
        if (!products || products.length === 0) {
            showEmptyState();
            return;
        }
        const rows = products.map((product) => buildRow(product)).join('');
        $tableBody.html(rows);
        updateDerivedStats(products);
        updateSearchFilter();
    }

    function handleAjaxError(jqXHR) {
        let message = t('toastUpdateError', 'Une erreur est survenue.');
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
            message = jqXHR.responseJSON.message;
        }
        showToast(message, 'error');
    }

    function loadProducts() {
        $.ajax({
            url: ajaxUrl,
            method: 'GET',
            data: { action: 'get_products' },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                refreshTable(response.data || []);
            } else {
                showEmptyState();
            }
        }).fail(handleAjaxError);
    }

    function loadStats() {
        $.ajax({
            url: ajaxUrl,
            method: 'GET',
            data: { action: 'get_stats' },
            dataType: 'json',
        }).done((response) => {
            if (response.success && response.data) {
                $statsTotalArticles.text(response.data.total_articles);
                $statsValeurAchat.text(formatCurrency(response.data.valeur_achat));
                $statsValeurVente.text(formatCurrency(response.data.valeur_vente));
                $statsMargeTotale.text(formatCurrency(response.data.marge_totale));
            }
        }).fail(handleAjaxError);
    }

    if ($incompleteToggle.length) {
        $incompleteToggle.on('change', function () {
            $followUpSection.toggleClass('is-active', this.checked);
        });
    }

    $('#product-image').on('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
            $('#image-preview').attr('src', '').addClass('is-empty');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            $('#image-preview').attr('src', event.target.result).removeClass('is-empty');
        };
        reader.readAsDataURL(file);
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_product');
        formData.set('a_completer', $incompleteToggle.is(':checked') ? '1' : '0');

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
                $followUpSection.removeClass('is-active');
                showToast(response.message || t('toastAddSuccess', 'Produit ajouté.'));
                loadProducts();
                loadStats();
            } else {
                showToast(response.message || t('toastAddError', "Erreur lors de l'ajout."), 'error');
            }
        }).fail(() => {
            showToast(t('toastAddError', "Erreur lors de l'ajout."), 'error');
        });
    });

    $(document).on('click', '.delete-product', function () {
        const $row = $(this).closest('tr');
        const id = $row.data('id');

        if (!window.confirm(t('deleteConfirm', 'Supprimer cet article ?'))) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'delete_product', id },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(response.message || t('toastDeleteSuccess', 'Article supprimé.'));
                $row.remove();
                if ($tableBody.find('tr').length === 0) {
                    showEmptyState();
                }
                loadStats();
                loadProducts();
            } else {
                showToast(response.message || t('toastDeleteError', 'Suppression impossible.'), 'error');
            }
        }).fail(() => {
            showToast(t('toastDeleteError', 'Suppression impossible.'), 'error');
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
        const id = $cell.closest('tr').data('id');
        const field = $cell.data('field');
        let value = $cell.text().trim().replace(',', '.');
        const original = $cell.data('original');

        if (!id) {
            return;
        }

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
            data: { action: 'update_product', id, field, value },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(response.message || t('toastUpdateSuccess', 'Valeur mise à jour.'));
                const prixAchat = parseFloat($cell.closest('tr').find('[data-field="prix_achat"]').text().replace(',', '.')) || 0;
                const prixVente = parseFloat($cell.closest('tr').find('[data-field="prix_vente"]').text().replace(',', '.')) || 0;
                const marge = prixVente - prixAchat;
                $cell.closest('tr').find('.cell-marge').text(formatCurrency(marge));
                loadStats();
                loadProducts();
            } else {
                $cell.text(original);
                showToast(response.message || t('toastUpdateError', 'Mise à jour impossible.'), 'error');
            }
        }).fail(() => {
            $cell.text(original);
            showToast(t('toastUpdateError', 'Mise à jour impossible.'), 'error');
        });
    });

    $(document).on('click', '.toggle-incomplete', function () {
        const $button = $(this);
        const $row = $button.closest('tr');
        const id = $row.data('id');
        const isCurrentlyIncomplete = $button.data('incomplete') === 1 || $button.data('incomplete') === '1';
        const nextValue = isCurrentlyIncomplete ? 0 : 1;

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'update_product', id, field: 'a_completer', value: nextValue },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                $button.data('incomplete', nextValue);
                $row.attr('data-incomplete', nextValue ? '1' : '0');
                const statusCell = $row.find('.cell-status');
                if (nextValue) {
                    statusCell.html('<span class="status-badge badge-incomplete">À compléter</span>');
                    $button.text('☑️').attr('title', t('toggleComplete', 'Marquer comme complet'));
                } else {
                    statusCell.html('<span class="status-badge badge-complete">Complet</span>');
                    $button.text('⏳').attr('title', t('toggleIncomplete', 'Marquer comme à compléter'));
                }
                showToast(response.message || t('toastUpdateSuccess', 'Valeur mise à jour.'));
                loadProducts();
            } else {
                showToast(response.message || t('toastUpdateError', 'Mise à jour impossible.'), 'error');
            }
        }).fail(() => {
            showToast(t('toastUpdateError', 'Mise à jour impossible.'), 'error');
        });
    });

    $searchInput.on('keyup', updateSearchFilter);

    $('#export-csv').on('click', function () {
        const rows = [];
        rows.push(['Nom', 'Référence', 'Casier', 'Prix achat', 'Prix vente', 'Stock', 'Marge', 'Statut']);

        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state') || !$row.is(':visible')) {
                return;
            }

            const nom = $row.find('.item-name').text().trim();
            const reference = $row.find('.cell-reference').text().trim();
            const casier = $row.find('.cell-location').text().trim();
            const prixAchat = $row.find('[data-field="prix_achat"]').text().trim();
            const prixVente = $row.find('[data-field="prix_vente"]').text().trim();
            const stock = $row.find('[data-field="stock"]').text().trim();
            const marge = $row.find('.cell-marge').text().trim();
            const statut = $row.find('.cell-status').text().trim();

            rows.push([nom, reference, casier, prixAchat, prixVente, stock, marge, statut]);
        });

        if (rows.length === 1) {
            showToast(t('emptyInventory', 'Votre inventaire est vide.'), 'error');
            return;
        }

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

    loadProducts();
    loadStats();
});
