@extends('layouts.app')

@section('title', 'الفعاليّات')

@section('content')
    <x-page-header
        title="الفعاليّات"
        subtitle="اللقاءات المباشرة أوفلاين وأونلاين وهجين — بسعتها وكود حضورها ومكافأتها المتدرّجة."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => url('/admin')], ['label' => 'الفعاليّات']]">
        <x-slot:action>
            @can('events.create')
                <button type="button" data-modal-open="event-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ فعاليّة</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <div class="card p-3 mb-4 text-sm">
        <x-icon name="lock" size="16" /> <strong>كود الحضور مستمرّ لا يقفل</strong> — والمكافأة وحدها تتناقص على درجات زمنيّة.
        وشهادة الحضور تُضبَط في إدارة الشهادات لا هنا.
    </div>

    <x-filters :action="route('admin.events.index')">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">بحث بالاسم</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-mode" style="color: var(--text-muted)">النوع</label>
            <select id="f-mode" name="mode" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($modes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-status" style="color: var(--text-muted)">الحالة</label>
            <select id="f-status" name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach (['upcoming' => 'قادمة', 'past' => 'منتهية', 'draft' => 'مسودّة'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">فلتر</button>
    </x-filters>

    {{-- كروت رأسيّة — لا تمرير أفقيّ على الموبايل (2.15-ج) --}}
    <section class="space-y-3">
        @forelse ($events as $event)
            @php
                $registered = $event->registrations_count;
                $capacity = $event->capacity ?: 0;
                $percent = $capacity ? min(100, round($registered / $capacity * 100)) : 0;
            @endphp

            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $event->title_ar }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)"
                             title="{{ $event->starts_at?->format('Y-m-d H:i') }}">
                            {{ $modes[$event->mode] ?? $event->mode }} · {{ $event->starts_at?->diffForHumans() }}
                            @if ($event->attendance_code) · كود الحضور <code>{{ $event->attendance_code }}</code> @endif
                        </div>
                    </div>

                    <x-state-badge :state="match ($event->status) { 'published' => 'ok', 'cancelled' => 'danger', default => 'idle' }"
                                   :label="match ($event->status) { 'published' => 'منشورة', 'cancelled' => 'ملغاة', default => 'مسودّة' }" />
                </div>

                <div class="flex items-center gap-2 mt-3">
                    <div class="h-1.5 flex-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-full" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
                    </div>
                    <span class="text-xs shrink-0" style="color: var(--text-muted)">
                        {{ $registered }}{{ $capacity ? '/'.$capacity : '' }} مسجّل
                    </span>
                </div>

                <div class="flex items-center gap-3 mt-3 flex-wrap text-xs">
                    @can('event_registrations.list')
                        <a class="underline" href="{{ route('admin.events.registrations', $event) }}">المسجّلون والحضور</a>
                    @endcan
                    @can('events.edit')
                        <button type="button" class="underline" data-event-edit
                                data-id="{{ $event->id }}" data-title="{{ $event->title_ar }}"
                                data-title-en="{{ $event->title_en }}" data-mode="{{ $event->mode }}"
                                data-starts="{{ $event->starts_at?->format('Y-m-d\TH:i') }}"
                                data-capacity="{{ $event->capacity }}" data-code="{{ $event->attendance_code }}"
                                data-location="{{ $event->location }}" data-join="{{ $event->join_link }}"
                                data-registration="{{ $event->registration_link }}"
                                data-price-coins="{{ (int) $event->price_coins }}"
                                data-price-tickets="{{ (int) $event->price_tickets }}"
                                data-coupon="{{ $event->coupon_id }}" data-cover="{{ $event->cover_path }}"
                                data-status="{{ $event->status }}">تعديل</button>
                        @if ($event->status !== 'cancelled')
                            <form method="post" action="{{ route('admin.events.cancel', $event) }}"
                                  onsubmit="return confirm('تلغي الفعاليّة دي؟')">
                                @csrf
                                <button type="submit" class="underline" style="color: var(--color-state-danger)">إلغاء</button>
                            </form>
                        @endif
                    @endcan
                    @if ($event->registration_link)
                        <a class="underline" href="{{ $event->registration_link }}" target="_blank" rel="noopener">رابط التسجيل الخارجيّ</a>
                    @endif
                </div>
            </article>
        @empty
            <x-empty :message="setting('events.empty_message', 'لا فعاليّات — أنشئ أوّل لقاء.')" />
        @endforelse
    </section>

    @can('events.manage')
        @include('admin.volunteer.partials.settings-card', [
            'title' => 'إعدادات الفعاليّات',
            'rows' => $settings,
            'action' => route('admin.events.settings.save'),
            'lockedKeys' => ['events.attendance_code_persistent'],
        ])
    @endcan
@endsection

@section('mobile_action')
    @can('events.create')
        <button type="button" data-modal-open="event-modal"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ فعاليّة</button>
    @endcan
@endsection

@push('modals')
    @can('events.create')
        <x-modal id="event-modal" title="فعاليّة">
            <form method="post" action="{{ route('admin.events.save') }}">
                @csrf
                <input type="hidden" name="id" id="ev-id">

                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="text-sm font-semibold">العنوان (عربيّ)
                        <input type="text" name="title_ar" id="ev-title" required maxlength="180"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">العنوان (إنجليزيّ)
                        <input type="text" name="title_en" id="ev-title-en" maxlength="180"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="text-sm font-semibold">الوصف (عربيّ)
                        <textarea name="description" rows="2" maxlength="4000"
                                  class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <label class="text-sm font-semibold">الوصف (إنجليزيّ)
                        <textarea name="description_en" rows="2" maxlength="4000"
                                  class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                </div>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">النوع
                        <select name="mode" id="ev-mode" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($modes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">يبدأ
                        <input type="datetime-local" name="starts_at" id="ev-starts" required
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">ينتهي (اختياريّ)
                        <input type="datetime-local" name="ends_at"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="text-sm font-semibold">المكان (للأوفلاين والهجين)
                        <input type="text" name="location" id="ev-location" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">رابط الانضمام (للأونلاين والهجين)
                        <input type="url" name="join_link" id="ev-join" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">رابط التسجيل الخارجيّ
                        <input type="url" name="registration_link" id="ev-registration" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">رابط التسجيل بعد الانتهاء
                        <input type="url" name="recording_link" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">السعة
                        <input type="number" min="1" name="capacity" id="ev-capacity"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                {{-- ⭐ السعر: مجّانيّ/كوينز/تذكرة + كوبون (12.11) — كان مُتحقَّقًا منه
                     خادميًّا ومقروءًا في صفحة المستخدم وبلا أيّ حقل هنا، فلا سبيل
                     لضبطه إلّا بتعديل قاعدة البيانات باليد. --}}
                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">السعر (كوينز)
                        <input type="number" min="0" step="1" name="price_coins" id="ev-price-coins" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <span class="block text-xs mt-1" style="color: var(--text-muted)">صفر = مجّانيّة</span>
                    </label>
                    <label class="text-sm font-semibold">السعر (تذاكر)
                        <input type="number" min="0" step="1" name="price_tickets" id="ev-price-tickets" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">كوبون خصم (اختياريّ)
                        <select name="coupon_id" id="ev-coupon" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">— بلا كوبون</option>
                            @foreach ($coupons as $coupon)
                                <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                {{-- الغلاف (12.11) — العمود كان موجودًا وميّتًا بلا حقل يكتبه --}}
                <label class="block text-sm font-semibold mt-3">غلاف الفعاليّة
                    <input type="text" name="cover_path" id="ev-cover" maxlength="255"
                           placeholder="مسار الصورة من مكتبة الوسائط"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">كود الحضور (OTP رقميّ)
                        <input type="text" name="attendance_code" id="ev-code" maxlength="32"
                               placeholder="يُولَّد تلقائيًّا لو فاضي"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    {{-- نوع شهادة الحضور يُضبَط في «إدارة الشهادات» لا هنا (13.3 · 12.5) --}}
                    <div class="text-sm font-semibold">نوع شهادة الحضور
                        <p class="mt-1 rounded-xl px-3 py-2 text-xs font-normal leading-relaxed"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text-muted)">
                            بيتظبط من <strong>إدارة الشهادات</strong> — النوع المربوط بالفعاليّات هو المفتاح
                            «{{ setting('events.certificate.default_type_key', 'event') }}»، وأيّ تعديل عليه بيسري على كلّ الفعاليّات.
                        </p>
                    </div>
                    <label class="text-sm font-semibold">الحالة
                        <select name="status" id="ev-status" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="draft">مسودّة</option>
                            <option value="published">منشورة</option>
                        </select>
                    </label>
                </div>

                {{-- جدول المكافأة المتدرّجة زمنيًّا (13.3) --}}
                <details class="mt-3 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <summary class="cursor-pointer text-sm font-semibold select-none">المكافأة المتدرّجة زمنيًّا</summary>
                    <div class="space-y-2 mt-2">
                        @foreach ($defaultTiers as $i => $tier)
                            <div class="grid grid-cols-3 gap-2">
                                <label class="text-xs">خلال (ساعة)
                                    <input type="number" min="1" name="reward_tiers[{{ $i }}][hours]" value="{{ $tier['hours'] ?? '' }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">XP
                                    <input type="number" min="0" name="reward_tiers[{{ $i }}][xp]" value="{{ $tier['xp'] ?? 0 }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">تذاكر
                                    <input type="number" min="0" name="reward_tiers[{{ $i }}][tickets]" value="{{ $tier['tickets'] ?? 0 }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            </div>
                        @endforeach
                    </div>
                </details>

                {{-- الأجندة والمتحدّثون --}}
                <details class="mt-2 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <summary class="cursor-pointer text-sm font-semibold select-none">الأجندة والمتحدّثون</summary>
                    <div class="space-y-2 mt-2">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" name="agenda[{{ $i }}][title]" placeholder="عنوان الجلسة" maxlength="180"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <input type="text" name="agenda[{{ $i }}][speaker]" placeholder="المتحدّث" maxlength="120"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <input type="datetime-local" name="agenda[{{ $i }}][starts_at]"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                            </div>
                        @endfor
                    </div>
                </details>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ الفعاليّة</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-event-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('ev-id').value = btn.dataset.id;
                document.getElementById('ev-title').value = btn.dataset.title;
                document.getElementById('ev-title-en').value = btn.dataset.titleEn || '';
                document.getElementById('ev-mode').value = btn.dataset.mode;
                document.getElementById('ev-starts').value = btn.dataset.starts || '';
                document.getElementById('ev-capacity').value = btn.dataset.capacity || '';
                document.getElementById('ev-code').value = btn.dataset.code || '';
                document.getElementById('ev-location').value = btn.dataset.location || '';
                document.getElementById('ev-join').value = btn.dataset.join || '';
                document.getElementById('ev-registration').value = btn.dataset.registration || '';
                document.getElementById('ev-price-coins').value = btn.dataset.priceCoins || 0;
                document.getElementById('ev-price-tickets').value = btn.dataset.priceTickets || 0;
                document.getElementById('ev-coupon').value = btn.dataset.coupon || '';
                document.getElementById('ev-cover').value = btn.dataset.cover || '';
                document.getElementById('ev-status').value = btn.dataset.status;
                const modal = document.getElementById('event-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
