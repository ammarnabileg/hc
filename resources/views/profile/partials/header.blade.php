@php
    use App\Http\Controllers\Ui\ProfileExtrasController;
    use App\Services\Account\ProfileVisibility;

    $isVolunteerHeader = (bool) $membership;

    // زرّ «مشاركة الحساب» (10) — الروابط تُبنى على الخادم فلا تتسرّب صياغة خاطئة
    $shareLinks = ProfileExtrasController::shareLinks($owner);
    $bioMax = max(20, (int) setting('profile.bio.max_chars', 280));

    // لقب السفير (7.6.1) — ألقاب بلا شارات (2.9-8)
    $ambassadorTitle = trim((string) ($owner->ambassador_title ?? ''));
@endphp

{{--
  هيدر البروفايل (10.0-ب · 13.4-م):
  ⭐ الأفاتار **بلا أيّ هالة** — قاعدة صريحة (2.10.1-16)، ومكوّن x-avatar يفرضها.
  وللمتطوّع تزيد: لقب «مشرف» · البوزشن · شارة Rep · شارة نادي التميّز الذهبيّة.
--}}
<header class="card p-5 mb-4">
    <div class="flex flex-wrap items-start gap-4">

        <div class="relative">
            <x-avatar :user="$owner" size="20" />
            {{-- نقطة نشاط فقط — **بلا «آخر ظهور»** (13.4-م) --}}
            @if (($overview['is_online'] ?? false))
                <span class="absolute bottom-1 start-1 w-3 h-3 rounded-full animate-pulse"
                      style="background: var(--color-state-ok); outline: 2px solid var(--surface-raised)"
                      title="{{ setting('account.profile.header.online_label', 'نشط دلوقتي') }}" aria-label="{{ setting('account.profile.header.online_label', 'نشط دلوقتي') }}"></span>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl md:text-2xl font-extrabold">{{ $owner->name }}</h1>

                @if ($isVolunteerHeader)
                    {{-- لقب «مشرف» (10.0-ب) --}}
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          style="background: color-mix(in srgb, var(--color-brand-500) 18%, transparent); color: var(--color-brand-500)">{{ setting('account.profile.header.supervisor_label', 'مشرف') }}</span>
                @endif
            </div>

            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm" style="color: var(--text-muted)">
                <span class="font-mono">#{{ $owner->code }}</span>
                <span>مستوى الحساب {{ $owner->level }}</span>

                {{-- ⭐ المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (12.14-د) --}}
                @if ($owner->governorate)
                    <span>{{ $owner->country?->name_ar }}{{ $owner->country && $owner->governorate ? ' · ' : '' }}{{ $owner->governorate->name_ar }}</span>
                @elseif ($owner->country && $visibility->canSee('country', $viewer, $owner, $level))
                    <span>{{ $owner->country->name_ar }}</span>
                @endif
            </div>

            {{-- بايو (Bio) قابل للتعديل (10) — بحفظ تلقائيّ و«اتحفظ ✓» بجواره (2.17-ب) --}}
            @if ($isOwner)
                <div class="mt-3">
                    <label class="block">
                        <span class="sr-only">{{ setting('account.profile.header.bio_label', 'النبذة الشخصيّة') }}</span>
                        <textarea data-bio-field rows="2" maxlength="{{ $bioMax }}"
                                  placeholder="{{ setting('account.profile.header.bio_placeholder', 'اكتب نبذة قصيرة عنك — سطر واحد يكفي.') }}"
                                  class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $owner->bio }}</textarea>
                    </label>
                    <span data-bio-saved class="text-xs" style="color: var(--color-state-ok); visibility: hidden">{{ setting('cv.autosave.saved_label', 'اتحفظ ✓') }}</span>
                </div>
            @elseif (trim((string) $owner->bio) !== '')
                <p class="mt-3 text-sm" style="color: var(--text)">{{ $owner->bio }}</p>
            @endif

            @if ($isVolunteerHeader)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    {{-- البوزشن والكيان --}}
                    <span class="rounded-full px-3 py-1 text-xs"
                          style="background: var(--surface-sunken); color: var(--text)">
                        {{ $membership->position?->name_ar }}
                        @if ($membership->entity) · {{ $membership->entity->name_ar }} @endif
                    </span>

                    {{-- شارة Rep بلونها ورمزها (2.16) --}}
                    @if ($rep)
                        <x-state-badge :state="$rep['state']" :label="'Rep '.rtrim(rtrim(number_format($rep['score'], 1), '0'), '.')" />

                        {{-- شارة نادي التميّز الذهبيّة بلمعان — شرف لا حالة (2.16) --}}
                        @if ($rep['in_club'])
                            <span class="animate-shimmer rounded-full px-3 py-1 text-xs font-bold"
                                  style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">
                                ★ نادي التميّز
                            </span>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col items-stretch gap-2 w-full md:w-auto">
            {{-- [نسخ رابطي /u/CODE] — الرابط الدائم يعمل من كلّ مكان (13.4-م) --}}
            <button type="button" data-copy-profile="{{ $profileUrl }}"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('account.profile.header.copy_link_label', 'نسخ رابطي') }}</button>

            {{-- ⭐ «مشاركة الحساب» ⟵ بوب-أب فيه أزرار المشاركة + الرابط العامّ (10) --}}
            <button type="button" data-modal-open="profile-share"
                    class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                مشاركة الحساب
            </button>

            @if ($isOwner)
                <a href="{{ route('settings.index') }}"
                   class="btn text-center rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('account.profile.header.edit_label', 'تعديل البروفايل') }}</a>
            @endif
        </div>
    </div>

    @if ($level !== ProfileVisibility::OWNER)
        {{-- سطر واحد يوضّح مستوى المشاهدة — سطر لكلّ شرح (2.15-أ-8) --}}
        <p class="mt-4 text-xs" style="color: var(--text-muted)">{{ $levelLabel }} — البيانات الحسّاسة مخفيّة افتراضيًّا.</p>
    @endif
</header>
