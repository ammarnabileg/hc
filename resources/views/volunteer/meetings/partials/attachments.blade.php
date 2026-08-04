@php
    /**
     * ⭐ المرفق المقيَّد يظهر بعنوانه وقفلٍ وزرّ [اطلب وصولًا] — ولا يُخفى تمامًا (24.4).
     * والمفتوح يُفتَح مباشرةً.
     */
    $items = collect($items ?? []);
@endphp

@if ($items->isNotEmpty())
    <div class="mt-4">
        <h3 class="text-sm font-bold mb-2">{{ setting('volunteer.meetings_attachments.heading', 'المرفقات') }}</h3>
        <ul class="space-y-2">
            @foreach ($items as $item)
                @php $restricted = (bool) ($item->tags['restricted'] ?? false) && ! $canManage; @endphp
                <li class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="border: 1px solid var(--border)">
                    @if ($restricted)
                        @include('volunteer.meetings.partials.icon', ['name' => 'lock'])
                        <span class="truncate flex-1" style="color: var(--text-muted)">{{ $item->name }}</span>
                        <form method="post" action="{{ route('volunteer.attendance.request', $meeting) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs"
                                    style="border: 1px solid var(--border)">{{ setting('volunteer.meetings_attachments.action', 'اطلب وصولًا') }}</button>
                        </form>
                    @else
                        @include('volunteer.meetings.partials.icon', ['name' => 'attachment'])
                        <a class="truncate flex-1 hover:underline" target="_blank" rel="noopener"
                           href="{{ \Illuminate\Support\Facades\Storage::url($item->path) }}">{{ $item->name }}</a>
                        @include('volunteer.meetings.partials.icon', ['name' => 'download'])
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
