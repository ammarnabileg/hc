{{--
 | دفعةٌ واحدة من صفوف الإعدادات — مصدرٌ **واحد** لشكل الصفّ.
 |
 | تُصيَّر مرّةً داخل الصفحة (دفعة المجموعة المفتوحة) ومرّةً من نقطة
 | `admin.settings.batch` عند فتح كارتٍ آخر أو الضغط على «حمّل المزيد».
 | فلا نسختان تفترقان: واحدةٌ في Blade وأخرى مبنيّة في JS.
 |
 | $rows · $registry · $endpoint
--}}
@foreach ($rows as $setting)
    @include('admin.settings.partials.field', [
        'setting' => $setting,
        'registry' => $registry,
        'endpoint' => $endpoint,
    ])
@endforeach
