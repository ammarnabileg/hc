@php
    use Illuminate\Support\Facades\Route;

    $u = auth()->user();
    $unread = $u->notificationsFeed()->whereNull('read_at')->count();
    // نقطة انطلاق الاستطلاع اللحظيّ (Toast — 2.8): لا نُطالِع القديم عند أوّل نبضة
    $lastNotificationId = (int) ($u->notificationsFeed()->max('id') ?? 0);

    /*
     | ⭐ الـTopbar حرفيًّا من ملف الهويّة (`#topbar`): Breadcrumb على جهة البداية
     | و`.top-actions` على جهة النهاية — بلا شعار (الشعار في رأس السايد بار) ولا
     | أفاتار (بطاقة البروفايل في السايد بار) ولا أيقونة بحث (بحث السايد بار).
     |
     | العنوان والـBreadcrumb يصلان من `x-page-header` عبر `View::share('hcPage')`:
     | قالب الصفحة يُنفَّذ قبل الـLayout، فما شاركه المكوّن يراه هذا الجزء.
     | وصفحةٌ بلا `x-page-header` تقع على `@section('title')` وجذر «المنصّة».
     */
    $page = $hcPage ?? [];
    $pageTitle = (string) ($page['title'] ?? $__env->yieldContent('title', config('app.name')));
    $crumbs = array_values((array) ($page['breadcrumbs'] ?? []));
    $homeUrl = Route::has('dashboard') ? route('dashboard') : '/';

    /*
     | ⭐ التثبيت (Pin) — البديل المعتمَد عن «آخر ما زرت» المرفوض (2.15-د): المرجع
     | يضع زرّه في `.top-actions` بجوار الجرس، فهنا موضعه لا في هيدر الصفحة.
     */
    $pinRoute = request()->route()?->getName();
    $showPin = ($page['pinnable'] ?? true) && $pinRoute && Route::has($pinRoute);
    $isPinned = $showPin && collect($u->pinned_pages ?? [])->pluck('route')->contains($pinRoute);
@endphp

{{-- شريط نادي الخامسة العلويّ: يظهر داخل النافذة بتوقيت المستخدم وحدها (7.2) --}}
@include('achievements.components.club-topbar')

<header id="topbar" class="sticky top-0 z-50 flex items-center justify-between gap-5 px-4 md:px-6"
        style="height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--border)">

    {{-- الموبايل: زرّ اللوحة المنزلقة + سطر الشعار الصغير + عنوان الصفحة (13 · 2.15-ج) --}}
    <div class="flex md:hidden items-center gap-2 min-w-0 flex-1">
        <button type="button" class="icon-button shrink-0" data-drawer-toggle
                aria-label="{{ setting('nav.header.menu_aria', 'القائمة') }}"><x-icon name="menu" size="20" /></button>
        <div class="min-w-0">
            <span class="mobile-brandline"><span class="brand-dot"></span>{{ config('app.name') }}</span>
            <strong class="mobile-page-title truncate">{{ $pageTitle }}</strong>
        </div>
    </div>

    {{-- الديسكتوب: Breadcrumb في كلّ شاشة داخليّة (2.15-د) — جذره «المنصّة» ثمّ الفتات ثمّ الصفحة --}}
    <nav class="breadcrumb hidden md:flex min-w-0" aria-label="{{ setting('nav.header.breadcrumb_aria', 'أنت هنا') }}">
        <a href="{{ $homeUrl }}" class="extra-crumb hover:underline inline-flex items-center shrink-0"
           style="min-inline-size: var(--touch-min, 44px); min-block-size: var(--touch-min, 44px)">{{ config('app.name') }}</a>
        @forelse ($crumbs as $crumb)
            <x-icon name="left" size="15" />
            @if (! $loop->last)
                <a href="{{ $crumb['url'] ?? '#' }}" class="hover:underline inline-flex items-center shrink-0"
                   style="min-inline-size: var(--touch-min, 44px); min-block-size: var(--touch-min, 44px)">{{ $crumb['label'] }}</a>
            @else
                <span class="truncate" aria-current="page">{{ $crumb['label'] }}</span>
            @endif
        @empty
            <x-icon name="left" size="15" />
            <span class="truncate" aria-current="page">{{ $pageTitle }}</span>
        @endforelse
    </nav>

    {{-- الأفعال: الجرس ثمّ التثبيت ثمّ سويتش «وضع متقدّم» في أقصى الجهة (تعليمات المالك) --}}
    <div class="top-actions shrink-0">
        {{-- مبدّل سياق العضويّة (قسم/محافظة/ملفّ) — كلّ شيء يُقرأ داخل العضويّة النشطة --}}
        @volunteer
            @if (($userMemberships ?? collect())->count() > 1)
                <form method="get" class="hidden sm:block">
                    <select name="membership" onchange="this.form.submit()"
                            class="rounded-lg px-3 text-sm"
                            style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                            aria-label="{{ setting('nav.header.membership_aria', 'سياق العضويّة') }}">
                        @foreach ($userMemberships as $m)
                            <option value="{{ $m->id }}" @selected(($activeMembership?->id) === $m->id)>
                                {{ $m->entity?->name_ar }} · {{ $m->position?->name_ar }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        @endvolunteer

        {{-- جرس الإشعارات بتاباته: الكلّ · المنصّة · التطوّع (2.8) --}}
        <div class="relative" x-data="{ open: false }" data-notifications-poll
             data-last-id="{{ $lastNotificationId }}" data-poll-url="{{ route('notifications.poll') }}"
             data-poll-seconds="{{ (int) setting('notifications.toast.poll_seconds', 20) }}">
            <button type="button" class="icon-button relative" data-bell aria-label="{{ setting('nav.header.bell_aria', 'الإشعارات') }}">
                <x-icon name="bell" size="20" />
                <span @class(['absolute top-1 end-1 text-[10px] rounded-full px-1.5', 'hidden' => $unread <= 0]) data-unread-badge
                      style="background: var(--color-state-danger); color: #fff">{{ $unread }}</span>
            </button>
            @include('partials.bell')
        </div>

        {{-- التثبيت للديسكتوب/التابلت كالمرجع (`desktop-only`): المثبَّتة تعيش في السايد بار، وهو Drawer على الموبايل --}}
        @if ($showPin)
            <button type="button" data-pin-toggle="{{ $pinRoute }}" data-pin-label="{{ $pageTitle }}"
                    data-pinned="{{ $isPinned ? '1' : '0' }}" class="icon-button hidden md:inline-flex"
                    aria-pressed="{{ $isPinned ? 'true' : 'false' }}"
                    style="color: {{ $isPinned ? 'var(--color-brand-500)' : 'var(--text-muted)' }}"
                    aria-label="{{ $isPinned ? (string) setting('ux.page_header.aria_label_expr_1', 'فكّ تثبيت الصفحة') : (string) setting('ux.page_header.aria_label_expr_2', 'ثبّت الصفحة أعلى السايد بار') }}"
                    title="{{ $isPinned ? (string) setting('ux.page_header.title_expr_1', 'مثبَّتة') : (string) setting('ux.page_header.title_expr_2', 'ثبّت الصفحة') }}">
                <x-icon name="pin" size="18" />
            </button>
        @endif

        <x-advanced-toggle />
    </div>
</header>
