jQuery(document).ready(function ($) {
    const ajaxUrl = inventorySettings.ajaxUrl;
    const uploadsUrl = inventorySettings.uploadsUrl;

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

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
        });
    }

    function buildRow(product) {
        const imageCell = product.image
            ? `<img src="${uploadsUrl}${product.image}" alt="${product.nom}" class="inventory-thumb">`
            : '<div class="inventory-thumb placeholder">✨</div>';
        const isIncomplete = Number(product.a_completer) === 1;
        const marge = (Number(product.prix_vente) - Number(product.prix_achat)).toFixed(2);
        const statusBadge = isIncomplete
            ? '<span class="status-badge badge-incomplete">À compléter</span>'
            : '';
        const toggleTitle = isIncomplete ? 'Marquer comme complet' : 'Marquer comme à compléter';
        const toggleIcon = isIncomplete ? '☑️' : '⏳';

        return `
            <tr data-id="${product.id}" data-incomplete="${isIncomplete ? '1' : '0'}">
                <td class="cell-image">${imageCell}</td>
                <td class="cell-title">
                    <div class="item-name">${product.nom || ''}</div>
                    <div class="item-reference">${product.reference || ''} ${statusBadge}</div>
                </td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_achat">${Number(product.prix_achat).toFixed(2)}</td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_vente">${Number(product.prix_vente).toFixed(2)}</td>
                <td class="cell-stock inventory-editable" contenteditable="true" data-field="stock">${parseInt(product.stock, 10)}</td>
                <td class="cell-marge">${formatCurrency(marge)}</td>
                <td class="cell-actions">
                    <button class="btn-icon toggle-incomplete" data-incomplete="${isIncomplete ? '1' : '0'}" title="${toggleTitle}">${toggleIcon}</button>
                    <button class="btn-icon delete-product" title="Supprimer">✖</button>
                </td>
            </tr>
        `;
    }

    function updateDerivedStats(products) {
        if (!Array.isArray(products) || products.length === 0) {
            $statLowStock.text(0);
            $statOutOfStock.text(0);
            $statAverageMargin.text(formatCurrency(0));
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
            const marge = prixVente - prixAchat;
            totalMargin += marge;
            marginCount += 1;

            if (Number(product.a_completer) === 1) {
                incompleteCount += 1;
            }
        });

        $statLowStock.text(lowStock);
        $statOutOfStock.text(outOfStock);
        const averageMargin = marginCount ? totalMargin / marginCount : 0;
        $statAverageMargin.text(formatCurrency(averageMargin));
        $statIncomplete.text(incompleteCount);
    }

    function showEmptyState() {
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
        updateDerivedStats([]);
        $statIncomplete.text(0);
    }

    function showToast(message, type = 'success') {
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
        }, 3000);
    }

    function loadProducts() {
        $.ajax({
            url: ajaxUrl,
            method: 'GET',
            data: { action: 'get_products' },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                if (!response.data || response.data.length === 0) {
                    showEmptyState();
                    return;
                }

                const rows = response.data.map((product) => buildRow(product)).join('');
                $tableBody.html(rows);
                updateDerivedStats(response.data);
                updateSearchFilter();
            }
        }).fail(() => {
            showToast('Impossible de charger les produits.', 'error');
        });
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
        });
    }

    function updateSearchFilter() {
        const query = $searchInput.val()?.toLowerCase() || '';
        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state')) {
                return;
            }
            const text = $row.text().toLowerCase();
            $row.toggle(text.includes(query));
        });
    }

    $('#inventory-form').on('submit', function (event) {
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
                showToast(response.message || 'Produit ajouté.');
                loadProducts();
                loadStats();
            } else {
                showToast(response.message || 'Erreur lors de l\'ajout.', 'error');
            }
        }).fail(() => {
            showToast('Erreur lors de l\'ajout du produit.', 'error');
        });
    });

    $incompleteToggle.on('change', function () {
        $followUpSection.toggleClass('is-active', this.checked);
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
            data: { action: 'delete_product', id },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(response.message || 'Article supprimé.');
                $row.remove();
                if ($tableBody.find('tr').length === 0) {
                    showEmptyState();
                }
                loadStats();
                loadProducts();
            } else {
                showToast(response.message || 'Suppression impossible.', 'error');
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
        const id = $cell.closest('tr').data('id');
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
            data: { action: 'update_product', id, field, value },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast('Valeur mise à jour.');
                const prixAchat = parseFloat($cell.closest('tr').find('[data-field="prix_achat"]').text().replace(',', '.')) || 0;
                const prixVente = parseFloat($cell.closest('tr').find('[data-field="prix_vente"]').text().replace(',', '.')) || 0;
                const marge = prixVente - prixAchat;
                $cell.closest('tr').find('.cell-marge').text(formatCurrency(marge));
                loadStats();
                loadProducts();
            } else {
                $cell.text(original);
                showToast(response.message || 'Mise à jour impossible.', 'error');
            }
        }).fail(() => {
            $cell.text(original);
            showToast('Mise à jour impossible.', 'error');
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
                const referenceEl = $row.find('.item-reference');
                if (nextValue) {
                    if (!referenceEl.find('.status-badge').length) {
                        referenceEl.append(' <span class="status-badge badge-incomplete">À compléter</span>');
                    }
                    $button.text('☑️').attr('title', 'Marquer comme complet');
                } else {
                    referenceEl.find('.status-badge').remove();
                    $button.text('⏳').attr('title', 'Marquer comme à compléter');
                }
                showToast('Statut mis à jour.');
                loadProducts();
            } else {
                showToast(response.message || 'Mise à jour impossible.', 'error');
            }
        }).fail(() => {
            showToast('Mise à jour impossible.', 'error');
        });
    });

    $searchInput.on('keyup', updateSearchFilter);

    $('#export-csv').on('click', function () {
        const rows = [];
        rows.push(['Nom', 'Référence', 'Prix achat', 'Prix vente', 'Stock', 'Marge']);

        $tableBody.find('tr').each(function () {
            const $row = $(this);
            if ($row.hasClass('empty-state') || !$row.is(':visible')) {
                return;
            }

            const nom = $row.find('.item-name').text().trim();
            const reference = $row.find('.item-reference').text().trim();
            const prixAchat = $row.find('[data-field="prix_achat"]').text().trim();
            const prixVente = $row.find('[data-field="prix_vente"]').text().trim();
            const stock = $row.find('[data-field="stock"]').text().trim();
            const marge = $row.find('.cell-marge').text().trim();

            rows.push([nom, reference, prixAchat, prixVente, stock, marge]);
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

    loadProducts();
    loadStats();
});
