/** App shell: auth guard, tab switching, logout, shared subcategory cache. */

const AppState = { user: null, subcategoriesByPillar: {} };

function switchView(viewName) {
    document.querySelectorAll('.view').forEach((el) => {
        el.hidden = el.id !== `view-${viewName}`;
    });
    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.view === viewName);
    });
    if (viewName === 'dashboard' && window.Dashboard) window.Dashboard.load();
    if (viewName === 'transactions' && window.Transactions) window.Transactions.load();
    if (viewName === 'budget' && window.Budget) window.Budget.load();
}

async function loadSubcategoriesInto(selectEl, pillar) {
    if (!AppState.subcategoriesByPillar[pillar]) {
        try {
            const res = await api.get(`subcategories.php?pillar=${encodeURIComponent(pillar)}`);
            AppState.subcategoriesByPillar[pillar] = res.subcategories;
        } catch (e) {
            AppState.subcategoriesByPillar[pillar] = [];
        }
    }
    const list = AppState.subcategoriesByPillar[pillar];
    selectEl.innerHTML = '<option value="">Uncategorised</option>' +
        list.map((s) => `<option value="${s.id}">${escapeHtml(s.label)}</option>`).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

(function init() {
    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });
    document.querySelectorAll('[data-view-link]').forEach((btn) => {
        btn.addEventListener('click', () => switchView(btn.dataset.viewLink));
    });

    document.getElementById('logout-btn').addEventListener('click', async () => {
        try { await api.post('logout.php'); } catch (e) { /* proceed regardless */ }
        window.location.href = 'index.html';
    });

    (async function guardAndBoot() {
        try {
            const res = await api.get('me.php');
            if (!res.authenticated) {
                window.location.href = 'index.html';
                return;
            }
            AppState.user = res.user;
            document.getElementById('user-name').textContent = res.user.name;
            switchView('dashboard');
        } catch (e) {
            window.location.href = 'index.html';
        }
    })();
})();
