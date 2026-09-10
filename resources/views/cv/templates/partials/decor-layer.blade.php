{{--
 | ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV — الطبقة الزخرفيّة (12.7-ب).
 | جزءٌ مشترك يُضمَّن بسطرٍ واحد في كلٍّ من classic.blade.php وmodern.blade.php
 | — لا تكرار منطقٍ بين القالبين.
 |
 | ⚠️ حصر الصفحة الأولى فقط (CV طويل متعدّد الصفحات): القيد **CSS بحتٌ**
 | (`overflow:hidden` على صندوقٍ ارتفاعه 297mm بالضبط من أعلى `.sheet`) لا
 | خادميّ — لأنّ `.sheet` نفسها `min-block-size:297mm` (تكبر بلا حدٍّ أعلى مع
 | محتوًى طويل)، فقصّ الطبقة الزخرفيّة وحدها بصندوقٍ داخليّ بارتفاعٍ ثابت
 | يمنعها من التكرار أو الطفو في صفحاتٍ لاحقة بلا المساس بامتداد `.sheet` نفسها
 | لصفحاتٍ تالية عند الطباعة. ولا حاجة لاختبار خادميّ منفصل لهذا القيد تحديدًا:
 | لا يوجد كودٌ خادميّ يقرّر الصفحة — العرض بصريٌّ بحت.
 |
 | ⭐ فوق أو خلف المحتوى المتدفّق: `z` تُستعمَل كـCSS z-index حرفيًّا. بلا نقلٍ
 | في ترتيب الـDOM: صندوقٌ position:absolute بلا z-index صريح على الحاوية
 | (فلا يفتح سياق تكديسٍ جديد) يجعل كلّ طبقة تُقارَن مباشرةً مع محتوى `.sheet`
 | المتدفّق العاديّ حسب ترتيب CSS القياسيّ — z سالبٌ يرسم خلف المحتوى، وصفرٌ أو
 | موجبٌ يرسمها فوقه، بصرف النظر عن مكان هذا الـ@include في الملف.
 |
 | قالبٌ بلا $decorLayers (فارغة/null): لا يُطبَع أيّ شيء — توافقٌ خلفيّ كامل.
--}}
@if (! empty($decorLayers))
    <div class="cv-decor-layer" aria-hidden="true"
         style="position:absolute; inset:0; block-size:297mm; overflow:hidden; pointer-events:none;">
        @foreach ($decorLayers as $layer)
            @if (($layer['type'] ?? null) === 'text')
                <div style="position:absolute; left:{{ (int) ($layer['x'] ?? 0) }}%; top:{{ (int) ($layer['y'] ?? 0) }}%;
                            z-index:{{ (int) ($layer['z'] ?? 0) }}; font-size:{{ (int) ($layer['size'] ?? 14) }}pt;
                            color:{{ $layer['color'] ?? '#000000' }}; text-align:{{ $layer['align'] ?? 'right' }};
                            transform:rotate({{ (int) ($layer['rotate'] ?? 0) }}deg); white-space:pre-wrap; margin:0;">{{ $layer['text'] ?? '' }}</div>
            @elseif (($layer['type'] ?? null) === 'image')
                <img src="{{ \Illuminate\Support\Facades\Storage::url($layer['path'] ?? '') }}" alt=""
                     style="position:absolute; left:{{ (int) ($layer['x'] ?? 0) }}%; top:{{ (int) ($layer['y'] ?? 0) }}%;
                            inline-size:{{ (int) ($layer['w'] ?? 100) }}%; block-size:{{ (int) ($layer['h'] ?? 100) }}%;
                            z-index:{{ (int) ($layer['z'] ?? 0) }}; opacity:{{ (int) ($layer['opacity'] ?? 100) / 100 }};
                            object-fit:cover;">
            @endif
        @endforeach
    </div>
@endif
