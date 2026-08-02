@php
    use Illuminate\Support\Facades\Route as RouteFacade;

    // الشهادات من **المصدر الواحد** (12.5 / مكتبتي 20) — لا حساب موازٍ (10.0-أ)
    $canSee = $visibility->canSee('certificates', $viewer, $owner, $level);
@endphp

@if (! $canSee)
    <x-empty :message="setting('account.profile.certificates.hidden_message', 'الشهادات مش متاحة على البروفايل ده.')" />
@elseif ($certificates->isEmpty())
    <x-empty :message="setting('account.profile.certificates.empty_message', 'لسّه بدري — أوّل شهادة مستنّياك.')" />
@else
    <div class="grid md:grid-cols-2 gap-3">
        @foreach ($certificates as $certificate)
            @php
                // ⭐ 10.0-أ يطلب **زرّ التحقّق** و**LinkedIn** بجوار كلّ شهادة
                $verifyUrl = RouteFacade::has('verify.certificate')
                    ? route('verify.certificate', ['code' => $certificate->code])
                    : null;

                $shareText = str_replace(
                    [':name', ':platform'],
                    [$certificate->certificate_type?->name_ar ?? '', setting('platform.identity.name', config('app.name'))],
                    (string) setting('growth.linkedin.share_text', 'أتممتُ [المسار] وحصلتُ على شهادة معتمدة.'),
                );

                $linkedInUrl = $verifyUrl
                    ? 'https://www.linkedin.com/sharing/share-offsite/?url='.urlencode($verifyUrl)
                    : null;
            @endphp

            <article class="card p-4 animate-fadeup">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm truncate">{{ $certificate->certificate_type?->name_ar ?? setting('cv.section.certificate_fallback', 'شهادة') }}</h2>
                        <p class="text-xs mt-1 font-mono" style="color: var(--text-muted)">{{ $certificate->code }}</p>
                    </div>
                    {{-- شارة «شهادة معتمدة» ذهبيّة — شرف لا حالة تشغيليّة (2.16) --}}
                    <x-state-badge state="honor" :label="setting('account.profile.certificates.verified_badge', 'شهادة معتمدة')" />
                </div>

                <p class="text-xs mt-3" style="color: var(--text-muted)"
                   title="{{ $certificate->issued_at?->format('Y-m-d') }}">
                    {{ setting('account.profile.certificates.issued_prefix', 'صدرت') }} {{ $certificate->issued_at?->diffForHumans() }}
                </p>

                @if ($verifyUrl)
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <a href="{{ $verifyUrl }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center rounded-xl px-4 text-xs font-semibold motion-standard"
                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            {{ setting('account.profile.certificates.verify_label', 'تحقّق من الشهادة') }}
                        </a>

                        {{-- أيقونة LinkedIn مرسومة SVG بهويّتنا — بلا أيّ مكتبة أيقونات --}}
                        <a href="{{ $linkedInUrl }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-2 rounded-xl px-4 text-xs font-semibold motion-standard"
                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                           title="{{ $shareText }}">
                            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="currentColor">
                                <rect x="0.5" y="4.5" width="3" height="9" rx=".6" />
                                <circle cx="2" cy="2" r="1.6" />
                                <path d="M6 4.5h2.8v1.2A3 3 0 0 1 13.5 8.4v5.1h-3V9A1.4 1.4 0 0 0 9 7.6 1.4 1.4 0 0 0 7.6 9v4.5H6z" />
                            </svg>
                            {{ setting('account.profile.certificates.linkedin_label', 'شارك على LinkedIn') }}
                        </a>
                    </div>
                @endif
            </article>
        @endforeach
    </div>
@endif
