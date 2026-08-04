@php
    /** خليّة واحدة من جدول المستخدمين — والحسّاس مقنَّع بإعداد قابل للإطفاء (24.1) */
    $statuses = \App\Services\Admin\UserDirectory::STATUSES;
@endphp

@switch($column)
    @case('name')
        <a href="{{ route('admin.users.show', $row) }}" class="inline-flex items-center gap-2 hover:underline">
            <x-avatar :user="$row" size="7" />
            <span class="truncate">{{ $row->shortName() }}</span>
        </a>
        @break

    @case('code')
        <span style="color: var(--text-muted)">#{{ $row->code }}</span>
        @break

    @case('email')
        {{ $directory->mask($row->email, 'email') }}
        @break

    @case('phone')
        {{ $directory->mask($row->phone, 'phone') }}
        @break

    @case('country')
        {{ $row->country?->name_ar ?? '—' }}
        @break

    @case('status')
        <x-state-badge :state="$directory->statusState($row->status)" :label="$statuses[$row->status] ?? $row->status" />
        @break

    @case('roles')
        <span class="text-xs" style="color: var(--text-muted)">
            {{ $row->roles->pluck('name_ar')->join(' · ') ?: '—' }}
        </span>
        @break

    @case('xp')
        {{ number_format((int) $row->xp) }}
        @break

    @case('last_seen')
        <span class="cursor-help" title="{{ $row->last_seen_at?->format('Y-m-d H:i') ?? setting('admin.users.partials.cell.madkhlsh_lsh', 'مادخلش لسّه') }}">
            {{ $row->last_seen_at?->diffForHumans() ?? '—' }}
        </span>
        @break

    @case('created_at')
        <span class="cursor-help" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
            {{ $row->created_at?->diffForHumans() }}
        </span>
        @break
@endswitch
