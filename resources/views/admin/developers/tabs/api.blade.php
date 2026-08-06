@php
    $u = auth()->user();
    $canCreate = $u?->can('integrations.create');
    $canEdit = $u?->can('integrations.edit');
    $canDelete = $u?->can('integrations.delete');
    $scopeLabels = $data['scopeLabels'];
@endphp

{{--
    ⛔ المفتاح الكامل نصًّا صريحًا — مرّة واحدة فقط عبر فلاش الجلسة (12.15-أ).
    إعادة تحميل هذه الصفحة **لا يُظهره ثانيةً** — آليّة الفلاش القياسيّة تمحوه
    تلقائيًّا بعد هذا الطلب، ولا يُخزَّن نصًّا صريحًا في القاعدة أبدًا (12.15-ج).
--}}
@if ($data['plainKey'])
    <div class="card p-4 mb-4" style="border: 1px solid var(--color-state-warning)">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <div class="font-bold" style="color: var(--color-state-warning)">
                    {{ setting('developers.admin.plain_key_title', 'احفظ المفتاح الآن') }}
                </div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    {{ setting('developers.admin.plain_key_warning', 'هذا هو المفتاح الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة.') }}
                </p>
                <code id="plain-api-key" class="block mt-2 rounded-xl px-3 py-2 text-sm select-all"
                      style="background: var(--surface-sunken); word-break: break-all">{{ $data['plainKey'] }}</code>
            </div>
            <button type="button" class="btn shrink-0 rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                    style="background: var(--color-brand-500); color:#04201c"
                    onclick="navigator.clipboard.writeText(document.getElementById('plain-api-key').textContent.trim())">
                {{ setting('developers.admin.plain_key_copy_cta', 'نسخ') }}
            </button>
        </div>
    </div>
@endif

{{-- + مفتاح جديد (12.15-أ) --}}
@can('integrations.create')
    <section class="card p-4 md:p-5 mb-4">
        <h2 class="font-bold mb-3">{{ setting('developers.admin.form_title', 'مفتاح API جديد') }}</h2>

        <form method="post" action="{{ route('admin.developers.api-keys.store') }}" class="grid gap-3 md:grid-cols-2">
            @csrf

            <label class="text-sm md:col-span-2">{{ setting('developers.admin.field_name', 'اسم وصفيّ') }}
                <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <fieldset class="text-sm md:col-span-2">
                <legend class="mb-1">{{ setting('developers.admin.field_scopes', 'الصلاحيّات (Scopes)') }}</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($data['scopeOptions'] as $scope)
                        <label class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs"
                               style="background: var(--surface-sunken); border: 1px solid var(--border)">
                            <input type="checkbox" name="scopes[]" value="{{ $scope }}">
                            <span>{{ $scopeLabels[$scope] ?? $scope }}</span>
                            <code class="opacity-60">{{ $scope }}</code>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="text-sm">{{ setting('developers.admin.field_expires', 'تاريخ الانتهاء (اختياريّ)') }}
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-sm">{{ setting('developers.admin.field_rate_limit', 'حدّ الطلبات بالدقيقة (اختياريّ — فارغ يرث الحدّ العامّ)') }}
                <input type="number" min="1" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute') }}"
                       placeholder="{{ $data['defaultRateLimit'] }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

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
                    {{ setting('developers.admin.save_cta', 'إنشاء المفتاح') }}
                </button>
            </div>
        </form>
    </section>
@endcan

