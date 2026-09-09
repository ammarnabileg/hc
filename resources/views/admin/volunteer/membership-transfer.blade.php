@extends('layouts.admin')

@section('title', setting('admin.volunteer.membership_transfer.title', 'نقل بين الأقسام'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.membership_transfer.title', 'نقل بين الأقسام')"
        :subtitle="$membership->user?->name.' — '.($membership->position?->name_ar).' · '.($membership->entity?->name_ar)"
        :breadcrumbs="[['label' => setting('admin.volunteer.org.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.org.alhykl_walsaa', 'الهيكل والسعة'), 'url' => route('admin.volunteer.org')], ['label' => setting('admin.volunteer.membership_transfer.title', 'نقل بين الأقسام')]]" />

    <div class="card p-4 md:p-5 max-w-xl">
        <p class="text-sm mb-4" style="color: var(--text-muted)">
            {{ setting('admin.volunteer.membership_transfer.rule_note', 'نقلٌ لقسمٍ رئيسيّ آخر يبدأ ببوزشن «كوردنيتور» أيًّا كانت الدرجة الحاليّة. النقل بين الأقسام الفرعيّة داخل نفس القسم الرئيسي يحتفظ بالدرجة.') }}
        </p>

        @if ($destinations->isEmpty())
            <p class="text-sm py-6 text-center" style="color: var(--text-muted)">{{ setting('admin.volunteer.membership_transfer.empty', 'مفيش كيان آخر على نفس المسار يُنقَل إليه.') }}</p>
        @else
            <form method="post" action="{{ route('admin.volunteer.org.memberships.transfer', $membership) }}" class="space-y-3">
                @csrf

                <label class="block text-sm">
                    <span class="block mb-1 font-semibold">{{ setting('admin.volunteer.membership_transfer.field_entity', 'القسم الجديد') }}</span>
                    <select name="entity_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($destinations as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="block mb-1 font-semibold">{{ setting('admin.volunteer.membership_transfer.field_reason', 'سبب النقل (اختياريّ)') }}</span>
                    <textarea name="reason" rows="3" maxlength="500" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('admin.volunteer.membership_transfer.submit', 'انقل العضويّة') }}</button>
            </form>
        @endif
    </div>
@endsection
