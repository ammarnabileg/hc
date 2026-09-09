@extends('layouts.admin')

@section('title', setting('admin.guidance.email_templates.qwalb_albryd', 'قوالب البريد'))

@section('content')
    {{-- قوالب البريد (24.3 سطر 5067 · email_templates.*) — كانت صلاحيّةً بلا شاشة --}}
    <x-page-header
        :title="setting('admin.guidance.email_templates.qwalb_albryd', 'قوالب البريد')"
        :subtitle="setting('admin.guidance.email_templates.subtitle', 'نصّ القالب لكلّ نوع إشعار، ووسومه الديناميكيّة، وحالته.')"
        :breadcrumbs="[
            ['label' => setting('admin.guidance.notifications.alishaarat', 'الإشعارات'), 'url' => route('admin.guidance.notifications')],
            ['label' => setting('admin.guidance.email_templates.qwalb_albryd', 'قوالب البريد')],
        ]">
        <x-slot:action>
            <div class="flex items-center gap-2">
                @can('email_templates.import')
                    <button type="button" data-modal-open="email-template-import"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--surface-raised)">{{ setting('admin.guidance.email_templates.astyrad', 'استيراد') }}</button>
                @endcan
                @can('email_templates.export')
                    <a href="{{ route('admin.guidance.email-templates.export') }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                       style="background: var(--surface-raised)">{{ setting('admin.guidance.email_templates.tsdyr', 'تصدير') }}</a>
                @endcan
                @can('email_templates.create')
                    <button type="button" data-modal-open="email-template-new"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.email_templates.qalb_jdyd', 'قالب جديد') }}</button>
                @endcan
            </div>
        </x-slot:action>
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty :message="setting('admin.guidance.email_templates.mfysh_qwalb_lsa', 'مفيش قوالب لسّه.')" />
    @else
        <div class="space-y-3">
            @foreach ($templates as $template)
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <strong class="text-sm">{{ $template->name }}</strong>
                                <x-state-badge :state="$template->is_enabled ? 'ok' : 'idle'"
                                               :label="$template->is_enabled ? setting('admin.guidance.email_templates.mfaal', 'مفعّل') : setting('admin.guidance.email_templates.mafaal', 'غير مفعّل')" />
                                @if ($template->status === 'archived')
                                    <x-state-badge state="idle" :label="setting('admin.guidance.email_templates.marshf', 'مؤرشَف')" />
                                @endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $template->category ? ($types[$template->category] ?? $template->category) : setting('admin.guidance.email_templates.qalb_aam', 'قالبٌ عامّ — غير مربوط بنوعٍ') }}
                            </div>
                            @if ($template->subject)
                                <div class="text-sm mt-2 font-semibold">{{ $template->subject }}</div>
                            @endif
                            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($template->body, 160) }}</p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @can('email_templates.edit')
                                <button type="button" data-modal-open="email-template-edit-{{ $template->id }}"
                                        class="btn rounded-xl px-3 py-2 text-xs"
                                        style="background: var(--surface-sunken)">{{ setting('admin.guidance.email_templates.tadyl', 'تعديل') }}</button>
                            @endcan
                            @can('email_templates.archive')
                                <form method="post" action="{{ route('admin.guidance.email-templates.archive', $template) }}">
                                    @csrf
                                    <button class="btn rounded-xl px-3 py-2 text-xs" style="background: var(--surface-sunken)">
                                        {{ $template->status === 'archived' ? setting('admin.guidance.email_templates.astaad', 'استعادة') : setting('admin.guidance.email_templates.arshf', 'أرشفة') }}
                                    </button>
                                </form>
                            @endcan
                            @can('email_templates.delete')
                                <form method="post" action="{{ route('admin.guidance.email-templates.destroy', $template) }}"
                                      onsubmit="return confirm('{{ setting('admin.guidance.email_templates.confirm_delete', 'هنشيل القالب ده خالص — نكمّل؟') }}')">
                                    @csrf
                                    @method('delete')
                                    <button class="btn rounded-xl px-3 py-2 text-xs" style="background: var(--color-state-danger); color: #fff">{{ setting('admin.guidance.email_templates.hdhf', 'حذف') }}</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>

                @can('email_templates.edit')
                    <x-modal :id="'email-template-edit-'.$template->id" :title="$template->name">
                        <form id="email-template-edit-form-{{ $template->id }}" method="post"
                              action="{{ route('admin.guidance.email-templates.update', $template) }}" class="space-y-3">
                            @csrf
                            @method('put')
                            <x-form.input name="name" :label="setting('admin.guidance.email_templates.asm_alqalb', 'اسم القالب')" :value="$template->name" required />

                            <label class="block">
                                <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.alnwa_almrtbt', 'النوع المرتبط (اختياريّ)') }}</span>
                                <select name="category" class="w-full rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    <option value="">{{ setting('admin.guidance.email_templates.qalb_aam', 'قالبٌ عامّ — غير مربوط بنوعٍ') }}</option>
                                    @foreach ($types as $key => $label)
                                        <option value="{{ $key }}" @selected($template->category === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <x-form.input name="subject" :label="setting('admin.guidance.email_templates.aleenwan', 'عنوان الرسالة (اختياريّ)')" :value="$template->subject" />

                            <label class="block">
                                <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.mhtwa_alqalb', 'محتوى القالب') }}</span>
                                <textarea name="body" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $template->body }}</textarea>
                                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                    {{ setting('admin.guidance.email_templates.wswm_mtaha', 'الوسوم المتاحة:') }} {{ implode(' · ', array_keys($tokens ?? [])) }}
                                </span>
                            </label>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_enabled" value="0">
                                <input type="checkbox" name="is_enabled" value="1" @checked($template->is_enabled)>
                                {{ setting('admin.guidance.email_templates.mfaal', 'مفعّل') }}
                            </label>
                        </form>

                        <x-slot:footer>
                            <button type="submit" form="email-template-edit-form-{{ $template->id }}"
                                    class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.email_templates.hfz', 'احفظ') }}</button>
                        </x-slot:footer>
                    </x-modal>
                @endcan
            @endforeach
        </div>
    @endif

    @can('email_templates.create')
        <x-modal id="email-template-new" :title="setting('admin.guidance.email_templates.qalb_jdyd', 'قالب جديد')">
            <form id="email-template-new-form" method="post" action="{{ route('admin.guidance.email-templates.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name" :label="setting('admin.guidance.email_templates.asm_alqalb', 'اسم القالب')" required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.alnwa_almrtbt', 'النوع المرتبط (اختياريّ)') }}</span>
                    <select name="category" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.guidance.email_templates.qalb_aam', 'قالبٌ عامّ — غير مربوط بنوعٍ') }}</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <x-form.input name="subject" :label="setting('admin.guidance.email_templates.aleenwan', 'عنوان الرسالة (اختياريّ)')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.mhtwa_alqalb', 'محتوى القالب') }}</span>
                    <textarea name="body" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1">
                    {{ setting('admin.guidance.email_templates.mfaal', 'مفعّل') }}
                </label>
            </form>

            <x-slot:footer>
                <button type="submit" form="email-template-new-form"
                        class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.email_templates.idafa', 'إضافة') }}</button>
            </x-slot:footer>
        </x-modal>
    @endcan

    @can('email_templates.import')
        <x-modal id="email-template-import" :title="setting('admin.guidance.email_templates.astyrad', 'استيراد')">
            <form id="email-template-import-form" method="post" action="{{ route('admin.guidance.email-templates.import') }}"
                  enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.mlf_csv', 'ملفّ CSV بنفس ترويسة التصدير') }}</span>
                    <input type="file" name="file" accept=".csv,text/csv" required class="w-full text-sm">
                </label>
            </form>

            <x-slot:footer>
                <button type="submit" form="email-template-import-form"
                        class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.email_templates.astyrad', 'استيراد') }}</button>
            </x-slot:footer>
        </x-modal>
    @endcan
@endsection
