@php
    $active = $maintenance->isActive();
    $state = $maintenance->publicState();
    $scheduled = $maintenance->scheduled();
@endphp

{{-- بطاقة الحالة + العدّاد المتبقّي + مَن فعّلها ومنذ متى (24.3) --}}
<div class="card p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <x-state-badge :state="$active ? 'danger' : 'ok'" :label="$active ? 'الصيانة شغّالة' : 'المنصّة شغّالة'" />
                @if ($window)
                    <span class="text-xs" style="color: var(--text-muted)">
                        فعّلها {{ $window->started_by()->first()?->name ?? '—' }} · {{ $window->started_at?->diffForHumans() }}
                    </span>
                @endif
            </div>

            @if ($active)
                {{-- ⭐ نفس مساحة العدّاد بالظبط قبل الصفر وبعده — فلا تقفز الصفحة --}}
                <div class="mt-2 text-2xl font-extrabold" id="maintenance-countdown"
                     data-ends="{{ $state['ends_at'] }}"
                     data-overrun-text="{{ $maintenance->overrunMessage() }}">—</div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('maintenance.manage')
                @if ($active)
                    @foreach ([1, 3] as $hours)
                        <form method="post" action="{{ route('admin.settings.maintenance.extend') }}">
                            @csrf
                            <input type="hidden" name="hours" value="{{ $hours }}">
                            <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">+{{ $hours }} ساعة</button>
                        </form>
                    @endforeach

                    <form method="post" action="{{ route('admin.settings.maintenance.extend') }}" class="flex items-center gap-1">
                        @csrf
                        <input type="number" name="hours" min="1" value="6" class="w-16 rounded-xl px-2 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                               aria-label="تمديد مخصّص بالساعات">
                        <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">تمديد</button>
                    </form>

                    <form method="post" action="{{ route('admin.settings.maintenance.lift') }}">
                        @csrf
                        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">رفع الصيانة</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if ($active)
        <p class="text-xs mt-3" style="color: var(--text-muted)">
            كلّ المهل والديدلاينات مجمّدة دلوقتي، وهتُستأنف من حيث وقفت عند الرفع — استئناف لا إلغاء.
        </p>
    @endif
</div>

@unless ($active)
    @can('maintenance.manage')
        <form method="post" action="{{ route('admin.settings.maintenance.start') }}" class="card p-4 space-y-3">
            @csrf
            <h2 class="font-bold text-sm">تفعيل وضع الصيانة العامّ</h2>

            <label class="block text-sm">
                <span class="block mb-1">رسالة الصيانة (اللي هيقراها المستخدم)</span>
                <textarea name="message" rows="3" required
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                          placeholder="{{ setting('system.maintenance.message') }}">{{ old('message', setting('system.maintenance.message')) }}</textarea>
            </label>

            <div class="grid sm:grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="block mb-1">عدد الساعات</span>
                    <input type="number" name="hours" min="1" required
                           value="{{ old('hours', (int) setting('system.maintenance.default_hours', 2)) }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                {{-- ⭐ «مجدول — يبدأ تلقائيًّا» (12.7-ج): سيبه فاضي تشتغل حالًا --}}
                <label class="block text-sm">
                    <span class="block mb-1">وقت البدء (اختياريّ — سيبه فاضي تبدأ حالًا)</span>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </div>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: color-mix(in srgb, var(--color-state-danger) 25%, transparent); color: var(--color-state-danger)">
                تفعيل وضع الصيانة
            </button>

            {{-- ⛔ ولا صيانة جزئيّة لميزة بعينها — أُلغيت؛ الإطفاء من «مفاتيح المزايا» --}}
            <p class="text-xs" style="color: var(--text-muted)">
                الصيانة عامّة للمنصّة كلّها فقط — إطفاء ميزة بعينها بيتمّ من تاب «مفاتيح المزايا».
            </p>
        </form>
    @endcan
@endunless

{{-- صيانة مجدولة لسّه ما بدأتش — ظاهرة ويمكن إلغاؤها (12.7-ج) --}}
@if ($scheduled['at'] && ! $active)
    <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <x-state-badge state="warn" label="صيانة مجدولة" />
            <span class="ms-2">هتبدأ لوحدها {{ $scheduled['at'] }} لمدّة {{ $scheduled['hours'] }} ساعة.</span>
        </div>
        @can('maintenance.manage')
            <form method="post" action="{{ route('admin.settings.maintenance.unschedule') }}">
                @csrf
                <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">إلغاء الجدولة</button>
            </form>
        @endcan
    </div>
@endif

{{-- ⭐ استثناء IP الأدمن — **مصدر واحد** يُحرَّر من هنا مباشرةً (12.7-ج).
     كان الحقل يظهر في البحث فقط، وكانت للقائمة نسختان في مفتاحين. --}}
@can('maintenance.manage')
    @php($exemptSetting = \App\Models\Setting::query()->where('key', 'system.maintenance.exempt_ips')->first())
    @if ($exemptSetting)
        <div class="card p-4">
            <h2 class="font-bold text-sm mb-2">استثناء IP الأدمن</h2>
            <x-settings.field :setting="$exemptSetting" />
            <p class="text-xs mt-2" style="color: var(--text-muted)">
                سطر أو فاصلة لكلّ IP — دي القائمة الوحيدة اللي بتفتح الموقع وقت الصيانة.
            </p>
        </div>
    @endif
@endcan

<div class="card overflow-hidden">
    <div class="p-3 text-sm font-bold" style="border-bottom: 1px solid var(--border)">فترات الصيانة السابقة</div>

    @forelse ($windows as $row)
        <div class="p-3 text-sm flex flex-wrap items-center justify-between gap-2" style="border-top: 1px solid var(--border)">
            <div>
                <div>{{ $row->started_at?->format('Y/m/d H:i') }} ← {{ $row->ended_at?->format('Y/m/d H:i') ?? 'شغّالة' }}</div>
                <div class="text-xs" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit((string) $row->message, 60) }}</div>
            </div>
            <x-state-badge :state="$row->deadlines_recomputed ? 'ok' : 'warn'"
                           :label="$row->deadlines_recomputed ? 'اتعاد حساب المهل' : 'لسه'" />
        </div>
    @empty
        <div class="p-4"><x-empty message="مافيش فترات صيانة سابقة." /></div>
    @endforelse
</div>

@push('scripts')
<script>
// عدّاد الصيانة — ولا عدّاد سالب أبدًا: الرسالة تتبدّل بنفس المساحة (12.7-و-1)
(function () {
    var node = document.getElementById('maintenance-countdown');
    if (!node) { return; }

    var ends = new Date(node.getAttribute('data-ends')).getTime();
    var overrun = node.getAttribute('data-overrun-text');

    function tick() {
        var left = Math.floor((ends - Date.now()) / 1000);

        if (left <= 0) { node.textContent = overrun; return; }

        var h = Math.floor(left / 3600);
        var m = Math.floor((left % 3600) / 60);
        var s = left % 60;
        node.textContent = h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
