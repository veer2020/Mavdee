/**
 * assets/js/auth.js
 * Login/registration form validation, password strength indicator,
 * CSRF-aware fetch submission, and session-check helpers.
 */
(function () {
    'use strict';

    // ── CSRF token helper ─────────────────────────────────────────────────────
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    // ── Shared fetch helper ───────────────────────────────────────────────────
    async function authFetch(url, data) {
        const token = getCsrfToken();
        const body = Object.assign({}, data, { csrf_token: token });

        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        });

        let json;
        try {
            json = await resp.json();
        } catch {
            json = { success: false, error: 'Unexpected server response.' };
        }
        json._status = resp.status;
        return json;
    }

    // ── Login form handler ────────────────────────────────────────────────────
    // FIX #6: initLoginForm() was completely empty — login button did nothing.
    // Added full submit handler mirroring the register flow.
    function initLoginForm() {
        const form = document.querySelector('form[data-auth="login"], #loginForm, .login-form');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!window.validateLoginForm(form)) return;

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Signing in\u2026'; }

            const email    = form.querySelector('input[name="email"], input[type="email"]').value.trim();
            const password = form.querySelector('input[name="password"], input[type="password"]').value;

            try {
                const data = await authFetch('/api/auth/login.php', { email, password });

                if (data.success) {
                    if (typeof showToast === 'function') showToast('Login successful! Redirecting\u2026', 'success');
                    window.location.href = data.redirectTo || '/dashboard.php';
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Login failed. Please try again.', 'error');
                    } else {
                        alert(data.error || 'Login failed.');
                    }
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                }
            } catch (err) {
                if (typeof showToast === 'function') {
                    showToast('Network error. Please try again.', 'error');
                }
                if (btn) { btn.disabled = false; btn.textContent = originalText; }
            }
        });
    }

    // ── Register form handler ─────────────────────────────────────────────────
    function initRegisterForm() {
        const form = document.querySelector('form[data-auth="register"], #registerForm, .register-form');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!window.validateRegisterForm(form)) return;

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Creating account\u2026'; }

            const name     = form.querySelector('input[name="name"]').value.trim();
            const email    = form.querySelector('input[name="email"]').value.trim();
            const password = form.querySelector('input[name="password"]').value;

            try {
                const data = await authFetch('/api/auth/register.php', { name, email, password });

                if (data.success) {
                    if (typeof showToast === 'function') showToast('Account created! Redirecting\u2026', 'success');
                    window.location.href = data.redirectTo || '/dashboard.php';
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Registration failed. Please try again.', 'error');
                    } else {
                        alert(data.error || 'Registration failed.');
                    }
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                }
            } catch (err) {
                if (typeof showToast === 'function') {
                    showToast('Network error. Please try again.', 'error');
                }
                if (btn) { btn.disabled = false; btn.textContent = originalText; }
            }
        });
    }

    // ── Forgot-password form handler ──────────────────────────────────────────
    function initForgotPasswordForm() {
        const form = document.querySelector('form[data-auth="forgot"], #forgotForm');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Sending\u2026'; }

            const emailInput = form.querySelector('input[name="email"]');
            const email = emailInput ? emailInput.value.trim() : '';

            const token = getCsrfToken();
            const body = new URLSearchParams({ email, csrf_token: token });

            try {
                const resp = await fetch('/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                });
                const data = await resp.json();

                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast('If that email is registered, a reset link has been sent.', 'success');
                    }
                    form.reset();
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.error || 'Something went wrong. Please try again.', 'error');
                    }
                }
            } catch {
                if (typeof showToast === 'function') {
                    showToast('Network error. Please try again.', 'error');
                }
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = originalText; }
            }
        });
    }

    // ── Password strength indicator ───────────────────────────────────────────
    function passwordStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        if (score <= 1) return 'weak';
        if (score === 2) return 'fair';
        if (score === 3) return 'strong';
        return 'very-strong';
    }

    window.attachPasswordStrength = function (input) {
        if (!input) return;

        let indicator = input.parentElement.querySelector('.pwd-strength');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'pwd-strength';
            indicator.style.cssText = 'margin-top:4px;height:4px;border-radius:2px;transition:background 0.3s,width 0.3s;width:0';
            input.parentElement.appendChild(indicator);
        }

        let label = input.parentElement.querySelector('.pwd-strength-label');
        if (!label) {
            label = document.createElement('div');
            label.className = 'pwd-strength-label';
            label.style.cssText = 'font-size:0.75rem;margin-top:4px;color:#888;';
            input.parentElement.appendChild(label);
        }

        input.addEventListener('input', function () {
            const val = input.value;
            if (!val) { indicator.style.width = '0'; label.textContent = ''; return; }
            const strength = passwordStrength(val);
            const map = {
                'weak':        { width: '25%',  color: '#e74c3c', text: 'Weak' },
                'fair':        { width: '50%',  color: '#e67e22', text: 'Fair' },
                'strong':      { width: '75%',  color: '#2ecc71', text: 'Strong' },
                'very-strong': { width: '100%', color: '#27ae60', text: 'Very Strong' },
            };
            const info = map[strength];
            indicator.style.width = info.width;
            indicator.style.background = info.color;
            label.textContent = info.text;
            label.style.color = info.color;
        });
    };

    // ── Form validation helpers ───────────────────────────────────────────────
    window.validateLoginForm = function (form) {
        const email    = form.querySelector('input[type="email"], input[name="email"]');
        const password = form.querySelector('input[type="password"], input[name="password"]');

        if (email && !email.value.trim()) {
            email.focus();
            if (typeof showToast === 'function') showToast('Please enter your email address.', 'error');
            return false;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            email.focus();
            if (typeof showToast === 'function') showToast('Please enter a valid email address.', 'error');
            return false;
        }
        if (password && !password.value) {
            password.focus();
            if (typeof showToast === 'function') showToast('Please enter your password.', 'error');
            return false;
        }
        return true;
    };

    window.validateRegisterForm = function (form) {
        const name    = form.querySelector('input[name="name"]');
        const email   = form.querySelector('input[type="email"], input[name="email"]');
        const password = form.querySelector('input[name="password"]');
        const confirm = form.querySelector('input[name="confirm_password"], input[name="password_confirm"]');

        if (name && !name.value.trim()) {
            name.focus();
            if (typeof showToast === 'function') showToast('Please enter your name.', 'error');
            return false;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            email.focus();
            if (typeof showToast === 'function') showToast('Please enter a valid email address.', 'error');
            return false;
        }
        if (password && password.value.length < 8) {
            password.focus();
            if (typeof showToast === 'function') showToast('Password must be at least 8 characters.', 'error');
            return false;
        }
        if (confirm && password && confirm.value !== password.value) {
            confirm.focus();
            if (typeof showToast === 'function') showToast('Passwords do not match.', 'error');
            return false;
        }
        return true;
    };

    window.initPasswordVisibilityToggles = function () {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            if (button.dataset.passwordToggleReady === '1') return;
            const target = document.querySelector(button.getAttribute('data-password-toggle'));
            if (!target) return;

            button.dataset.passwordToggleReady = '1';
            button.setAttribute('aria-pressed', 'false');

            button.addEventListener('click', function () {
                const showing = target.type === 'text';
                target.type = showing ? 'password' : 'text';
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                button.textContent = showing ? 'Show' : 'Hide';
            });
        });
    };

    // ── Auto-init on DOM ready ────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        window.initPasswordVisibilityToggles();
        initLoginForm();
        initRegisterForm();
        initForgotPasswordForm();

        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            const form = input.form;
            const hasConfirm = !!(form && form.querySelector('input[name="confirm_password"], input[name="password_confirm"]'));
            const isNew = input.name === 'new_password' || input.autocomplete === 'new-password' || input.dataset.passwordStrength === '1';
            if (isNew || (input.name === 'password' && hasConfirm)) {
                window.attachPasswordStrength(input);
            }
        });
    });

})();