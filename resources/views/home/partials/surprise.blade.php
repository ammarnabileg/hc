@php
    /**
     * العناصر العائمة (2.6):
     *  أ) سهم العودة لأعلى — على **يمين** الشاشة، يظهر مع التمرير لأسفل.
     *  ب) أيقونة الرسالة الإيجابيّة — تظهر باحتمال حقيقيّ من الإعدادات، وعند
     *     الضغط يفتح **ظرف** تطلع منه **ورقة** فيها رسالة من مكتبة الأدمن.
     *  ج) التكديس: لمّا يظهر السهم ترتفع أيقونة الرسالة فوقه بحركة انسيابيّة
     *     بلا تداخل — وكلّه CSS/JS خام بلا أيّ مكتبة خارجيّة.
     *
     * المتغيّرات: $surprise (نموذج الرسالة أو null) · $offerTicket (bool)
     */
    $surprise = $surprise ?? null;
    $offerTicket = ($offerTicket ?? false) && auth()->check();
    $envelopeTitle = (string) setting('engagement.positive.envelope_title', 'وصلتك رسالة');
    $openLabel = (string) setting('engagement.positive.open_label', 'افتح الظرف');
    $closeLabel = (string) setting('engagement.positive.close_label', 'تمام');
    $ticketLabel = (string) setting('engagement.positive.ticket_label', 'استلام تذكرة');
    $iconLabel = (string) setting('engagement.positive.icon_label', 'رسالة إيجابيّة مستنّياك');
    $topLabel = (string) setting('ux.back_to_top.label', 'ارجع لأعلى الصفحة');
    $ticketAmount = (int) setting('engagement.positive.ticket_amount', 1);
@endphp

{{--
  ⭐ العنصران العائمان على **يمين** الشاشة (2.6-أ و2.6-ب — مرّتين نصًّا).
  ولذلك نستعمل `right` الفيزيائيّة لا `inset-inline-end`: الصفحة `dir="rtl"`،
  و«نهاية السطر» فيها هي **اليسار** — فكان الاثنان يقعان في الجهة الخطأ.
--}}
<div class="fixed z-40 flex flex-col items-center gap-2 pointer-events-none"
     style="right: 1rem; bottom: 1rem" data-floating>

    @if ($surprise)
        <button type="button" data-surprise-open aria-label="{{ $iconLabel }}" title="{{ $iconLabel }}"
                class="btn pointer-events-auto inline-flex items-center justify-center rounded-full motion-standard animate-fadeup"
                style="width:48px;height:48px;background: var(--color-brand-500); color:#04201c;
                       box-shadow: 0 8px 24px rgb(0 0 0 / .25)">
            @include('home.partials.icon', ['name' => 'envelope', 'size' => 22])
        </button>
    @endif

    {{-- سهم العودة لأعلى (2.6-أ) — مخفيّ حتى ينزل المستخدم --}}
    <button type="button" data-back-to-top hidden aria-label="{{ $topLabel }}" title="{{ $topLabel }}"
            class="btn pointer-events-auto inline-flex items-center justify-center rounded-full motion-standard"
            style="width:44px;height:44px;background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        @include('home.partials.icon', ['name' => 'top', 'size' => 20])
    </button>
</div>

@if ($surprise)
    {{-- الظرف: بوب-أب برأس ثابت وجسم متمرّر (2.10.1-17) وقابل للإغلاق بـESC (2.14-ب) --}}
    <div id="surprise-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
         style="background: rgb(0 0 0 / .6)" data-surprise-modal role="dialog" aria-modal="true"
         aria-label="{{ $envelopeTitle }}">
        <div class="modal-shell card w-full max-w-sm text-center p-6 animate-fadeup">
            <div class="mx-auto mb-3 surprise-flap" style="color: var(--color-brand-500)">
                @include('home.partials.icon', ['name' => 'envelope', 'size' => 56])
            </div>

            <h2 class="font-extrabold">{{ $envelopeTitle }}</h2>

            {{-- الورقة تطلع من الظرف — حركة واحدة قصيرة بإيقاع المنصّة (2.17-د) --}}
            <div class="surprise-sheet rounded-2xl p-4 mt-4 text-sm"
                 style="background: var(--surface-sunken); border: 1px solid var(--border)">
                @if ($surprise->emoji)
                    <div class="text-2xl mb-1" aria-hidden="true">{{ $surprise->emoji }}</div>
                @endif
                <p class="leading-relaxed">{{ $surprise->body_ar }}</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                @if ($offerTicket)
                    {{-- القيمة الحقيقيّة مكتوبة قبل الضغط — بلا Dark Patterns (21.1-د) --}}
                    <form method="post" action="{{ route('positive.ticket') }}">
                        @csrf
                        <button type="submit"
                                class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color:#04201c">
                            @include('home.partials.icon', ['name' => 'ticket', 'size' => 18])
                            {{ $ticketLabel }} ({{ $ticketAmount }})
                        </button>
                    </form>
                @endif

                <button type="button" data-surprise-close
                        class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $closeLabel }}</button>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    <style>
        @keyframes surprise-flap { 0%,100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg); } }
        .surprise-flap { display: inline-block; animation: surprise-flap 1.6s var(--ease-standard) infinite; }

        @keyframes surprise-sheet-up {
            from { opacity: 0; transform: translateY(14px) scale(.96); }
            to   { opacity: 1; transform: none; }
        }
        .surprise-sheet { animation: surprise-sheet-up 320ms var(--ease-standard) both; }
        /* التحكّم في الحركة من إعداد المستخدم داخل المنصّة (app.css) لا من
           `prefers-reduced-motion` — مرفوض نصًّا في 2.3 و2.14-ب. */
    </style>
    <script>
        (() => {
            /* سهم العودة لأعلى + التكديس الانسيابيّ فوقه (2.6-أ/ج) */
            const top = document.querySelector('[data-back-to-top]');
            if (top) {
                const threshold = Number({{ (int) setting('ux.back_to_top.after_px', 320) }}) || 320;
                const sync = () => { top.hidden = window.scrollY < threshold; };
                window.addEventListener('scroll', sync, { passive: true });
                top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
                sync();
            }

            /* الظرف: فتح/إغلاق بضغطة أو ESC — لا يعطّل المستخدم أبدًا (2.14-ب) */
            const modal = document.querySelector('[data-surprise-modal]');
            if (!modal) return;

            const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

            document.querySelector('[data-surprise-open]')?.addEventListener('click', open);
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.closest('[data-surprise-close]')) close();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        })();
    </script>
@endpush
