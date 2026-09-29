{{--
    نصوص السكربت المشترك التي تصل المستخدم (2.13): تُحقَن مرّةً في كلّ لياوت
    كتلة JSON يقرأها app.js عند الحاجة، بدل نصٍّ عربيٍّ محروق داخل السكربت.
--}}
@php
    $uxTexts = [
        'session_expired' => setting('ux.script.session_expired', 'الجلسة انتهت. حدّث الصفحة وكمّل.'),
        'network_failed' => setting('ux.script.network_failed', 'النت قطع لحظة. جرّب تاني.'),
    ];
@endphp
<script type="application/json" id="ux-texts">{!! json_encode($uxTexts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
