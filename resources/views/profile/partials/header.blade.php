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
{{--
  ⭐ هيدر البروفايل — حرفيًّا من ملف الهويّة (`.profile-head`): أفاتار كبير +
  عنوان علويّ + H1 + سطر بيانات، وأفعال مكدّسة على الجهة الأخرى.
--}}
<section class="profile-head">
    <div class="relative">
        <x-avatar :user="$owner" size="28" />
        {{-- نقطة نشاط فقط — **بلا «آخر ظهور»** (13.4-م) --}}
        @if (($overview['is_online'] ?? false))
            <span class="absolute bottom-1 start-1 w-3 h-3 rounded-full animate-pulse"
                  style="background: var(--color-state-ok); outline: 2px solid var(--surface-raised)"
                  title="{{ setting('account.profile.header.online_label', 'نشط دلوقتي') }}" aria-label="{{ setting('account.profile.header.online_label', 'نشط دلوقتي') }}"></span>
        @endif
    </div>

    <div class="min-w-0">
        <span class="eyebrow">{{ setting('profile.header.eyebrow', 'ملف المتدرّب') }}</span>
        <div class="flex flex-wrap items-center gap-2">
            <h1>{{ $owner->name }}</h1>
            @if ($isVolunteerHeader)
                {{-- لقب «مشرف» (10.0-ب) --}}
                <span class="pill" style="color: var(--brand); border-color: var(--brand)">{{ setting('account.profile.header.supervisor_label', 'مشرف') }}</span>
            @endif
        </div>

        <div class="cluster small muted mt-2">
            <bdi>#{{ $owner->code }}</bdi>
            <span>{{ setting('profile.header.text_1', 'مستوى الحساب') }} {{ $owner->level }}</span>
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
                              placeholder="{{ setting('account.profile.header.bio_placeholder', 'اكتب نبذة قصيرة عنك، سطر واحد يكفي.') }}"
                              style="max-width: 420px">{{ $owner->bio }}</textarea>
                </label>
                <span data-bio-saved class="save-indicator" style="visibility: hidden">{{ setting('cv.autosave.saved_label', 'اتحفظ ✓') }}</span>
            </div>
        @elseif (trim((string) $owner->bio) !== '')
            <p class="mt-3">{{ $owner->bio }}</p>
        @endif

        @if ($isVolunteerHeader)
            <div class="cluster mt-3">
                {{-- البوزشن والكيان --}}
                <span class="pill">
                    {{ $membership->position?->name_ar }}
                    @if ($membership->entity) · {{ $membership->entity->name_ar }} @endif
                </span>

                {{-- شارة Rep بلونها ورمزها (2.16) --}}
                @if ($rep)
                    <x-state-badge :state="$rep['state']" :label="'Rep '.rtrim(rtrim(number_format($rep['score'], 1), '0'), '.')" />

                    {{-- شارة نادي التميّز الذهبيّة بلمعان — شرف لا حالة (2.16) --}}
                    @if ($rep['in_club'])
                        <span class="status honor animate-shimmer">
                            <x-icon name="award" size="14" /> {{ setting('profile.header.text_2', 'نادي التميّز') }}
                        </span>
                    @endif
                @endif
            </div>
        @endif

        @if ($level !== ProfileVisibility::OWNER)
            {{-- سطر واحد يوضّح مستوى المشاهدة — سطر لكلّ شرح (2.15-أ-8) --}}
            <p class="small muted mt-3">{{ $levelLabel }}، {{ setting('profile.header.text_4', 'البيانات الحسّاسة مخفيّة افتراضيًّا.') }}</p>
        @endif
    </div>

    <div class="actions stack" style="gap: 8px">
        {{-- [نسخ رابطي /u/CODE] — الرابط الدائم يعمل من كلّ مكان (13.4-م) --}}
        <button type="button" data-copy-profile="{{ $profileUrl }}" class="btn btn-p inline-flex items-center gap-2">
            <x-icon name="share" size="16" /> {{ setting('account.profile.header.copy_link_label', 'نسخ رابطي') }}
        </button>

        {{-- ⭐ «مشاركة الحساب» ⟵ بوب-أب فيه أزرار المشاركة + الرابط العامّ (10) --}}
        <button type="button" data-modal-open="profile-share" class="btn text">
            {{ setting('profile.header.text_3', 'مشاركة الحساب') }}
        </button>

        @if ($isOwner)
            <a href="{{ route('settings.index') }}" class="btn text">{{ setting('account.profile.header.edit_label', 'تعديل البروفايل') }}</a>
        @endif
    </div>
</section>