{{-- جدول المفاتيح (12.15-أ) --}}
<section class="card p-0 overflow-hidden mb-4">
    <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.keys_title', 'مفاتيح الـAPI') }}</h2>

    @if ($data['keys']->isEmpty())
        <div class="p-4">
            <x-empty :message="setting('developers.admin.empty_keys', 'لا مفاتيح بعد — أنشئ أوّل مفتاح API.')" />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_name', 'الاسم') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_prefix', 'البادئة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_scopes', 'الصلاحيّات (Scopes)') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_last_used', 'آخر استخدام') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_expires', 'تاريخ الانتهاء') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_rate_limit', 'حدّ المعدّل/دقيقة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_status', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_actions', 'إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['keys'] as $key)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">{{ $key->name }}</td>
                            <td class="px-4 py-3"><code>{{ $key->key_prefix }}</code></td>
                            <td class="px-4 py-3">
                                @foreach ((array) $key->scopes as $scope)
                                    <span class="inline-block text-xs rounded-full px-2 py-0.5 mb-1"
                                          style="background: var(--surface-sunken)">{{ $scopeLabels[$scope] ?? $scope }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $key->last_used_at?->format('Y/m/d H:i') ?? setting('developers.admin.never_used', 'لم يُستخدَم بعد') }}
                                @if ($key->last_used_ip)
                                    <div style="color: var(--text-muted)">{{ $key->last_used_ip }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $key->expires_at?->format('Y/m/d') ?? setting('developers.admin.no_expiry', 'بلا انتهاء') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $key->rate_limit_per_minute ?? setting('developers.admin.inherits_default', 'الحدّ العامّ') }}</td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="$key->status === 'active' ? 'ok' : 'idle'"
                                               :label="$key->status === 'active' ? setting('developers.admin.status_active', 'فعّال') : setting('developers.admin.status_revoked', 'مُبطَل')" />
                            </td>
                            <td class="px-4 py-3">
                                @if ($key->status === 'active')
                                    <span class="flex items-center gap-2 flex-wrap">
                                        @can('integrations.edit')
                                            <form method="post" action="{{ route('admin.developers.api-keys.rotate', $key) }}"
                                                  onsubmit="return confirm('{{ setting('developers.admin.rotate_confirm', 'تدوير المفتاح يُبطل القديم فورًا ويصدر مفتاحًا جديدًا بنفس الاسم والصلاحيّات — تأكيد؟') }}')">
                                                @csrf
                                                <button type="submit" class="text-xs underline">{{ setting('developers.admin.rotate_cta', 'تدوير') }}</button>
                                            </form>
                                        @endcan
                                        @can('integrations.delete')
                                            <form method="post" action="{{ route('admin.developers.api-keys.revoke', $key) }}"
                                                  onsubmit="return confirm('{{ setting('developers.admin.revoke_confirm', 'إبطال المفتاح فوريّ ولا رجعة فيه — تأكيد؟') }}')">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('developers.admin.revoke_cta', 'إبطال') }}</button>
                                            </form>
                                        @endcan
                                    </span>
                                @else
                                    <span class="text-xs" style="color: var(--text-muted)">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- سجلّ الاستخدام — آخر 100 طلب (12.15-أ) --}}
<section class="card p-0 overflow-hidden mb-4">
    <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.usage_title', 'سجلّ الاستخدام (آخر 100 طلب)') }}</h2>

    @if ($data['usageLogs']->isEmpty())
        <div class="p-4">
            <x-empty :message="setting('developers.admin.usage_empty', 'لا طلبات مسجَّلة بعد على هذا المفتاح.')" />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_time', 'الوقت') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.col_name', 'الاسم') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_method', 'الطريقة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_path', 'المسار') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_status', 'كود الردّ') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_ip', 'IP') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.usage_col_duration', 'زمن الاستجابة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['usageLogs'] as $log)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 text-xs">{{ $log->created_at?->format('Y/m/d H:i:s') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $log->apiKey?->name }}</td>
                            <td class="px-4 py-3 text-xs">{{ $log->method }}</td>
                            <td class="px-4 py-3 text-xs"><code>{{ $log->path }}</code></td>
                            <td class="px-4 py-3 text-xs">{{ $log->status_code }}</td>
                            <td class="px-4 py-3 text-xs">{{ $log->ip }}</td>
                            <td class="px-4 py-3 text-xs">{{ $log->duration_ms }}ms</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- كتالوج نقاط النهاية — توثيق مرجعيّ Read-only من كتالوج ثابت في الكود (12.15-أ) --}}
<section class="card p-0 overflow-hidden">
    <h2 class="font-bold p-4 pb-0">{{ setting('developers.admin.catalog_title', 'كتالوج نقاط النهاية (Endpoints)') }}</h2>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background: var(--surface-sunken)">
                    <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.catalog_col_method', 'الطريقة') }}</th>
                    <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.catalog_col_path', 'المسار') }}</th>
                    <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.catalog_col_scope', 'الـScope المطلوب') }}</th>
                    <th class="text-start px-4 py-3 font-semibold">{{ setting('developers.admin.catalog_col_description', 'الوصف') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['catalog'] as $endpoint)
                    <tr style="border-top: 1px solid var(--border)">
                        <td class="px-4 py-3"><code>{{ $endpoint['method'] }}</code></td>
                        <td class="px-4 py-3"><code>{{ $endpoint['path'] }}</code></td>
                        <td class="px-4 py-3 text-xs">
                            {{ $endpoint['scope'] ? ($scopeLabels[$endpoint['scope']] ?? $endpoint['scope']) : setting('developers.admin.catalog_no_scope', 'بلا Scope — يكفي مفتاحٌ صالح') }}
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $endpoint['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
