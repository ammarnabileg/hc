@php
    /**
     * صفّ إشعار (2.8): أيقونة الفئة · سطر السياق · الوقت · نقطة غير مقروء.
     * والمهلة تُعرَض عدّادًا ملوّنًا برمزه من قاموس الحالة (2.16).
     */
    use App\Services\Notifications\Notifier;

    $deadlineState = Notifier::deadlineState($notification->deadline_at);
    // منتهية المهلة: الصفّ يبقى مقروءًا بشارة «انتهت المهلة» بلا زرّ (13) — لا فعل يُطلَب على وعدٍ فات
    $expired = $deadlineState === 'danger';
    $isUnread = ! $notification->read_at && ! $expired;
@endphp

<div class="flex items-start gap-3 px-3 py-3 motion-standard"
     data-notification="{{ $notification->id }}"
     @if ($isUnread) data-unread="1" @endif
     @style(['background: var(--surface-sunken)' => $isUnread])>

    <span class="text-lg leading-6" aria-hidden="true">{{ Notifier::categoryIcon($notification->category) }}</span>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            @if ($isUnread)
                {{-- نقطة «غير مقروء» — ومعها خلفيّة الصفّ فلا يحمل اللونُ المعنى وحده (2.16-ب) --}}
                <span class="inline-block w-2 h-2 rounded-full shrink-0"
                      style="background: var(--color-brand-500)" aria-label="{{ setting('notifications.row.aria_label_1', 'غير مقروء') }}"></span>
            @endif
            <span class="text-sm font-semibold truncate">{{ $notification->title }}</span>
        </div>

        @if ($notification->body)
            <p class="text-xs mt-0.5 truncate" style="color: var(--text-muted)">{{ $notification->body }}</p>
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="text-xs" style="color: var(--text-muted)"
                  title="{{ $notification->created_at?->format('Y-m-d H:i') }}">{{ $notification->created_at?->diffForHumans() }}</span>

            @if ($expired)
                <x-state-badge state="idle" :label="setting('notifications.row.expired_label', 'انتهت المهلة')" />
            @elseif ($deadlineState)
                <x-state-badge :state="$deadlineState" :label="$notification->deadline_at->diffForHumans()" />
            @endif

            <button type="button" class="text-xs" style="color: var(--color-brand-500)"
                    data-notification-details
                    data-title="{{ $notification->title }}"
                    data-body="{{ $notification->body }}"
                    data-url="{{ $notification->url }}"
                    data-action-label="{{ $actionLabel }}">{{ setting('notifications.row.text_1', 'التفاصيل') }}</button>
        </div>
    </div>

    <div class="flex flex-col items-end gap-1 shrink-0">
        {{-- صفّ «يحتاج إجراء»: الفعل داخل الصفّ نفسه بلا بوب-أب (2.15-ب) — وينتهي بانتهاء المهلة (13) --}}
        @if ($notification->requires_action && $notification->url && ! $expired)
            <a href="{{ $notification->url }}"
               class="btn inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ $actionLabel }}</a>
        @endif

        @if ($isUnread)
            <form method="post" action="{{ route('notifications.read', $notification) }}" data-ajax-form>
                @csrf
                <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs motion-standard"
                        style="background: var(--surface-raised); border: 1px solid var(--border)">{{ setting('notifications.row.text_2', 'تعليم كمقروء') }}</button>
            </form>
        @endif
    </div>
</div>
