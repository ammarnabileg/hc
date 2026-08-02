@extends('layouts.app')
@use('App\Services\Store\Coins')

@section('title', $bundle->name_ar)

@section('content')
    {{--
        عناصر الباقة (18): إضافة/حذف · **تسعير مستقلّ لكلّ عنصر (Override)** يصل
        الإنبوت **بسعره الطبيعيّ كقيمة افتراضيّة** · و«القيمة الإجماليّة» محسوبة
        من العناصر لا مكتوبة — فلا «وفّرت X» بلا سند (2.9).
        سؤال واحد للشاشة: «إيه اللي جوّه الباقة دي؟» (2.15-أ-1).
    --}}
    <x-page-header :title="$bundle->name_ar"
                   subtitle="عناصر الباقة وتسعير كلّ عنصر داخلها"
                   :breadcrumbs="[
                       ['label' => 'المتجر', 'url' => route('admin.store.index', ['tab' => 'bundles'])],
                       ['label' => $bundle->name_ar],
                   ]" />

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    @error('item_slug')
        <x-toast :message="$message" state="danger" />
    @enderror

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @if ($items->isEmpty())
                <x-empty message="الباقة لسّه فاضية — ضيف أوّل عنصر وهتتحسب قيمتها تلقائيًّا." />
            @else
                <div class="card overflow-hidden">
                    <table class="hidden md:table w-full text-sm">
                        <thead style="background: var(--surface-sunken)">
                            <tr class="text-xs" style="color: var(--text-muted)">
                                <th class="text-start p-3">العنصر</th>
                                <th class="text-start p-3">النوع</th>
                                <th class="text-start p-3">السعر الطبيعيّ</th>
                                <th class="text-start p-3">السعر داخل الباقة</th>
                                <th class="text-start p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $line)
                                <tr style="border-top: 1px solid var(--border)">
                                    <td class="p-3 font-semibold">{{ $line['title'] }}</td>
                                    <td class="p-3">{{ ['course' => 'تدريب', 'path' => 'مسار', 'product' => 'منتج'][$line['type']] ?? $line['type'] }}</td>
                                    <td class="p-3">{{ Coins::label($line['list_value']) }}</td>
                                    <td class="p-3">
                                        {{ Coins::label($line['value']) }}
                                        @if ($line['has_override'])
                                            <x-state-badge state="warn" label="سعر خاصّ" />
                                        @endif
                                    </td>
                                    <td class="p-3 text-end">
                                        <form method="post"
                                              action="{{ route('admin.store.bundles.items.destroy', [$bundle, $line['id']]) }}">
                                            @csrf
                                            @method('delete')
                                            <button class="text-xs hover:underline" style="color: var(--text-muted)">شيله</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
                    <div class="md:hidden">
                        @foreach ($items as $line)
                            <div class="p-3 text-sm" style="border-top: 1px solid var(--border)">
                                <div class="font-semibold">{{ $line['title'] }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-muted)">
                                    {{ Coins::label($line['value']) }}
                                    @if ($line['has_override'])
                                        · الطبيعيّ {{ Coins::label($line['list_value']) }}
                                    @endif
                                </div>
                                <form method="post" class="mt-2"
                                      action="{{ route('admin.store.bundles.items.destroy', [$bundle, $line['id']]) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="text-xs hover:underline" style="color: var(--text-muted)">شيله</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="card p-4 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span style="color: var(--text-muted)">{{ setting('store.bundle.total_value_label', 'القيمة الإجماليّة') }}</span>
                    <b>{{ Coins::label($totalValue) }}</b>
                </div>
                <div class="flex items-center justify-between">
                    <span style="color: var(--text-muted)">سعر الباقة</span>
                    <b>{{ Coins::label($bundle->price_coins) }}</b>
                </div>
                <div class="flex items-center justify-between">
                    <span style="color: var(--text-muted)">التوفير المعروض</span>
                    <b>{{ Coins::label(max($totalValue - (float) $bundle->price_coins, 0)) }}</b>
                </div>
                <p class="text-xs pt-1" style="color: var(--text-muted)">
                    القيمة الإجماليّة محسوبة من عناصر الباقة — فما يراه المستخدم هو الفرق الحقيقيّ (18 · 2.9).
                </p>
            </div>

            <form method="post" action="{{ route('admin.store.bundles.items.store', $bundle) }}"
                  class="card p-4 space-y-3" data-bundle-item-form>
                @csrf
                <h2 class="font-bold text-sm">ضيف عنصر</h2>

                <label class="block text-sm">
                    <span class="block mb-1">العنصر</span>
                    <select name="item_slug" data-bundle-item-select required
                            class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">اختر…</option>
                        @foreach ($options as $type => $rows)
                            <optgroup label="{{ ['course' => 'تدريبات', 'path' => 'مسارات', 'product' => 'منتجات'][$type] ?? $type }}">
                                @foreach ($rows as $row)
                                    <option value="{{ $row['slug'] }}" data-type="{{ $type }}" data-price="{{ $row['price'] }}">
                                        {{ $row['title'] }} — {{ Coins::label($row['price']) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>

                <input type="hidden" name="item_type" data-bundle-item-type value="">

                {{-- ⭐ السعر الطبيعيّ كقيمة افتراضيّة في الإنبوت (18) — قابل للتعديل بسهولة --}}
                <x-form.input name="price_coins" label="سعره داخل الباقة (Override)" type="number" step="0.01"
                              hint="بيوصل بسعره الطبيعيّ — عدّله لو عايز سعرًا خاصًّا داخل الباقة." />

                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">ضيف للباقة</button>
            </form>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        // ردّ فوريّ (2.17-ب): اختيار العنصر يملأ نوعه وسعره الطبيعيّ في الإنبوت
        (function () {
            const form = document.querySelector('[data-bundle-item-form]');
            if (!form) return;

            const select = form.querySelector('[data-bundle-item-select]');
            const type = form.querySelector('[data-bundle-item-type]');
            const price = form.querySelector('[name="price_coins"]');

            select?.addEventListener('change', () => {
                const option = select.selectedOptions[0];
                if (!option || !option.dataset.type) return;
                if (type) type.value = option.dataset.type;
                if (price) price.value = option.dataset.price;
            });
        })();
    </script>
@endpush
