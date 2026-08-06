{{-- مستحقّ ولم تُصدَر — الإصدار التلقائيّ يغطّيها، وهنا الإصدار اليدويّ (24.2 فلتر «مستحقّ ولم تُصدَر») --}}
<div class="flex items-center justify-between gap-3 flex-wrap mb-3">
    <h2 class="font-bold">{{ setting('admin.volunteer.certificates.msthq_wlm_tsdr', 'مستحقّ ولم تُصدَر') }}</h2>
    <span class="text-xs" style="color: var(--text-muted)">
        {!! strtr(setting('admin.volunteer.certificates.alisdar_altlqayy_v1_ahtfal_almstwa_v2_dhrwa', 'الإصدار التلقائيّ: :v1 · احتفال المستوى :v2 (ذروة)'), [':v1' => e(setting('volunteer_cert.auto_issue', true) ? setting('admin.volunteer.certificates.mfal', 'مفعَّل') : setting('admin.volunteer.certificates.mwqwf', 'موقوف')), ':v2' => e(setting('volunteer_cert.celebration_tier', 3))]) !!}
    </span>
</div>

@forelse ($pending as $row)
    <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
        <div class="min-w-0">
            <div class="truncate font-semibold">{{ $row['membership']->user?->name }}</div>
            <div class="text-xs" style="color: var(--text-muted)">
                {{ $row['membership']->position?->name_ar }} · {{ $row['membership']->entity?->name_ar }} · {{ $row['days'] }} {{ setting('admin.volunteer.certificates.ywma_2', 'يومًا') }}
            </div>
        </div>
        @can('volunteer_certificates.create')
            <form method="post" action="{{ route('admin.volunteer.certificates.issue') }}">
                @csrf
                <input type="hidden" name="membership_id" value="{{ $row['membership']->id }}">
                <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.certificates.asdr', 'أصدر') }}</button>
            </form>
        @endcan
    </div>
@empty
    <x-empty :message="setting('admin.volunteer.certificates.mfysh_msthqyn_dlwqty_alshrwt_bthmy_qyma', 'مفيش مستحقّين دلوقتي — الشروط بتحمي قيمة الشهادة.')" />
@endforelse
