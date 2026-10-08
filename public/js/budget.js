const Budget = (function () {
    function showBanner(message, isSuccess) {
        const el = document.getElementById('budget-banner');
        el.textContent = message;
        el.className = 'banner' + (isSuccess ? ' success' : '');
        el.hidden = false;
    }
    function hideBanner() {
        document.getElementById('budget-banner').hidden = true;
    }

    function updatePctNote() {
        const needs = parseInt(document.getElementById('budget-needs').value, 10) || 0;
        const wants = parseInt(document.getElementById('budget-wants').value, 10) || 0;
        const savings = parseInt(document.getElementById('budget-savings').value, 10) || 0;
        const total = needs + wants + savings;
        const note = document.getElementById('pct-total-note');
        note.textContent = `Total: ${total}%` + (total !== 100 ? ' — must equal 100%' : '');
        note.className = 'pct-total-note ' + (total === 100 ? 'is-valid' : 'is-invalid');
        return total === 100;
    }

    async function load() {
        hideBanner();
        try {
            const res = await api.get('budget.php');
            if (res.budget) {
                document.getElementById('budget-period').value = res.budget.period_type;
                document.getElementById('budget-total').value = res.budget.total_amount;
                document.getElementById('budget-needs').value = res.budget.needs_pct;
                document.getElementById('budget-wants').value = res.budget.wants_pct;
                document.getElementById('budget-savings').value = res.budget.savings_pct;
            }
            updatePctNote();
        } catch (e) {
            showBanner(e.message);
        }
    }

    function initForm() {
        ['budget-needs', 'budget-wants', 'budget-savings'].forEach((id) => {
            document.getElementById(id).addEventListener('input', updatePctNote);
        });

        document.getElementById('budget-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            hideBanner();
            if (!updatePctNote()) {
                showBanner('Needs, Wants and Savings percentages must add up to 100.');
                return;
            }
            const submitBtn = document.getElementById('budget-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';
            try {
                await api.post('budget.php', {
                    period_type: document.getElementById('budget-period').value,
                    total_amount: parseFloat(document.getElementById('budget-total').value),
                    needs_pct: parseInt(document.getElementById('budget-needs').value, 10),
                    wants_pct: parseInt(document.getElementById('budget-wants').value, 10),
                    savings_pct: parseInt(document.getElementById('budget-savings').value, 10),
                });
                showBanner('Budget saved.', true);
            } catch (err) {
                showBanner(err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save budget';
            }
        });
    }

    initForm();
    return { load };
})();

window.Budget = Budget;
