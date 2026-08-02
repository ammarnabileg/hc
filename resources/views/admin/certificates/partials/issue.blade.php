{{-- 3) الإصدار (12.5-ج): بالكود فرديًّا أو جماعيًّا بتحقّق ومعاينة ومنع تكرار --}}
@if ($types->isEmpty())
    <x-empty message="جهّز نوع شهادة الأوّل عشان تقدر تصدر."
             action="الأنواع والقوالب" :href="route('admin.certificates.index', ['tab' => 'types'])" />
@else
    <form method="post" action="{{ route('admin.certificates.verify-codes') }}" class="card p-4 space-y-4">
        @csrf

        <div class="grid md:grid-cols-2 gap-3">
            <label class="block">
                <span class="block text-sm mb-1">نوع الشهادة</span>
                <select name="certificate_type_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="block text-sm mb-1">اللغة</span>
                <select name="language" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="ar">عربيّة</option>
                    <option value="en">إنجليزيّة</option>
                </select>
            </label>
        </div>

        <label class="block">
            <span class="block text-sm mb-1">أكواد الأشخاص</span>
            <textarea name="codes" rows="4" required
                      placeholder="{{ setting('certificates.issue.codes_placeholder', 'الصق الأكواد مفصولة بمسافة أو فاصلة…') }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                كود واحد للإصدار الفرديّ، أو لحدّ {{ $batchLimit }} كود للإصدار الجماعيّ.
            </span>
        </label>

        <div class="flex gap-2 flex-wrap">
            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">تحقّق من الأكواد</button>
            <button formaction="{{ route('admin.certificates.preview') }}"
                    class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">معاينة قبل الإصدار</button>
        </div>
    </form>
@endif
