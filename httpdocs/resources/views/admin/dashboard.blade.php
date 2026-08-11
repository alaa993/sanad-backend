<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة الإدارة — سند</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sanad.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a href="{{ route('home') }}" class="brand">
                <img src="{{ asset('images/sanad-logo.png') }}" alt="سند" class="brand-logo" style="height:40px;filter:brightness(0) invert(1)">
            </a>
            <nav class="admin-nav" id="admin-nav">
                <button type="button" data-panel="overview" class="active">نظرة عامة</button>
                <button type="button" data-panel="users">المستخدمون</button>
                <button type="button" data-panel="specialists">الأخصائيون</button>
                <button type="button" data-panel="organizations">المؤسسات</button>
                <button type="button" data-panel="appointments">المواعيد</button>
                <button type="button" data-panel="library">المكتبة</button>
                <button type="button" data-panel="wallet">المحفظة</button>
                <button type="button" data-panel="settings">الإعدادات</button>
            </nav>
            <button type="button" class="btn btn-ghost" id="logout-btn" style="margin-top:auto;color:#fff;border-color:rgba(255,255,255,.2)">تسجيل الخروج</button>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <h1 id="panel-title">نظرة عامة</h1>
                <div id="admin-user-label" style="color:#6F7A92;font-weight:600"></div>
            </header>
            <div class="admin-content">
                <div id="global-error" class="alert alert-error admin-hidden"></div>

                <section id="panel-overview" class="admin-panel">
                    <div class="stats-grid" id="stats-grid">
                        <div class="stat-card"><div class="label">المستخدمون</div><div class="value" data-stat="users">—</div></div>
                        <div class="stat-card"><div class="label">الأخصائيون</div><div class="value" data-stat="specialists">—</div></div>
                        <div class="stat-card"><div class="label">المؤسسات</div><div class="value" data-stat="organizations">—</div></div>
                        <div class="stat-card"><div class="label">المواعيد اليوم</div><div class="value" data-stat="appointments_today">—</div></div>
                    </div>
                    <div class="panel-card">
                        <div class="head"><h2>ملخص المنصة</h2></div>
                        <div style="padding:1rem 1.1rem;color:#6F7A92">
                            مرحبًا في لوحة إدارة سند. استخدم القائمة لمراجعة المستخدمين، اعتماد الأخصائيين والمؤسسات، ومتابعة المواعيد.
                        </div>
                    </div>
                </section>

                <section id="panel-users" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>آخر المستخدمين</h2><span id="users-count" class="badge badge-approved">—</span></div>
                        <div id="users-table-wrap" class="loading">جاري التحميل…</div>
                    </div>
                </section>

                <section id="panel-specialists" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>الأخصائيون</h2></div>
                        <div id="specialists-table-wrap" class="loading">جاري التحميل…</div>
                    </div>
                </section>

                <section id="panel-organizations" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>المؤسسات</h2></div>
                        <div id="organizations-table-wrap" class="loading">جاري التحميل…</div>
                    </div>
                </section>

                <section id="panel-appointments" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>المواعيد</h2></div>
                        <div id="appointments-table-wrap" class="loading">جاري التحميل…</div>
                    </div>
                </section>

                <section id="panel-library" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>مقالات المكتبة</h2></div>
                        <div id="library-table-wrap" class="loading">جاري التحميل…</div>
                    </div>
                </section>

                <section id="panel-wallet" class="admin-panel admin-hidden">
                    <div class="panel-card" style="margin-bottom:1rem">
                        <div class="head"><h2>إنشاء كوبون نقاط</h2></div>
                        <form id="coupon-form" style="padding:1rem 1.1rem;display:grid;gap:.75rem;max-width:480px">
                            <div class="form-group" style="margin:0"><label>رمز الكوبون</label><input type="text" name="code" required></div>
                            <div class="form-group" style="margin:0"><label>النقاط</label><input type="number" name="points" min="1" required></div>
                            <button type="submit" class="btn btn-primary">إنشاء</button>
                        </form>
                    </div>
                    <div class="panel-card">
                        <div class="head"><h2>إضافة رصيد لمستخدم</h2></div>
                        <form id="credit-form" style="padding:1rem 1.1rem;display:grid;gap:.75rem;max-width:480px">
                            <div class="form-group" style="margin:0"><label>معرّف المستخدم</label><input type="number" name="user_id" min="1" required></div>
                            <div class="form-group" style="margin:0"><label>النقاط</label><input type="number" name="points" min="1" required></div>
                            <button type="submit" class="btn btn-primary">إضافة</button>
                        </form>
                    </div>
                </section>

                <section id="panel-settings" class="admin-panel admin-hidden">
                    <div class="panel-card">
                        <div class="head"><h2>إعدادات المنصة</h2></div>
                        <form id="settings-form" style="padding:1rem 1.1rem;display:grid;gap:1rem">
                            <div class="form-group" style="margin:0">
                                <label>نص سياسة الخصوصية (يظهر في التطبيق)</label>
                                <textarea name="privacy_policy" rows="5" style="width:100%;padding:.75rem;border-radius:12px;border:1px solid #e8ecf4;font-family:inherit"></textarea>
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>معلومات التواصل (يظهر في التطبيق)</label>
                                <textarea name="contact_info" rows="4" style="width:100%;padding:.75rem;border-radius:12px;border:1px solid #e8ecf4;font-family:inherit"></textarea>
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>عمولة المنصة %</label>
                                <input type="number" name="platform_fee_percent" min="0" max="100" style="width:120px;padding:.75rem;border-radius:12px;border:1px solid #e8ecf4">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:fit-content">حفظ الإعدادات</button>
                        </form>
                    </div>
                    <div class="panel-card" style="margin-top:1rem">
                        <div class="head"><h2>تغيير كلمة المرور</h2></div>
                        <form id="password-form" style="padding:1rem 1.1rem;display:grid;gap:.75rem;max-width:420px">
                            <div class="form-group" style="margin:0"><label>كلمة المرور الحالية</label><input type="password" name="current_password" required></div>
                            <div class="form-group" style="margin:0"><label>كلمة مرور جديدة</label><input type="password" name="new_password" required></div>
                            <div class="form-group" style="margin:0"><label>تأكيد كلمة المرور</label><input type="password" name="new_password_confirmation" required></div>
                            <button type="submit" class="btn btn-ghost">تحديث كلمة المرور</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/admin-dashboard.js') }}"></script>
</body>
</html>
