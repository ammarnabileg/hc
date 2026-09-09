{{-- 3) الإصدار (12.5-ج): بالكود فرديًّا أو جماعيًّا بتحقّق ومعاينة ومنع تكرار --}}
@if ($types->isEmpty())
    <x-empty :message="setting('admin.certificates.partials.issue.jhz_nwa_shhada_alawl_ashan_tqdr_tsdr', 'جهّز نوع شهادة الأوّل عشان تقدر تصدر.')"
             :action="setting('admin.certificates.partials.issue.alanwaa_walqwalb', 'الأنواع والقوالب')" :href="route('admin.certificates.index', ['tab' => 'types'])" />
@else
    <form method="post" action="{{ route('admin.certificates.verify-codes') }}" data-issue-form class="card p-4 space-y-4">
        @csrf

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.nwa_alshhada', 'نوع الشهادة') }}</span>
                <select name="certificate_type_id" data-issue-type required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.allgha', 'اللغة') }}</span>
                <select name="language" data-issue-language class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="ar">{{ setting('admin.certificates.partials.issue.arbya', 'عربيّة') }}</option>
                    <option value="en">{{ setting('admin.certificates.partials.issue.injlyzya', 'إنجليزيّة') }}</option>
                </select>
            </label>
        </div>

        {{-- ⭐ [2026-09-10] اختيار القالب وقت الإصدار (سطر 2406) — فاضي = الافتراضيّ/الأحدث كما كان دائمًا --}}
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.alqalb_akhtyary', 'القالب (اختياريّ)') }}</span>
            <select name="template_id" data-issue-template class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.certificates.partials.issue.altsmym_alaftraady_alahdth', 'التصميم الافتراضيّ/الأحدث') }}</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" data-type="{{ $template->certificate_type_id }}" data-language="{{ $template->language }}" class="hidden">
                        {{ setting('admin.certificates.partials.issue.nskha', 'نسخة') }} {{ $template->version }}{{ $template->is_default ? ' — '.setting('admin.certificates.partials.issue.alaftraadya', 'الافتراضيّة') : '' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.akwad_alashkhas', 'أكواد الأشخاص') }}</span>
            <textarea name="codes" rows="4" required
                      placeholder="{{ setting('certificates.issue.codes_placeholder', 'الصق الأكواد مفصولة بمسافة أو فاصلة…') }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                {!! strtr(setting('admin.certificates.partials.issue.kwd_wahd_llisdar_alfrdy_aw_lhd_v1_kwd', 'كود واحد للإصدار الفرديّ، أو لحدّ :v1 كود للإصدار الجماعيّ.'), [':v1' => e($batchLimit)]) !!}
            </span>
        </label>

        <div class="flex gap-2 flex-wrap">
            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.certificates.partials.issue.thqq_mn_alakwad', 'تحقّق من الأكواد') }}</button>
            <button type="button" data-issue-preview-open
                    class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.certificates.partials.issue.maayna_qbl_alisdar', 'معاينة قبل الإصدار') }}</button>
        </div>
    </form>

    {{--
        ⭐ [2026-09-10] «معاينة قبل الإصدار (بوب-أب، الشهادات تحت بعضها)»
        (سطر 4660 · 12.5-ج) — كانت صفحةً كاملة لا بوب-أب. الرأس ثابت والجسم
        `overflow-y:auto` كنصّ pop-box (17)، ومحتواه نفس فيو المعاينة بوجهه
        العاري (`?fragment=1`) — فزرّ «أصدِر الشهادات» داخله حقيقيٌّ لا نسخة.
    --}}
    <x-modal id="issue-preview-modal" :title="setting('admin.certificates.partials.issue.maayna_qbl_alisdar', 'معاينة قبل الإصدار')">
        <div data-issue-preview-body class="min-h-[6rem]">
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.certificates.partials.issue.jar_altjhyz', 'جارٍ التجهيز…') }}</p>
        </div>
    </x-modal>

    @push('scripts')
        <script>
            (() => {
                const typeSelect = document.querySelector('[data-issue-type]');
                const languageSelect = document.querySelector('[data-issue-language]');
                const templateSelect = document.querySelector('[data-issue-template]');

                if (! typeSelect || ! languageSelect || ! templateSelect) return;

                const syncTemplates = () => {
                    const type = typeSelect.value;
                    const language = languageSelect.value;
                    let matched = false;

                    templateSelect.querySelectorAll('option[data-type]').forEach((option) => {
                        const visible = option.dataset.type === type && option.dataset.language === language;
                        option.classList.toggle('hidden', ! visible);
                        if (visible) matched = true;
                    });

                    // القالب المختار سابقًا قد لا يخصّ النوع/اللغة الجديدين — يرجع للافتراضيّ لا يبقى مختارًا زورًا
                    const current = templateSelect.querySelector('option:checked');
                    if (current && current.classList.contains('hidden')) templateSelect.value = '';
                    if (! matched) templateSelect.value = '';
                };

                typeSelect.addEventListener('change', syncTemplates);
                languageSelect.addEventListener('change', syncTemplates);
                syncTemplates();
            })();

            // ⭐ [2026-09-10] «معاينة قبل الإصدار» بوب-أب — يجلب نفس الفيو بوجهه العاري (?fragment=1) بدل مغادرة الصفحة
            (() => {
                const openButton = document.querySelector('[data-issue-preview-open]');
                const form = document.querySelector('[data-issue-form]');
                const modal = document.getElementById('issue-preview-modal');
                const body = modal?.querySelector('[data-issue-preview-body]');

                if (! openButton || ! form || ! modal || ! body) return;

                openButton.addEventListener('click', () => {
                    body.innerHTML = '<p class="text-sm" style="color: var(--text-muted)">' + @json(setting('admin.certificates.partials.issue.jar_altjhyz')) + '</p>';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    const data = new FormData(form);
                    data.set('fragment', '1');

                    fetch('{{ route('admin.certificates.preview') }}', {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin',
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then((response) => response.text())
                        .then((html) => { body.innerHTML = html; })
                        .catch(() => {
                            body.innerHTML = '<p class="text-sm">' + @json(setting('admin.certificates.partials.issue.tathr_almaayna')) + '</p>';
                        });
                });
            })();
        </script>
    @endpush
@endif
