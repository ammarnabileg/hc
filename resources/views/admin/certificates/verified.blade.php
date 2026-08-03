@extends('layouts.admin')

@section('title', 'تحقّق من الأكواد')

@section('content')
    {{-- نتيجة التحقّق قبل الإصدار (12.5-ج): صالح / غير موجود / صدرت له قبل كده --}}
    <x-page-header
        :title="'تحقّق من الأكواد — '.$type->name_ar"
        subtitle="راجع النتيجة، وبعدين عاين وأصدِر."
        :breadcrumbs="[
            ['label' => 'الشهادات', 'url' => route('admin.certificates.index')],
            ['label' => 'إصدار', 'url' => route('admin.certificates.index', ['tab' => 'issue'])],
            ['label' => 'التحقّق'],
        ]" />

    <div class="space-y-3">
        @foreach ($rows as $row)
            <div class="card p-3 flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold">{{ $row['code'] }}</div>
                    @if ($row['user'])
                        {{-- اسمه كلينك للبروفايل — يقلّل الخطأ (12.5-ج) --}}
                        <a href="{{ $row['user']->profileUrl() }}" class="text-sm underline"
                           style="color: var(--color-brand-400)">{{ $row['user']->name }}</a>
                    @endif
                </div>
                <x-state-badge :state="$row['state']" :label="$row['message']" />
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('admin.certificates.preview') }}" class="mt-4 flex gap-2 flex-wrap">
        @csrf
        <input type="hidden" name="certificate_type_id" value="{{ $type->id }}">
        <input type="hidden" name="language" value="{{ $language }}">
        <input type="hidden" name="codes" value="{{ $raw }}">
        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">معاينة قبل الإصدار</button>
        <a href="{{ route('admin.certificates.index', ['tab' => 'issue']) }}"
           class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">رجوع</a>
    </form>
@endsection
