{{--
    بوب-أبات إدارة الاجتماع من اللوحة (24.2-أوّلًا): إدارة الكود/الأسئلة ·
    رفع المحضر والمرفقات والتسجيل · تثبيت بوست · إلغاء بسبب.

    نسخةٌ واحدة لكلّ فعل، ومسارُها يتبدّل بزرّ الصفّ — صفحةٌ فيها عشرون
    اجتماعًا لا تحتمل ثمانين فورمًا مكرّرًا في الـDOM (2.7).
--}}

<x-modal id="questions-modal" :title="setting('admin.meetings_admin.partials.manage_modals.idara_kwd_alhdwr_walasyla', 'إدارة كود الحضور والأسئلة')">
    <form method="post" action="{{ route('admin.meetings.index') }}" data-questions-form class="space-y-3">
        @csrf
        <p class="text-sm" style="color: var(--text-muted)">
            {{ setting('admin.meetings_admin.partials.manage_modals.althqq_kllh_ala_alkhadm', 'التحقّق كلّه على الخادم — والإجابة الصحيحة لا تغادره أبدًا.') }}
        </p>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.kwd_hdwr_otp', 'كود حضور / OTP') }}</span>
            <input type="text" name="attendance_code" maxlength="32" autocomplete="off"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <fieldset class="rounded-xl p-3" style="border: 1px solid var(--border)">
            <legend class="text-xs px-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.swal_akhtyarat', 'سؤال اختيارات') }}</legend>
            <input type="text" name="questions[0][prompt]" placeholder="{{ setting('admin.meetings_admin.partials.manage_modals.ns_alswal', 'نصّ السؤال') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <input type="text" name="questions[0][options]" placeholder="{{ setting('admin.meetings_admin.partials.manage_modals.alkhyarat_mfswla_bfasla', 'الخيارات مفصولة بفاصلة') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <input type="text" name="questions[0][correct_answer]" placeholder="{{ setting('admin.meetings_admin.partials.manage_modals.alajaba_alshyha', 'الإجابة الصحيحة') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </fieldset>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.partials.manage_modals.ahfz', 'احفظ') }}</button>
    </form>
</x-modal>

<x-modal id="minutes-modal" :title="setting('admin.meetings_admin.partials.manage_modals.rfa_almhdr_walmrfqat', 'رفع المحضر والمرفقات')">
    <form method="post" action="{{ route('admin.meetings.index') }}" data-minutes-form enctype="multipart/form-data" class="space-y-3">
        @csrf
        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.almhdr', 'المحضر') }}</span>
            <textarea name="minutes" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.rabt_altsjyl', 'رابط التسجيل (للاجتماع الأونلاين)') }}</span>
            <input type="url" name="recording_url" placeholder="https://…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.mrfqat', 'مرفقات') }}</span>
            <input type="file" name="attachments[]" multiple class="w-full text-sm">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="restricted" value="1">
            <span>{{ setting('admin.meetings_admin.partials.manage_modals.mrfq_mqyd', 'مرفق مقيَّد — يظهر بقفله وزرّ «اطلب وصولًا»') }}</span>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.partials.manage_modals.arfa', 'ارفع') }}</button>
    </form>
</x-modal>

<x-modal id="pin-modal" :title="setting('admin.meetings_admin.partials.manage_modals.tthbyt_bwst', 'تثبيت بوست أعلى النقاش')">
    <form method="post" action="{{ route('admin.meetings.index') }}" data-pin-form class="space-y-3">
        @csrf
        <p class="text-sm" style="color: var(--text-muted)">
            {{ setting('admin.meetings_admin.partials.manage_modals.almthbt_yalw_dayma', 'المثبَّت يعلو النقاش دائمًا — واختيار المثبَّت نفسه يفكّ تثبيته.') }}
        </p>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.albwst', 'البوست') }}</span>
            <select name="post_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></select>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.partials.manage_modals.thbt', 'ثبّت') }}</button>
    </form>
</x-modal>

<x-modal id="cancel-modal" :title="setting('admin.meetings_admin.partials.manage_modals.ilgha_alajtmaa_bsbb', 'إلغاء الاجتماع بسبب')">
    <form method="post" action="{{ route('admin.meetings.index') }}" data-cancel-form class="space-y-3">
        @csrf
        <p class="text-sm" style="color: var(--text-muted)">
            {{ setting('admin.meetings_admin.partials.manage_modals.almlgha_la_tftah_lh_nafdha', 'الملغى لا تُفتَح له نافذة حضور ولا يُخصَم على أحدٍ غيابه — وجمهوره يوصله السبب في إشعار.') }}
        </p>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.manage_modals.sbb_alilgha', 'سبب الإلغاء') }}</span>
            <textarea name="reason" rows="3" required maxlength="500"
                      placeholder="{{ setting('admin.meetings_admin.partials.manage_modals.mthal_taard_alqaa_ma_faalya', 'مثال: تعارض الموعد مع فعاليّة الكيان — هيتحدّد موعد جديد.') }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-state-danger); color: #fff">{{ setting('admin.meetings_admin.partials.manage_modals.alg_alajtmaa', 'ألغِ الاجتماع') }}</button>
    </form>
</x-modal>
