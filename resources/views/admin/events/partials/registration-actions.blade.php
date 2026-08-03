{{--
  أفعال الصفّ داخل الصفّ نفسه (2.15-ب) — والمحظور **يُخفى لا يُعطَّل** (2.15-أ-7).
  و«منح مكافأة يدويّ» يفتح **12.9** محمَّلًا بكود المستخدم كما ينصّ 24.3، فلا
  تتكرّر شاشة المنح هنا (مصدر واحد — صفر ازدواج).
--}}
<div class="flex flex-wrap items-center gap-2">
    @if ($canToggle)
        <form method="post" action="{{ route('admin.events.attendance.toggle', $registration) }}">
            @csrf
            <button type="submit" class="text-xs underline" style="min-height: 44px">
                {{ $registration->attended
                    ? setting('events.registrations.mark_absent', 'علّم غائبًا')
                    : setting('events.registrations.mark_attended', 'علّم حاضرًا') }}
            </button>
        </form>
    @endif

    @if ($canGrant && $registration->user)
        <a href="{{ route('admin.rewards.index', ['code' => $registration->user->code]) }}"
           class="text-xs underline inline-flex items-center" style="min-height: 44px">
            {{ setting('events.registrations.grant_manual', 'منح مكافأة يدويّ') }}
        </a>
    @endif
</div>
