<?php
/**
 * إعدادات جنوب 24
 * عند النقل إلى الاستضافة: غيّر site_url وبيانات قاعدة البيانات واجعل debug = false
 */
return [
    'site_url'  => 'http://localhost/janoub24',   // بدون / في النهاية — مثال للإنتاج: https://janoub24.com
    'timezone'  => 'Asia/Beirut',
    'debug'     => true,

    // نسخة تجريبية؟ true = منع جوجل من الأرشفة. اجعلها false عند الإطلاق الفعلي على الدومين الأساسي
    'staging'   => false,

    'db' => [
        'host' => '127.0.0.1',
        'name' => 'janoub24',
        'user' => 'root',
        'pass' => '',
    ],

    // مدة تخزين الصفحات العامة مؤقتاً بالثواني (0 للتعطيل)
    'cache_ttl' => 90,

    // عدد الأخبار في صفحات الأقسام والأرشيف
    'per_page'  => 20,
];
