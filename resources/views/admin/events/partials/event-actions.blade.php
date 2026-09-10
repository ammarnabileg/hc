{{--
  أفعال صفّ الفعاليّة — مشتركةٌ بين جدول الديسكتوب وكروت الموبايل (صفر ازدواج)،
  والمحظور **يُخفى لا يُعطَّل** (2.15-أ-7).

  ⭐ [2026-09-10] «معاينة صفحة الفعاليّة قبل النشر» (12.11) — رابطٌ يفتح
  `events.show` بتبويبٍ جديد، محروسٌ بـ`events.manage` لا `events.edit`:
  فالمعاينة نفسها تعمل بفضل `Trainee\EventController::visible()` الذي يسمح
  برؤية المسوَّدة لمن يملك **events.manage** تحديدًا (لا لكلّ من يقدر يعدّل).
--}}
<div class="flex items-center gap-3 flex-wrap text-xs">
    @can('event_registrations.list')
        <a class="underline" href="{{ route('admin.events.registrations', $event) }}">{{ setting('admin.events.index.almsjlwn_walhdwr', 'المسجّلون والحضور') }}</a>
    @endcan
    @can('events.edit')
        <button type="button" class="underline" data-event-edit
                data-id="{{ $event->id }}" data-title="{{ $event->title_ar }}"
                data-title-en="{{ $event->title_en }}" data-mode="{{ $event->mode }}"
                data-starts="{{ $event->starts_at?->format('Y-m-d\TH:i') }}"
                data-capacity="{{ $event->capacity }}" data-code="{{ $event->attendance_code }}"
                data-location="{{ $event->location }}" data-lat="{{ $event->lat }}" data-lng="{{ $event->lng }}"
                data-join="{{ $event->join_link }}"
                data-registration="{{ $event->registration_link }}"
                data-price-coins="{{ (int) $event->price_coins }}"
                data-price-tickets="{{ (int) $event->price_tickets }}"
                data-coupon="{{ $event->coupon_id }}" data-cover="{{ $event->cover_path }}"
                data-status="{{ $event->status }}"
                data-reminders="{{ $event->reminders_enabled ? 1 : 0 }}">{{ setting('admin.events.index.tadyl', 'تعديل') }}</button>
        @if ($event->status !== 'cancelled')
            <form method="post" action="{{ route('admin.events.cancel', $event) }}"
                  onsubmit="return confirm('{{ setting('admin.events.index.tlghy_alfaalya_dy', 'تلغي الفعاليّة دي؟') }}')">
                @csrf
                <button type="submit" class="underline" style="color: var(--color-state-danger)">{{ setting('admin.events.index.ilgha', 'إلغاء') }}</button>
            </form>
        @endif
    @endcan
    @can('events.manage')
        <a class="underline" href="{{ route('events.show', $event->slug) }}" target="_blank" rel="noopener">{{ setting('admin.events.index.maayna', 'معاينة') }}</a>
    @endcan
    @if ($event->registration_link)
        <a class="underline" href="{{ $event->registration_link }}" target="_blank" rel="noopener">{{ setting('admin.events.index.rabt_altsjyl_alkharjy', 'رابط التسجيل الخارجيّ') }}</a>
    @endif
</div>
