@extends('layouts.admin')
@use('App\Services\Store\Coins')
@use('App\Services\Store\BundleLanding')

@section('title', $bundle->name_ar)

@section('content')
    {{--
        ⭐ **فورم البندل بأقسامه الستّة** — الخمسة المنصوصة في 24
        (`[الهويّة] [العناصر] [التسعير 🔒] [العرض] [الإتاحة]`) + `[كود مخصّص 🔒]`
        بأمر المالك، ومعها **نصوص اللاندنج وسكشناتها** فيصير كلّ نصٍّ في الصفحة
        قابلًا للتعديل من إعدادات هذا البندل وحده.

        🔒 وقسمان **يُحذَفان من المخرَج** لغير صاحبهما — لا يُعطَّلان (2.15-أ-7):
           [التسعير] لمن لا يملك `pricing.edit`، و[كود مخصّص] لغير مالك المنصّة.
    --}}
    @php
        $sectionLabels = (array) setting('store.admin.bundles.section_labels', []);
        $typeLabels = (array) setting('store.bundle.item_type_labels', []);
        $whenLabels = (array) setting('store.bundle.code_when_labels', []);
        $landingUrl = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);
        $sectionTitles = (array) setting('store.admin.bundles.section_titles', []);
        $codeSlotLabels = (array) setting('store.admin.bundles.code_slot_labels', []);
    @endphp

    <x-page-header :title="$bundle->name_ar"
                   :subtitle="setting('store.admin.bundles.form_subtitle', 'كلّ ما في صفحة الباقة — من عناصرها لآخر نصّ فيها.')"
                   :breadcrumbs="[
                       ['label' => setting('store.admin.breadcrumb_label', 'المتجر'), 'url' => route('admin.store.index', ['tab' => 'bundles'])],
                       ['label' => $bundle->name_ar],
                   ]">
        <x-slot:action>
            {{-- أزرار هيدر 24: معاينة اللاندنج · تكرار بندل (و«+ بندل» في شاشة الجدول) --}}
            <a href="{{ $landingUrl }}" target="_blank" rel="noopener"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold"
               style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.admin.bundles.preview_label') }}</a>

            @if (auth()->user()->allows('bundles.create'))
                <form method="post" action="{{ route('admin.store.bundles.duplicate', $bundle) }}">
                    @csrf
                    <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised); min-height: 44px">{{ setting('store.admin.bundles.duplicate_label') }}</button>
                </form>
            @endif
        </x-slot:action>
    </x-page-header>

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @error('item_slug')
        <x-toast :message="$message" state="danger" />
    @enderror

    {{-- ============================================================ [العناصر] --}}
    <section class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4 min-w-0">
            <h2 class="font-bold">{{ $sectionLabels['items'] ?? '' }}</h2>

            @if ($items->isEmpty())
                <x-empty :message="setting('store.admin.bundles.empty_items_text', 'الباقة لسّه فاضية — ضيف أوّل عنصر وهتتحسب قيمتها تلقائيًّا.')" />
            @else
                <div class="card overflow-hidden">
                    @foreach ($items as $line)
                        {{--
                            صفّ العنصر بنصّ 24: **السعر الطبيعيّ كقيمة افتراضيّة في
                            الإنبوت قابلة للتعديل (Override)** + **Toggle «اعرضه كبونص»**.
                        --}}
                        <form method="post" action="{{ route('admin.store.bundles.items.update', [$bundle, $line['id']]) }}"
                              class="p-3 flex flex-wrap items-end gap-3 min-w-0" style="border-top: 1px solid var(--border)">
                            @csrf
                            @method('put')

                            <div class="min-w-0" style="flex: 1 1 12rem">
                                <span class="block font-semibold text-sm">{{ $line['title'] }}</span>
                                <span class="block text-xs" style="color: var(--text-muted)">
                                    {{ $typeLabels[$line['type']] ?? $line['type'] }} ·
                                    {{ setting('store.admin.bundles.natural_price_label', 'الطبيعيّ') }} {{ Coins::label($line['list_value']) }}
                                </span>
                            </div>

                            @if ($canPrice)
                                {{-- 🔒 «Override عناصر البندل» منصوصٌ في وصف `pricing.edit` (12.2.2) --}}
                                <label class="text-xs min-w-0">
                                    <span class="block mb-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.override_label', 'سعره داخل الباقة') }}</span>
                                    <input type="number" step="0.01" min="0" name="price_coins" value="{{ $line['value'] }}"
                                           class="rounded-xl px-3 py-2 text-sm" style="width: 8rem; min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            @endif

                            <label class="inline-flex items-center gap-2 text-xs" style="min-height: 44px">
                                <input type="hidden" name="is_bonus" value="0">
                                <input type="checkbox" name="is_bonus" value="1" @checked($line['is_bonus'])>
                                <span>{{ setting('store.admin.bundles.bonus_toggle_label', 'اعرضه كبونص') }}</span>
                            </label>

                            <button class="rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.admin.bundles.save_row_label', 'احفظ') }}</button>
                        </form>

                        <form method="post" class="px-3 pb-3" action="{{ route('admin.store.bundles.items.destroy', [$bundle, $line['id']]) }}">
                            @csrf
                            @method('delete')
                            <button class="text-xs hover:underline" style="color: var(--text-muted); min-height: 44px">{{ setting('store.admin.bundles.remove_item_label', 'شيل العنصر') }}</button>
                        </form>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('admin.store.bundles.items.store', $bundle) }}"
                  class="card p-4 space-y-3" data-bundle-item-form>
                @csrf
                <h3 class="font-bold text-sm">{{ setting('store.admin.bundles.add_item_title', 'ضيف عنصر') }}</h3>

                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('store.admin.bundles.item_label', 'العنصر') }}</span>
                    <select name="item_slug" data-bundle-item-select required
                            class="w-full rounded-xl px-3 py-2 text-sm"
                            style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">…</option>
                        @foreach ($options as $type => $optionRows)
                            <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                                @foreach ($optionRows as $row)
                                    <option value="{{ $row['slug'] }}" data-type="{{ $type }}" data-price="{{ $row['price'] }}">
                                        {{ $row['title'] }} — {{ Coins::label($row['price']) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>

                <input type="hidden" name="item_type" data-bundle-item-type value="">

                @if ($canPrice)
                    {{-- ⭐ السعر الطبيعيّ كقيمة افتراضيّة في الإنبوت (18) — قابل للتعديل بسهولة --}}
                    <x-form.input name="price_coins" data-bundle-item-price
                                  :label="setting('store.admin.bundles.override_label', 'سعره داخل الباقة')" type="number" step="0.01"
                                  :hint="setting('store.admin.bundles.override_hint')" />
                @endif

                <label class="inline-flex items-center gap-2 text-sm" style="min-height: 44px">
                    <input type="hidden" name="is_bonus" value="0">
                    <input type="checkbox" name="is_bonus" value="1">
                    <span>{{ setting('store.admin.bundles.bonus_toggle_label', 'اعرضه كبونص') }}</span>
                </label>

                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.admin.bundles.add_item_button', 'ضيف للباقة') }}</button>
            </form>
        </div>

        {{-- ==================================================== [التسعير 🔒] --}}
        <aside class="space-y-4 min-w-0">
            @if ($canPrice)
                <div class="card p-4 space-y-2 text-sm">
                    <h2 class="font-bold">{{ $sectionLabels['pricing'] ?? '' }}</h2>

                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--text-muted)">{{ setting('store.bundle.total_value_label') }}</span>
                        {{-- ⭐ «محسوبة تلقائيًّا، للقراءة» بنصّ 24 — فلا إنبوت لها أصلًا --}}
                        <b>{{ Coins::label($totalValue) }}</b>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--text-muted)">{{ setting('store.admin.bundles.col_savings') }}</span>
                        <b>{{ $totalValue > 0 ? (int) round(max($totalValue - (float) $bundle->price_coins, 0) / $totalValue * 100) : 0 }}%</b>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--text-muted)">{{ setting('store.admin.bundles.col_purchases') }}</span>
                        <b>{{ $purchases }}</b>
                    </div>

                    <p class="text-xs pt-1" style="color: var(--text-muted)">{{ setting('store.admin.bundles.computed_value_hint') }}</p>
                </div>
            @else
                {{-- 🔒 12.7: مسؤول التسويق والمتجر يقرأ التسعير ولا يعدّله — والحقول محذوفة لا معطّلة --}}
                <div class="card p-4 text-sm">
                    <p style="color: var(--text-muted)">{{ setting('store.admin.bundles.pricing_locked_text') }}</p>
                    <p class="mt-2"><b>{{ Coins::label((float) $bundle->price_coins) }}</b></p>
                </div>
            @endif
        </aside>
    </section>

    {{-- ============ [الهويّة] · [التسعير] · [العرض] · [الإتاحة] · النصوص · [كود] --}}
    <form method="post" action="{{ route('admin.store.bundles.update', $bundle) }}" class="mt-5 space-y-4" data-bundle-form>
        @csrf
        @method('put')

        {{-- ------------------------------------------------------- [الهويّة] --}}
        <section class="card p-5 space-y-3">
            <h2 class="font-bold">{{ $sectionLabels['identity'] ?? '' }}</h2>

            <div class="grid gap-3 md:grid-cols-2">
                <x-form.input name="name_ar" :label="setting('store.admin.bundles.name_ar_label', 'الاسم (عربيّ)')" :value="$bundle->name_ar" required />
                <x-form.input name="name_en" :label="setting('store.admin.bundles.name_en_label', 'الاسم (إنجليزيّ)')" :value="$bundle->name_en" />
                <x-form.input name="slug" label="Slug" :value="$bundle->slug" required />

                <label class="block text-sm">
                    <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.status_label', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ((array) setting('store.admin.status_labels', []) as $key => $label)
                            <option value="{{ $key }}" @selected($bundle->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm md:col-span-2">
                    <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.description_label', 'الوصف (عربيّ)') }}</span>
                    <textarea name="description" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ $bundle->description }}</textarea>
                </label>

                <label class="block text-sm md:col-span-2">
                    <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.description_en_label', 'الوصف (إنجليزيّ)') }}</span>
                    <textarea name="description_en" rows="3" dir="ltr" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ $bundle->description_en }}</textarea>
                </label>

                <label class="inline-flex items-center gap-2 text-sm" style="min-height: 44px">
                    <input type="hidden" name="is_indexable" value="0">
                    <input type="checkbox" name="is_indexable" value="1" @checked($bundle->is_indexable)>
                    <span>{{ setting('store.admin.bundles.indexable_label', 'اسمح لمحرّكات البحث تفهرس الصفحة') }}</span>
                </label>
            </div>
        </section>

        {{-- ------------------------------------------------------- [التسعير 🔒] --}}
        @if ($canPrice)
            <section class="card p-5 space-y-3">
                <h2 class="font-bold">{{ $sectionLabels['pricing'] ?? '' }}</h2>

                <div class="grid gap-3 md:grid-cols-3">
                    <x-form.input name="price_coins" :label="setting('store.admin.bundles.price_label', 'سعر البندل (كوينز)')" type="number" step="0.01"
                                  :value="(float) $bundle->price_coins" />

                    {{-- ⭐ للقراءة فقط بنصّ 24 — ولا `name` لها فلا تُرسَل ولا تُكتَب --}}
                    <label class="block text-sm">
                        <span class="block mb-1 font-semibold">{{ setting('store.bundle.total_value_label') }}</span>
                        <output class="block rounded-xl px-3 py-2 text-sm"
                                style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">{{ Coins::label($totalValue) }}</output>
                    </label>

                    <label class="block text-sm">
                        <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.discount_percent_label', 'نسبة الخصم المعروضة') }}</span>
                        <output class="block rounded-xl px-3 py-2 text-sm"
                                style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">{{ $totalValue > 0 ? (int) round(max($totalValue - (float) $bundle->price_coins, 0) / $totalValue * 100) : 0 }}%</output>
                    </label>
                </div>

                <p class="text-xs" style="color: var(--text-muted)">{{ setting('store.admin.bundles.computed_value_hint') }}</p>
            </section>
        @endif

        {{-- ------------------------------------------------------- [العرض] --}}
        <section class="card p-5 space-y-3">
            <h2 class="font-bold">{{ $sectionLabels['display'] ?? '' }}</h2>

            <label class="inline-flex items-center gap-2 text-sm" style="min-height: 44px">
                <input type="hidden" name="show_anchor_strikethrough" value="0">
                <input type="checkbox" name="show_anchor_strikethrough" value="1" @checked($bundle->show_anchor_strikethrough)>
                <span>{{ setting('store.admin.bundles.anchor_toggle_label', 'اشطب السعر الطبيعيّ (Anchoring)') }}</span>
            </label>

            <label class="inline-flex items-center gap-2 text-sm ms-4" style="min-height: 44px">
                <input type="hidden" name="show_total_value" value="0">
                <input type="checkbox" name="show_total_value" value="1" @checked($bundle->show_total_value)>
                <span>{{ setting('store.admin.bundles.total_value_toggle_label', 'اعرض القيمة الإجماليّة') }}</span>
            </label>

            <x-form.input name="bonus_text_template" :label="setting('store.admin.bundles.bonus_template_label', 'قالب نصّ البونص لهذا البندل')"
                          :value="$bundle->bonus_text_template" :placeholder="setting('store.bundle.bonus_text')"
                          :hint="setting('store.admin.bundles.bonus_template_hint', 'المتغيّرات: {item} · {amount} — وسيبه فاضي عشان يورث القالب العامّ.')" />
        </section>

        {{-- ------------------------------------------------------- [الإتاحة] --}}
        <section class="card p-5 space-y-3">
            <h2 class="font-bold">{{ $sectionLabels['availability'] ?? '' }}</h2>

            <div class="grid gap-3 md:grid-cols-3">
                <x-form.input name="available_from" :label="setting('store.admin.bundles.available_from_label', 'بداية الإتاحة')" type="datetime-local"
                              :value="$bundle->available_from?->format('Y-m-d\TH:i')" />
                <x-form.input name="available_until" :label="setting('store.admin.bundles.available_until_label', 'نهاية الإتاحة')" type="datetime-local"
                              :value="$bundle->available_until?->format('Y-m-d\TH:i')" />
                <x-form.input name="purchase_limit" :label="setting('store.admin.bundles.purchase_limit_label', 'حدّ الشراء (عدد المقاعد)')" type="number"
                              :value="$bundle->purchase_limit" />
            </div>

            {{-- ⭐ القاعدة الأخلاقيّة مكتوبةٌ في الشاشة نفسها، لا في تعليقٍ يقرؤه المطوّر وحده --}}
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('store.admin.bundles.availability_hint') }}</p>
        </section>

        {{-- --------------------------- نصوص اللاندنج وسكشناتها (أمر المالك) --------- --}}
        <section class="card p-5 space-y-4">
            <h2 class="font-bold">{{ setting('store.admin.bundles.landing_section_title') }}</h2>
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('store.admin.bundles.inherit_hint') }}</p>

            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                @foreach (BundleLanding::SECTIONS as $section => $globalKey)
                    @include('admin.store.partials.bundle-section-switch', [
                        'section' => $section,
                        'label' => $sectionTitles[$section] ?? $section,
                    ])
                @endforeach
            </div>

            @php
                $groups = (array) setting('store.admin.bundles.text_groups', []);
                $textLabels = (array) setting('store.admin.bundles.text_labels', []);
                $longFields = (array) setting('store.admin.bundles.long_text_keys', []);
            @endphp

            @foreach ($groups as $groupKey => $groupLabel)
                <details class="rounded-xl p-4" style="background: var(--surface-sunken)" @if($loop->first) open @endif>
                    <summary class="cursor-pointer font-semibold text-sm" style="min-height: 44px; display: flex; align-items: center">{{ $groupLabel }}</summary>

                    <div class="grid gap-3 md:grid-cols-2 mt-3">
                        @foreach (BundleLanding::TEXTS as $key => $globalKey)
                            @continue(! str_starts_with($key, $groupKey.'.'))
                            @include('admin.store.partials.bundle-text-field', [
                                'key' => $key,
                                'label' => $textLabels[$key] ?? $key,
                                'rows' => in_array($key, $longFields, true) ? 3 : 0,
                            ])
                        @endforeach

                        {{-- القوائم الخاصّة بهذا البندل — سطرٌ لكلّ عنصر --}}
                        @if ($groupKey === 'hero')
                            <label class="block text-sm md:col-span-2">
                                <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.outcomes_label', 'بعد الباقة هتقدر… (سطر لكلّ نتيجة)') }}</span>
                                <textarea name="landing_outcomes" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ implode("\n", (array) ($bundle->landing_outcomes ?? [])) }}</textarea>
                            </label>
                        @endif

                        @if ($groupKey === 'fit')
                            <label class="block text-sm">
                                <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.fit_for_label', 'مناسبة لـ (سطر لكلّ حالة)') }}</span>
                                <textarea name="landing_fit_for" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ implode("\n", (array) ($bundle->landing_fit_for ?? [])) }}</textarea>
                            </label>
                            <label class="block text-sm">
                                <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.not_fit_for_label', 'مش مناسبة لـ (سطر لكلّ حالة)') }}</span>
                                <textarea name="landing_not_fit_for" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ implode("\n", (array) ($bundle->landing_not_fit_for ?? [])) }}</textarea>
                            </label>
                        @endif

                        @if ($groupKey === 'faq')
                            <label class="block text-sm md:col-span-2">
                                <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.faq_label', 'أسئلة هذا البندل — سطر لكلّ سؤال بصيغة: السؤال | الإجابة') }}</span>
                                <textarea name="landing_faq" rows="5" class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ collect((array) ($bundle->landing_faq ?? []))->map(fn ($r) => ($r['q'] ?? '').' | '.($r['a'] ?? ''))->implode("\n") }}</textarea>
                            </label>
                        @endif
                    </div>
                </details>
            @endforeach
        </section>

        {{-- ------------------------------------------------ [كود مخصّص 🔒] --}}
        @if ($canInjectCode)
            <section class="card p-5 space-y-3">
                <h2 class="font-bold">{{ setting('store.bundle.code_section_title') }}</h2>
                <p class="text-xs" style="color: var(--color-state-warn)">{{ setting('store.bundle.code_owner_only_note') }}</p>

                @foreach ([['landing_head_code', 'landing_head_code_when', 'head'], ['landing_body_end_code', 'landing_body_end_code_when', 'body_end']] as [$field, $whenField, $slot])
                    <label class="block text-sm">
                        <span class="block mb-1 font-semibold">{{ $codeSlotLabels[$slot] ?? $slot }}</span>
                        <textarea name="{{ $field }}" rows="5" dir="ltr" spellcheck="false"
                                  class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical; font-family: monospace">{{ $bundle->{$field} }}</textarea>
                    </label>

                    {{-- ⭐ خانة «متى يُحقَن؟» **إلزاميّة** — والافتراضيّ الأضيق (21.3-د · 2.9) --}}
                    <label class="block text-sm">
                        <span class="block mb-1 font-semibold">{{ setting('store.admin.bundles.code_when_label', 'متى يُحقَن؟') }}</span>
                        <select name="{{ $whenField }}" required class="w-full rounded-xl px-3 py-2 text-sm"
                                style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($whenLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($bundle->{$whenField} ?: 'ads') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach

                <p class="text-xs" style="color: var(--text-muted)">{{ setting('store.bundle.code_consent_note') }}</p>
            </section>
        @endif

        <div class="flex items-center gap-3">
            <button class="btn rounded-xl px-6 py-3 text-sm font-bold"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('store.admin.bundles.save_label', 'احفظ البندل') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        // ردّ فوريّ (2.17-ب): اختيار العنصر يملأ نوعه وسعره الطبيعيّ في الإنبوت
        (function () {
            var form = document.querySelector('[data-bundle-item-form]');
            if (!form) return;

            var select = form.querySelector('[data-bundle-item-select]');
            var type = form.querySelector('[data-bundle-item-type]');
            var price = form.querySelector('[data-bundle-item-price]');

            if (select) {
                select.addEventListener('change', function () {
                    var option = select.selectedOptions[0];
                    if (!option || !option.dataset.type) return;
                    if (type) type.value = option.dataset.type;
                    if (price) price.value = option.dataset.price;
                });
            }
        })();

        /*
         | ↺ «رجّع للموروث» — **يمسح** الحقل ولا يكتب الافتراضيّ فيه. والفارغ لا
         | يُخزَّن على الخادم، فيعود المفتاح غائبًا = وراثةٌ حيّة تتبع النصّ العامّ.
         */
        document.querySelectorAll('[data-revert-field]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = document.querySelector('[data-landing-text="' + btn.dataset.revertField + '"]');
                if (field) { field.value = ''; field.focus(); }
            });
        });
    </script>
@endpush
