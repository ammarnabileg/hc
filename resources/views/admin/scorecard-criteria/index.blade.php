@extends('layouts.admin')

@section('title', setting('scorecard_criteria.page_title', 'معايير المقابلة'))

@section('content')
    {{-- 13.4-د: معايير يضيفها الأدمن بلا حدود، يجاوب عليها المشرف بدرجة /10 --}}
    <x-page-header :title="setting('scorecard_criteria.page_title', 'معايير المقابلة')"
                   :subtitle="setting('scorecard_criteria.page_subtitle', 'معايير نتيجة المقابلة الأولى (Scorecard) — بدرجة ووزن اختياريّ لكلّ معيار.')"
                   :breadcrumbs="[
                       ['label' => setting('scorecard_criteria.breadcrumb_volunteer', 'إدارة التطوّع'), 'url' => route('admin.volunteer.index')],
                       ['label' => setting('scorecard_criteria.page_title', 'معايير المقابلة')],
                   ]">
        @can('scorecard_criteria.create')
            <x-slot:action>
                <button type="button" data-modal-open="scorecard-criterion-new"
                        class="btn hidden md:inline-flex items-center rounded-xl px-4 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('scorecard_criteria.add_label', '+ معيار جديد') }}
                </button>
            </x-slot:action>
        @endcan
    </x-page-header>

    @if ($criteria->isEmpty())
        <x-empty :message="setting('scorecard_criteria.empty_message', 'مفيش معايير لسّه — ابدأ بأوّل معيار.')" />
    @else
        <div class="space-y-3">
            @foreach ($criteria as $criterion)
                <div class="card p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <strong class="text-sm">{{ $criterion->label_ar }}</strong>
                            <x-state-badge :state="$criterion->is_archived ? 'idle' : 'ok'"
                                           :label="$criterion->is_archived ? setting('scorecard_criteria.archived_badge', 'مؤرشف') : setting('scorecard_criteria.active_badge', 'شغّال')" />
                        </div>
                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ (int) ($usage[$criterion->id] ?? 0) }} {{ setting('scorecard_criteria.usage_suffix', 'نتيجة استعملته') }}
                        </span>
                    </div>

                    <form method="post" action="{{ route('admin.volunteer.scorecard-criteria.update', $criterion) }}" class="grid gap-3 sm:grid-cols-3">
                        @csrf
                        @method('put')
                        <x-form.input name="label_ar" :label="setting('scorecard_criteria.field.label', 'اسم المعيار')" :value="$criterion->label_ar" required />
                        <x-form.input name="weight" type="number" :label="setting('scorecard_criteria.field.weight', 'الوزن')" :value="$criterion->weight" />
                        <x-form.input name="sort_order" type="number" :label="setting('scorecard_criteria.field.sort', 'الترتيب')" :value="$criterion->sort_order" />

                        <div class="sm:col-span-3 flex flex-wrap items-center justify-between gap-2 mt-1">
                            <div class="flex items-center gap-2">
                                @can('scorecard_criteria.delete')
                                    @if ($criterion->is_archived)
                                        @can('scorecard_criteria.restore')
                                            <button type="submit" formmethod="post"
                                                    formaction="{{ route('admin.volunteer.scorecard-criteria.restore', $criterion) }}"
                                                    class="btn rounded-xl px-4 text-sm"
                                                    style="min-height: 44px; background: var(--surface-sunken); color: var(--text)">
                                                {{ setting('scorecard_criteria.restore_label', '↺ استرجاع') }}
                                            </button>
                                        @endcan
                                    @else
                                        <button type="submit" formmethod="post"
                                                formaction="{{ route('admin.volunteer.scorecard-criteria.destroy', $criterion) }}"
                                                name="_method" value="DELETE"
                                                onclick="return confirm('{{ setting('scorecard_criteria.confirm_archive', 'أرشفة المعيار: يختفي من الإدخال الجديد، والنتائج القديمة تفضل تعرض درجته موسومًا «معيار مؤرشف». متأكّد؟') }}')"
                                                class="btn rounded-xl px-4 text-sm"
                                                style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">
                                            <input type="hidden" name="mode" value="new_only">
                                            {{ setting('scorecard_criteria.archive_label', 'أرشفة (من الجديد فقط)') }}
                                        </button>
                                    @endif

                                    <button type="submit" formmethod="post"
                                            formaction="{{ route('admin.volunteer.scorecard-criteria.destroy', $criterion) }}"
                                            name="_method" value="DELETE"
                                            onclick="return confirm('{{ setting('scorecard_criteria.confirm_delete', 'حذف نهائيّ: يختفي المعيار تمامًا — حتى من نتائج المقابلات القديمة. الأثر لا يُتراجع عنه. متأكّد؟') }}')"
                                            class="btn rounded-xl px-4 text-sm"
                                            style="min-height: 44px; background: var(--surface-sunken); color: var(--danger, #b3261e)">
                                        <input type="hidden" name="mode" value="new_and_old">
                                        {{ setting('scorecard_criteria.delete_label', 'حذف نهائيّ (من الجديد والقديم)') }}
                                    </button>
                                @endcan
                            </div>

                            @can('scorecard_criteria.edit')
                                <button type="submit" class="btn rounded-xl px-5 text-sm font-semibold motion-standard"
                                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                                    {{ setting('scorecard_criteria.save_label', 'حفظ') }}
                                </button>
                            @endcan
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @can('scorecard_criteria.create')
        <x-modal id="scorecard-criterion-new" :title="setting('scorecard_criteria.add_label', '+ معيار جديد')">
            <form id="scorecard-criterion-new-form" method="post" action="{{ route('admin.volunteer.scorecard-criteria.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="label_ar" :label="setting('scorecard_criteria.field.label', 'اسم المعيار')" required />
                <x-form.input name="weight" type="number" :label="setting('scorecard_criteria.field.weight', 'الوزن')" value="1" />
                <x-form.input name="sort_order" type="number" :label="setting('scorecard_criteria.field.sort', 'الترتيب')" value="0" />
            </form>

            <x-slot:footer>
                <button type="submit" form="scorecard-criterion-new-form"
                        class="btn rounded-xl px-5 text-sm font-semibold"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('scorecard_criteria.create_label', 'إضافة') }}
                </button>
            </x-slot:footer>
        </x-modal>
    @endcan
@endsection

@section('mobile_action')
    @can('scorecard_criteria.create')
        <button type="button" data-modal-open="scorecard-criterion-new"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('scorecard_criteria.add_label', '+ معيار جديد') }}
        </button>
    @endcan
@endsection
