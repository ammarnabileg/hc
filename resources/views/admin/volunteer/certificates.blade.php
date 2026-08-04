@extends('layouts.admin')

@section('title', setting('admin.volunteer.certificates.shhadat_alttwa', 'شهادات التطوّع'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.certificates.shhadat_alttwa', 'شهادات التطوّع')"
        :subtitle="setting('admin.volunteer.certificates.arbaa_anwaa_la_khams_lha_mjanya_balkaml_wbla', 'أربعة أنواع لا خامس لها — مجّانيّة بالكامل، وبلا أيّ أرقام داخليّة على الورقة.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.certificates.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.certificates.alshhadat', 'الشهادات')]]">
        <x-slot:action>
            @can('volunteer_certificates.create')
                <form method="post" action="{{ route('admin.volunteer.certificates.auto-issue') }}">
                    @csrf
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.certificates.shghl_alisdar_altlqayy', 'شغّل الإصدار التلقائيّ') }}</button>
                </form>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'certificates'])

    {{-- شرطا الاستحقاق معلنان في أعلى الشاشة — مانع التضخّم (13.4-ع-ب) --}}
    <div class="card p-3 mb-4 text-sm space-y-1">
        <div>{{ setting('admin.volunteer.certificates.almda_fy_albwzshn', '① المدّة في البوزشن ≥') }} <strong>{{ $minDays }}</strong> {{ setting('admin.volunteer.certificates.ywma', 'يومًا (') }}<code>volunteer_cert.min_days_in_position</code>).</div>
        <div>② <strong>{{ setting('admin.volunteer.certificates.drja_alaltzam_ghyr_salba', 'درجة الالتزام غير سالبة') }}</strong> {{ setting('admin.volunteer.certificates.wqt_alisdar', 'وقت الإصدار.') }}</div>
        <div><x-icon name="lock" size="16" /> {{ setting('admin.volunteer.certificates.shhada_wahda_lkl_bwzshn_kyan_waltrqya_tsdr', 'شهادة واحدة لكلّ (بوزشن × كيان) — والترقية تُصدر الأعلى لا نسخة مكرّرة.') }}</div>
    </div>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        @foreach ($types as $key => $type)
            <div class="card p-4">
                <div class="text-sm font-semibold">{{ $type['label'] }}</div>
                <div class="mt-2"><x-state-badge :state="$type['enabled'] ? 'ok' : 'idle'" :label="$type['enabled'] ? setting('admin.volunteer.certificates.mfala', 'مفعّلة') : setting('admin.volunteer.certificates.mwqwfa', 'موقوفة')" /></div>
            </div>
        @endforeach
    </section>

    {{-- مستحقّ ولم تُصدَر — الإصدار التلقائيّ يغطّيها، وهنا الإصدار اليدويّ --}}
    <section class="card p-4 md:p-5">
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
    </section>

    {{-- السجلّ الصادر --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.certificates.alsjl_alsadr', 'السجلّ الصادر') }}</h2>

        @forelse ($issued as $certificate)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $certificate->user?->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        <code>{{ $certificate->code }}</code> · {{ $certificate->issued_at?->format('Y-m-d') }}
                        @if (($certificate->data_snapshot['position'] ?? null))
                            · {{ $certificate->data_snapshot['position'] }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-state-badge :state="match ($certificate->status) { 'valid' => 'ok', 'expired' => 'idle', default => 'danger' }"
                                   :label="match ($certificate->status) { 'valid' => setting('admin.volunteer.certificates.sarya', 'سارية'), 'expired' => setting('admin.volunteer.certificates.mnthya', 'منتهية'), default => setting('admin.volunteer.certificates.mlghaa', 'ملغاة') }" />
                    @can('volunteer_certificates.edit')
                        @if ($certificate->status === 'valid')
                            <button type="button" class="text-xs underline" style="color: var(--color-state-danger)"
                                    data-revoke data-id="{{ $certificate->id }}">{{ setting('admin.volunteer.certificates.ilgha', 'إلغاء') }}</button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <x-empty :message="setting('admin.volunteer.certificates.la_shhadat_sadra_bad', 'لا شهادات صادرة بعد.')" />
        @endforelse
    </section>

    @can('volunteer_certificates.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.volunteer.certificates.shrwt_alasthqaq_walanwaa_walqwalb', 'شروط الاستحقاق والأنواع والقوالب'),
            'rows' => $settings,
            'action' => route('admin.volunteer.certificates.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_cert'),
            'lockedKeys' => [
                'volunteer_cert.one_per_position_entity',
                'volunteer_cert.free_locked',
                'volunteer_cert.hide_internal_numbers',
                'volunteer_cert.revoke_only_on_fraud',
            ],
            'open' => true,
        ])
    @endcan
@endsection

@push('modals')
    @can('volunteer_certificates.edit')
        <x-modal id="revoke-modal" :title="setting('admin.volunteer.certificates.ilgha_shhada_lltzwyr_almthbt_whdh', 'إلغاء شهادة — للتزوير المثبَت وحده')">
            <form method="post" action="{{ route('admin.volunteer.certificates.revoke', 0) }}" id="revoke-form">
                @csrf
                <p class="text-sm mb-3" style="color: var(--color-state-warn)">
                    {{ setting('admin.volunteer.certificates.alilgha_astthna_wahd', '▲ الإلغاء استثناء واحد:') }} <strong>{{ setting('admin.volunteer.certificates.altzwyr_aw_alghsh_almthbt', 'التزوير أو الغشّ المثبَت') }}</strong>{{ setting('admin.volunteer.certificates.waliqsa_whdh', '. والإقصاء وحده') }} <strong>{{ setting('admin.volunteer.certificates.la_ylghy', 'لا يُلغي') }}</strong> {{ setting('admin.volunteer.certificates.shhada_an_aml_hqyqy', 'شهادةً عن عمل حقيقيّ.') }}
                </p>

                <label class="block text-sm font-semibold mb-1" for="rev-reason">{{ setting('admin.volunteer.certificates.alqrar_almwthq', 'القرار الموثّق') }}</label>
                <textarea name="reason" id="rev-reason" rows="3" required minlength="10" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <label class="flex items-center gap-2 text-sm mb-3">
                    <input type="checkbox" name="fraud_confirmed" value="1" required>
                    {{ setting('admin.volunteer.certificates.aqr_ban_altzwyr_mthbt_wmwthq', 'أُقرّ بأنّ التزوير مثبَت وموثّق.') }}
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-state-danger); color: #fff">{{ setting('admin.volunteer.certificates.algh_alshhada', 'ألغِ الشهادة') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-revoke]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const form = document.getElementById('revoke-form');
                form.action = '{{ route('admin.volunteer.certificates.revoke', 0) }}'.replace(/0$/, btn.dataset.id);
                const modal = document.getElementById('revoke-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
