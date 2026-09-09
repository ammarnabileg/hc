{{--
    ⭐ [2026-09-10] دعوة عضوٍ لملفٍّ مؤقّتٍ مفتوحٍ بالفعل (case_files.assign —
    سطر 1517) — برابط دعوة أو بإضافة مباشرة بكود العضو. كانت `FileDrafts::
    generateInviteLink()` جاهزةً بلا أيّ زرٍّ يستدعيها بعد فتح الملفّ.
--}}
<x-modal :id="'case-file-assign-'.$entity->id" :title="setting('admin.volunteer.org.daawt_ado_llmlf', 'دعوة عضو للملفّ').' — '.$entity->name_ar">
    <form method="post" action="{{ route('admin.volunteer.org.case-files.assign', $entity) }}" class="space-y-3">
        @csrf
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.volunteer.org.albwzshn', 'البوزشن') }}</span>
            <select name="position_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($caseFilePositions as $position)
                    <option value="{{ $position->id }}">{{ $position->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.volunteer.org.kwd_aldw_akhtyary', 'كود العضو (اختياريّ)') }}</span>
            <input type="text" name="user_code" class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                {{ setting('admin.volunteer.org.sybh_fady_lywld_rabt_daawa_bdl_alidafa_almbashra', 'سيبه فاضي ليولِّد رابط دعوة بدل الإضافة المباشرة.') }}
            </span>
        </label>

        <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.abat_2', 'ابعت') }}</button>
    </form>

    @if ($caseFileInviteLinks->get($entity->id, collect())->isNotEmpty())
        <div class="mt-4 pt-3 text-xs space-y-1" style="border-top: 1px solid var(--border)">
            <strong class="block mb-1">{{ setting('admin.volunteer.org.rwabt_aldawa_almwlda', 'روابط الدعوة المولَّدة') }}</strong>
            @foreach ($caseFileInviteLinks->get($entity->id) as $link)
                <div class="truncate">
                    {{ $link->position?->name_ar }} —
                    <span style="color: var(--text-muted)">{{ $link->isExpired() ? setting('admin.volunteer.org.mnthy', 'منتهٍ') : route('volunteer.file-invites.show', $link->token) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-modal>
