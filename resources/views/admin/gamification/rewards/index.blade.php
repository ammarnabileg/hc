@extends('layouts.admin')

@section('title', setting('admin.gamification.rewards.index.idara_almkafat', 'إدارة المكافآت'))

@php
    use App\Services\Images\BoardSnapshot;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\URL;

    /*
     | ⭐ زرّ «**حفظ الصورة**» (12.9) — كان مستمعه `window.print()`، أي **طباعة
     | متصفّح** لا صورة: زرٌّ يَعِد بما لا يقع، وهو عطبٌ في ذاته. والنصّ الحاكم:
     | «**بعد التنفيذ:** بطاقة احترافيّة لكلّ مستلِم (صورته + القيمة مكتوبة
     | عليها) + «**حفظ الصورة**» + **أسهم يمين/شمال** للتنقّل بين المستلمين».
     | ومحرّك الرسم على الخادم موجودٌ ويعمل (12.14-هـ · 12.14-و): مسار
     | `export.image` بحمولةٍ **موقَّعة** ورسّامٍ واحد لكلّ لوحات المنصّة — فلا
     | نكتب رسّامًا ثانيًا، ولا نضيف مكتبة، ولا نستبدل الوعد بالطباعة.
     */
    $mayExportImage = auth()->check() && Gate::allows('image_export.use');
    $cardLinks = [];

    if ($mayExportImage) {
        foreach ($cards ?? [] as $i => $card) {
            $snapshot = new BoardSnapshot(
                'card',
                (string) ($card['title'] ?? ''),
                trim(($card['name'] ?? '').' · #'.($card['code'] ?? '')),
                [[
                    'rank' => 1,
                    'name' => (string) ($card['name'] ?? ''),
                    'value' => number_format((float) $card['value'], 2).' '.($card['currency'] ?? ''),
                ]],
            );

            $cardLinks[$i] = URL::signedRoute('export.image', ['d' => $snapshot->encode()]).'&download=1';
        }
    }
@endphp

