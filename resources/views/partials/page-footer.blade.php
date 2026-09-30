{{--
    تذييل الصفحة كالمرجع (`.page-footer` في ملف الهويّة): سطر الهويّة على جهة البداية،
    ورابط المساعدة على جهة النهاية؛ خطّ فاصل أعلى، 12px بلون هادئ، وعلى الموبايل
    يتراصّ عموديًّا. داخل `#content` كي يرث حشوته السفليّة فوق الأشرطة والأزرار
    العائمة. النصوص من `setting()` (2.13).
--}}
<footer class="page-footer">
    <span>{{ config('app.name') }} · {{ setting('ux.footer.tagline', 'تعلّم يصنع أثرًا') }}</span>
    @if (\Illuminate\Support\Facades\Route::has('help.index'))
        <a href="{{ route('help.index') }}">{{ setting('ux.footer.help', 'مركز المساعدة') }}</a>
    @endif
</footer>
