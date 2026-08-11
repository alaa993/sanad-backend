@extends('layouts.sanad')

@section('title', 'تواصل معنا')

@section('content')
<section class="legal-page">
    <div class="container legal-card">
        <h1>تواصل معنا</h1>
        <p>نحن هنا لمساعدتك في أي استفسار تقني أو متعلق بالخصوصية أو الحساب.</p>

        @if (session('success'))
            <p style="color:#1a7f4b;font-weight:600">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p style="color:#c0392b;font-weight:600">{{ session('error') }}</p>
        @endif

        <form method="post" action="{{ route('contact.submit') }}" style="margin:1.5rem 0">
            @csrf
            <label style="display:block;margin-bottom:.35rem;font-weight:600">الاسم</label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                   style="width:100%;padding:.75rem;border:1px solid #dbe2ef;border-radius:12px;margin-bottom:1rem">

            <label style="display:block;margin-bottom:.35rem;font-weight:600">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="190"
                   style="width:100%;padding:.75rem;border:1px solid #dbe2ef;border-radius:12px;margin-bottom:1rem">

            <label style="display:block;margin-bottom:.35rem;font-weight:600">الرسالة</label>
            <textarea name="message" required rows="6" maxlength="5000"
                      style="width:100%;padding:.75rem;border:1px solid #dbe2ef;border-radius:12px;margin-bottom:1rem">{{ old('message') }}</textarea>

            <button type="submit" class="btn btn-primary">إرسال الرسالة</button>
        </form>

        <h2>البريد الإلكتروني</h2>
        <p>
            <a href="mailto:{{ config('sanad.support_email') }}">{{ config('sanad.support_email') }}</a>
        </p>

        <h2>الخصوصية وحذف الحساب</h2>
        <ul>
            <li><a href="{{ route('privacy') }}">سياسة الخصوصية</a></li>
            <li><a href="{{ route('delete-account') }}">حذف الحساب</a></li>
            <li><a href="{{ route('terms') }}">شروط الاستخدام</a></li>
        </ul>

        <h2>وقت الاستجابة</h2>
        <p>نسعى للرد خلال 1–3 أيام عمل. للحالات العاجلة المتعلقة بالسلامة، تواصل مع جهات الطوارئ المحلية.</p>
    </div>
</section>
@endsection
