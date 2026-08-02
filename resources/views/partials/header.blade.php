@php
    $u = auth()->user();
    $unread = $u->notificationsFeed()->whereNull('read_at')->count();
@endphp

<header class="sticky top-0 z-50 flex items-center gap-3 px-4 py-3"
        style="height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--border)">

    <button type="button" class="md:hidden text-xl" data-drawer-toggle aria-label="القائمة">☰</button>

    <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/' }}"
       class="font-extrabold" style="color: var(--color-brand-500)">{{ config('app.name') }}</a>

    <div class="flex-1"></div>

    {{-- مبدّل سياق العضويّة (قسم/محافظة/ملفّ) — كلّ شيء يُقرأ داخل العضويّة النشطة --}}
    @volunteer
        @if (($userMemberships ?? collect())->count() > 1)
            <form method="get" class="hidden sm:block">
                <select name="membership" onchange="this.form.submit()"
                        class="rounded-xl px-3 py-1.5 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                        aria-label="سياق العضويّة">
                    @foreach ($userMemberships as $m)
                        <option value="{{ $m->id }}" @selected(($activeMembership?->id) === $m->id)>
                            {{ $m->entity?->name_ar }} — {{ $m->position?->name_ar }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    @endvolunteer

    {{-- جرس الإشعارات بتاباته: الكلّ · المنصّة · التطوّع (2.8) --}}
    <div class="relative" x-data="{ open: false }">
        <button type="button" class="relative text-xl" data-bell aria-label="الإشعارات">
            🔔
            @if ($unread > 0)
                <span class="absolute -top-1 -end-1 text-[10px] rounded-full px-1.5"
                      style="background: var(--color-state-danger); color: #fff">{{ $unread }}</span>
            @endif
        </button>
        @include('partials.bell')
    </div>

    <a href="{{ \Illuminate\Support\Facades\Route::has('profile.me') ? route('profile.me') : '#' }}">
        <x-avatar :user="$u" size="9" />
    </a>
</header>
