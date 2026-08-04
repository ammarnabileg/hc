{{--
  أزرار هيدر بروفايل المتطوّع (13.4-م · 10.0-ب):
  «شكر (Kudos)» · «واتساب» · وللمخوَّل «سجلّ المشرف» + دروب-داون «إجراءات».
  و«إجراءات» هو **المدخل المنصوص عليه لمعاملة السلوك** (13.4-ن-هـ).
--}}
<div data-volunteer-header-actions class="mt-4 pt-4 flex flex-wrap items-center gap-2"
     style="border-top: 1px solid var(--border)">

    <x-state-badge :state="$header['rep_state']" :label="$header['rep_label']" />

    @if ($header['is_club'])
        <span class="animate-shimmer rounded-full px-3 py-1 text-xs font-bold"
              style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">★ {{ setting('volunteer.profile_header_actions.text', 'نادي التميّز') }}</span>
    @endif

    {{-- سطر واحد يوضّح مستوى المشاهدة في طبقة التطوّع (13.4-م) --}}
    <span class="text-xs" style="color: var(--text-muted)">{{ $header['level_label'] }}</span>

    <span class="flex-1"></span>

    @if ($header['can_kudos'])
        <button type="button" data-modal-open="kudos-modal"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_header_actions.action', 'شكر') }} <x-icon name="contribution" size="16" /></button>
    @endif

    @if ($header['whatsapp'])
        <a href="{{ $header['whatsapp'] }}" target="_blank" rel="noopener"
           class="btn rounded-xl px-4 py-2 text-sm motion-standard"
           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.common.whatsapp', 'واتساب') }}</a>
    @elseif ($header['whatsapp_locked'])
        {{-- الحقل المقفول لا يُعرَض فراغًا — يظهر مكانه طريق الوصول (13.4-م-2) --}}
        <a href="?tab=volunteer_contact"
           class="btn rounded-xl px-4 py-2 text-sm motion-standard"
           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text-muted)">{{ setting('volunteer.profile_header_actions.link', 'اطلب إظهار الرقم') }}</a>
    @endif

    @if ($header['can_see_audit'])
        <a href="{{ route('volunteer.profile.audit', ['code' => $owner->code]) }}"
           class="btn rounded-xl px-4 py-2 text-sm motion-standard"
           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_header_actions.link_2', 'سجلّ المشرف') }}</a>
    @endif

    @if ($header['actions'] !== [])
        <div class="relative">
            <button type="button" data-actions-toggle
                    class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                    style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.profile_header_actions.action_2', 'إجراءات ⌄') }}</button>

            <div data-actions-menu class="hidden absolute end-0 mt-2 z-30 min-w-56 rounded-xl overflow-hidden"
                 style="background: var(--surface-raised); border: 1px solid var(--border)">
                @foreach ($header['actions'] as $action)
                    <a href="{{ $action['url'] }}" class="block px-4 py-2 text-sm motion-standard"
                       style="color: var(--text)">{{ $action['label'] }}</a>
                @endforeach
            </div>
        </div>
    @endif
</div>

@if ($header['can_kudos'])
    <x-modal id="kudos-modal" :title="setting('volunteer.profile_header_actions.tooltip', 'اشكر زميلك')">
        <form method="POST" action="{{ route('volunteer.kudos.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="receiver_id" value="{{ $owner->id }}">
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.profile_header_actions.text_2', 'السبب المكتوب هو اللي بيفرق — القصّة أقوى من العدّاد.') }}</p>
            <textarea name="reason" rows="3" required minlength="3" maxlength="1000"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                      placeholder="{{ setting('volunteer.profile_header_actions.placeholder', 'اكتب سبب الشكر…') }}"></textarea>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.profile_header_actions.action_3', 'ابعت الشكر') }}</button>
        </form>
    </x-modal>
@endif
