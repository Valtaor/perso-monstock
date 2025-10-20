jQuery(function ($) {
    const settings = window.inventorySettings || {};
    const ajaxUrl = settings.ajaxUrl || window.ajaxurl || '';
    const uploadsUrl = settings.uploadsUrl || '';
    const i18n = settings.i18n || {};

    const $body = $('body');
    const $form = $('#inventory-form');
    const $tableBody = $('#inventory-table-body');
    const $emptyState = $('#empty-state');
    const $searchInput = $('#inventory-search');
    const $filterCasier = $('#filterCasier');
    const $filterStatus = $('#filterStatus');
    const $quickFilters = $('.quick-filter-btn');
    const $toggleFilters = $('#toggleFilters');
    const $exportCsv = $('#export-csv');
    const $toastContainer = $('.inventory-toast-stack');
    const $themeToggle = $('#themeToggle');
    const $photoInput = $('#product-image');
    const $photoPreview = $('#image-preview');
    const $photoPreviewContainer = $('#photoPreviewContainer');
    const $cancelEdit = $('#cancel-edit');
    const $submitButton = $('#submit-button');
    const $formSection = $('.form-section');
    const $statsTotalArticles = $('#stat-total-articles');
    const $statsOutOfStock = $('#stat-out-of-stock');
    const $statsValeurVente = $('#stat-valeur-vente');
    const $statsValeurAchat = $('#stat-valeur-achat');
    const $statsMargeTotale = $('#stat-marge-totale');
    const $statLowStock = $('#stat-low-stock');
    const $statAverageMargin = $('#stat-average-margin');
    const $statIncomplete = $('#stat-incomplete');
    const $mobileAddItem = $('#mobileAddItem');
    const $mobileScrollInventory = $('#mobileScrollInventory');
    const $mobileScrollStats = $('#mobileScrollStats');
    const $statsCard = $('#statsCard');
    const $inventoryCard = $('#inventoryCard');

    let products = [];
    let currentFilters = {
        search: '',
        casier: 'all',
        status: 'all',
    };

    function syncQuickFilters(value) {
        $quickFilters.attr('aria-pressed', 'false').removeClass('active');
        $quickFilters.filter(`[data-status-filter="${value}"]`).attr('aria-pressed', 'true').addClass('active');
    }

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

    function safeNumber(value) {
        const number = parseFloat(value);
        return Number.isFinite(number) ? number : 0;
    }

    function safeInteger(value) {
        const number = parseInt(value, 10);
        return Number.isFinite(number) ? number : 0;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
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
        }, 3600);
    }

    function loadTheme() {
        const stored = window.localStorage.getItem('inventoryTheme');
        if (stored === 'dark') {
            $body.addClass('inventory-dark');
        }
    }

    function toggleTheme() {
        $body.toggleClass('inventory-dark');
        window.localStorage.setItem('inventoryTheme', $body.hasClass('inventory-dark') ? 'dark' : 'light');
    }

    function renderPreview(file) {
        if (!file) {
            $photoPreview.attr('src', '').addClass('is-empty');
            $photoPreviewContainer.removeClass('visible');
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            $photoPreview.attr('src', event.target.result);
            $photoPreview.removeClass('is-empty');
            $photoPreviewContainer.addClass('visible');
        };
        reader.readAsDataURL(file);
    }

    function buildStatus(product) {
        const stock = safeInteger(product.stock);
        const isIncomplete = Number(product.a_completer) === 1;
        if (stock <= 0) {
            return { className: 'rupture', label: t('statusOutOfStock', 'Rupture') };
        }
        if (isIncomplete) {
            return { className: 'incomplet', label: t('statusIncomplete', 'À compléter') };
        }
        return { className: 'en-stock', label: t('statusInStock', 'En stock') };
    }

    function truncate(text, maxLength) {
        const value = text || '';
        if (value.length <= maxLength) {
            return value;
        }
        return `${value.slice(0, maxLength - 1)}…`;
    }

    function buildRow(product) {
        const stock = safeInteger(product.stock);
        const prixAchat = safeNumber(product.prix_achat);
        const prixVente = safeNumber(product.prix_vente);
        const marge = prixVente - prixAchat;
        const status = buildStatus(product);
        const isIncomplete = Number(product.a_completer) === 1;
        const safeName = escapeHtml(product.nom || '');
        const imageCell = product.image
            ? `<img src="${uploadsUrl}${product.image}" alt="${safeName}" class="inventory-thumb">`
            : '<div class="inventory-thumb placeholder" aria-hidden="true">💎</div>';

        const notes = escapeHtml(product.notes);
        const description = escapeHtml(product.description);
        const reference = escapeHtml(product.reference);
        const emplacement = escapeHtml(product.emplacement);
        const dateAchat = escapeHtml(product.date_achat);

        const truncatedNotes = escapeHtml(truncate(product.notes || '', 32));
        const truncatedDescription = escapeHtml(truncate(product.description || '', 32));

        const suiviBadges = [];
        if (isIncomplete) {
            suiviBadges.push('<span class="badge-later">⏳ ' + t('statusIncomplete', 'À compléter') + '</span>');
        }
        if (notes) {
            suiviBadges.push(`<span class="tag-chip" title="${notes}">📝 ${truncatedNotes}</span>`);
        }
        if (description) {
            suiviBadges.push(`<span class="tag-chip" title="${description}">📌 ${truncatedDescription}</span>`);
        }

        return `
            <tr
                data-id="${product.id}"
                data-casier="${emplacement}"
                data-status="${status.className}"
                data-incomplete="${isIncomplete ? '1' : '0'}"
            >
                <td data-label="${t('columnPhoto', 'Photo')}">${imageCell}</td>
                <td data-label="${t('columnTitle', 'Titre')}">
                    <div class="item-title">${safeName || '—'}</div>
                    <div class="item-meta">
                        ${reference ? `<span>Réf. ${reference}</span>` : ''}
                        ${emplacement ? `<span>${t('columnCasier', 'Casier')} ${emplacement}</span>` : ''}
                    </div>
                </td>
                <td data-label="${t('columnInfos', 'Infos')}">
                    <div class="item-tags">
                        <span class="tag-chip editable" contenteditable="true" data-field="prix_achat" data-type="float">${t('labelPurchase', 'Achat')} : ${prixAchat.toFixed(2)}</span>
                        <span class="tag-chip editable" contenteditable="true" data-field="prix_vente" data-type="float">${t('labelSale', 'Vente')} : ${prixVente.toFixed(2)}</span>
                        ${dateAchat ? `<span class="tag-chip">${t('labelDate', 'Acheté le')} ${dateAchat}</span>` : ''}
                    </div>
                </td>
                <td data-label="${t('columnQuantity', 'Quantité')}">
                    <span class="quantity-badge editable" contenteditable="true" data-field="stock" data-type="int">${stock}</span>
                </td>
                <td data-label="${t('columnFollowUp', 'Suivi')}">
                    <div class="item-tags">
                        ${suiviBadges.join('') || '<span class="tag-chip">—</span>'}
                    </div>
                </td>
                <td data-label="${t('columnStatus', 'Statut')}">
                    <span class="status ${status.className}">${status.label}</span>
                    <div class="item-meta">${formatCurrency(marge)}</div>
                </td>
                <td data-label="${t('columnActions', 'Actions')}">
                    <div class="actions-cell">
                        <button type="button" class="toggle-incomplete" title="${isIncomplete ? t('toggleComplete', 'Marquer comme complet') : t('toggleIncomplete', 'Marquer comme à compléter')}">
                            ${isIncomplete ? '☑️' : '⏳'}
                        </button>
                        <button type="button" class="delete-product" title="${t('deleteConfirm', 'Supprimer')}" aria-label="${t('deleteConfirm', 'Supprimer')}">
                            ✖
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    function refreshTable() {
        let filtered = products.slice();
        const query = currentFilters.search.trim().toLowerCase();

        if (query) {
            filtered = filtered.filter((product) => {
                const text = [
                    product.nom,
                    product.reference,
                    product.emplacement,
                    product.notes,
                    product.description,
                    product.date_achat,
                ]
                    .join(' ')
                    .toLowerCase();
                return text.includes(query);
            });
        }

        const selectedCasier = (currentFilters.casier || '').toLowerCase();
        if (selectedCasier && selectedCasier !== 'all') {
            filtered = filtered.filter((product) => (product.emplacement || '').toLowerCase() === selectedCasier);
        }

        if (currentFilters.status !== 'all') {
            filtered = filtered.filter((product) => {
                const status = buildStatus(product);
                if (currentFilters.status === 'incomplet') {
                    return Number(product.a_completer) === 1;
                }
                return status.className === currentFilters.status;
            });
        }

        $tableBody.empty();

        if (filtered.length === 0) {
            $emptyState.addClass('visible');
            return;
        }

        const rows = filtered.map(buildRow).join('');
        $tableBody.html(rows);
        $emptyState.removeClass('visible');
        bindRowEvents();
    }

    function updateCasierFilterOptions() {
        const uniqueCasiers = new Set();
        products.forEach((product) => {
            if (product.emplacement) {
                uniqueCasiers.add(product.emplacement);
            }
        });

        const current = $filterCasier.val();
        $filterCasier.empty();
        $('<option>').val('all').text(t('filterAllCasiers', 'Tous les casiers')).appendTo($filterCasier);
        Array.from(uniqueCasiers)
            .sort((a, b) => a.localeCompare(b))
            .forEach((casier) => {
                $('<option>').val(casier).text(casier).appendTo($filterCasier);
            });
        if (current && current !== 'all') {
            $filterCasier.val(current);
        }
    }

    function updateStatusFilterOptions() {
        const current = $filterStatus.val();
        $filterStatus.empty();
        $filterStatus.append(`<option value="all">${t('filterAllStatus', 'Tous les statuts')}</option>`);
        $filterStatus.append(`<option value="en-stock">${t('statusInStock', 'En stock')}</option>`);
        $filterStatus.append(`<option value="rupture">${t('statusOutOfStock', 'Rupture')}</option>`);
        $filterStatus.append(`<option value="incomplet">${t('statusIncomplete', 'À compléter')}</option>`);
        if (current) {
            $filterStatus.val(current);
        }
    }

    function updateStats() {
        let totalStock = 0;
        let totalValeurAchat = 0;
        let totalValeurVente = 0;
        let lowStock = 0;
        let outOfStock = 0;
        let incomplete = 0;
        let totalMargin = 0;
        let marginCount = 0;

        products.forEach((product) => {
            const stock = safeInteger(product.stock);
            const prixAchat = safeNumber(product.prix_achat);
            const prixVente = safeNumber(product.prix_vente);
            totalStock += stock;
            totalValeurAchat += prixAchat * stock;
            totalValeurVente += prixVente * stock;
            if (stock <= 0) {
                outOfStock += 1;
            } else if (stock <= 3) {
                lowStock += 1;
            }
            if (Number(product.a_completer) === 1) {
                incomplete += 1;
            }
            totalMargin += prixVente - prixAchat;
            marginCount += 1;
        });

        $statsTotalArticles.text(totalStock);
        $statsOutOfStock.text(outOfStock);
        $statsValeurVente.text(formatCurrency(totalValeurVente));
        $statsValeurAchat.text(formatCurrency(totalValeurAchat));
        $statsMargeTotale.text(formatCurrency(totalValeurVente - totalValeurAchat));
        $statLowStock.text(lowStock);
        $statIncomplete.text(incomplete);
        $statAverageMargin.text(formatCurrency(marginCount ? totalMargin / marginCount : 0));
    }

    function bindRowEvents() {
        $tableBody.find('.toggle-incomplete').off('click').on('click', function () {
            const $row = $(this).closest('tr');
            const productId = safeInteger($row.data('id'));
            const isIncomplete = Number($row.data('incomplete')) === 1;
            const newValue = isIncomplete ? 0 : 1;
            updateProductField(productId, 'a_completer', newValue, () => {
                const product = products.find((item) => Number(item.id) === productId);
                if (product) {
                    product.a_completer = newValue;
                    refreshTable();
                    updateStats();
                    showToast(newValue ? t('markedIncomplete', 'Objet marqué à compléter.') : t('markedComplete', 'Objet marqué complet.'));
                }
            });
        });

        $tableBody.find('.delete-product').off('click').on('click', function () {
            const $row = $(this).closest('tr');
            const productId = safeInteger($row.data('id'));
            if (!window.confirm(t('deleteConfirm', 'Supprimer cet article ?'))) {
                return;
            }
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_product',
                    id: productId,
                },
            })
                .done((response) => {
                    if (response && response.success) {
                        products = products.filter((item) => Number(item.id) !== productId);
                        refreshTable();
                        updateStats();
                        showToast(t('toastDeleteSuccess', 'Produit supprimé.'), 'success');
                    } else {
                        showToast((response && response.message) || t('toastDeleteError', 'Suppression impossible.'), 'error');
                    }
                })
                .fail(() => {
                    showToast(t('toastDeleteError', 'Suppression impossible.'), 'error');
                });
        });

        $tableBody.find('.editable').off('focus').on('focus', function () {
            const $this = $(this);
            $this.data('original', $this.text().trim());
        });

        $tableBody.find('.editable').off('blur').on('blur', function () {
            const $this = $(this);
            const original = $this.data('original');
            let value = $this.text().trim().replace(/[^0-9,\.\-]/g, '').replace(',', '.');
            if (value === original) {
                return;
            }
            const field = $this.data('field');
            const type = $this.data('type');
            if (!field || !type) {
                return;
            }
            if (type === 'float') {
                value = parseFloat(value);
                if (!Number.isFinite(value)) {
                    $this.text(original);
                    return;
                }
            } else {
                value = parseInt(value, 10);
                if (!Number.isFinite(value)) {
                    $this.text(original);
                    return;
                }
            }
            const $row = $this.closest('tr');
            const productId = safeInteger($row.data('id'));
            updateProductField(productId, field, value, (updatedValue) => {
                const product = products.find((item) => Number(item.id) === productId);
                if (product) {
                    product[field] = updatedValue;
                    refreshTable();
                    updateStats();
                    showToast(t('toastUpdateSuccess', 'Valeur mise à jour.'), 'success');
                }
            }, () => {
                $this.text(original);
            });
        });
    }

    function updateProductField(productId, field, value, onSuccess, onError) {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'update_product',
                id: productId,
                field,
                value,
            },
        })
            .done((response) => {
                if (response && response.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(value);
                    }
                } else {
                    if (typeof onError === 'function') {
                        onError();
                    }
                    showToast((response && response.message) || t('toastUpdateError', 'Mise à jour impossible.'), 'error');
                }
            })
            .fail(() => {
                if (typeof onError === 'function') {
                    onError();
                }
                showToast(t('toastUpdateError', 'Mise à jour impossible.'), 'error');
            });
    }

    function handleFormSubmit(event) {
        event.preventDefault();
        const formData = new FormData($form[0]);
        formData.append('action', 'add_product');

        $submitButton.prop('disabled', true).addClass('is-loading');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        })
            .done((response) => {
                if (response && response.success) {
                    $form[0].reset();
                    renderPreview(null);
                    showToast(t('toastAddSuccess', 'Produit ajouté avec succès.'), 'success');
                    fetchProducts();
                } else {
                    showToast((response && response.message) || t('toastAddError', "Erreur lors de l'ajout du produit."), 'error');
                }
            })
            .fail(() => {
                showToast(t('toastAddError', "Erreur lors de l'ajout du produit."), 'error');
            })
            .always(() => {
                $submitButton.prop('disabled', false).removeClass('is-loading');
            });
    }

    function fetchProducts() {
        $.ajax({
            url: ajaxUrl,
            method: 'GET',
            dataType: 'json',
            data: { action: 'get_products' },
        })
            .done((response) => {
                if (response && response.success && Array.isArray(response.data)) {
                    products = response.data;
                } else if (Array.isArray(response)) {
                    products = response;
                } else {
                    products = [];
                }
                updateCasierFilterOptions();
                updateStatusFilterOptions();
                refreshTable();
                updateStats();
            })
            .fail(() => {
                products = [];
                refreshTable();
                updateStats();
                showToast(t('loadError', 'Impossible de charger les produits.'), 'error');
            });
    }

    function exportCsv() {
        if (!products.length) {
            showToast(t('emptyInventory', 'Votre inventaire est vide.'), 'warning');
            return;
        }
        const headers = ['ID', 'Nom', 'Référence', 'Casier', 'Prix achat', 'Prix vente', 'Stock', 'A compléter', 'Notes', 'Description', 'Date achat'];
        const rows = products.map((product) => [
            product.id,
            product.nom || '',
            product.reference || '',
            product.emplacement || '',
            safeNumber(product.prix_achat).toFixed(2),
            safeNumber(product.prix_vente).toFixed(2),
            safeInteger(product.stock),
            Number(product.a_completer) === 1 ? '1' : '0',
            product.notes ? product.notes.replace(/"/g, '""') : '',
            product.description ? product.description.replace(/"/g, '""') : '',
            product.date_achat || '',
        ]);
        const csv = [headers, ...rows]
            .map((row) => row.map((value) => `"${value}"`).join(';'))
            .join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'inventaire.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    loadTheme();
    syncQuickFilters(currentFilters.status);
    fetchProducts();

    $form.on('submit', handleFormSubmit);
    $photoInput.on('change', (event) => {
        const [file] = event.target.files;
        renderPreview(file);
    });

    $themeToggle.on('click', toggleTheme);

    $searchInput.on('input', () => {
        currentFilters.search = $searchInput.val() || '';
        refreshTable();
    });

    $filterCasier.on('change', () => {
        currentFilters.casier = $filterCasier.val();
        refreshTable();
    });

    $filterStatus.on('change', () => {
        currentFilters.status = $filterStatus.val();
        syncQuickFilters(currentFilters.status);
        refreshTable();
    });

    $quickFilters.on('click', function () {
        const value = $(this).data('status-filter');
        currentFilters.status = value;
        $filterStatus.val(value);
        syncQuickFilters(value);
        refreshTable();
    });

    $toggleFilters.on('click', function () {
        const expanded = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', expanded ? 'false' : 'true');
        $('#filtersPanel').toggleClass('open', !expanded);
    });

    $exportCsv.on('click', exportCsv);

    $mobileAddItem.on('click', () => {
        $formSection.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $mobileScrollInventory.on('click', () => {
        $inventoryCard.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $mobileScrollStats.on('click', () => {
        $statsCard.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $cancelEdit.on('click', () => {
        $form[0].reset();
        $cancelEdit.attr('hidden', true);
        $submitButton.text(t('submitLabel', "Ajouter à l'inventaire"));
        renderPreview(null);
    });
});
