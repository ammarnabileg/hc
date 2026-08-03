{{--
  **بلوك الإعدادات الخمسة** (24.3):
  Toggle «إظهار شارة تجريبيّة للمزايا الجديدة» · سلوك الميزة الموقوفة (إخفاء
  كامل ⇄ إظهار رسالة) · نصّ الرسالة الافتراضيّ (ع/إ) · Toggle تنبيه الأدمن عند
  إيقاف ميزة > N ساعة · ↺ Reset.

  والزرّ `حفظ` في الهيدر هو submit لهذا الفورم (`form="features-settings-form"`)
  — فالزرّ المذكور في الدستور **يحفظ فعلًا** ولا يكون زينة.
--}}
<form id="features-settings-form" method="post" action="{{ route('admin.features.settings') }}" class="card p-4 space-y-4">
    @csrf

    <h2 class="text-sm font-bold">{{ setting('features.ui.settings_title', 'إعدادات المفاتيح') }}</h2>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="features__show_beta_badge" value="1"
               @checked(setting('features.show_beta_badge', true)) @disabled(! $mayEdit)>
        <span>{{ $features['labels']['features.show_beta_badge'] ?? '' }}</span>
    </label>

    <label class="block text-sm">
        <span class="block mb-1">{{ setting('features.ui.popup.behavior', 'سلوك الميزة الموقوفة') }}</span>
        <select name="features__disabled_behavior" @disabled(! $mayEdit)
                class="w-full rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="hide" @selected(setting('features.disabled_behavior', 'hide') === 'hide')>{{ setting('features.ui.popup.behavior_hide', 'إخفاء كامل') }}</option>
            <option value="message" @selected(setting('features.disabled_behavior', 'hide') === 'message')>{{ setting('features.ui.popup.behavior_message', 'إظهار رسالة') }}</option>
        </select>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="block mb-1">{{ setting('features.ui.popup.message_ar', 'اللي المستخدم هيشوفه بدلها (عربيّ)') }}</span>
            <textarea name="features__disabled_message" rows="2" @disabled(! $mayEdit)
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('features.disabled_message', 'الميزة دي متوقّفة مؤقّتًا — هترجع قريب.') }}</textarea>
        </label>

        <label class="block text-sm">
            <span class="block mb-1">{{ setting('features.ui.popup.message_en', 'اللي المستخدم هيشوفه بدلها (إنجليزيّ)') }}</span>
            <textarea name="features__disabled_message_en" rows="2" dir="ltr" @disabled(! $mayEdit)
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('features.disabled_message_en', 'This feature is paused for a moment — it will be back soon.') }}</textarea>
        </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="features__alert_long_outage" value="1"
                   @checked(setting('features.alert_long_outage', true)) @disabled(! $mayEdit)>
            <span>{{ $features['labels']['features.alert_long_outage'] ?? '' }}</span>
        </label>

        <label class="block text-sm">
            <span class="block mb-1">{{ $features['labels']['features.alert_after_hours'] ?? '' }}</span>
            <input type="number" name="features__alert_after_hours" min="1" @disabled(! $mayEdit)
                   value="{{ (int) setting('features.alert_after_hours', 24) }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
    </div>

    @if ($mayEdit)
        {{-- ↺ Reset للبلوك: يرجّع الخمسة لقيمها الافتراضيّة (24.3) --}}
        <button name="action" value="reset" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken)">{{ setting('features.ui.action.reset', '↺') }} {{ setting('features.ui.settings_title', 'إعدادات المفاتيح') }}</button>
    @endif

    <p class="text-xs" style="color: var(--text-muted)">{{ setting('features.ui.pinned_rule', 'لا صيانة جزئيّة لميزة بعينها — أُلغيت؛ الإطفاء يتمّ من هنا فقط.') }}</p>
</form>
