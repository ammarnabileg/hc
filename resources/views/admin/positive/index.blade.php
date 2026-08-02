@extends('layouts.admin')

@section('title', 'الرسائل الإيجابيّة')

@php
    /**
     * إدارة مكتبة الرسائل الإيجابيّة (2.6-ب · 2.13).
     * سؤال واحد للشاشة: «إيه الرسائل اللي بتظهر ومتى؟» — والفعل الرئيسيّ واحد
     * (+ رسالة)، والباقي داخل كلّ صفّ. والجدول كروت رأسيّة على الموبايل
     * بلا أيّ تمرير أفقيّ (2.15-ج).
     */
    $canCreate = auth()->user()?->can('positive_messages.create');
    $canEdit = auth()->user()?->can('positive_messages.edit');
    $canDelete = auth()->user()?->can('positive_messages.delete');
    $canManage = auth()->user()?->can('positive_messages.manage');
@endphp

@section('content')
    <x-page-header title="الرسائل الإيجابيّة"
                   subtitle="كلمة تشجيع في وقتها — بنبرة المنصّة وبلا مبالغة، ولكلّ رسالة سياقها."
                   :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')], ['label' => 'الرسائل الإيجابيّة']]">
        @if ($canCreate)
            <x-slot:action>
                <button type="button" data-modal-open="positive-modal" data-positive-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ رسالة</button>
            </x-slot:action>
        @endif
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi label="كلّ الرسائل" :value="$counts['total']" icon="✉️" />
        <x-kpi label="المفعّلة" :value="$counts['active']" icon="✅" />
        <x-kpi label="السياقات" :value="$counts['contexts']" icon="🧭" />
        <x-kpi label="مرّات الظهور" :value="$counts['shown']" icon="👀" />
    </div>

    {{-- ثلاثة فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) --}}
    <x-filters :action="route('admin.positive.index')">
        <label class="text-sm">السياق
            <select name="context" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($contexts as $key => $label)
                    <option value="{{ $key }}" @selected($context === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">الحالة
            <select name="state" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="active" @selected($state === 'active')>مفعّلة</option>
                <option value="paused" @selected($state === 'paused')>موقوفة</option>
            </select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">فلترة</button>
    </x-filters>

    @if ($messages->isEmpty())
        <x-empty message="لسّه مافيش رسائل — أضف أوّل كلمة تشجيع من زرّ «+ رسالة» فوق." />
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($messages as $message)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs shrink-0"
                              style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $contexts[$message->context] ?? $message->context }}
                        </span>
                        <x-state-badge :state="$message->is_active ? 'ok' : 'idle'"
                                       :label="$message->is_active ? 'مفعّلة' : 'موقوفة'" />
                    </div>

                    <p class="mt-3 text-sm break-words">
                        @if ($message->emoji)<span aria-hidden="true">{{ $message->emoji }}</span> @endif{{ $message->body_ar }}
                    </p>

                    <div class="mt-3 pt-3 flex flex-wrap items-center justify-between gap-2 text-xs"
                         style="border-top: 1px solid var(--border); color: var(--text-muted)">
                        <span>ظهرت {{ $message->shown_count }} مرّة</span>

                        <span class="flex items-center gap-3">
                            @if ($canEdit)
                                <button type="button" class="underline" data-positive-edit
                                        data-id="{{ $message->id }}"
                                        data-action="{{ route('admin.positive.update', $message) }}"
                                        data-context="{{ $message->context }}"
                                        data-body="{{ $message->body_ar }}"
                                        data-emoji="{{ $message->emoji }}"
                                        data-sort="{{ $message->sort_order }}"
                                        data-active="{{ $message->is_active ? 1 : 0 }}">تعديل</button>

                                <form method="post" action="{{ route('admin.positive.toggle', $message) }}">
                                    @csrf
                                    <button type="submit" class="underline">{{ $message->is_active ? 'أوقف' : 'فعّل' }}</button>
                                </form>
                            @endif

                            @if ($canDelete)
                                <form method="post" action="{{ route('admin.positive.destroy', $message) }}"
                                      onsubmit="return confirm('تحذف الرسالة دي نهائيًّا؟')">
                                    @csrf @method('delete')
                                    <button type="submit" class="underline" style="color: var(--color-state-danger)">حذف</button>
                                </form>
                            @endif
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $messages->links() }}</div>
    @endif

    {{-- معاينة حيّة قبل ما يشوفها الناس (2.13-د) --}}
    @if ($canEdit)
        <form method="post" action="{{ route('admin.positive.preview') }}" class="card p-4 mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm">جرّب سياقًا
                <select name="context" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($contexts as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">اسحب رسالة</button>
            <span class="text-xs" style="color: var(--text-muted)">بتشوف اللي هيشوفه المستخدم بالظبط.</span>
        </form>
    @endif

    @if ($canManage)
        @include('admin.positive.partials.settings', ['settings' => $settings])
    @endif
@endsection

@section('mobile_action')
    @if ($canCreate)
        <button type="button" data-modal-open="positive-modal" data-positive-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-bold"
                style="background: var(--color-brand-500); color: #04201c">+ رسالة</button>
    @endif
@endsection

@push('modals')
    @if ($canCreate || $canEdit)
        <x-modal id="positive-modal" title="رسالة إيجابيّة">
            <form method="post" action="{{ route('admin.positive.store') }}" data-positive-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-positive-method>

                <label class="block text-sm font-semibold mb-1" for="positive-context">السياق — إمتى تظهر الرسالة؟</label>
                <select name="context" id="positive-context" required
                        class="w-full rounded-xl px-3 py-2 text-sm mb-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($contexts as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    السياقات نفسها إعداد — تقدر تزوّدها من «إعدادات الميزة» تحت.
                </p>

                <label class="block text-sm font-semibold mb-1" for="positive-body">نصّ الرسالة</label>
                <textarea name="body_ar" id="positive-body" required rows="3" maxlength="400"
                          placeholder="مثال: خلّصت الدرس — كمّل بهدوء، إنت ماشي صحّ."
                          class="w-full rounded-xl px-3 py-2 text-sm mb-1"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    نبرة المنصّة: مبسّطة محترمة دافئة — بلا مبالغة وبلا لوم للمستخدم (2.17-ج).
                </p>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">رمز صغير (اختياريّ)
                        <input type="text" name="emoji" id="positive-emoji" maxlength="16"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">الترتيب
                        <input type="number" name="sort_order" id="positive-sort" min="0" max="9999" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm mb-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="positive-active" value="1" checked>
                    <span>مفعّلة — تظهر للمستخدمين</span>
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">احفظ الرسالة</button>
            </form>
        </x-modal>
    @endif
@endpush

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('positive-modal');
            if (!modal) return;

            const form = modal.querySelector('[data-positive-form]');
            const method = modal.querySelector('[data-positive-method]');
            const storeUrl = @json(route('admin.positive.store'));

            const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };

            document.querySelectorAll('[data-positive-new]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = storeUrl;
                    method.value = 'POST';
                    form.querySelector('#positive-body').value = '';
                    form.querySelector('#positive-emoji').value = '';
                    form.querySelector('#positive-sort').value = '0';
                    form.querySelector('#positive-active').checked = true;
                });
            });

            document.querySelectorAll('[data-positive-edit]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.action = btn.dataset.action;
                    method.value = 'PUT';
                    form.querySelector('#positive-context').value = btn.dataset.context;
                    form.querySelector('#positive-body').value = btn.dataset.body;
                    form.querySelector('#positive-emoji').value = btn.dataset.emoji || '';
                    form.querySelector('#positive-sort').value = btn.dataset.sort || '0';
                    form.querySelector('#positive-active').checked = btn.dataset.active === '1';
                    open();
                });
            });
        })();
    </script>
@endpush
