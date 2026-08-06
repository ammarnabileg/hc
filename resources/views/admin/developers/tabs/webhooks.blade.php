@php
    $u = auth()->user();
    $canCreate = $u?->can('webhooks.create');
    $canEdit = $u?->can('webhooks.edit');
    $canManage = $u?->can('webhooks.manage');
    $canDelete = $u?->can('webhooks.delete');
    $eventLabels = $data['eventLabels'];

    $deliveryStateOf = fn (string $status) => match ($status) {
        'success' => 'ok',
        'failed' => 'warn',
        'exhausted' => 'danger',
        default => 'idle', // pending
    };
@endphp

{{--
    ⛔ السرّ الكامل نصًّا صريحًا — مرّة واحدة فقط عبر فلاش الجلسة (12.15-ب).
    إعادة تحميل هذه الصفحة **لا يُظهره ثانيةً** — نفس آليّة `plain_api_key`
    في تاب API حرفيًّا (12.15-ج).
--}}
@if ($data['plainSecret'])
    <div class="card p-4 mb-4" style="border: 1px solid var(--color-state-warning)">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <div class="font-bold" style="color: var(--color-state-warning)">
                    {{ setting('developers.admin.webhook_secret_title', 'احفظ سرّ الويب-هوك الآن') }}
                </div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ setting('developers.admin.webhook_secret_warning', 'هذا هو السرّ الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة. استخدمه للتحقّق من توقيع HMAC في رأس X-Webhook-Signature.') }}
                </p>
                <code id="plain-webhook-secret" class="block mt-2 rounded-xl px-3 py-2 text-sm select-all"
                      style="background: var(--surface-sunken); word-break: break-all">{{ $data['plainSecret'] }}</code>
            </div>
            <button type="button" class="btn shrink-0 rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c"
                    onclick="navigator.clipboard.writeText(document.getElementById('plain-webhook-secret').textContent.trim())">
                {{ setting('developers.admin.plain_key_copy_cta', 'نسخ') }}
            </button>
        </div>
    </div>
@endif

{{-- + ويب-هوك جديد (12.15-ب) --}}
@can('webhooks.create')
    <section class="card p-4 md:p-5 mb-4">
        <h2 class="font-bold mb-3">{{ setting('developers.admin.webhook_form_title', 'ويب-هوك جديد') }}</h2>

        <form method="post" action="{{ route('admin.developers.webhooks.store') }}" class="grid gap-3 md:grid-cols-2">
            @csrf

            <label class="text-sm">{{ setting('developers.admin.field_name', 'اسم وصفيّ') }}
                <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-sm">{{ setting('developers.admin.field_url', 'رابط الاستقبال (URL)') }}
                <input type="url" name="url" required maxlength="2048" value="{{ old('url') }}" placeholder="https://example.com/webhook"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <fieldset class="text-sm md:col-span-2">
                <legend class="mb-1">{{ setting('developers.admin.field_events', 'الأحداث المشترَك فيها') }}</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($data['eventOptions'] as $event)
                        <label class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs"
                               style="background: var(--surface-sunken); border: 1px solid var(--border)">
                            <input type="checkbox" name="events[]" value="{{ $event }}">
                            <span>{{ $eventLabels[$event] ?? $event }}</span>
                            <code class="opacity-60">{{ $event }}</code>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @if ($errors->any())
                <div class="md:col-span-2 text-xs" style="color: var(--color-state-danger)">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="md:col-span-2">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('developers.admin.webhook_save_cta', 'تسجيل الويب-هوك') }}
                </button>
            </div>
        </form>
    </section>
@endcan