@section('content')
    <x-page-header
        :title="setting('admin.gamification.rewards.index.idara_almkafat', 'إدارة المكافآت')"
        :subtitle="setting('admin.gamification.rewards.index.amnh_aw_akhsm_lkwd_wahd_aw_myat_dfaa_wahda', 'امنح أو اخصم لكود واحد أو مئات دفعةً واحدة — بسبب موثّق وسجلّ تدقيق.')"
        :breadcrumbs="[['label' => setting('admin.gamification.rewards.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('admin.gamification.rewards.index.almkafat', 'المكافآت')]]" />

    {{-- قفل معلَن: النزول تحت الصفر مسموح صراحةً (12.9) --}}
    <div class="card p-3 mb-4 text-sm">
        <x-icon name="lock" size="16" /> <strong>{{ setting('admin.gamification.rewards.index.alkhsm_yqdr_ynzl_tht_alsfr', 'الخصم يقدر ينزل تحت الصفر') }}</strong> {{ setting('admin.gamification.rewards.index.msmwh_sraha_walakwad_alkhatya_aw_almkrra', '— مسموح صراحةً. والأكواد الخاطئة أو المكرّرة') }} <strong>{{ setting('admin.gamification.rewards.index.tstbad_btnbyh', 'تُستبعَد بتنبيه') }}</strong> {{ setting('admin.gamification.rewards.index.wla_yhsl_fshl_samt', 'ولا يحصل فشل صامت.') }}
    </div>

    {{-- بطاقات التهنئة بعد المنح: صورة + قيمة + حفظ الصورة + أسهم تنقّل --}}
    @if ($cards)
        <section class="card p-4 md:p-5 mb-4" id="cards-deck" data-index="0" data-total="{{ count($cards) }}">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold">{{ setting('admin.gamification.rewards.index.tm_almnh', 'تمّ المنح') }} <x-icon name="celebrate" size="16" /></h2>
                <span class="text-xs" style="color: var(--text-muted)"><span id="card-pos">1</span> {{ setting('admin.gamification.rewards.index.mn', 'من') }} {{ count($cards) }}</span>
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
                            {{ $card['name'] }} · #{{ $card['code'] }} {{ setting('admin.gamification.rewards.index.alrsyd_badha', '· الرصيد بعدها') }} {{ number_format($card['after'], 2) }}
                        </div>
                    </div>
                </figure>
            @endforeach

            <div class="flex items-center justify-between gap-2 mt-3">
                <button type="button" id="card-prev" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.gamification.rewards.index.alsabq', '‹ السابق') }}</button>
                {{-- ⭐ صورة PNG حقيقيّة تُرسَم على الخادم — رابطٌ موقَّع لكلّ بطاقة (12.9 · 12.14-و) --}}
                @if ($mayExportImage)
                    @foreach ($cardLinks as $i => $link)
                        <a href="{{ $link }}" data-card-save="{{ $i }}" download
                           class="rounded-xl px-3 py-2 text-sm {{ $i ? 'hidden' : '' }}"
                           style="background: var(--surface-sunken)">{{ setting('admin.gamification.rewards.index.ahfz_alswra', 'احفظ الصورة') }}</a>
                    @endforeach
                @else
                    {{-- المحظور يُخفى لا يُعطَّل (2.15-أ-7) — ونضع فراغًا يحفظ توزيع الأسهم --}}
                    <span></span>
                @endif
                <button type="button" id="card-next" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.gamification.rewards.index.altaly', 'التالي ›') }}</button>
            </div>
        </section>
    @endif

    {{-- فورم أوّلًا ثمّ المعاينة ثمّ التأكيد (12.9) --}}
    @can('manual_rewards.create')
        <form method="post" action="{{ $preview ? route('admin.rewards.grant') : route('admin.rewards.preview') }}" class="card p-4 md:p-5">
            @csrf

            <label class="block text-sm font-semibold mb-1" for="rw-codes">{{ setting('admin.gamification.rewards.index.akwad_almstkhdmyn_akthr_mn_kwd_bmsafa_aw', 'أكواد المستخدمين (أكثر من كود بمسافة أو فاصلة)') }}</label>
            <textarea name="codes" id="rw-codes" rows="3" required
                      class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('codes', $old['codes'] ?? '') }}</textarea>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.alnwa', 'النوع') }}
                    <select name="direction" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="credit" @selected(($old['direction'] ?? 'credit') === 'credit')>{{ setting('admin.gamification.rewards.index.mnh', 'منح') }}</option>
                        <option value="debit" @selected(($old['direction'] ?? '') === 'debit')>{{ setting('admin.gamification.rewards.index.khsm', 'خصم') }}</option>
                    </select>
                </label>

                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.alamla', 'العملة') }}
                    <select name="currency" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->code }}" @selected(($old['currency'] ?? '') === $currency->code)>{{ $currency->name_ar }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.alqyma_almwhda', 'القيمة الموحّدة') }}
                    <input type="number" step="any" min="0" name="amount" required value="{{ $old['amount'] ?? '' }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.sbb_althwyl', 'سبب التحويل') }}
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
                <label class="block text-sm font-semibold mb-1" for="rw-reference">{{ setting('admin.gamification.rewards.index.mrja_almaamla', 'مرجع المعاملة') }}</label>
                <input type="text" name="reference" id="rw-reference" maxlength="120" value="{{ $old['reference'] ?? '' }}"
                       class="w-full md:w-72 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <p class="text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.gamification.rewards.index.ma_tshyh_khta_tqny_almrja_ilzamy', 'مع «تصحيح خطأ تقنيّ» المرجع إلزاميّ.') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-3 mt-3">
                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.almlahzat', 'الملاحظات') }}
                    <select name="notes_key" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($notes as $key => $label)
                            <option value="{{ $key }}" @selected(($old['notes_key'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.tfasyl_idafya', 'تفاصيل إضافيّة') }}
                    <input type="text" name="notes" maxlength="1000" value="{{ $old['notes'] ?? '' }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
            </div>

            @if ($preview)
                {{-- جدول المعاينة: الرصيد قبل/بعد + إزالة كود + عدّاد + قيمة لكلّ كود --}}
                <div class="mt-4 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                        <h2 class="font-bold text-sm">{{ setting('admin.gamification.rewards.index.almaayna_qbl_altnfydh', 'المعاينة قبل التنفيذ') }}</h2>
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ $preview['rows']->count() }} {!! strtr(setting('admin.gamification.rewards.index.shyh_v1_khta_v2_mkrr_alijmaly', 'صحيح · :v1 خطأ · :v2 مكرّر · الإجماليّ'), [':v1' => e(count($preview['invalid'])), ':v2' => e(count($preview['duplicates']))]) !!} {{ $preview['total'] }}
                        </span>
                    </div>

                    @if ($preview['invalid'] || $preview['duplicates'])
                        <p class="text-xs mb-2" style="color: var(--color-state-danger)">
                            {!! strtr(setting('admin.gamification.rewards.index.atstbadt_v1_rajaha_lw_almfrwd_tthsb', '◉ اتستبعدت: :v1 — راجعها لو المفروض تتحسب.'), [':v1' => e(implode('، ', array_merge($preview['invalid'], $preview['duplicates'])))]) !!}
                        </p>
                    @endif

                    @foreach ($preview['rows'] as $row)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm border-b" style="border-color: var(--border)"
                             data-preview-row data-code="{{ $row['code'] }}">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $row['user']->name }} <span class="text-xs" style="color: var(--text-muted)">#{{ $row['code'] }}</span></div>
                                <div class="text-xs" style="color: var(--text-muted)">
                                    {!! strtr(setting('admin.gamification.rewards.index.qbl_v1_bad', 'قبل :v1 ⟵ بعد'), [':v1' => e($row['before'])]) !!}
                                    <strong style="color: {{ $row['after'] < 0 ? 'var(--color-state-danger)' : 'var(--text)' }}">{{ $row['after'] }}</strong>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <input type="number" step="any" name="per_code[{{ $row['code'] }}]" value="{{ abs($row['value']) }}"
                                       aria-label="{{ strtr(setting('admin.gamification.rewards.index.qyma_v1', 'قيمة :v1'), [':v1' => e($row['code'])]) }}"
                                       class="w-24 rounded-lg px-2 py-1.5 text-sm text-center"
                                       style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <button type="button" class="text-xs underline" data-remove-code style="color: var(--color-state-danger)">{{ setting('admin.gamification.rewards.index.izala', 'إزالة') }}</button>
                            </div>
                        </div>
                    @endforeach

                    <label class="flex items-start gap-2 text-sm mt-3">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>{{ setting('admin.gamification.rewards.index.awkd_htmnh_htkhsm_ijmaly', 'أؤكّد: هتمنح/هتخصم إجماليّ') }} <strong>{{ $preview['total'] }}</strong>
                            {{ setting('admin.gamification.rewards.index.l', 'لـ') }}<strong>{{ $preview['rows']->count() }}</strong> {{ setting('admin.gamification.rewards.index.mstkhdm', 'مستخدم.') }}</span>
                    </label>
                </div>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.rewards.index.nfdh_almkafat', 'نفّذ المكافآت') }}</button>
            @else
                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.rewards.index.thqq_mn_alakwad', 'تحقّق من الأكواد') }}</button>
            @endif
        </form>
    @endcan

    {{-- استهداف بشريحة بدل الأكواد اليدويّة --}}
    @can('manual_rewards.import')
        <form method="post" action="{{ route('admin.rewards.segment') }}" class="card p-4 mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm font-semibold">{{ setting('admin.gamification.rewards.index.asthdaf_bshryha', 'استهداف بشريحة') }}
                <select name="segment" class="rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($segments as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.gamification.rewards.index.hml_alakwad', 'حمّل الأكواد') }}</button>
            <span class="text-xs" style="color: var(--text-muted)">{!! strtr(setting('admin.gamification.rewards.index.almaalja_ala_dfaat_v1_kwd_dfaa', 'المعالجة على دفعات (:v1 كود/دفعة).'), [':v1' => e(setting('rewards.batch_size', 500))]) !!}</span>
        </form>
    @endcan

    {{-- سجلّ المنح + Audit --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.gamification.rewards.index.sjl_almnh', 'سجلّ المنح') }}</h2>
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
            <x-empty :message="setting('admin.gamification.rewards.index.mfysh_mnh_lsh_aktb_kwda_wahda_ala_alaql', 'مفيش منح لسّه — اكتب كودًا واحدًا على الأقلّ.')" />
        @endforelse
    </section>

    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.gamification.rewards.index.sjl_altdqyq', 'سجلّ التدقيق') }}</h2>
        @forelse ($audit as $log)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <span class="truncate">{{ $log->user?->name ?? setting('admin.gamification.rewards.index.alnzam', 'النظام') }} — {{ $log->action }}</span>
                <span class="text-xs shrink-0" style="color: var(--text-muted)">{{ $log->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <x-empty :message="setting('admin.gamification.rewards.index.la_hrkat_tdqyq_bad', 'لا حركات تدقيق بعد.')" />
        @endforelse
    </section>

    @can('manual_rewards.manage')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.gamification.rewards.index.iadadat_almkafat', 'إعدادات المكافآت'),
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
            // رابط حفظ الصورة لكلّ بطاقة — والظاهر منه واحدٌ يتبع البطاقة الظاهرة
            const saves = deck.querySelectorAll('[data-card-save]');
            const pos = document.getElementById('card-pos');
            const show = (i) => {
                const total = cards.length;
                const index = ((i % total) + total) % total;
                cards.forEach((c, n) => c.classList.toggle('hidden', n !== index));
                saves.forEach((a, n) => a.classList.toggle('hidden', n !== index));
                deck.dataset.index = index;
                pos.textContent = index + 1;
            };
            document.getElementById('card-prev').addEventListener('click', () => show(Number(deck.dataset.index) - 1));
            document.getElementById('card-next').addEventListener('click', () => show(Number(deck.dataset.index) + 1));
        }
    </script>
@endpush
