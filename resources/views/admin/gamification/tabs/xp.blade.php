{{-- XP والتذاكر (12.10 · 7 · 7.1): مصادر الكسب وأوجه الصرف وقيمتا التدريب --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">مصادر كسب XP والتذاكر</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">القيمة · الحدّ اليوميّ · التفعيل — والصفّ الفارغ يُهمَل عند الحفظ.</p>

    <form method="post" action="{{ route('admin.gamification.xp.rows.save') }}">
        @csrf
        <input type="hidden" name="key" value="xp_rules.earn">

        <div class="space-y-2">
            @foreach (array_values($data['earn']) as $i => $row)
                @php $consumed = array_key_exists($row['key'] ?? '', $data['earnConsumed'] ?? []); @endphp
                <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                    {{-- ⭐ الصفّ الذي لا يقرؤه الكود يُقال فيه ذلك صراحةً — لا إعداد بلا أثر (2.13) --}}
                    @unless ($consumed)
                        <p class="col-span-2 md:col-span-5 text-xs -mt-1 mb-1 inline-flex items-start gap-1" style="color: var(--color-state-warn)">
                            <x-icon name="warning" size="13" />
                            <span>المفتاح ده مالوش مستهلك في الكود — تعديله مش هيغيّر حاجة.
                                مكافآت النادي في تاب «الستريك» (سلّم الحضور)، ومكافأة الدعوة تذكرة مش XP.</span>
                        </p>
                    @endunless
                    <label class="text-xs">المصدر
                        <input type="text" name="rows[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">المفتاح
                        <input type="text" name="rows[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">القيمة
                        <input type="number" step="any" name="rows[{{ $i }}][value]" value="{{ $row['value'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">حدّ يوميّ (0 = بلا حدّ)
                        <input type="number" min="0" name="rows[{{ $i }}][daily_cap]" value="{{ $row['daily_cap'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">الحالة
                        <select name="rows[{{ $i }}][enabled]" class="w-full rounded-lg px-2 py-1.5 mt-1"
                                style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                            <option value="1" @selected($row['enabled'] ?? true)>مفعَّل</option>
                            <option value="0" @selected(! ($row['enabled'] ?? true))>موقوف</option>
                        </select>
                    </label>
                </div>
            @endforeach

            {{-- صفّ فارغ للإضافة — بلا شاشة منفصلة (2.15-أ-6) --}}
            @php $next = count($data['earn']); @endphp
            <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                <label class="text-xs">مصدر جديد
                    <input type="text" name="rows[{{ $next }}][label]" placeholder="اسم المصدر"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">المفتاح
                    <input type="text" name="rows[{{ $next }}][key]" placeholder="source.key"
                           class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">القيمة
                    <input type="number" step="any" name="rows[{{ $next }}][value]" value="0"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">حدّ يوميّ
                    <input type="number" min="0" name="rows[{{ $next }}][daily_cap]" value="0"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">الحالة
                    <select name="rows[{{ $next }}][enabled]" class="w-full rounded-lg px-2 py-1.5 mt-1"
                            style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                        <option value="1">مفعَّل</option>
                        <option value="0">موقوف</option>
                    </select>
                </label>
            </div>
        </div>

        @can('xp_rules.edit')
            <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">احفظ مصادر الكسب</button>
        @endcan
    </form>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">مواضع الصرف</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">الوجه · العملة · التكلفة · لحظة الخصم.</p>

    <form method="post" action="{{ route('admin.gamification.xp.rows.save') }}">
        @csrf
        <input type="hidden" name="key" value="xp_rules.spend">

        <div class="space-y-2">
            @foreach (array_values($data['spend']) as $i => $row)
                <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                    <label class="text-xs">الوجه
                        <input type="text" name="rows[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">المفتاح
                        <input type="text" name="rows[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">العملة
                        <input type="text" name="rows[{{ $i }}][currency]" value="{{ $row['currency'] ?? 'tickets' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">التكلفة
                        <input type="number" step="any" name="rows[{{ $i }}][cost]" value="{{ $row['cost'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">لحظة الخصم
                        <input type="text" name="rows[{{ $i }}][moment]" value="{{ $row['moment'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>
            @endforeach
        </div>

        @can('xp_rules.edit')
            <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">احفظ مواضع الصرف</button>
        @endcan
    </form>
</section>

{{-- ⭐ قيمتا التدريب قبل/بعد نصف المهلة --}}
<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">تذاكر إتمام التدريب</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        قبل نصف المهلة: <strong>{{ setting('tickets.before_half_deadline', 2) }}</strong> تذكرة ·
        بعدها وحتى الديدلاين: <strong>{{ setting('tickets.after_half_deadline', 1) }}</strong> تذكرة ·
        نقطة المنتصف: <strong>{{ setting('tickets.midpoint_percent', 50) }}%</strong> من المهلة.
    </p>
    <p class="text-xs mt-2" style="color: var(--text-muted)">تُعدَّل من بلوك الإعدادات تحت.</p>
</section>

@can('xp_rules.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات XP والتذاكر',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_xp'],
        'lockedKeys' => ['xp_rules.course_xp_once_locked'],
        'open' => true,
    ])
@endcan
