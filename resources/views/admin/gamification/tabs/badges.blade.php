{{-- الشارات (7.4): إضافة/تعديل + **شرط الفتح مكتوب صراحةً** + الأيقونة --}}

<section class="card p-4 md:p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">بنك الشارات</h2>
        @can('badges.create')
            <button type="button" data-modal-open="badge-modal"
                    class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">+ شارة</button>
        @endcan
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse ($data['badges'] as $badge)
            <article class="rounded-xl p-3" style="background: var(--surface-sunken)">
                <div class="flex items-start gap-3">
                    {{-- أيقونة SVG بهويّة المنصّة — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
                    <span class="shrink-0 inline-flex items-center justify-center rounded-xl"
                          style="width: 42px; height: 42px; background: var(--surface); border: 1px solid var(--border)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                             style="color: var(--color-brand-500)">
                            <circle cx="12" cy="9" r="5" />
                            <path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <div class="font-semibold text-sm">{{ $badge->name_ar }}</div>
                        {{-- شرط الفتح مكتوب صراحةً — لا ألغاز --}}
                        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $badge->condition_text_ar }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mt-3">
                    <x-state-badge :state="$badge->is_active ? 'ok' : 'idle'" :label="$badge->is_active ? 'مفعّلة' : 'موقوفة'" />
                    <span class="flex items-center gap-2">
                        @can('badges.edit')
                            <button type="button" class="text-xs underline" data-badge-edit
                                    data-id="{{ $badge->id }}" data-key="{{ $badge->key }}"
                                    data-name="{{ $badge->name_ar }}" data-condition="{{ $badge->condition_text_ar }}"
                                    data-ckey="{{ $badge->condition_key }}" data-cvalue="{{ $badge->condition_value }}"
                                    data-icon="{{ $badge->icon_path }}">تعديل</button>
                        @endcan
                        @can('badges.delete')
                            <form method="post" action="{{ route('admin.gamification.badges.delete', $badge) }}"
                                  onsubmit="return confirm('تحذف الشارة دي؟')">
                                @csrf
                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
                            </form>
                        @endcan
                    </span>
                </div>
            </article>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty message="لا شارات بعد — أضف أوّل شارة." />
            </div>
        @endforelse
    </div>
</section>

@can('badges.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات الشارات',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_badges'],
    ])
@endcan

@push('modals')
    @can('badges.edit')
        <x-modal id="badge-modal" title="شارة">
            <form method="post" action="{{ route('admin.gamification.badges.save') }}">
                @csrf
                <input type="hidden" name="id" id="badge-id">

                <label class="block text-sm font-semibold mb-1" for="badge-key">المفتاح</label>
                <input type="text" name="key" id="badge-key" required maxlength="64"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3 font-mono"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="badge-name">الاسم</label>
                <input type="text" name="name_ar" id="badge-name" required maxlength="120"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="badge-condition">شرط الفتح — مكتوب صراحةً</label>
                <input type="text" name="condition_text_ar" id="badge-condition" required maxlength="255"
                       placeholder="مثال: أكمل 10 دروس في أسبوع واحد."
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">مفتاح الشرط الآليّ
                        <input type="text" name="condition_key" id="badge-ckey" maxlength="64"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">قيمته
                        <input type="number" min="0" name="condition_value" id="badge-cvalue"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <label class="block text-sm font-semibold mb-1" for="badge-icon">مسار الأيقونة (SVG بهويّة المنصّة)</label>
                <input type="text" name="icon_path" id="badge-icon" maxlength="255"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <input type="hidden" name="is_active" value="1">

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ الشارة</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-badge-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('badge-id').value = btn.dataset.id;
                document.getElementById('badge-key').value = btn.dataset.key;
                document.getElementById('badge-name').value = btn.dataset.name;
                document.getElementById('badge-condition').value = btn.dataset.condition;
                document.getElementById('badge-ckey').value = btn.dataset.ckey || '';
                document.getElementById('badge-cvalue').value = btn.dataset.cvalue || '';
                document.getElementById('badge-icon').value = btn.dataset.icon || '';
                const modal = document.getElementById('badge-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
