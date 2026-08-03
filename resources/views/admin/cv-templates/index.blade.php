@extends('layouts.admin')

@section('title', setting('cv.template.admin.page_title', 'قوالب السيرة الذاتيّة'))

@section('content')
    {{-- إدارة قوالب الـCV (9): الأدمن يضيف قوالب، ولكلٍّ عدد تذاكر خاصّ --}}
    <x-page-header
        :title="setting('cv.template.admin.page_title', 'قوالب السيرة الذاتيّة')"
        :subtitle="setting('cv.template.admin.page_subtitle', 'أضِف قوالب، وحدّد تذاكر كلّ قالب، ووقّف اللي مش عايزه.')"
        :breadcrumbs="[
            ['label' => setting('cv.template.admin.section_label', 'الإعدادات والنظام'), 'url' => route('admin.settings.index')],
            ['label' => setting('cv.template.admin.page_title', 'قوالب السيرة الذاتيّة')],
        ]">
        @can('cv_templates.create')
            <x-slot:action>
                <button type="button" data-modal-open="cv-template-new"
                        class="btn hidden md:inline-flex items-center rounded-xl px-4 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('cv.template.admin.add_label', 'قالب جديد') }}
                </button>
            </x-slot:action>
        @endcan
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty :message="setting('cv.template.admin.empty_message', 'مفيش قوالب لسّه — ابدأ بواحد.')" />
    @else
        <div class="space-y-3">
            @foreach ($templates as $template)
                <form method="post" action="{{ route('admin.cv-templates.update', $template) }}" class="card p-4">
                    @csrf
                    @method('put')

                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <strong class="text-sm">{{ $template->name }}</strong>
                            @if ($template->is_free)
                                <x-state-badge state="ok" :label="setting('cv.template.free_badge', 'مجّانيّ')" />
                            @endif
                            @unless ($template->is_active)
                                <x-state-badge state="idle" :label="setting('cv.template.admin.inactive_badge', 'موقوف')" />
                            @endunless
                        </div>

                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ $usage[$template->id] ?? 0 }} {{ setting('cv.template.admin.usage_suffix', 'سيرة تستعمله') }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <x-form.input name="name" :label="setting('cv.template.admin.name_label', 'اسم القالب')" :value="$template->name" required />
                        <x-form.input name="description" :label="setting('cv.template.admin.description_label', 'وصف مختصر')" :value="$template->description" />

                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('cv.template.admin.view_label', 'ملفّ العرض') }}</span>
                            <select name="view_path" class="w-full rounded-xl px-3 text-sm"
                                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                @foreach ($views as $view)
                                    <option value="{{ $view }}" @selected($template->view_path === $view)>{{ $view }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('cv.template.admin.price_label', 'التذاكر (فاضي = جدول أوجه الصرف)') }}</span>
                            <input type="number" name="price_tickets" min="0" step="1" value="{{ $template->getAttribute('price_tickets') }}"
                                   placeholder="{{ (int) $defaultPrice }}"
                                   class="w-full rounded-xl px-3 text-sm"
                                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                {{ setting('cv.template.admin.effective_price_prefix', 'الفعليّ الآن:') }} {{ (int) $template->priceTickets() }}
                            </span>
                        </label>

                        <x-form.input name="preview_path" :label="setting('cv.template.admin.preview_label', 'مسار صورة المعاينة')" :value="$template->preview_path" />
                        <x-form.input name="sort_order" type="number" :label="setting('cv.template.admin.sort_label', 'الترتيب')" :value="$template->sort_order" />
                    </div>

                    {{-- أثر القالب في مخرَج الـATS — العمق خلف خطوة واحدة (2.15) --}}
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm" style="min-height: 44px">
                            {{ setting('cv.template.admin.ats_summary', 'تباعد وأحجام مخرَج الـATS') }}
                        </summary>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mt-3">
                            @foreach ([
                                'margin_pt' => setting('cv.template.admin.margin_label', 'الهامش'),
                                'body_size_pt' => setting('cv.template.admin.body_label', 'حجم النصّ'),
                                'heading_size_pt' => setting('cv.template.admin.heading_label', 'حجم العنوان'),
                                'title_size_pt' => setting('cv.template.admin.title_label', 'حجم الاسم'),
                                'leading' => setting('cv.template.admin.leading_label', 'تباعد السطور'),
                            ] as $key => $label)
                                <label class="block">
                                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ $label }}</span>
                                    <input type="number" step="0.05" name="ats_options[{{ $key }}]"
                                           value="{{ $template->atsOptions()[$key] ?? '' }}"
                                           class="w-full rounded-xl px-3 text-sm"
                                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <label class="flex items-center gap-2" style="min-height: 44px">
                                <input type="hidden" name="is_free" value="0">
                                <input type="checkbox" name="is_free" value="1" class="w-5 h-5" @checked($template->is_free)>
                                {{ setting('cv.template.admin.is_free_label', 'القالب المجّانيّ') }}
                            </label>
                            <label class="flex items-center gap-2" style="min-height: 44px">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="w-5 h-5" @checked($template->is_active)>
                                {{ setting('cv.template.admin.is_active_label', 'متاح للمستخدمين') }}
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            @can('cv_templates.delete')
                                <button type="submit" formmethod="post"
                                        formaction="{{ route('admin.cv-templates.destroy', $template) }}"
                                        name="_method" value="DELETE"
                                        class="btn rounded-xl px-4 text-sm"
                                        style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">
                                    {{ setting('cv.template.admin.delete_label', 'حذف') }}
                                </button>
                            @endcan
                            <button type="submit" class="btn rounded-xl px-5 text-sm font-semibold motion-standard"
                                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                                {{ setting('cv.template.admin.save_label', 'حفظ') }}
                            </button>
                        </div>
                    </div>
                </form>
            @endforeach
        </div>
    @endif

    @can('cv_templates.create')
        <x-modal id="cv-template-new" :title="setting('cv.template.admin.add_label', 'قالب جديد')">
            <form id="cv-template-new-form" method="post" action="{{ route('admin.cv-templates.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name" :label="setting('cv.template.admin.name_label', 'اسم القالب')" required />
                <x-form.input name="description" :label="setting('cv.template.admin.description_label', 'وصف مختصر')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('cv.template.admin.view_label', 'ملفّ العرض') }}</span>
                    <select name="view_path" class="w-full rounded-xl px-3 text-sm"
                            style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($views as $view)
                            <option value="{{ $view }}">{{ $view }}</option>
                        @endforeach
                    </select>
                </label>

                <x-form.input name="price_tickets" type="number"
                              :label="setting('cv.template.admin.price_label', 'التذاكر (فاضي = جدول أوجه الصرف)')" />

                <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="w-5 h-5" checked>
                    {{ setting('cv.template.admin.is_active_label', 'متاح للمستخدمين') }}
                </label>
            </form>

            <x-slot:footer>
                <button type="submit" form="cv-template-new-form"
                        class="btn rounded-xl px-5 text-sm font-semibold"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('cv.template.admin.create_label', 'إضافة') }}
                </button>
            </x-slot:footer>
        </x-modal>
    @endcan
@endsection

@section('mobile_action')
    @can('cv_templates.create')
        <button type="button" data-modal-open="cv-template-new"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('cv.template.admin.add_label', 'قالب جديد') }}
        </button>
    @endcan
@endsection
