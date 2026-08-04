@extends('layouts.admin')

@section('title', setting('admin.guidance.complaint.shkwa', 'شكوى #').$complaint->number)

@section('content')
    {{-- بانل تفاصيل الشكوى: النصّ + المراسلات الداخليّة + الردّ والإغلاق (24.3) --}}
    <x-page-header
        :title="$complaint->title"
        :subtitle="'#'.$complaint->number.' · '.($complaint->category ?: setting('admin.guidance.complaint.bla_sbb_mhdd', 'بلا سبب محدَّد'))"
        :breadcrumbs="[
            ['label' => setting('admin.guidance.complaint.alshkawa', 'الشكاوى'), 'url' => route('admin.guidance.complaints')],
            ['label' => '#'.$complaint->number],
        ]" />

    <div class="grid lg:grid-cols-2 gap-4 items-start">
        <section class="card p-4 space-y-3">
            <div class="flex items-center gap-3">
                <x-avatar :user="$complaint->user" size="10" />
                <div class="min-w-0">
                    <div class="font-semibold">{{ $complaint->user?->name }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">#{{ $complaint->user?->code }}</div>
                </div>
                <div class="ms-auto">
                    <x-state-badge :state="\App\Services\Account\ComplaintService::stateOf($complaint->status)"
                                   :label="$statuses[$complaint->status] ?? $complaint->status" />
                </div>
            </div>

            <p class="text-sm whitespace-pre-line">{{ $complaint->body }}</p>

            @if ($complaint->wants_contact)
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('admin.guidance.complaint.yrghb_fy_altwasl_alqnaa', 'يرغب في التواصل — القناة:') }} {{ $complaint->contact_channel ?: setting('admin.guidance.complaint.ghyr_mhdda', 'غير محدَّدة') }}
                </p>
            @endif

            @if ($complaint->close_reason)
                <p class="text-xs">{{ setting('admin.guidance.complaint.sbb_alighlaq', 'سبب الإغلاق:') }} {{ $complaint->close_reason }}</p>
            @endif

            @can('complaints.assign')
                <form method="post" action="{{ route('admin.guidance.complaints.assign', $complaint) }}" class="flex gap-2">
                    @csrf
                    <select name="assigned_to" class="flex-1 rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.guidance.complaint.bla_isnad', '— بلا إسناد —') }}</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((int) $complaint->assigned_to === $assignee->id)>
                                {{ $assignee->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.guidance.complaint.asnd', 'أسنِد') }}</button>
                </form>
            @endcan
        </section>

        <section class="card p-4 space-y-3">
            <h2 class="font-bold">{{ setting('admin.guidance.complaint.almraslat', 'المراسلات') }}</h2>

            @forelse ($messages as $message)
                <div class="card p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ $message->user?->name }}</span>
                        @if ($message->is_internal)
                            <x-state-badge state="idle" :label="setting('admin.guidance.complaint.dakhly', 'داخليّ')" />
                        @else
                            <x-state-badge state="ok" :label="setting('admin.guidance.complaint.wsl_llmstkhdm', 'وصل للمستخدم')" />
                        @endif
                    </div>
                    <p class="text-sm mt-1 whitespace-pre-line">{{ $message->body }}</p>
                    <div class="text-xs mt-1" style="color: var(--text-muted)"
                         title="{{ $message->created_at }}">{{ $message->created_at?->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.guidance.complaint.mfysh_rdwd_lsh', 'مفيش ردود لسّه.') }}</p>
            @endforelse

            @can('complaints.edit')
                <form method="post" action="{{ route('admin.guidance.complaints.reply', $complaint) }}" class="space-y-2">
                    @csrf
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.guidance.complaint.alrd', 'الردّ') }}</span>
                        <textarea name="body" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_internal" value="1"> {{ setting('admin.guidance.complaint.mlahza_dakhlya_ma_twslsh_llmstkhdm', 'ملاحظة داخليّة (ما توصلش للمستخدم)') }}
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.guidance.complaint.alhala_bad_alrd', 'الحالة بعد الردّ') }}</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($complaint->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.complaint.abat_alrd', 'ابعت الردّ') }}</button>
                </form>

                @if ($complaint->status !== 'closed')
                    <form method="post" action="{{ route('admin.guidance.complaints.close', $complaint) }}" class="flex gap-2">
                        @csrf
                        <select name="reason" required class="flex-1 rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($closeReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.guidance.complaint.ighlaq_bsbb', 'إغلاق بسبب') }}</button>
                    </form>
                @endif
            @endcan
        </section>
    </div>

    @include('admin.courses.partials.toast')
@endsection
