{{-- الاحتفالات (2.14): ربط كلّ حدث بمستواه · الصوت · نصّ التهنئة · الحدّ اليوميّ للذروة --}}

<div class="card p-3 mb-4 text-sm space-y-1">
    <div>🔒 <strong>ثلاثة مستويات لا رابع</strong> — والحدّ اليوميّ لمستوى الذروة:
        <strong>{{ setting('celebrations.peak.daily_cap', 3) }}</strong> كي تبقى الذروة ذروةً.</div>
    <div>⭐ <strong>الأنيميشن حاضر دائمًا بلا توجل</strong> — والصوت وحده يخضع لتوجل الصوت في البروفايل.</div>
    <div>🔒 ممنوع الاحتفال بحدث سلبيّ أو بشراء بلا إنجاز — ولا تتراكم: يُعرَض الأعلى مستوى فقط.</div>
</div>

<section class="space-y-2">
    @forelse ($data['events'] as $event)
        <form method="post" action="{{ route('admin.gamification.celebrations.save', $event) }}"
              class="card p-3 grid md:grid-cols-4 gap-2 items-end">
            @csrf

            <div class="min-w-0">
                <div class="text-sm font-semibold truncate">{{ $event->label_ar }}</div>
                <code class="text-xs" style="color: var(--text-muted)">{{ $event->key }}</code>
            </div>

            <label class="text-xs">المستوى
                <select name="tier" class="w-full rounded-lg px-2 py-1.5 mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($data['tiers'] as $tier => $label)
                        <option value="{{ $tier }}" @selected($event->tier === $tier)>{{ $tier }} — {{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs">ملفّ الصوت (للصوت وحده توجل)
                <input type="text" name="sound_path" value="{{ $event->sound_path }}"
                       class="w-full rounded-lg px-2 py-1.5 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-xs">نصّ التهنئة
                <input type="text" name="message_ar" value="{{ $event->message_ar }}" maxlength="500"
                       class="w-full rounded-lg px-2 py-1.5 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <div class="md:col-span-4 flex items-center justify-between gap-2">
                <label class="text-xs flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked($event->is_active)>
                    مفعَّل
                </label>
                @can('celebrations.edit')
                    <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                            style="background: var(--surface-raised)">احفظ</button>
                @endcan
            </div>
        </form>
    @empty
        <x-empty message="كلّ الأحداث على مستوياتها الافتراضيّة." />
    @endforelse
</section>

@can('celebrations.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات الاحتفالات',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_celebrations'],
        'lockedKeys' => [
            'celebrations.animation_always_on',
            'celebrations.tiers_locked',
            'celebrations.once_per_event',
        ],
    ])
@endcan
