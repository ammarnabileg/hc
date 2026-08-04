{{--
    المكتبة الرقميّة والحماية (20.5): لكلّ منتج قابل للتحميل/Flip-only محميّ +
    تشغيل العلامة المائيّة + صلاحيّة زمنيّة + صفحات العيّنة + فهرس القارئ (20.3).
    والتحليلات **مجمّعة فقط** — بلا سجلّ فتح فرديّ لأيّ ملفّ (مرفوض صراحةً).
--}}

@if ($analytics)
    <section class="card p-4 mb-4">
        <div class="flex items-baseline justify-between gap-3 flex-wrap mb-3">
            <h2 class="font-bold">{{ setting('library.analytics.title', 'تحليلات المكتبة (مجمّعة)') }}</h2>
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('library.analytics.note') }}</p>
        </div>

        @if ($analytics['products'] === 0)
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('library.analytics.empty_text') }}</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 mb-4">
                <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                    <p class="text-xs" style="color: var(--text-muted)">{{ setting('library.analytics.readers_label', 'قرّاء') }}</p>
                    <p class="text-lg font-bold tabular-nums">{{ $analytics['readers'] }}</p>
                </div>
                <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                    <p class="text-xs" style="color: var(--text-muted)">{{ setting('library.analytics.completion_label', 'متوسّط الإكمال') }}</p>
                    <p class="text-lg font-bold tabular-nums">{{ $analytics['average_completion'] }}%</p>
                </div>
            </div>

            <h3 class="text-sm font-semibold mb-2">{{ setting('library.analytics.top_label', 'الأكثر قراءةً') }}</h3>
            <ul class="space-y-2">
                @foreach ($analytics['top'] as $row)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="truncate">{{ $row['title'] }}</span>
                        <span class="shrink-0 tabular-nums" style="color: var(--text-muted)">
                            {{ $row['readers'] }} {{ setting('library.analytics.readers_label', 'قرّاء') }}
                            · {{ $row['completion'] }}%
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

