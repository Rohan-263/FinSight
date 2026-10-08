const Transactions = (function () {
    let currentPage = 1;
    const perPage = 20;

    function buildQuery() {
        const params = new URLSearchParams({ page: currentPage, per_page: perPage });
        const pillar = document.getElementById('filter-pillar').value;
        const from = document.getElementById('filter-from').value;
        const to = document.getElementById('filter-to').value;
        if (pillar) params.set('pillar', pillar);
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        return params.toString();
    }

    function renderRows(transactions) {
        const tbody = document.getElementById('tx-tbody');
        tbody.innerHTML = transactions.map((t) => `
            <tr data-id="${t.id}">
                <td>${formatDateLong(t.txn_date)}</td>
                <td>${escapeHtml(t.payee || t.note || '—')}</td>
                <td><span class="pillar-chip ${t.pillar}">${t.pillar[0]}${t.pillar.slice(1).toLowerCase()}</span></td>
                <td>${escapeHtml(t.subcategory_label || '—')}</td>
                <td class="num tabular">${formatINR(t.amount)}</td>
                <td>
                    <div class="row-actions">
                        <button data-action="edit">Edit</button>
                        <button data-action="delete" class="danger">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('[data-action="edit"]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const id = e.target.closest('tr').dataset.id;
                const tx = transactions.find((t) => String(t.id) === id);
                openModal(tx);
            });
        });
        tbody.querySelectorAll('[data-action="delete"]').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                if (!confirm('Delete this transaction? This cannot be undone.')) return;
                const id = e.target.closest('tr').dataset.id;
                try {
                    await api.del(`transactions.php?id=${id}`);
                    load();
                } catch (err) {
                    alert(err.message);
                }
            });
        });
    }

    function renderPagination(pagination) {
        const el = document.getElementById('tx-pagination');
        if (pagination.total_pages <= 1) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        el.innerHTML = `
            <button class="btn btn-outline btn-small" id="prev-page" ${pagination.page <= 1 ? 'disabled' : ''}>Previous</button>
            <span>Page ${pagination.page} of ${pagination.total_pages}</span>
            <button class="btn btn-outline btn-small" id="next-page" ${pagination.page >= pagination.total_pages ? 'disabled' : ''}>Next</button>
        `;
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        if (prevBtn) prevBtn.addEventListener('click', () => { currentPage--; load(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { currentPage++; load(); });
    }

    async function load() {
        try {
            const res = await api.get(`transactions.php?${buildQuery()}`);
            const table = document.querySelector('#view-transactions table.ledger');
            const empty = document.getElementById('tx-empty');
            if (res.transactions.length === 0) {
                table.hidden = true;
                empty.hidden = false;
                document.getElementById('tx-pagination').hidden = true;
                return;
            }
            table.hidden = false;
            empty.hidden = true;
            renderRows(res.transactions);
            renderPagination(res.pagination);
        } catch (e) {
            alert(e.message);
        }
    }

    function showModalBanner(message) {
        const el = document.getElementById('tx-modal-banner');
        el.textContent = message;
        el.hidden = false;
    }
    function hideModalBanner() {
        document.getElementById('tx-modal-banner').hidden = true;
    }

    async function openModal(tx) {
        hideModalBanner();
        const backdrop = document.getElementById('tx-modal-backdrop');
        const title = document.getElementById('tx-modal-title');
        const idField = document.getElementById('tx-modal-id');
        const amountField = document.getElementById('tx-modal-amount');
        const pillarField = document.getElementById('tx-modal-pillar');
        const subcatField = document.getElementById('tx-modal-subcategory');
        const dateField = document.getElementById('tx-modal-date');
        const payeeField = document.getElementById('tx-modal-payee');
        const noteField = document.getElementById('tx-modal-note');

        title.textContent = tx ? 'Edit transaction' : 'Log a transaction';
        idField.value = tx ? tx.id : '';
        amountField.value = tx ? tx.amount : '';
        pillarField.value = tx ? tx.pillar : 'NEEDS';
        dateField.value = tx ? tx.txn_date : todayISO();
        payeeField.value = tx ? (tx.payee || '') : '';
        noteField.value = tx ? (tx.note || '') : '';

        await loadSubcategoriesInto(subcatField, pillarField.value);
        subcatField.value = tx && tx.subcategory_id ? tx.subcategory_id : '';
        pillarField.onchange = async () => { await loadSubcategoriesInto(subcatField, pillarField.value); };

        backdrop.hidden = false;
    }

    function closeModal() {
        document.getElementById('tx-modal-backdrop').hidden = true;
    }

    async function saveModal() {
        hideModalBanner();
        const id = document.getElementById('tx-modal-id').value;
        const payload = {
            amount: parseFloat(document.getElementById('tx-modal-amount').value),
            pillar: document.getElementById('tx-modal-pillar').value,
            subcategory_id: document.getElementById('tx-modal-subcategory').value || null,
            txn_date: document.getElementById('tx-modal-date').value,
            payee: document.getElementById('tx-modal-payee').value.trim(),
            note: document.getElementById('tx-modal-note').value.trim(),
        };
        const saveBtn = document.getElementById('tx-modal-save');
        saveBtn.disabled = true;
        try {
            if (id) {
                await api.put(`transactions.php?id=${id}`, payload);
            } else {
                await api.post('transactions.php', payload);
            }
            closeModal();
            load();
        } catch (err) {
            showModalBanner(err.message);
        } finally {
            saveBtn.disabled = false;
        }
    }

    function initControls() {
        document.getElementById('open-add-modal').addEventListener('click', () => openModal(null));
        document.getElementById('tx-modal-cancel').addEventListener('click', closeModal);
        document.getElementById('tx-modal-save').addEventListener('click', saveModal);
        document.getElementById('tx-modal-backdrop').addEventListener('click', (e) => {
            if (e.target.id === 'tx-modal-backdrop') closeModal();
        });
        document.getElementById('apply-filters').addEventListener('click', () => { currentPage = 1; load(); });
        document.getElementById('clear-filters').addEventListener('click', () => {
            document.getElementById('filter-pillar').value = '';
            document.getElementById('filter-from').value = '';
            document.getElementById('filter-to').value = '';
            currentPage = 1;
            load();
        });
    }

    initControls();
    return { load };
})();

window.Transactions = Transactions;
