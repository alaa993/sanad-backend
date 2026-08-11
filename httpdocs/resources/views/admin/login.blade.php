<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دخول الإدارة — سند</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sanad.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body">
    <div class="admin-login-wrap">
        <div class="admin-login-card">
            <a href="{{ route('home') }}" class="brand" style="margin-bottom:1rem;justify-content:center">
                <img src="{{ asset('images/sanad-logo.png') }}" alt="سند" class="brand-logo" style="height:56px">
            </a>
            <p style="text-align:center;margin:-0.5rem 0 1rem;color:#6F7A92;font-weight:700">لوحة الإدارة</p>
            <h1>تسجيل الدخول</h1>
            <p>لوحة تحكم المشرفين لإدارة المستخدمين والأخصائيين والمؤسسات</p>
            <div id="login-error" class="alert alert-error admin-hidden"></div>
            <form id="admin-login-form">
                <div class="form-group">
                    <label for="username">البريد أو اسم المستخدم</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">دخول</button>
            </form>
            <p style="text-align:center;margin-top:1rem;font-size:.9rem">
                <a href="{{ route('home') }}">← العودة للموقع</a>
            </p>
        </div>
    </div>
    <script>
        const TOKEN_KEY = 'sanad_admin_token';

        document.getElementById('admin-login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('login-error');
            err.classList.add('admin-hidden');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ username, password, device_name: 'admin-web' }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'بيانات الدخول غير صحيحة');
                }
                if (!data.user || data.user.role !== 'admin') {
                    throw new Error('هذا الحساب ليس حساب إدارة');
                }
                localStorage.setItem(TOKEN_KEY, data.token);
                window.location.href = '{{ route('admin.dashboard') }}';
            } catch (ex) {
                err.textContent = ex.message || 'تعذّر تسجيل الدخول';
                err.classList.remove('admin-hidden');
            }
        });

        if (localStorage.getItem(TOKEN_KEY)) {
            fetch('/api/auth/me', {
                headers: {
                    Authorization: 'Bearer ' + localStorage.getItem(TOKEN_KEY),
                    Accept: 'application/json',
                },
            }).then(r => r.ok ? window.location.href = '{{ route('admin.dashboard') }}' : localStorage.removeItem(TOKEN_KEY));
        }
    </script>
</body>
</html>