<div class="card overflow-hidden">
    <table class="hidden md:table w-full text-sm">
        <thead style="background: var(--surface-sunken)">
            <tr class="text-xs" style="color: var(--text-muted)">
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.almlf', 'الملفّ') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.wda_alhmaya', 'وضع الحماية') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.alalama_almayya', 'العلامة المائيّة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.alslahya', 'الصلاحيّة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.sfhat_alayna', 'صفحات العيّنة') }}</th>
                <th class="text-start p-3">{{ setting('admin.store.partials.table_library.tadyl_alhmaya', 'تعديل الحماية') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $item)
                <tr style="border-top: 1px solid var(--border)">
                    <td class="p-3 font-semibold">
                        {{ $item->name_ar }}
                        <span class="block text-xs font-normal" style="color: var(--text-muted)">{{ $item->type }}</span>
                    </td>
                    <td class="p-3">
                        <x-state-badge :state="$item->is_downloadable ? 'idle' : 'ok'"
                                       :label="$item->is_downloadable ? setting('admin.store.partials.table_library.qabl_llthmyl', 'قابل للتحميل') : setting('admin.store.partials.table_library.flip_only_mhmy', 'Flip-only محميّ')" />
                    </td>
                    <td class="p-3">
                        <x-state-badge :state="$item->watermark_enabled ? 'ok' : 'idle'"
                                       :label="$item->watermark_enabled ? setting('admin.store.partials.table_library.mfala', 'مفعَّلة') : setting('admin.store.partials.table_library.mtwqfa', 'متوقّفة')" />
                    </td>
                    <td class="p-3">
                        {{ $item->access_days ? $item->access_days.setting('admin.store.partials.table_library.ywm', ' يوم') : setting('admin.store.partials.table_library.wswl_daym', 'وصول دائم') }}
                    </td>
                    <td class="p-3 tabular-nums">{{ $item->teaser_pages }}</td>
                    <td class="p-3">
                        @can('product_protection.manage')
                            {{-- التفاصيل في بانل مطويّ لا صفحة جديدة (2.15-أ-6) --}}
                            <details>
                                <summary class="cursor-pointer text-xs underline list-none">{{ setting('admin.store.partials.table_library.tadyl', 'تعديل') }}</summary>

                                <form method="post" action="{{ route('admin.store.protection.update', $item) }}"
                                      class="mt-2 space-y-2 w-72">
                                    @csrf

                                    <select name="protection" class="w-full rounded-lg px-2 py-1 text-xs"
                                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                        @foreach ($protectionModes as $key => $label)
                                            <option value="{{ $key }}" @selected(($key === 'download') === (bool) $item->is_downloadable)>{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <label class="flex items-center gap-2 text-xs">
                                        <input type="hidden" name="watermark_enabled" value="0">
                                        <input type="checkbox" name="watermark_enabled" value="1" @checked($item->watermark_enabled)>
                                        <span>{{ setting('admin.store.partials.table_library.tshghyl_alalama_almayya', 'تشغيل العلامة المائيّة') }}</span>
                                    </label>

                                    <label class="block text-xs">
                                        <span class="block mb-1">{{ setting('admin.store.partials.table_library.slahya_zmnya_balayam', 'صلاحيّة زمنيّة بالأيّام') }} <span style="color: var(--text-muted)">{{ setting('admin.store.partials.table_library.fargh_daym', '(فارغ = دائم)') }}</span></span>
                                        <input type="number" name="access_days" value="{{ $item->access_days }}" min="1" max="36500"
                                               class="w-full rounded-lg px-2 py-1"
                                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                    </label>

                                    <label class="block text-xs">
                                        <span class="block mb-1">{{ setting('admin.store.partials.table_library.sfhat_alayna', 'صفحات العيّنة') }}</span>
                                        <input type="number" name="teaser_pages" value="{{ $item->teaser_pages }}" min="0" max="200"
                                               class="w-full rounded-lg px-2 py-1"
                                               style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                                    </label>

                                    <label class="block text-xs">
                                        <span class="block mb-1">{{ setting('admin.store.partials.table_library.fhrs_alqary_str_lkl_fsl', 'فهرس القارئ — سطر لكلّ فصل:') }} <code>{{ setting('admin.store.partials.table_library.rqm_alsfha_alanwan', 'رقم الصفحة | العنوان') }}</code></span>
                                        <textarea name="toc" rows="4" class="w-full rounded-lg px-2 py-1 font-mono"
                                                  style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">{{ $toc->toText($item) }}</textarea>
                                    </label>

                                    <button class="btn w-full rounded-lg px-3 py-1.5 text-xs font-semibold"
                                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.table_library.hfz', 'حفظ') }}</button>
                                </form>
                            </details>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
    <div class="md:hidden">
        @foreach ($rows as $item)
            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                <div class="font-semibold">{{ $item->name_ar }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ $item->is_downloadable ? setting('admin.store.partials.table_library.qabl_llthmyl', 'قابل للتحميل') : setting('admin.store.partials.table_library.flip_only_mhmy', 'Flip-only محميّ') }}
                    {{ setting('admin.store.partials.table_library.alama_mayya', '· علامة مائيّة') }} {{ $item->watermark_enabled ? setting('admin.store.partials.table_library.mfala', 'مفعَّلة') : setting('admin.store.partials.table_library.mtwqfa', 'متوقّفة') }}
                    · {{ $item->access_days ? $item->access_days.setting('admin.store.partials.table_library.ywm', ' يوم') : setting('admin.store.partials.table_library.wswl_daym', 'وصول دائم') }}
                    {!! strtr(setting('admin.store.partials.table_library.ayna_v1_sfha', '· عيّنة :v1 صفحة'), [':v1' => e($item->teaser_pages)]) !!}
                </div>
            </div>
        @endforeach
    </div>
</div>