{{-- جدول الويب-هوكس (12.15-ب) --}}
<section class="card p-0 overflow-hidden mb-4">
    <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.webhooks_title', 'الويب-هوكس المسجَّلة') }}</h2>

    @if ($data['webhooks']->isEmpty())
        <div class="p-4">
            <x-empty :message="setting('developers.admin.webhooks_empty', 'لا ويب-هوكس بعد — سجّل أوّل ويب-هوك.')" />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_name', 'الاسم') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.webhook_col_url', 'الرابط') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.field_events', 'الأحداث المشترَك فيها') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_status', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.webhook_col_last_run', 'آخر تشغيل ونتيجته') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_actions', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['webhooks'] as $webhook)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">{{ $webhook->name }}</td>
                            <td class="px-4 py-3 text-xs"><code style="word-break: break-all">{{ $webhook->url }}</code></td>
                            <td class="px-4 py-3">
                                @foreach ((array) $webhook->events as $event)
                                    <span class="inline-block text-xs rounded-full px-2 py-0.5 mb-1"
                                          style="background: var(--surface-sunken)">{{ $eventLabels[$event] ?? $event }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$webhook->status === 'active' ? 'ok' : 'idle'"
                                               :label="$webhook->status === 'active' ? setting('developers.admin.status_active', 'فعّال') : setting('developers.admin.webhook_status_paused', 'موقوف')" />
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $webhook->last_triggered_at?->format('Y/m/d H:i') ?? setting('developers.admin.webhook_never_triggered', 'لم يُشغَّل بعد') }}
                                @if ($webhook->last_response_code)
                                    <div style="color: var(--text-muted)">{{ setting('developers.admin.webhook_last_code', 'كود الردّ') }}: {{ $webhook->last_response_code }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2 flex-wrap">
                                    @can('webhooks.manage')
                                        <form method="post" action="{{ route('admin.developers.webhooks.test', $webhook) }}">
                                            @csrf
                                            <button type="submit" class="text-xs underline">{{ setting('developers.admin.webhook_test_cta', 'اختبار') }}</button>
                                        </form>
                                    @endcan
                                    @can('webhooks.edit')
                                        @if ($webhook->status === 'active')
                                            <form method="post" action="{{ route('admin.developers.webhooks.pause', $webhook) }}">
                                                @csrf
                                                <button type="submit" class="text-xs underline">{{ setting('developers.admin.webhook_pause_cta', 'إيقاف') }}</button>
                                            </form>
                                        @else
                                            <form method="post" action="{{ route('admin.developers.webhooks.resume', $webhook) }}">
                                                @csrf
                                                <button type="submit" class="text-xs underline">{{ setting('developers.admin.webhook_resume_cta', 'استئناف') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('webhooks.manage')
                                        <form method="post" action="{{ route('admin.developers.webhooks.rotate-secret', $webhook) }}"
                                              onsubmit="return confirm('{{ setting('developers.admin.webhook_rotate_confirm', 'تدوير السرّ يُبطل القديم فورًا — تأكيد؟') }}')">
                                            @csrf
                                            <button type="submit" class="text-xs underline">{{ setting('developers.admin.rotate_cta', 'تدوير') }}</button>
                                        </form>
                                    @endcan
                                    @can('webhooks.delete')
                                        <form method="post" action="{{ route('admin.developers.webhooks.destroy', $webhook) }}"
                                              onsubmit="return confirm('{{ setting('developers.admin.webhook_delete_confirm', 'حذف الويب-هوك نهائيّ — تأكيد؟') }}')">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('developers.admin.revoke_cta', 'إبطال') }}</button>
                                        </form>
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- سجلّ المحاولات — آخر 100 (12.15-ب) --}}
<section class="card p-0 overflow-hidden">
    <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.deliveries_title', 'سجلّ محاولات الإرسال (آخر 100)') }}</h2>

    @if ($data['deliveries']->isEmpty())
        <div class="p-4">
            <x-empty :message="setting('developers.admin.deliveries_empty', 'لا محاولات إرسال بعد.')" />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_time', 'الوقت') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_name', 'الاسم') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.deliveries_col_event', 'الحدث') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_status', 'كود الردّ') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.deliveries_col_attempts', 'عدد المحاولات') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_status', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.deliveries_col_payload', 'الحمولة والردّ') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_actions', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['deliveries'] as $delivery)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 text-xs">{{ $delivery->created_at?->format('Y/m/d H:i:s') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $delivery->webhook?->name }}</td>
                            <td class="px-4 py-3 text-xs"><code>{{ $delivery->event_key }}</code></td>
                            <td class="px-4 py-3 text-xs">{{ $delivery->response_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ $delivery->attempt_count }}</td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$deliveryStateOf($delivery->status)" :label="match($delivery->status) {
                                    'success' => setting('developers.admin.delivery_status_success', 'نجحت'),
                                    'failed' => setting('developers.admin.delivery_status_failed', 'فشلت — بانتظار إعادة'),
                                    'exhausted' => setting('developers.admin.delivery_status_exhausted', 'استُنفدت'),
                                    default => setting('developers.admin.delivery_status_pending', 'منتظرة'),
                                }" />
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <details>
                                    <summary class="cursor-pointer underline">{{ setting('developers.admin.deliveries_preview_cta', 'معاينة') }}</summary>
                                    <div class="mt-2 rounded-xl p-2" style="background: var(--surface-sunken); max-width: 320px">
                                        <div class="font-semibold">{{ setting('developers.admin.deliveries_payload_label', 'الحمولة') }}</div>
                                        <pre class="whitespace-pre-wrap break-all">{{ json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        @if ($delivery->response_body)
                                            <div class="font-semibold mt-2">{{ setting('developers.admin.deliveries_response_label', 'الردّ الخام') }}</div>
                                            <pre class="whitespace-pre-wrap break-all">{{ $delivery->response_body }}</pre>
                                        @endif
                                    </div>
                                </details>
                            </td>
                            <td class="px-4 py-3">
                                @can('webhooks.manage')
                                    @if (in_array($delivery->status, ['failed', 'exhausted'], true))
                                        <form method="post" action="{{ route('admin.developers.webhook-deliveries.retry', $delivery) }}">
                                            @csrf
                                            <button type="submit" class="text-xs underline">{{ setting('developers.admin.deliveries_retry_cta', 'إعادة إرسال') }}</button>
                                        </form>
                                    @else
                                        <span class="text-xs" style="color: var(--text-muted)">—</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
