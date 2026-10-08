(function () {
    const loginForm    = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const banner       = document.getElementById('banner');

    function showBanner(message) {
        banner.textContent = message;
        banner.hidden = false;
    }
    function hideBanner() {
        banner.hidden = true;
    }

    (async function checkExistingSession() {
        try {
            const res = await api.get('me.php');
            if (res.authenticated) window.location.href = 'app.html';
        } catch (e) { /* show login form */ }
    })();

    document.getElementById('show-register').addEventListener('click', () => {
        hideBanner();
        loginForm.hidden = true;
        registerForm.hidden = false;
        document.getElementById('switch-to-register').hidden = true;
        document.getElementById('switch-to-login').hidden = false;
    });

    document.getElementById('show-login').addEventListener('click', () => {
        hideBanner();
        registerForm.hidden = true;
        loginForm.hidden = false;
        document.getElementById('switch-to-login').hidden = true;
        document.getElementById('switch-to-register').hidden = false;
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideBanner();
        const submitBtn = document.getElementById('login-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in…';
        try {
            await api.post('login.php', {
                email: document.getElementById('login-email').value.trim(),
                password: document.getElementById('login-password').value,
            });
            window.location.href = 'app.html';
        } catch (err) {
            showBanner(err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Log in';
        }
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideBanner();
        const submitBtn = document.getElementById('register-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account…';
        try {
            await api.post('register.php', {
                name: document.getElementById('reg-name').value.trim(),
                email: document.getElementById('reg-email').value.trim(),
                password: document.getElementById('reg-password').value,
            });
            window.location.href = 'app.html';
        } catch (err) {
            showBanner(err.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create account';
        }
    });
})();
