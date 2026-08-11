<?php

return [
    'case_types' => [
        ['id' => 'bipolar', 'label_ar' => 'ثنائي القطب', 'label_en' => 'Bipolar disorder', 'specialist' => 'psychiatrist'],
        ['id' => 'anx_dep', 'label_ar' => 'قلق/اكتئاب', 'label_en' => 'Anxiety/Depression', 'specialist' => 'cbt'],
        ['id' => 'schizophrenia', 'label_ar' => 'فصام', 'label_en' => 'Schizophrenia', 'specialist' => 'psychiatrist'],
        ['id' => 'children', 'label_ar' => 'أطفال وسلوك', 'label_en' => 'Children/Behavior', 'specialist' => 'child'],
        ['id' => 'mild', 'label_ar' => 'اضطرابات خفيفة', 'label_en' => 'Mild disorders', 'specialist' => 'psychologist'],
        ['id' => 'identity', 'label_ar' => 'اضطرابات الهوية', 'label_en' => 'Identity disorders', 'specialist' => 'counselor'],
    ],

    'community_categories' => [
        ['id' => 'women', 'label_ar' => 'نساء', 'label_en' => 'Women'],
        ['id' => 'displaced', 'label_ar' => 'نازحون', 'label_en' => 'Displaced'],
        ['id' => 'trauma', 'label_ar' => 'صدمات', 'label_en' => 'Trauma'],
        ['id' => 'parents', 'label_ar' => 'آباء', 'label_en' => 'Parents'],
        ['id' => 'youth', 'label_ar' => 'شباب', 'label_en' => 'Youth'],
        ['id' => 'diaspora', 'label_ar' => 'الغربة', 'label_en' => 'Diaspora'],
    ],

    'group_age_categories' => [
        ['id' => 'children', 'label_ar' => 'أطفال', 'label_en' => 'Children'],
        ['id' => 'teens', 'label_ar' => 'مراهقون', 'label_en' => 'Teens'],
        ['id' => 'adults', 'label_ar' => 'بالغون', 'label_en' => 'Adults'],
        ['id' => 'seniors', 'label_ar' => 'كبار', 'label_en' => 'Seniors'],
    ],

    'group_disorder_tags' => [
        ['id' => 'anxiety', 'label_ar' => 'قلق', 'label_en' => 'Anxiety'],
        ['id' => 'depression', 'label_ar' => 'اكتئاب', 'label_en' => 'Depression'],
        ['id' => 'stress', 'label_ar' => 'توتر', 'label_en' => 'Stress'],
        ['id' => 'parenting', 'label_ar' => 'تربية', 'label_en' => 'Parenting'],
        ['id' => 'grief', 'label_ar' => 'فقد', 'label_en' => 'Grief'],
    ],

    'task_templates' => [
        ['id' => 'resistance', 'title_ar' => 'مقاومة الأفكار السلبية', 'title_en' => 'Resist negative thoughts', 'description_ar' => 'سجّل 3 أفكار سلبية وردّ عليها بأدلة موضوعية.'],
        ['id' => 'self_esteem', 'title_ar' => 'تعزيز الثقة بالنفس', 'title_en' => 'Build self-esteem', 'description_ar' => 'اكتب 5 إنجازات صغيرة حققتها هذا الأسبوع.'],
        ['id' => 'encouragement', 'title_ar' => 'تشجيع الذات', 'title_en' => 'Self encouragement', 'description_ar' => 'اقرأ جملة تشجيعية صباحاً ومساءً لمدة 3 أيام.'],
        ['id' => 'breathing', 'title_ar' => 'تمارين التنفس', 'title_en' => 'Breathing exercise', 'description_ar' => 'مارس تنفس 4-7-8 لمدة 5 دقائق يومياً.'],
    ],

    'pre_session_questions' => [
        ['id' => 'mood', 'label_ar' => 'كيف مزاجك اليوم؟ (1-10)', 'label_en' => 'How is your mood today? (1-10)', 'type' => 'scale'],
        ['id' => 'sleep', 'label_ar' => 'هل نمت جيداً الليلة الماضية؟', 'label_en' => 'Did you sleep well last night?', 'type' => 'boolean'],
        ['id' => 'medication', 'label_ar' => 'هل التزمت بالدواء؟', 'label_en' => 'Did you take medication as prescribed?', 'type' => 'boolean'],
        ['id' => 'concern', 'label_ar' => 'ما أكثر ما يقلقك قبل الجلسة؟', 'label_en' => 'What worries you most before the session?', 'type' => 'text'],
    ],

    'curated_library_tags' => ['syria', 'europe', 'refugee', 'diaspora', 'غربة', 'لجوء'],

    'journal_requires_recovery' => false,
    'recovery_min_completed_sessions' => 3,
    'recovery_min_benefit_score' => 70,

    'company_name' => env('SANAD_COMPANY_NAME', 'سند'),
    'support_email' => env('SANAD_SUPPORT_EMAIL', 'support@sanad.app'),
    'privacy_email' => env('SANAD_PRIVACY_EMAIL', 'privacy@sanad.app'),
    'app_store_url' => env('SANAD_APP_STORE_URL'),
    'play_store_url' => env('SANAD_PLAY_STORE_URL'),

    'session_price_points' => (int) env('SANAD_SESSION_PRICE_POINTS', 100),
    'session_price_wallet' => (int) env('SANAD_SESSION_PRICE_WALLET', 100),

    'payment_methods' => ['wallet', 'points', 'syriatel', 'mtn', 'coupon'],

    /** Ready-made wallet top-up point packs shown in apps. */
    'topup_presets' => [50, 100, 300],
];
