<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'سند — منصة دعم نفسي رقمية تربطك بأخصائيين معتمدين وجلسات آمنة ومجتمع داعم.')">
    <title>@yield('title', 'سند') — {{ config('sanad.company_name', 'سند') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sanad.css') }}">
    @stack('head')
</head>
<body>
    <header class="site-header">
        <div class="container inner">
            <a href="{{ route('home') }}" class="brand" aria-label="سند — الرئيسية">
                <img src="{{ asset('images/sanad-logo.png') }}" alt="سند" class="brand-logo" width="120" height="48">
            </a>
            <nav class="nav">
                <a href="{{ route('home') }}#features" class="hide-mobile">المميزات</a>
                <a href="{{ route('home') }}#how" class="hide-mobile">كيف يعمل</a>
                <a href="{{ route('home') }}#faq" class="hide-mobile">الأسئلة</a>
                <a href="{{ route('contact') }}" class="hide-mobile @if(request()->routeIs('contact')) active @endif">تواصل</a>
                <a href="{{ route('privacy') }}" @if(request()->routeIs('privacy')) class="active" @endif>الخصوصية</a>
                <a href="{{ route('delete-account') }}" @if(request()->routeIs('delete-account*')) class="active" @endif>حذف الحساب</a>
                <a href="{{ route('admin.login') }}" class="btn btn-ghost">لوحة الإدارة</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a href="{{ route('home') }}" class="brand" style="margin-bottom:.75rem">
                    <img src="{{ asset('images/sanad-logo.png') }}" alt="سند" class="brand-logo">
                </a>
                <p>منصة عربية للدعم النفسي الرقمي — جلسات، محادثات، مكتبة معرفية، ومجتمع آمن بإشراف مختصين.</p>
            </div>
            <div>
                <h4>روابط</h4>
                <ul>
                    <li><a href="{{ route('home') }}">الرئيسية</a></li>
                    <li><a href="{{ route('contact') }}">تواصل معنا</a></li>
                    <li><a href="{{ route('privacy') }}">سياسة الخصوصية</a></li>
                    <li><a href="{{ route('terms') }}">شروط الاستخدام</a></li>
                    <li><a href="{{ route('delete-account') }}">حذف الحساب</a></li>
                </ul>
            </div>
            <div>
                <h4>تواصل</h4>
                <ul>
                    <li><a href="mailto:{{ config('sanad.support_email') }}">{{ config('sanad.support_email') }}</a></li>
                    <li><a href="{{ route('admin.login') }}">دخول المشرفين</a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            © {{ date('Y') }} سند — جميع الحقوق محفوظة.
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
