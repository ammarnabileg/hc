{{--
    التحسين التدريجيّ (2.1): مَن جافاسكربت مقفول عنده لازم يفهم **إيه اللي ناقص**
    و**إزاي يفعّله خطوة بخطوة** — لا شاشة بيضاء ولا صمت.

    كلّ النصوص من الإعدادات (2.13)، والخطوات قائمة قابلة للتحرير بلا لمس كود.
--}}
<noscript>
    {{-- مثبّتة أسفل الشاشة: تظهر فوق أيّ ليَاوت بلا ما تزيح المحتوى ولا تغطّي الهيدر --}}
    <div style="position: fixed; inset-inline: 0; inset-block-end: 0; z-index: 70; max-block-size: 70vh; overflow-y: auto; padding: 1rem; background: var(--surface-raised, #10201c); border-block-start: 1px solid var(--border, rgba(0,212,184,.15)); color: var(--text, #e8f5f2); font-family: 'Cairo', sans-serif;">
        <div style="max-inline-size: 44rem; margin-inline: auto;">
            <strong style="display: block; font-size: 1rem; margin-block-end: .35rem;">
                {{ setting('ux.noscript.title', 'الجافاسكربت مقفول في متصفّحك') }}
            </strong>

            <p style="font-size: .875rem; margin-block-end: .5rem; color: var(--text-muted, #9bb3ad);">
                {{ setting('ux.noscript.body', 'الصفحة شغّالة وتقدر تقرأ وتتنقّل عادي، لكن الحفظ التلقائيّ والعدّادات والبوب-أبات محتاجة الجافاسكربت. تفعيله بياخد أقلّ من دقيقة:') }}
            </p>

            <ol style="font-size: .8125rem; margin-inline-start: 1.25rem; line-height: 1.9; color: var(--text-muted, #9bb3ad);">
                @foreach ((array) setting('ux.noscript.steps', [
                    {{-- ⚠️ نصّ لا ماركب: هذه القيمة **داخل تعبير PHP** في @foreach، فأيّ وسم Blade هنا يكسر تصريف القالب --}}
                    'افتح إعدادات المتصفّح من القائمة (⋮ أو أيقونة الإعدادات) فوق على اليمين.',
                    'ادخل على «الخصوصيّة والأمان» ثمّ «إعدادات المواقع».',
                    'اختار «جافاسكربت» وخلّيه «مسموح».',
                    'ارجع للصفحة دي واعمل تحديث (F5 أو سهم التحديث).',
                ]) as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>

            <p style="font-size: .8125rem; margin-block-start: .5rem; color: var(--text-muted, #9bb3ad);">
                {{ setting('ux.noscript.footer', 'لو المتصفّح عندك مختلف، دوّر على كلمة «JavaScript» جوّه الإعدادات — الخطوة واحدة في كلّ المتصفّحات.') }}
            </p>
        </div>
    </div>
</noscript>
