{{--
  البوب-أبات الأربعة — **نسخةٌ واحدة لكلٍّ** تُملأ من الصفّ المضغوط (2.15:
  التفاصيل في بوب-أب لا صفحة، ولا 34 نسخة مكرّرة في الـDOM).
--}}

{{-- [إيقاف ميزة] تأكيد + نصّ ما يراه المستخدم بدلها (ع/إ) + Toggle إشعار
     المتأثّرين + سبب الإيقاف (يدخل الـAudit) — 24.3 حرفيًّا --}}
<x-modal id="feature-disable-modal" :title="setting('features.ui.popup.disable_title', 'إيقاف ميزة')">
    <div class="space-y-3 text-sm">
        <p class="font-semibold" data-disable-feature-name></p>
        <p style="color: var(--color-state-warn)">{{ setting('features.ui.popup.confirm', 'الميزة دي هتتقفل على كلّ اللي في نطاقها فورًا. متأكّد؟') }}</p>

        <label class="block">
            <span class="block mb-1">{{ setting('features.ui.popup.reason', 'سبب الإيقاف (بيدخل الـAudit)') }}</span>
            <textarea rows="2" data-disable-reason required
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.popup.message_ar', 'اللي المستخدم هيشوفه بدلها (عربيّ)') }}</span>
                <textarea rows="2" data-disable-message-ar
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>

            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.popup.message_en', 'اللي المستخدم هيشوفه بدلها (إنجليزيّ)') }}</span>
                <textarea rows="2" dir="ltr" data-disable-message-en
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.popup.behavior', 'سلوك الميزة الموقوفة') }}</span>
                <select data-disable-behavior class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('features.ui.popup.behavior_inherit', 'زيّ الإعداد العامّ') }}</option>
                    <option value="hide">{{ setting('features.ui.popup.behavior_hide', 'إخفاء كامل') }}</option>
                    <option value="message">{{ setting('features.ui.popup.behavior_message', 'إظهار رسالة') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.popup.visibility', 'مين يشوفها وهي موقوفة') }}</span>
                <select data-disable-visibility class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="none">{{ setting('features.ui.visibility.none', 'لا أحد') }}</option>
                    <option value="admins">{{ setting('features.ui.visibility.admins', 'الأدمن فقط') }}</option>
                    <option value="roles">{{ setting('features.ui.visibility.roles', 'أدوار محدّدة') }}</option>
                </select>
            </label>
        </div>

        <label class="block">
            <span class="block mb-1">{{ setting('features.ui.popup.visible_roles', 'الأدوار اللي هتفضل شايفاها') }}</span>
            <select multiple size="4" data-disable-roles class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($features['roles'] as $role)
                    <option value="{{ $role->id }}">{{ $role->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2">
            <input type="checkbox" data-disable-notify>
            <span>{{ setting('features.ui.popup.notify', 'ابعت إشعار للمتأثّرين') }}</span>
        </label>

        <p class="text-xs" data-disable-status></p>
    </div>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken)">{{ setting('features.ui.popup.cancel', 'إلغاء') }}</button>
            <button type="button" data-disable-submit class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: color-mix(in srgb, var(--color-state-danger) 25%, transparent); color: var(--color-state-danger)">
                {{ setting('features.ui.popup.submit', 'أوقف الميزة') }}
            </button>
        </div>
    </x-slot:footer>
</x-modal>

{{-- [النطاق] عامّ ⇄ Override لدور/شريحة --}}
<x-modal id="feature-scope-modal" :title="setting('features.ui.scope.title', 'نطاق الميزة')">
    <div class="space-y-3 text-sm">
        <p class="font-semibold" data-scope-feature-name></p>
        <div class="text-xs" data-scope-current>{{ setting('features.ui.scope.empty', 'مافيش Override — الميزة عامّة.') }}</div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.scope.type', 'النوع') }}</span>
                <select data-scope-type class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="role">{{ setting('features.ui.scope.role', 'Override لدور') }}</option>
                    <option value="segment">{{ setting('features.ui.scope.segment', 'Override لشريحة') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="block mb-1">{{ setting('features.ui.scope.target', 'الدور أو الشريحة') }}</span>
                <select data-scope-target class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($features['roles'] as $role)
                        <option value="{{ $role->id }}" data-kind="role">{{ $role->name_ar }}</option>
                    @endforeach
                    @foreach ($features['segments'] as $segment)
                        <option value="{{ $segment->id }}" data-kind="segment" hidden>{{ $segment->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="block">
            <span class="block mb-1">{{ setting('features.ui.scope.value', 'القرار داخل النطاق') }}</span>
            <select data-scope-value class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="1">{{ setting('features.ui.scope.value_on', 'شغّالة') }}</option>
                <option value="0">{{ setting('features.ui.scope.value_off', 'موقوفة') }}</option>
                <option value="">{{ setting('features.ui.scope.clear', 'ارفع الـOverride (رجّعها عامّة)') }}</option>
            </select>
        </label>

        <p class="text-xs" data-scope-status></p>
    </div>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken)">{{ setting('features.ui.popup.cancel', 'إلغاء') }}</button>
            <button type="button" data-scope-submit class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('features.ui.save', 'حفظ') }}</button>
        </div>
    </x-slot:footer>
</x-modal>

{{-- [تفاصيل] ومنها **المسارات التي يحكمها المفتاح فعلًا** — فالمالك يرى الأثر
     لا الاسم وحده، وهو الفرق بين مفتاحٍ حقيقيّ وزينةٍ في شاشة --}}
<x-modal id="feature-details-modal" :title="setting('features.ui.popup.details_title', 'تفاصيل الميزة')">
    <div class="space-y-2 text-sm">
        <p class="font-semibold" data-details-name></p>
        <p class="font-mono text-xs" data-details-key></p>
        <div class="text-xs">{{ setting('features.ui.popup.routes', 'المسارات اللي بيحكمها المفتاح') }}:</div>
        <ul class="font-mono text-xs space-y-1" data-details-routes></ul>
    </div>
</x-modal>

{{-- [Audit] مَن · متى · **لماذا** --}}
<x-modal id="feature-audit-modal" :title="setting('features.ui.popup.audit_title', 'سجلّ الميزة')">
    <div class="text-sm" data-audit-body>{{ setting('features.ui.state.loading', 'بنحمّل…') }}</div>
</x-modal>
