@extends('layouts.sanad')

@section('title', 'حذف الحساب')
@section('meta_description', 'اطلب حذف حسابك في تطبيق سند بشكل دائم — مطلوب لمتاجر التطبيقات.')

@section('content')
<section class="legal-page">
    <div class="container">
        <div class="section-title" style="margin-bottom:1.5rem">
            <h2 style="margin:0;color:#1B2F63">حذف الحساب</h2>
            <p style="margin:.5rem 0 0">حذف دائم لحسابك وبياناتك الشخصية المرتبطة به في سند</p>
        </div>

        @if (session('deleted'))
            <div class="alert alert-success form-card">
                تم حذف حسابك بنجاح. لن تتمكن من تسجيل الدخول مرة أخرى بنفس البيانات.
                إذا احتجت مساعدة، راسلنا على {{ config('sanad.support_email') }}.
            </div>
        @else
            <div class="legal-card form-card">
                <div class="danger-box">
                    <strong>تنبيه:</strong> الحذف نهائي ولا يمكن التراجع عنه. ستُحذف بيانات الحساب، الرموز،
                    اليوميات، والمحتوى المرتبط بك وفق سياسة الاحتفاظ. قد تبقى سجلات مجمّعة غير شخصية لأغراض قانونية.
                </div>

                @if ($errors->any())
                    <div class="alert alert-error">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="post" action="{{ route('delete-account.submit') }}">
                    @csrf
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني المرتبط بالحساب</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" id="confirm" name="confirm" value="1" required {{ old('confirm') ? 'checked' : '' }}>
                        <label for="confirm">
                            أؤكد رغبتي في حذف حسابي وجميع بياناتي الشخصية المرتبطة به بشكل دائم.
                        </label>
                    </div>
                    <button type="submit" class="btn btn-accent" style="width:100%">حذف الحساب نهائيًا</button>
                </form>

                <p style="margin-top:1.25rem;font-size:.9rem;color:#6F7A92;text-align:center">
                    يمكنك أيضًا طلب الحذف من داخل التطبيق عبر الإعدادات → حذف الحساب (عند التفعيل).
                    <br>
                    <a href="{{ route('privacy') }}">اقرأ سياسة الخصوصية</a>
                </p>
            </div>
        @endif
    </div>
</section>
@endsection
