{{-- «شاشة أوّل مرّة» على أهمّ الشاشات فقط منعًا للزحام (2.15-د) --}}
<div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
    {{ setting('admin.ops.partials.onboarding_first_time.alshasha_almfala_tzhr_llmstkhdm', 'الشاشة المفعَّلة تظهر للمستخدم') }} <strong>{{ setting('admin.ops.partials.onboarding_first_time.bwb_ab_bmrahl', 'بوب-أب بمراحل') }}</strong> {{ setting('admin.ops.partials.onboarding_first_time.awl_zyara_w', 'أوّل زيارة، و') }}{{ setting('onboarding.first_time.replay_hint', 'زرّ «؟» يعيد الشرح وقت ما تحبّ.') }}
</div>

<form method="post" action="{{ route('admin.ops.onboarding.first-time') }}" class="card p-4 space-y-3">
    @csrf

    @foreach ($screens as $key => $label)
        @php
            $count = $counts[$key]['active'] ?? 0;
            $hasTemplate = isset($templates[$key]);
        @endphp
        <label class="flex items-start gap-3 p-2 rounded-xl" style="background: var(--surface-sunken); min-height: 44px">
            <input type="checkbox" name="screens[]" value="{{ $key }}" class="mt-1"
                   @checked(in_array($key, $enabled, true))>
            <span class="min-w-0">
                <span class="block text-sm font-semibold">{{ $label }}</span>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                    {{ $count }} {{ setting('admin.ops.partials.onboarding_first_time.mrhla_mfala', 'مرحلة مفعَّلة') }}
                    @if ($hasTemplate) · <x-icon name="game" size="16" /> {{ setting('admin.ops.partials.onboarding_first_time.fyh_qalb_jahz', 'فيه قالب جاهز') }} @endif
                    @if ($count === 0) {{ setting('admin.ops.partials.onboarding_first_time.mhtaja_mhtwa_qbl_ma_tfalha', '· محتاجة محتوى قبل ما تفعّلها') }} @endif
                </span>
            </span>
            <span class="ms-auto shrink-0">
                <x-state-badge :state="$count > 0 ? 'ok' : 'warn'" :label="$count > 0 ? setting('admin.ops.partials.onboarding_first_time.jahza', 'جاهزة') : setting('admin.ops.partials.onboarding_first_time.fadya', 'فاضية')" />
            </span>
        </label>
    @endforeach

    @can('onboarding.edit')
        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.ops.partials.onboarding_first_time.hfz_alshashat_almfala', 'حفظ الشاشات المفعَّلة') }}</button>
    @endcan
</form>

<div class="card p-4 mt-4">
    <h2 class="text-sm font-bold mb-2">{{ setting('admin.ops.partials.onboarding_first_time.alqwalb_aljahza', 'القوالب الجاهزة') }}</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_first_time.alqalb_nqta_bdaya_bytdaf_kmrahl_tqdr_tadlha', 'القالب نقطة بداية — بيتضاف كمراحل تقدر تعدّلها أو تمسحها.') }}</p>

    <div class="space-y-2">
        @forelse ($templates as $key => $stages)
            <div class="flex items-center justify-between gap-2 p-2 rounded-xl" style="background: var(--surface-sunken)">
                <span class="text-sm min-w-0 truncate">{{ $screens[$key] ?? $key }} · {{ count($stages) }} {{ setting('admin.ops.partials.onboarding_first_time.mrhla', 'مرحلة') }}</span>
                @can('onboarding.create')
                    <form method="post" action="{{ route('admin.ops.onboarding.template') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="screen" value="{{ $key }}">
                        <button class="rounded-xl px-3 py-2 text-xs" style="background: var(--surface-raised); min-height: 44px">{{ setting('admin.ops.partials.onboarding_first_time.astkhdm_alqalb', 'استخدم القالب') }}</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_first_time.mafysh_qwalb_jahza_lsh_dyfha_mn_iadadat', 'مافيش قوالب جاهزة لسه — ضيفها من إعدادات الـOnboarding.') }}</p>
        @endforelse
    </div>
</div>
