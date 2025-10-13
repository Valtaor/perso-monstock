jQuery(document).ready(function ($) {
    const ajaxUrl = inventorySettings.ajaxUrl;
    const uploadsUrl = inventorySettings.uploadsUrl;
    const nonce = inventorySettings.nonce;

    const $tableBody = $('#inventory-table-body');
    const $statsTotalArticles = $('#stat-total-articles');
    const $statsValeurAchat = $('#stat-valeur-achat');
    const $statsValeurVente = $('#stat-valeur-vente');
    const $statsMargeTotale = $('#stat-marge-totale');
    const $searchInput = $('#inventory-search');
    const $toastContainer = $('.inventory-toast-stack');

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
        const marge = (Number(product.prix_vente) - Number(product.prix_achat)).toFixed(2);

        return `
            <tr data-id="${product.id}">
                <td class="cell-image">${imageCell}</td>
                <td class="cell-title">
                    <div class="item-name">${product.nom || ''}</div>
                    <div class="item-reference">${product.reference || ''}</div>
                </td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_achat">${Number(product.prix_achat).toFixed(2)}</td>
                <td class="cell-price inventory-editable" contenteditable="true" data-field="prix_vente">${Number(product.prix_vente).toFixed(2)}</td>
                <td class="cell-stock inventory-editable" contenteditable="true" data-field="stock">${parseInt(product.stock, 10)}</td>
                <td class="cell-marge">${formatCurrency(marge)}</td>
                <td class="cell-actions">
                    <button class="btn-icon delete-product" title="Supprimer">✖</button>
                </td>
            </tr>
        `;
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

    function responseMessage(response, fallback) {
        if (response && response.data && typeof response.data.message !== 'undefined') {
            return response.data.message;
        }
        if (response && typeof response.message !== 'undefined') {
            return response.message;
        }
        return fallback;
    }

    function loadProducts() {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'inventory_get_products', nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                if (!response.data || response.data.length === 0) {
                    showEmptyState();
                    return;
                }

                const rows = response.data.map((product) => buildRow(product)).join('');
                $tableBody.html(rows);
                updateSearchFilter();
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
        formData.append('action', 'inventory_add_product');
        formData.append('nonce', nonce);

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
                $row.remove();
                if ($tableBody.find('tr').length === 0) {
                    showEmptyState();
                }
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
            data: { action: 'inventory_update_product', id, field, value, nonce },
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                showToast(responseMessage(response, 'Valeur mise à jour.'));
                const prixAchat = parseFloat($cell.closest('tr').find('[data-field="prix_achat"]').text().replace(',', '.')) || 0;
                const prixVente = parseFloat($cell.closest('tr').find('[data-field="prix_vente"]').text().replace(',', '.')) || 0;
                const marge = prixVente - prixAchat;
                $cell.closest('tr').find('.cell-marge').text(formatCurrency(marge));
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
