@props(['message' => '', 'state' => 'ok'])

<div class="card p-3 mb-4 flex items-center gap-2 animate-fadeup" role="status">
    <x-state-badge :state="$state" label="" />
    <span class="text-sm">{{ $message }}</span>
</div>
