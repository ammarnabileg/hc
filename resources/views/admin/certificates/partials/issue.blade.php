{{-- 3) الإصدار (12.5-ج): بالكود فرديًّا أو جماعيًّا بتحقّق ومعاينة ومنع تكرار --}}
@if ($types->isEmpty())
    <x-empty :message="setting('admin.certificates.partials.issue.jhz_nwa_shhada_alawl_ashan_tqdr_tsdr', 'جهّز نوع شهادة الأوّل عشان تقدر تصدر.')"
             :action="setting('admin.certificates.partials.issue.alanwaa_walqwalb', 'الأنواع والقوالب')" :href="route('admin.certificates.index', ['tab' => 'types'])" />
@else
    <form method="post" action="{{ route('admin.certificates.verify-codes') }}" class="card p-4 space-y-4">
        @csrf

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.nwa_alshhada', 'نوع الشهادة') }}</span>
                <select name="certificate_type_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.certificates.partials.issue.allgha', 'اللغة') }}</span>
                <select name="language" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="ar">{{ setting('admin.certificates.partials.issue.arbya', 'عربيّة') }}</option>
                    <option value="en">{{ setting('admin.certificates.partials.issue.injlyzya', 'إنجليزيّة') }}</option>
                </select>
            </label>
        </div>

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
            <button formaction="{{ route('admin.certificates.preview') }}"
                    class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.certificates.partials.issue.maayna_qbl_alisdar', 'معاينة قبل الإصدار') }}</button>
        </div>
    </form>
@endif
