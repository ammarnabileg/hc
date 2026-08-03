@extends('layouts.admin')

@section('title', 'إدارة المكافآت')

@section('content')
    <x-page-header
        title="إدارة المكافآت"
        subtitle="امنح أو اخصم لكود واحد أو مئات دفعةً واحدة — بسبب موثّق وسجلّ تدقيق."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => url('/admin')], ['label' => 'المكافآت']]" />

    {{-- قفل معلَن: النزول تحت الصفر مسموح صراحةً (12.9) --}}
    <div class="card p-3 mb-4 text-sm">
        <x-icon name="lock" size="16" /> <strong>الخصم يقدر ينزل تحت الصفر</strong> — مسموح صراحةً.
        والأكواد الخاطئة أو المكرّرة <strong>تُستبعَد بتنبيه</strong> ولا يحصل فشل صامت.
    </div>

    {{-- بطاقات التهنئة بعد المنح: صورة + قيمة + حفظ الصورة + أسهم تنقّل --}}
    @if ($cards)
        <section class="card p-4 md:p-5 mb-4" id="cards-deck" data-index="0" data-total="{{ count($cards) }}">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold">تمّ المنح <x-icon name="celebrate" size="16" /></h2>
                <span class="text-xs" style="color: var(--text-muted)"><span id="card-pos">1</span> من {{ count($cards) }}</span>
            </div>

            @foreach ($cards as $i => $card)
                <figure class="reward-card {{ $i ? 'hidden' : '' }}" data-card="{{ $i }}">
                    <div class="rounded-2xl p-6 text-center animate-fadeup"
                         style="background: linear-gradient(160deg, var(--color-brand-800), var(--surface-raised)); border: 1px solid var(--border)">
                        <div class="text-sm" style="color: var(--text-muted)">{{ $card['title'] }}</div>
                        {{-- العدّاد التصاعديّ — والرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
                        <div class="mt-2 text-4xl font-extrabold"
                             data-count-to="{{ number_format($card['value']) }}">{{ number_format($card['value']) }}</div>
                        <div class="mt-1 text-sm">{{ $card['currency'] }}</div>
                        <div class="mt-3 text-xs" style="color: var(--text-muted)">
                            {{ $card['name'] }} · #{{ $card['code'] }} · الرصيد بعدها {{ number_format($card['after'], 2) }}
                        </div>
                    </div>
                </figure>
            @endforeach

            <div class="flex items-center justify-between gap-2 mt-3">
                <button type="button" id="card-prev" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">‹ السابق</button>
                <button type="button" id="card-save" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">احفظ الصورة</button>
                <button type="button" id="card-next" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">التالي ›</button>
            </div>
        </section>
    @endif

    {{-- فورم أوّلًا ثمّ المعاينة ثمّ التأكيد (12.9) --}}
    @can('manual_rewards.create')
        <form method="post" action="{{ $preview ? route('admin.rewards.grant') : route('admin.rewards.preview') }}" class="card p-4 md:p-5">
            @csrf

            <label class="block text-sm font-semibold mb-1" for="rw-codes">أكواد المستخدمين (أكثر من كود بمسافة أو فاصلة)</label>
            <textarea name="codes" id="rw-codes" rows="3" required
                      class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('codes', $old['codes'] ?? '') }}</textarea>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                <label class="text-sm font-semibold">النوع
                    <select name="direction" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="credit" @selected(($old['direction'] ?? 'credit') === 'credit')>منح</option>
                        <option value="debit" @selected(($old['direction'] ?? '') === 'debit')>خصم</option>
                    </select>
                </label>

                <label class="text-sm font-semibold">العملة
                    <select name="currency" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->code }}" @selected(($old['currency'] ?? '') === $currency->code)>{{ $currency->name_ar }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold">القيمة الموحّدة
                    <input type="number" step="any" min="0" name="amount" required value="{{ $old['amount'] ?? '' }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="text-sm font-semibold">سبب التحويل
                    <select name="reason" id="rw-reason" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($reasons as $key => $label)
                            <option value="{{ $key }}" @selected(($old['reason'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- حقل المرجع يظهر مع الأسباب التي تحتاجه فقط (القيود تُشرَح لحظة الحاجة — 2.15-د) --}}
            <div class="mt-3" id="rw-reference-wrap">
                <label class="block text-sm font-semibold mb-1" for="rw-reference">مرجع المعاملة</label>
                <input type="text" name="reference" id="rw-reference" maxlength="120" value="{{ $old['reference'] ?? '' }}"
                       class="w-full md:w-72 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <p class="text-xs mt-1" style="color: var(--text-muted)">مع «تصحيح خطأ تقنيّ» المرجع إلزاميّ.</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-3 mt-3">
                <label class="text-sm font-semibold">الملاحظات
                    <select name="notes_key" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($notes as $key => $label)
                            <option value="{{ $key }}" @selected(($old['notes_key'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold">تفاصيل إضافيّة
                    <input type="text" name="notes" maxlength="1000" value="{{ $old['notes'] ?? '' }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </div>

            @if ($preview)
                {{-- جدول المعاينة: الرصيد قبل/بعد + إزالة كود + عدّاد + قيمة لكلّ كود --}}
                <div class="mt-4 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                        <h2 class="font-bold text-sm">المعاينة قبل التنفيذ</h2>
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ $preview['rows']->count() }} صحيح ·
                            {{ count($preview['invalid']) }} خطأ ·
                            {{ count($preview['duplicates']) }} مكرّر ·
                            الإجماليّ {{ $preview['total'] }}
                        </span>
                    </div>

                    @if ($preview['invalid'] || $preview['duplicates'])
                        <p class="text-xs mb-2" style="color: var(--color-state-danger)">
                            ◉ اتستبعدت:
                            {{ implode('، ', array_merge($preview['invalid'], $preview['duplicates'])) }}
                            — راجعها لو المفروض تتحسب.
                        </p>
                    @endif

                    @foreach ($preview['rows'] as $row)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm border-b" style="border-color: var(--border)"
                             data-preview-row data-code="{{ $row['code'] }}">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $row['user']->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $row['code'] }}</span></div>
                                <div class="text-xs" style="color: var(--text-muted)">
                                    قبل {{ $row['before'] }} ⟵ بعد
                                    <strong style="color: {{ $row['after'] < 0 ? 'var(--color-state-danger)' : 'var(--text)' }}">{{ $row['after'] }}</strong>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <input type="number" step="any" name="per_code[{{ $row['code'] }}]" value="{{ abs($row['value']) }}"
                                       aria-label="قيمة {{ $row['code'] }}"
                                       class="w-24 rounded-lg px-2 py-1.5 text-sm text-center"
                                       style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <button type="button" class="text-xs underline" data-remove-code style="color: var(--color-state-danger)">إزالة</button>
                            </div>
                        </div>
                    @endforeach

                    <label class="flex items-start gap-2 text-sm mt-3">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>أؤكّد: هتمنح/هتخصم إجماليّ <strong>{{ $preview['total'] }}</strong>
                            لـ<strong>{{ $preview['rows']->count() }}</strong> مستخدم.</span>
                    </label>
                </div>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">نفّذ المكافآت</button>
            @else
                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">تحقّق من الأكواد</button>
            @endif
        </form>
    @endcan

    {{-- استهداف بشريحة بدل الأكواد اليدويّة --}}
    @can('manual_rewards.import')
        <form method="post" action="{{ route('admin.rewards.segment') }}" class="card p-4 mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm font-semibold">استهداف بشريحة
                <select name="segment" class="rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($segments as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">حمّل الأكواد</button>
            <span class="text-xs" style="color: var(--text-muted)">المعالجة على دفعات ({{ setting('rewards.batch_size', 500) }} كود/دفعة).</span>
        </form>
    @endcan

    {{-- سجلّ المنح + Audit --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">سجلّ المنح</h2>
        @forelse ($ledger as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row->user?->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $row->user?->code }}</span></div>
                    <div class="text-xs" style="color: var(--text-muted)" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                        {{ $row->reason }} · {{ $row->created_at?->diffForHumans() }}
                    </div>
                </div>
                <span class="font-bold shrink-0"
                      style="color: {{ $row->amount < 0 ? 'var(--color-state-danger)' : 'var(--color-state-ok)' }}">
                    {{ $row->amount > 0 ? '+' : '' }}{{ (float) $row->amount }} {{ $row->currency?->name_ar }}
                </span>
            </div>
        @empty
            <x-empty message="مفيش منح لسّه — اكتب كودًا واحدًا على الأقلّ." />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">سجلّ التدقيق</h2>
        @forelse ($audit as $log)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $log->user?->name ?? 'النظام' }} — {{ $log->action }}</span>
                <span class="text-xs shrink-0" style="color: var(--text-muted)">{{ $log->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <x-empty message="لا حركات تدقيق بعد." />
        @endforelse
    </section>

    @can('manual_rewards.manage')
        @include('admin.volunteer.partials.settings-card', [
            'title' => 'إعدادات المكافآت',
            'rows' => $settings,
            'action' => route('admin.rewards.settings.save'),
            'lockedKeys' => ['rewards.allow_negative_balance'],
        ])
    @endcan
@endsection

@push('scripts')
    <script>
        // إزالة كود من المعاينة — ردّ فوريّ بلا رحلة للسيرفر (2.17-ب)
        document.querySelectorAll('[data-remove-code]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-preview-row]');
                const code = row.dataset.code;
                const codes = document.getElementById('rw-codes');
                codes.value = codes.value
                    .split(/[\s,;\n\t]+/)
                    .filter((c) => c.trim().toUpperCase() !== code)
                    .join(' ');
                row.remove();
            });
        });

        // أسهم التنقّل بين بطاقات التهنئة
        const deck = document.getElementById('cards-deck');
        if (deck) {
            const cards = deck.querySelectorAll('[data-card]');
            const pos = document.getElementById('card-pos');
            const show = (i) => {
                const total = cards.length;
                const index = ((i % total) + total) % total;
                cards.forEach((c, n) => c.classList.toggle('hidden', n !== index));
                deck.dataset.index = index;
                pos.textContent = index + 1;
            };
            document.getElementById('card-prev').addEventListener('click', () => show(Number(deck.dataset.index) - 1));
            document.getElementById('card-next').addEventListener('click', () => show(Number(deck.dataset.index) + 1));
            document.getElementById('card-save').addEventListener('click', () => window.print());
        }
    </script>
@endpush
