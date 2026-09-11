{{--
    فورم **«+ اجتماع»** من لوحة الإدارة (24.2-أوّلًا).

    حقولُه هي حقول فورم لوحة التطوّع نفسها لأنّ المتحكّمين يستدعيان
    `MeetingManager::creationRules()` نفسها — فلا حقلَ يقبله بابٌ ويرفضه الآخر.
    والزيادة الوحيدة **رابط التسجيل**: عمود «التسجيل» في جدول هذه الشاشة.

    وقائمة «النطاق» هنا كيانات المنصّة كلّها لا كيانات المُنشئ — ومن لا يملك
    نطاقًا واسعًا تُقصَر قيمتُه على كيانه في الخدمة نفسها (Server-side)، فلا
    يفتح قائمةً أوسعَ من صلاحيّته بتعديل الـHTML.
--}}
<x-modal id="meeting-new" :title="setting('admin.meetings_admin.partials.create_modal.ajtmaa_jdyd', 'اجتماع جديد')">
    <form method="post" action="{{ route('admin.meetings.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.alanwan', 'العنوان') }}</span>
            <input type="text" name="title" required maxlength="180"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.alwsf', 'الوصف') }}</span>
            <textarea name="description" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.almwad', 'الموعد') }}</span>
                <input type="datetime-local" name="scheduled_at" required
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.aljmhwr', 'الجمهور') }}</span>
                <select name="audience" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="entity">{{ setting('admin.meetings_admin.partials.create_modal.alqsm', 'القسم') }}</option>
                    <option value="sub_entity">{{ setting('admin.meetings_admin.partials.create_modal.alqsm_alfray', 'القسم الفرعيّ') }}</option>
                    <option value="all">{{ setting('admin.meetings_admin.partials.create_modal.kl_almttwayn', 'كلّ المتطوّعين') }}</option>
                </select>
            </label>
        </div>

        <label class="block text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.alntaq', 'النطاق') }}</span>
            <select name="entity_id" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.alrabt_alkharjy', 'الرابط الخارجيّ') }}</span>
                <input type="url" name="external_link" placeholder="https://…"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.rabt_altsjyl', 'رابط التسجيل') }}</span>
                <input type="url" name="recording_url" placeholder="https://…"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        </div>

        {{-- «خيارات متقدّمة» مطويّة، والفورم يعمل كاملًا بدونها (2.15-د) --}}
        <details class="rounded-xl p-3" style="border: 1px solid var(--border)">
            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.khyarat_mtqdma', 'خيارات متقدّمة: تذكير · كود حضور · أسئلة · مرفقات') }}</summary>

            <div class="mt-3 space-y-3">
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.tdhkyr_qbl_almwad', 'تذكير قبل الموعد (ساعات)') }}</span>
                    <input type="number" name="reminder_hours" min="0" max="168"
                           value="{{ setting('meetings.reminder.hours_before', 2) }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.kwd_hdwr_otp', 'كود حضور / OTP') }}</span>
                    <input type="text" name="attendance_code" maxlength="32" autocomplete="off"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <fieldset class="rounded-xl p-3" style="border: 1px solid var(--border)">
                    <legend class="text-xs px-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.swal_akhtyarat', 'سؤال اختيارات') }}</legend>
                    <input type="text" name="questions[0][prompt]" placeholder="{{ setting('admin.meetings_admin.partials.create_modal.ns_alswal', 'نصّ السؤال') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <input type="text" name="questions[0][options]" placeholder="{{ setting('admin.meetings_admin.partials.create_modal.alkhyarat_mfswla_bfasla', 'الخيارات مفصولة بفاصلة') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mb-2"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <input type="text" name="questions[0][correct_answer]" placeholder="{{ setting('admin.meetings_admin.partials.create_modal.alajaba_alshyha', 'الإجابة الصحيحة') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </fieldset>

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.create_modal.mrfqat', 'مرفقات') }}</span>
                    <input type="file" name="attachments[]" multiple class="w-full text-sm">
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="restricted" value="1">
                    <span>{{ setting('admin.meetings_admin.partials.create_modal.mrfq_mqyd', 'مرفق مقيَّد — يظهر بقفله وزرّ «اطلب وصولًا»') }}</span>
                </label>
            </div>
        </details>

        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.meetings_admin.partials.create_modal.anshy_alajtmaa', 'أنشئ الاجتماع') }}</button>
    </form>
</x-modal>
