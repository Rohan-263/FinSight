const Dashboard = (function () {
    let chartInstance = null;
    const STATUS_LABEL = { green: 'On track', amber: 'Getting close', red: 'Over budget' };

    function showBanner(message) {
        const el = document.getElementById('dashboard-banner');
        el.textContent = message;
        el.hidden = false;
    }
    function hideBanner() {
        document.getElementById('dashboard-banner').hidden = true;
    }

    function renderPillars(pillars) {
        const grid = document.getElementById('pillars-grid');
        const labelMap = { NEEDS: 'Needs', WANTS: 'Wants', SAVINGS: 'Savings' };
        grid.innerHTML = pillars.map((p) => {
            const overClass = p.remaining < 0 ? 'is-over' : '';
            const remainingText = p.remaining < 0
                ? `${formatINR(Math.abs(p.remaining))} over`
                : `${formatINR(p.remaining)} left`;
            const widthPct = Math.min(100, p.percent_used);
            return `
                <div class="pillar" data-pillar="${p.pillar}">
                    <div class="pillar-name"><span class="pillar-dot"></span>${labelMap[p.pillar]}</div>
                    <div class="pillar-amounts">
                        <span class="pillar-spent tabular">${formatINR(p.spent)}</span>
                        <span class="pillar-alloc tabular">/ ${formatINR(p.allocated)}</span>
                    </div>
                    <div class="pillar-remaining ${overClass} tabular">${remainingText}</div>
                    <div class="bar-track"><div class="bar-fill status-${p.status}" style="width:${widthPct}%"></div></div>
                    <div class="pillar-status-label status-${p.status}">${STATUS_LABEL[p.status]}</div>
                </div>
            `;
        }).join('');
    }

    function renderChart(pillars) {
        const ctx = document.getElementById('pillar-chart');
        const labels = pillars.map((p) => ({ NEEDS: 'Needs', WANTS: 'Wants', SAVINGS: 'Savings' }[p.pillar]));
        const allocated = pillars.map((p) => p.allocated);
        const spent = pillars.map((p) => p.spent);
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Allocated', data: allocated, backgroundColor: '#D9CFAE' },
                    { label: 'Spent', data: spent, backgroundColor: '#2B5D50' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'IBM Plex Sans' } } } },
                scales: { y: { ticks: { callback: (v) => formatINR(v) } } },
            },
        });
    }

    function renderStreak(streak) {
        document.getElementById('streak-current').textContent = streak.current_streak;
        document.getElementById('streak-longest').textContent = streak.longest_streak;
        document.getElementById('streak-nudge').hidden = streak.last_logged_date === todayISO();
    }

    async function renderRecentEntries() {
        try {
            const res = await api.get('transactions.php?page=1&per_page=6');
            const tbody = document.getElementById('recent-tbody');
            const table = document.getElementById('recent-table');
            const empty = document.getElementById('recent-empty');
            if (res.transactions.length === 0) {
                table.hidden = true;
                empty.hidden = false;
                return;
            }
            table.hidden = false;
            empty.hidden = true;
            tbody.innerHTML = res.transactions.map((t) => `
                <tr>
                    <td>${formatDateLong(t.txn_date)}</td>
                    <td>${escapeHtml(t.subcategory_label || t.payee || t.note || '—')}</td>
                    <td><span class="pillar-chip ${t.pillar}">${t.pillar[0]}${t.pillar.slice(1).toLowerCase()}</span></td>
                    <td class="num tabular">${formatINR(t.amount)}</td>
                </tr>
            `).join('');
        } catch (e) { /* non-fatal */ }
    }

    async function load() {
        hideBanner();
        const monthPicker = document.getElementById('month-picker');
        if (!monthPicker.value) monthPicker.value = todayISO().slice(0, 7);

        try {
            const res = await api.get(`dashboard.php?month=${monthPicker.value}`);
            document.getElementById('no-budget-notice').hidden = res.has_budget;
            document.getElementById('dashboard-content').hidden = !res.has_budget;
            if (!res.has_budget) return;

            const monthDate = new Date(res.month + '-01T00:00:00');
            document.getElementById('dashboard-month-label').textContent =
                monthDate.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });

            renderPillars(res.pillars);
            renderStreak(res.streak);
            try {
                renderChart(res.pillars);
            } catch (chartErr) {
                document.querySelector('.chart-wrap').innerHTML = '<p class="hint">Chart could not be loaded.</p>';
            }
            await renderRecentEntries();
        } catch (e) {
            showBanner(e.message);
        }
    }

    async function initQuickAddForm() {
        const pillarSelect = document.getElementById('qa-pillar');
        const subcatSelect = document.getElementById('qa-subcategory');
        const dateInput = document.getElementById('qa-date');
        dateInput.value = todayISO();

        await loadSubcategoriesInto(subcatSelect, pillarSelect.value);
        pillarSelect.addEventListener('change', () => loadSubcategoriesInto(subcatSelect, pillarSelect.value));

        document.getElementById('quick-add-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            hideBanner();
            const submitBtn = document.getElementById('qa-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';
            try {
                await api.post('transactions.php', {
                    amount: parseFloat(document.getElementById('qa-amount').value),
                    pillar: pillarSelect.value,
                    subcategory_id: subcatSelect.value || null,
                    txn_date: dateInput.value,
                    payee: document.getElementById('qa-payee').value.trim(),
                    note: document.getElementById('qa-note').value.trim(),
                });
                document.getElementById('quick-add-form').reset();
                dateInput.value = todayISO();
                await load();
            } catch (err) {
                showBanner(err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save transaction';
            }
        });
    }

    document.getElementById('month-picker').addEventListener('change', load);
    initQuickAddForm();

    return { load };
})();

window.Dashboard = Dashboard;
