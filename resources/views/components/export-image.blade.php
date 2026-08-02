@props([
    'title' => '',
    'subtitle' => '',
    'rows' => [],
    'kind' => 'leaderboard',
    'id' => null,
])

@php
    use App\Http\Controllers\Ui\ExportImageController;
    use App\Models\ImageTemplate;
    use App\Services\Images\BoardSnapshot;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\URL;

    /*
     | ⭐ زرّ [استخراج كصورة] — مكوّن واحد لكلّ لوحات المنصّة (12.14-هـ):
     |   كلّ ليدر بورد · كلّ لوحة إحصاءات · كلّ بطاقة إنجاز.
     | و**الصلاحيّة على الاستخراج**: مَن لا يملك `image_export.use` لا يرى الزرّ
     | أصلًا — يُخفى ولا يُعطَّل (2.15-أ-7).
     */
    $mayExport = auth()->check() && Gate::allows('image_export.use');

    if ($mayExport) {
        $viewer = auth()->user();
        $modalId = $id ?: 'export-image-'.substr(md5($title.$kind.count($rows)), 0, 8);

        $snapshot = new BoardSnapshot($kind, (string) $title, (string) $subtitle, array_values($rows));

        // الحمولة موقَّعة — الشكل يتغيّر بالخيارات، والبيانات لا تُزوَّر
        $signed = URL::signedRoute('export.image', ['d' => $snapshot->encode()]);

        /*
         | فورم GET يمسح كويري الـaction، فنفكّ الرابط الموقَّع إلى
         | مسارٍ + حقول مخفيّة — وإلّا ضاع التوقيع وسقط الطلب بـ403.
         */
        $signedPath = strtok($signed, '?');
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $signedQuery);

        $presets = app(\App\Services\Images\TemplateLayers::class)->presets();

        // القوالب المتاحة لهذا المستخدم وحده (12.14-ز) — التسويق يصمّم والناس تنشر
        $templates = ImageTemplate::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->latest('id')
            ->limit((int) setting('images.export.templates_limit', 20))
            ->get()
            ->filter(fn ($t) => ExportImageController::visibleTo($t, $viewer))
            ->values();
    }
@endphp

@if ($mayExport)
    {{-- مساحة اللمس 44×44 شرط قبول على الموبايل (2.15-ج) --}}
    <button type="button" data-modal-open="{{ $modalId }}"
            class="btn inline-flex items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold motion-standard"
            style="min-width: 44px; min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
            aria-label="استخراج كصورة" title="استخراج كصورة">
        {{-- أيقونة SVG مرسومة داخل المشروع — ممنوع أيّ مكتبة أيقونات (2.16-ج) --}}
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect x="3" y="4" width="18" height="14" rx="2" />
            <circle cx="8.5" cy="9" r="1.6" />
            <path d="M3 15l4.5-4 3.5 3 3-2.5L21 16" />
            <path d="M12 18.5v3M12 21.5l-2-2M12 21.5l2-2" />
        </svg>
        <span class="hidden sm:inline">استخراج كصورة</span>
    </button>

    <x-modal :id="$modalId" title="استخراج كصورة">
        <form method="get" action="{{ $signedPath }}" target="_blank" rel="noopener"
              data-export-form class="space-y-4 text-sm">
            {{-- الحمولة الموقَّعة تُعاد كما هي، وإلّا سقط التوقيع --}}
            @foreach ($signedQuery as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block mb-1">اللي هيظهر</span>
                    <select name="top" class="w-full rounded-xl px-3 py-2"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        <option value="top10">أفضل 10</option>
                        <option value="top3">أفضل 3</option>
                        <option value="me">صفّي أنا</option>
                    </select>
                </label>

                <label class="block">
                    <span class="block mb-1">المقاس</span>
                    <select name="size" class="w-full rounded-xl px-3 py-2"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        @foreach ($presets as $key => $preset)
                            <option value="{{ $key }}">{{ $preset['label'] ?? $key }} — {{ $preset['width'] }}×{{ $preset['height'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="block mb-1">القالب</span>
                    <select name="template" class="w-full rounded-xl px-3 py-2"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        <option value="0">بلا قالب — تصميم المنصّة</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- ⭐ الطبقات: رفع الفريم لأعلى أو إنزاله لأسفل (12.14-أ) --}}
                <label class="block">
                    <span class="block mb-1">طبقة الفريم</span>
                    <select name="frame" class="w-full rounded-xl px-3 py-2"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                        <option value="above">فوق المحتوى ↑</option>
                        <option value="below">تحت المحتوى ↓</option>
                    </select>
                </label>
            </div>

            <label class="flex items-center gap-2" style="min-height: 44px">
                {{-- الحقل المخفيّ يضمن وصول «لا» عند إلغاء التحديد --}}
                <input type="hidden" name="avatars" value="0">
                <input type="checkbox" name="avatars" value="1" checked class="w-5 h-5">
                <span>إظهار الأفاتارات</span>
            </label>

            {{-- سطر واحد لكلّ شرح (2.15-أ-8) --}}
            <p class="text-xs" style="color: var(--text-muted)">
                الصورة بتحمل تاريخ اللقطة وشعار المنصّة — والمحافظة بتظهر دايمًا.
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="submit" name="download" value="1"
                        class="btn rounded-xl px-4 py-2 font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">نزّل الصورة</button>
                <button type="submit" name="download" value="0"
                        class="btn rounded-xl px-4 py-2 motion-standard"
                        style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">افتحها للمعاينة</button>
            </div>
        </form>
    </x-modal>
@endif
