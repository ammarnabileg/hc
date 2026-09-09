{{-- معاينة الجرس (24.3 سطر 5067) — نفس بطاقة صفّ الجرس الحقيقيّة --}}
<div class="flex gap-2 rounded-xl px-2 py-2" style="background: var(--surface-sunken)">
    <span class="text-lg leading-6" aria-hidden="true">{{ \App\Services\Notifications\Notifier::categoryIcon($category) }}</span>
    <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold">{{ $title }}</span>
        <span class="block text-xs mt-0.5" style="color: var(--text-muted)">{{ $body }}</span>
    </span>
</div>
<p class="text-xs mt-2" style="color: var(--text-muted)">
    @if ($hasTemplate)
        {!! strtr(setting('admin.guidance.notifications.mayna_alqalb_almfaal_lnwa_v1', 'ده قالب :v1 المفعّل — بيتبدّل بالوسوم زيّ [اسم] وقت الإرسال الفعليّ.'), [':v1' => e($label)]) !!}
    @else
        {{ setting('admin.guidance.notifications.mafysh_qalb_mfaal_lhdha_alnwa', 'مفيش قالبٌ مفعّل لهذا النوع بعد.') }}
    @endif
</p>
