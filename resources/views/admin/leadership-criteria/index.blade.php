@extends('layouts.admin')

@section('title', setting('leadership_criteria.page_title', 'معايير مؤشّر القيادة'))

@section('content')
    {{-- 13.4-ن-د: بنود يحدّدها الأدمن، يقيَّم عليها الأبلاين أسبوعيًّا من داونلاينه --}}
    <x-page-header :title="setting('leadership_criteria.page_title', 'معايير مؤشّر القيادة')"
                   :subtitle="setting('leadership_criteria.page_subtitle', 'بنود التقييم الأسبوعيّ (Leadership Pulse) — بدرجة ووزن اختياريّ لكلّ معيار.')"
                   :breadcrumbs="[
                       ['label' => setting('leadership_criteria.breadcrumb_volunteer', 'إدارة التطوّع'), 'url' => route('admin.volunteer.index')],
                       ['label' => setting('leadership_criteria.page_title', 'معايير مؤشّر القيادة')],
                   ]">
        @can('leadership_criteria.create')
            <x-slot:action>
                <button type="button" data-modal-open="leadership-criterion-new"
                        class="btn hidden md:inline-flex items-center rounded-xl px-4 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('leadership_criteria.add_label', '+ معيار جديد') }}
                </button>
            </x-slot:action>
        @endcan
    </x-page-header>

    @if ($criteria->isEmpty())
        <x-empty :message="setting('leadership_criteria.empty_message', 'مفيش معايير لسّه — ابدأ بأوّل معيار، وإلّا هيرفض النظام كلّ تقييم أسبوعيّ.')" />
    @else
        <div class="space-y-3">
            @foreach ($criteria as $criterion)
                <div class="card p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <strong class="text-sm">{{ $criterion->label_ar }}</strong>
                            <code class="text-xs" style="color: var(--text-muted)">{{ $criterion->key }}</code>
                            <x-state-badge :state="$criterion->is_archived ? 'idle' : 'ok'"
                                           :label="$criterion->is_archived ? setting('leadership_criteria.archived_badge', 'مؤرشف') : setting('leadership_criteria.active_badge', 'شغّال')" />
                        </div>
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ (int) ($usage[$criterion->key] ?? 0) }} {{ setting('leadership_criteria.usage_suffix', 'تقييم استعمله') }}
                        </span>
                    </div>

                    <form method="post" action="{{ route('admin.volunteer.leadership-criteria.update', $criterion) }}" class="grid gap-3 sm:grid-cols-3">
                        @csrf
                        @method('put')
                        <x-form.input name="label_ar" :label="setting('leadership_criteria.field.label', 'اسم المعيار')" :value="$criterion->label_ar" required />
                        <x-form.input name="weight" type="number" :label="setting('leadership_criteria.field.weight', 'الوزن')" :value="$criterion->weight" />
                        <x-form.input name="sort_order" type="number" :label="setting('leadership_criteria.field.sort', 'الترتيب')" :value="$criterion->sort_order" />

                        <div class="sm:col-span-3 flex flex-wrap items-center justify-between gap-2 mt-1">
                            <div class="flex items-center gap-2">
                                @can('leadership_criteria.delete')
                                    @if ($criterion->is_archived)
                                        @can('leadership_criteria.restore')
                                            <button type="submit" formmethod="post"
                                                    formaction="{{ route('admin.volunteer.leadership-criteria.restore', $criterion) }}"
                                                    class="btn rounded-xl px-4 text-sm"
                                                    style="min-height: 44px; background: var(--surface-sunken); color: var(--text)">
                                                {{ setting('leadership_criteria.restore_label', '↺ استرجاع') }}
                                            </button>
                                        @endcan
                                    @else
                                        <button type="submit" formmethod="post"
                                                formaction="{{ route('admin.volunteer.leadership-criteria.destroy', $criterion) }}"
                                                name="_method" value="DELETE"
                                                onclick="return confirm('{{ setting('leadership_criteria.confirm_archive', 'أرشفة المعيار: يختفي من تقييم الأسبوع الجديد، والتقييمات القديمة تفضل تعرض درجته موسومًا «معيار مؤرشف». متأكّد؟') }}')"
                                                class="btn rounded-xl px-4 text-sm"
                                                style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">
                                            <input type="hidden" name="mode" value="new_only">
                                            {{ setting('leadership_criteria.archive_label', 'أرشفة (من الجديد فقط)') }}
                                        </button>
                                    @endif

                                    <button type="submit" formmethod="post"
                                            formaction="{{ route('admin.volunteer.leadership-criteria.destroy', $criterion) }}"
                                            name="_method" value="DELETE"
                                            onclick="return confirm('{{ setting('leadership_criteria.confirm_delete', 'حذف نهائيّ: يختفي المعيار تمامًا — حتى من التقييمات القديمة. الأثر لا يُتراجع عنه. متأكّد؟') }}')"
                                            class="btn rounded-xl px-4 text-sm"
                                            style="min-height: 44px; background: var(--surface-sunken); color: var(--danger, #b3261e)">
                                        <input type="hidden" name="mode" value="new_and_old">
                                        {{ setting('leadership_criteria.delete_label', 'حذف نهائيّ (من الجديد والقديم)') }}
                                    </button>
                                @endcan
                            </div>

                            @can('leadership_criteria.edit')
                                <button type="submit" class="btn rounded-xl px-5 text-sm font-semibold motion-standard"
                                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                                    {{ setting('leadership_criteria.save_label', 'حفظ') }}
                                </button>
                            @endcan
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @can('leadership_criteria.create')
        <x-modal id="leadership-criterion-new" :title="setting('leadership_criteria.add_label', '+ معيار جديد')">
            <form id="leadership-criterion-new-form" method="post" action="{{ route('admin.volunteer.leadership-criteria.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="key" :label="setting('leadership_criteria.field.key', 'المفتاح')" :hint="setting('leadership_criteria.field.key_hint', 'حروف إنجليزيّة صغيرة وأرقام و_ فقط — ثابت بعد الإنشاء.')" required />
                <x-form.input name="label_ar" :label="setting('leadership_criteria.field.label', 'اسم المعيار')" required />
                <x-form.input name="weight" type="number" :label="setting('leadership_criteria.field.weight', 'الوزن')" value="1" />
                <x-form.input name="sort_order" type="number" :label="setting('leadership_criteria.field.sort', 'الترتيب')" value="0" />
            </form>

            <x-slot:footer>
                <button type="submit" form="leadership-criterion-new-form"
                        class="btn rounded-xl px-5 text-sm font-semibold"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('leadership_criteria.create_label', 'إضافة') }}
                </button>
            </x-slot:footer>
        </x-modal>
    @endcan
@endsection

@section('mobile_action')
    @can('leadership_criteria.create')
        <button type="button" data-modal-open="leadership-criterion-new"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('leadership_criteria.add_label', '+ معيار جديد') }}
        </button>
    @endcan
@endsection
