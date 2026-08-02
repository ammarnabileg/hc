@php
    use App\Models\Cv;

    /*
     | ما نقص من القسم 9 في شاشة واحدة مطويّة (2.15 — العمق خلف خطوة واحدة):
     |  · **استيراد CV جاهز** ⟵ تحليل ⟵ «تبديل ولا إضافة؟» ⟵ معاينة قبل الحفظ.
     |  · **تصدير PDF متوافق مع ATS** — يُولَّد على الخادم.
     |  · **رابط سيرة عامّ** قابل للمشاركة زيّ صفحة الشهادة.
     | ولا تظهر لزائرٍ بلا حساب — لأنّها كلّها تكتب في سيرته هو.
     */
    $record = auth()->check() ? Cv::query()->where('user_id', auth()->id())->first() : null;
    $publicUrl = $record?->public_slug ? route('cv.public', ['slug' => $record->public_slug]) : null;
@endphp

@auth
    <section class="card p-4 mb-4" data-cv-tools>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h2 class="font-bold text-sm">{{ setting('cv.tools.title', 'أدوات السيرة') }}</h2>
            <a href="{{ route('cv.ats') }}"
               class="btn inline-flex items-center rounded-xl px-4 text-sm font-semibold motion-standard"
               style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                {{ setting('cv.tools.ats_label', 'تحميل PDF متوافق مع ATS') }}
            </a>
        </div>

        <details class="mb-3">
            <summary class="cursor-pointer text-sm" style="min-height: 44px">{{ setting('cv.tools.import_summary', 'ارفع CV جاهز وهنملّي بدالك') }}</summary>

            <form data-cv-import class="mt-3 flex flex-wrap items-end gap-2" enctype="multipart/form-data">
                @csrf
                <label class="block">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('cv.tools.file_label', 'ملفّ السيرة') }}</span>
                    <input type="file" name="file" required
                           accept=".txt,.md,.html,.htm,.docx,.pdf"
                           class="text-sm" style="min-height: 44px">
                </label>

                <button type="submit" class="btn rounded-xl px-4 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    {{ setting('cv.tools.parse_label', 'حلّل الملفّ') }}
                </button>
            </form>

            {{-- المعاينة + سؤال «تبديل ولا إضافة؟» قبل أيّ كتابة (9) --}}
            <div data-cv-import-preview class="hidden mt-3 rounded-xl p-3 text-sm"
                 style="background: var(--surface-sunken); border: 1px solid var(--border)">
                <p data-cv-import-summary class="mb-2"></p>
                <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('cv.tools.import_hint', 'راجع الأرقام — والحفظ مش هيحصل غير لما تختار.') }}</p>

                <div class="flex flex-wrap gap-2">
                    <button type="button" data-cv-import-mode="replace"
                            class="btn rounded-xl px-4 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('cv.tools.replace_label', 'بدّل بياناتي') }}</button>
                    <button type="button" data-cv-import-mode="append"
                            class="btn rounded-xl px-4 text-sm motion-standard"
                            style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">{{ setting('cv.tools.append_label', 'أضف عليها') }}</button>
                </div>
            </div>
        </details>

        {{-- رابط سيرة عامّ (9) --}}
        <div class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
                <input type="checkbox" data-cv-public class="w-5 h-5" @checked($record?->is_public)>
                <span>{{ setting('cv.tools.public_toggle_label', 'شغّل الرابط العامّ للسيرة') }}</span>
            </label>

            <input type="text" readonly data-cv-public-url value="{{ $publicUrl }}"
                   class="flex-1 min-w-48 rounded-xl px-3 text-xs font-mono {{ $publicUrl ? '' : 'hidden' }}"
                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
    </section>
@endauth
