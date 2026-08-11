@extends('layouts.sanad')

@section('title', 'الرئيسية')

@section('content')
<section class="hero hero-modern">
    <div class="hero-atmosphere" aria-hidden="true"></div>
    <div class="container hero-modern-inner">
        <img src="{{ asset('images/sanad-logo.png') }}" alt="سند" class="hero-brand-mark" width="160" height="64">
        <h1 class="hero-brand-name">سند</h1>
        <p class="lead hero-lead">دعم نفسي موثوق — أينما كنت</p>
        <div class="hero-actions hero-actions-center">
            <a href="#download" class="btn btn-primary">حمّل التطبيق</a>
            <a href="{{ route('contact') }}" class="btn btn-ghost">تواصل معنا</a>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <div class="section-title">
            <h2>رعاية واضحة وبسيطة</h2>
            <p>جلسة آمنة، محادثة خاصة، ومجتمع داعم — بلا تعقيد</p>
        </div>
        <div class="features features-modern">
            <article class="feature-row">
                <div class="feature-index">01</div>
                <div>
                    <h3>جلسات فيديو آمنة</h3>
                    <p>احجز مع أخصائي معتمد وانضم بضغطة واحدة مع تذكيرات ومتابعة.</p>
                </div>
            </article>
            <article class="feature-row">
                <div class="feature-index">02</div>
                <div>
                    <h3>محادثات وفضفضة خاصة</h3>
                    <p>مساحة هادئة للتعبير والدعم مع خصوصية واضحة من الواجهة.</p>
                </div>
            </article>
            <article class="feature-row">
                <div class="feature-index">03</div>
                <div>
                    <h3>للأخصائيين والمؤسسات</h3>
                    <p>إدارة المواعيد والمستفيدين والتقارير من منصة واحدة.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section section-alt" id="how">
    <div class="container">
        <div class="section-title">
            <h2>كيف يعمل سند؟</h2>
            <p>ثلاث خطوات فقط لبدء رحلتك</p>
        </div>
        <div class="features features-modern">
            <article class="feature-row">
                <div class="feature-index">1</div>
                <div>
                    <h3>أنشئ حسابك</h3>
                    <p>سجّل وأكمل استمارة الاستقبال بخطوات قصيرة.</p>
                </div>
            </article>
            <article class="feature-row">
                <div class="feature-index">2</div>
                <div>
                    <h3>احجز جلستك</h3>
                    <p>اختر الأخصائي والوقت المناسب وادفع بشفافية.</p>
                </div>
            </article>
            <article class="feature-row">
                <div class="feature-index">3</div>
                <div>
                    <h3>تابع تعافيك</h3>
                    <p>مهام، مكتبة، ومجتمع داعم بإشراف مختص.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section" id="faq">
    <div class="container legal-card">
        <div class="section-title">
            <h2>أسئلة شائعة</h2>
        </div>
        <details class="faq-item">
            <summary>هل الجلسات سرية؟</summary>
            <p>نعم. المحادثات والجلسات محمية، ونلتزم بسياسة خصوصية واضحة مع إمكانية حذف الحساب.</p>
        </details>
        <details class="faq-item">
            <summary>هل يدعم سند اللاجئين والمغتربين؟</summary>
            <p>نعم. مكتبة مخصّصة لسوريا وأوروبا ومجتمعات للغربة والصدمات والنازحين.</p>
        </details>
        <details class="faq-item">
            <summary>كيف أدفع للجلسات؟</summary>
            <p>عبر نقاط المحفظة أو الرصيد أو MTN Mobile Money حسب توفر الخدمة في منطقتك.</p>
        </details>
        <details class="faq-item">
            <summary>هل يمكن للمؤسسات استخدام سند؟</summary>
            <p>نعم. لوحة مؤسسات مع تقارير دورية ومتابعة المستفيدين والأخصائيين.</p>
        </details>
    </div>
</section>

<section class="container" id="download">
    <div class="cta-band">
        <div>
            <h2 style="margin:0 0 .35rem;font-size:1.5rem">ابدأ رحلتك مع سند</h2>
            <p>حمّل التطبيق وابدأ بخطوة واحدة واضحة.</p>
        </div>
        <div style="display:flex;gap:.65rem;flex-wrap:wrap">
            @if (config('sanad.app_store_url'))
                <a href="{{ config('sanad.app_store_url') }}" class="btn btn-accent" target="_blank" rel="noopener">App Store</a>
            @else
                <span class="btn btn-accent" style="opacity:.7">App Store — قريبًا</span>
            @endif
            @if (config('sanad.play_store_url'))
                <a href="{{ config('sanad.play_store_url') }}" class="btn btn-ghost" style="background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.25)" target="_blank" rel="noopener">Google Play</a>
            @else
                <span class="btn btn-ghost" style="background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.25);opacity:.7">Google Play — قريبًا</span>
            @endif
        </div>
    </div>
</section>
@endsection
