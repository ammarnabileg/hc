<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 🧩 نصوص الإعدادات بلا شرطةٍ طويلة: المالك رفض «—» في كلّ ما يقرأه المستخدم،
 * والصوت المطلوب مصريّ دارج. وقد مرّ القوالبُ والخدماتُ والبذور في تغييرٍ سابق،
 * وبقيت صفوفٌ كُتبت من **مايجريشنز قديمة** فلا تُعدَّل في مكانها (قاعدة البناء §1):
 * القائم لا يُمَسّ، والنقص يُصحَّح بمايجريشن جديد. وهذا هو.
 *
 * والتصحيح **مشروط**: لا يُكتَب إلّا فوق النصّ القديم بعينه، فلو كان المالك قد
 * حرّر النصّ من شاشة الإعدادات (2.13-ب حقٌّ له) بقي تحريرُه كما هو.
 */
return new class extends Migration
{
    /** النصّ القديم ⟵ الجديد، لكلّ مفتاح. */
    private function replacements(): array
    {
        return [
            'account.delete.data_notes' => '["بروفايلك وبياناتك الشخصيّة هتتشال من كلّ الشاشات فورًا.", "شهاداتك الصادرة هتفضل قابلة للتحقّق برقمها، دي حقّ الجهة اللي استلمتها.", "معاملات المحفظة والمشتريات بتفضل في السجلّ الماليّ بالقانون، بلا اسمك.", "مساهماتك في التطوّع بتفضل باسم «عضو سابق» علشان شغل الفريق ما يتكسرش.", "رصيدك الحاليّ بيسقط ومش هيرجع لو رجعت تاني."]',
            'announcements.email.footer' => 'وصلتك الرسالة دي لأنّك مفعّل قناة البريد، وتقدر توقّفها من إعدادات حسابك.',
            'auth.logout.all_devices_done' => 'قفلنا كلّ الجلسات ✓، سجّل دخولك تاني.',
            'auth.otp.error_missing' => 'مابعتناش رمز لسّه، اضغط «إرسال» الأوّل.',
            'auth.otp.error_step_lost' => 'الجلسة رجعت لأوّل خطوة، اكتب بريدك وأكّده تاني وهنكمّل من هناك.',
            'auth.otp.inline_hint' => 'هنبعت رمز من {length} أرقام على بريدك، اكتبه هنا عشان نتأكّد إنّه بريدك فعلًا.',
            'auth.otp.replay_failed_text' => 'بريدك اتأكّد ✓ بس فيه بيانات اتغيّرت وإحنا بنكمّل، صحّح المكتوب بالأحمر واضغط استكمال، ومش هنطلب منك الرمز تاني.',
            'auth.otp.verified_text' => 'بريدك اتأكّد، كمّل باقي البيانات.',
            'auth.password_reset.done_text' => 'كلمة السرّ اتغيّرت ✓، ادخل بيها دلوقتي. وقفلنا كلّ الجلسات القديمة للأمان.',
            'auth.password_reset.expired_text' => 'الرابط ده انتهت صلاحيّته. اطلب واحدًا جديدًا، بياخد ثانية.',
            'auth.session.persistent_hint' => 'هتفضل داخل على طول، لحدّ ما تعمل «تسجيل خروج» بنفسك.',
            'developers.admin.catalog_no_scope' => 'بلا Scope، يكفي مفتاحٌ صالح',
            'developers.admin.delivery_status_failed' => 'فشلت · بانتظار إعادة',
            'developers.admin.empty_keys' => 'لا مفاتيح بعد، أنشئ أوّل مفتاح API.',
            'developers.admin.field_rate_limit' => 'حدّ الطلبات بالدقيقة (اختياريّ، فارغ يرث الحدّ العامّ)',
            'developers.admin.plain_key_warning' => 'هذا هو المفتاح الكامل، لن يظهر ثانيةً بعد إغلاق هذه الرسالة.',
            'developers.admin.revoke_confirm' => 'إبطال المفتاح فوريّ ولا رجعة فيه، تأكيد؟',
            'developers.admin.rotate_confirm' => 'تدوير المفتاح يُبطل القديم فورًا ويصدر مفتاحًا جديدًا بنفس الاسم والصلاحيّات، تأكيد؟',
            'developers.admin.terminal_error_generic' => 'تعذّر تنفيذ الأمر، حاول ثانيةً.',
            'developers.admin.terminal_output_empty' => 'لا مخرَجات بعد، نفّذ أمرًا لعرضها هنا.',
            'developers.admin.terminal_warning_body' => 'أيّ أمرٍ هنا يُنفَّذ مباشرةً على الخادم، لا قيود ولا تراجع، وكلّ أمرٍ مسجَّل.',
            'developers.admin.webhook_delete_confirm' => 'حذف الويب-هوك نهائيّ، تأكيد؟',
            'developers.admin.webhook_rotate_confirm' => 'تدوير السرّ يُبطل القديم فورًا، تأكيد؟',
            'developers.admin.webhook_secret_warning' => 'هذا هو السرّ الكامل، لن يظهر ثانيةً بعد إغلاق هذه الرسالة. استخدمه للتحقّق من توقيع HMAC في رأس X-Webhook-Signature.',
            'developers.admin.webhooks_empty' => 'لا ويب-هوكس بعد، سجّل أوّل ويب-هوك.',
            'developers.api.doc.ping' => 'فحص صلاحيّة المفتاح، يردّ OK باسم المفتاح.',
            'onboarding.account.email_invalid' => 'الشكل ده مش بريد صالح، راجع الكتابة.',
            'onboarding.account.email_taken' => 'البريد ده مستعمَل قبل كده، ادخل بيه أو استرجع كلمة السرّ.',
            'onboarding.account.phone_hint' => 'الرقم للتواصل بس، مش هنبعتلك عليه كود تحقّق.',
            'onboarding.account.step_label' => 'خطوة 1 من 2 · بيانات الدخول',
            'onboarding.account.title' => 'إنشاء حساب · بياناتك الأساسيّة',
            'onboarding.identity.governorate_failed' => 'تعذّر تحميل المحافظات، جرّب تاني',
            'onboarding.identity.name_ar_error' => 'اكتب اسمك ثلاثيًّا بالعربيّ، علشان الشهادة هتطلع بالاسم ده.',
            'onboarding.identity.name_en_error' => 'اكتب اسمك ثلاثيًّا بالإنجليزيّ، علشان النسخة الإنجليزيّة من الشهادة بتطلع بيه.',
            'onboarding.identity.step_label' => 'خطوة 2 من 2 · بيانات الشهادة',
            'onboarding.identity.subtitle' => 'البيانات دي هي اللي بتطلع على شهاداتك وإفاداتك، اكتبها زيّ ما تحبّ تشوفها عليها.',
            'onboarding.instructions.html' => '<h2>أهلًا بيك 👋</h2><p>قبل ما تبدأ، خُد دقيقتين تعرف المكان اللي إنت داخله وقوانينه.</p><h3>احترام أوّلًا</h3><p>كلّ اللي هنا زمايل. التعامل باحترام شرط بقاء، مش نصيحة.</p><h3>مجهودك ملكك</h3><p>شهاداتك ونقاطك بتتبني على شغلك إنت، والانتحال بيلغي الشهادة.</p><h3>التفعيل مجّانيّ</h3><p>مافيش رسوم تفعيل ولا اشتراك. حسابك بيتفعّل باعتماد من الإدارة بس.</p>',
            'onboarding.placement.admin.bad_html' => 'الكود فيه وسم مش مقفول، صلّحه قبل الحفظ عشان ما يكسرش شاشة المسجّلين.',
            'onboarding.placement.admin.delete_confirm' => 'هنشيل السؤال وإجاباته، نكمّل؟',
            'onboarding.placement.admin.empty' => 'لا أسئلة تمهيديّة، أضِف أوّل سؤال',
            'onboarding.placement.admin.field_embed_hint' => 'من أيّ مكان، وبنراجع اتّزان الوسوم قبل الحفظ.',
            'onboarding.placement.admin.field_options_hint' => 'خيار في كلّ سطر، تُترَك فاضية للإجابة النصّيّة.',
            'onboarding.placement.admin.skipped_warning' => 'الإعداد «مفعَّل» لكن ولا سؤال نشِط في البنك، فكلّ مُسجَّل جديد يتخطّى هذه الخطوة صامتًا. أضِف سؤالًا نشِطًا أو أوقف الخطوة من الإعدادات صراحةً.',
            'onboarding.placement.admin.subtitle' => 'أسئلة المسجّل الجديد ومكافأة كلّ سؤال، والترتيب بالسحب.',
            'onboarding.placement.empty_text' => 'مافيش أسئلة دلوقتي، كمّل على طول.',
            'onboarding.placement.intro_html' => '<p>مافيش رسوب هنا، ده مجرّد تعارف عشان نعرف نبدأ معاك منين. وكلّ سؤال ليه مكافأة.</p>',
            'onboarding.placement.result_text' => 'خلّصت ✓ إجاباتك الصحيحة :score من :total، وكسبت :xp XP و:tickets تذكرة.',
            'onboarding.referral.success_text' => 'تمام ✓ هديّتك اتفعّلت، كمّل تسجيلك.',
            'onboarding.review.html' => '<p>سجّلت بنجاح، والتفعيل <strong>مجّانيّ</strong> وبيتمّ باعتماد من الإدارة. هنبلّغك أوّل ما يتفعّل.</p>',
            'system.settings_registry.group_catalog_165' => 'المطوّرين · API',
            'system.settings_registry.tabs_49' => 'المطوّرين · API',
            'updates.failure_next_steps' => 'راجع سبب الفشل تحت، وابعته لمطوّر المنصّة مع اسم الهجرة. لو الاستعادة اشتغلت فالبيانات رجعت لحالتها قبل التحديث والمنصّة شغّالة عاديّ، بس متكرّرش التحديث قبل ما السبب يتصلّح.',
            'updates.maintenance_message' => 'بنحدّث المنصّة دلوقتي، دقايق ونرجع.',
            'wars.arena.focus.tagline' => 'عمل عميق بلا مقاطعة، والعدّ مبنيّ على أمانتك.',
            'wars.messages.focus_joined' => 'انضممت، وتذكرتك راحت لصاحب التحدّي 🎟️',
            'wars.messages.focus_started' => 'التحدّي بدأ، ركّز وإحنا معاك 🧘',
            'wars.messages.ready_cancelled' => 'اتلغى استعدادك، ارجع للساحة وقت ما تحبّ.',
            'wars.messages.withdrew_match' => 'انسحبت من المواجهة، والخصم كسبها.',
            'wars.messages.withdrew_penalty' => 'انسحبت، والانسحاب بيكلّف، خلّي بالك المرّة الجاية.',
        ];
    }

    /** النصّ القديم كما كان قبل التصحيح — شرطُ الكتابة وطريقُ الرجوع. */
    private function previous(): array
    {
        return [
            'account.delete.data_notes' => '["بروفايلك وبياناتك الشخصيّة هتتشال من كلّ الشاشات فورًا.","شهاداتك الصادرة هتفضل قابلة للتحقّق برقمها — دي حقّ الجهة اللي استلمتها.","معاملات المحفظة والمشتريات بتفضل في السجلّ الماليّ بالقانون، بلا اسمك.","مساهماتك في التطوّع بتفضل باسم «عضو سابق» علشان شغل الفريق ما يتكسرش.","رصيدك الحاليّ بيسقط ومش هيرجع لو رجعت تاني."]',
            'announcements.email.footer' => 'وصلتك الرسالة دي لأنّك مفعّل قناة البريد — تقدر توقّفها من إعدادات حسابك.',
            'auth.logout.all_devices_done' => 'قفلنا كلّ الجلسات ✓ — سجّل دخولك تاني.',
            'auth.otp.error_missing' => 'مابعتناش رمز لسّه — اضغط «إرسال» الأوّل.',
            'auth.otp.error_step_lost' => 'الجلسة رجعت لأوّل خطوة — اكتب بريدك وأكّده تاني وهنكمّل من هناك.',
            'auth.otp.inline_hint' => 'هنبعت رمز من {length} أرقام على بريدك — اكتبه هنا عشان نتأكّد إنّه بريدك فعلًا.',
            'auth.otp.replay_failed_text' => 'بريدك اتأكّد ✓ بس فيه بيانات اتغيّرت وإحنا بنكمّل — صحّح المكتوب بالأحمر واضغط استكمال، ومش هنطلب منك الرمز تاني.',
            'auth.otp.verified_text' => 'بريدك اتأكّد — كمّل باقي البيانات.',
            'auth.password_reset.done_text' => 'كلمة السرّ اتغيّرت ✓ — ادخل بيها دلوقتي. وقفلنا كلّ الجلسات القديمة للأمان.',
            'auth.password_reset.expired_text' => 'الرابط ده انتهت صلاحيّته. اطلب واحدًا جديدًا — بياخد ثانية.',
            'auth.session.persistent_hint' => 'هتفضل داخل على طول — لحدّ ما تعمل «تسجيل خروج» بنفسك.',
            'developers.admin.catalog_no_scope' => 'بلا Scope — يكفي مفتاحٌ صالح',
            'developers.admin.delivery_status_failed' => 'فشلت — بانتظار إعادة',
            'developers.admin.empty_keys' => 'لا مفاتيح بعد — أنشئ أوّل مفتاح API.',
            'developers.admin.field_rate_limit' => 'حدّ الطلبات بالدقيقة (اختياريّ — فارغ يرث الحدّ العامّ)',
            'developers.admin.plain_key_warning' => 'هذا هو المفتاح الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة.',
            'developers.admin.revoke_confirm' => 'إبطال المفتاح فوريّ ولا رجعة فيه — تأكيد؟',
            'developers.admin.rotate_confirm' => 'تدوير المفتاح يُبطل القديم فورًا ويصدر مفتاحًا جديدًا بنفس الاسم والصلاحيّات — تأكيد؟',
            'developers.admin.terminal_error_generic' => 'تعذّر تنفيذ الأمر — حاول ثانيةً.',
            'developers.admin.terminal_output_empty' => 'لا مخرَجات بعد — نفّذ أمرًا لعرضها هنا.',
            'developers.admin.terminal_warning_body' => 'أيّ أمرٍ هنا يُنفَّذ مباشرةً على الخادم — لا قيود ولا تراجع، وكلّ أمرٍ مسجَّل.',
            'developers.admin.webhook_delete_confirm' => 'حذف الويب-هوك نهائيّ — تأكيد؟',
            'developers.admin.webhook_rotate_confirm' => 'تدوير السرّ يُبطل القديم فورًا — تأكيد؟',
            'developers.admin.webhook_secret_warning' => 'هذا هو السرّ الكامل — لن يظهر ثانيةً بعد إغلاق هذه الرسالة. استخدمه للتحقّق من توقيع HMAC في رأس X-Webhook-Signature.',
            'developers.admin.webhooks_empty' => 'لا ويب-هوكس بعد — سجّل أوّل ويب-هوك.',
            'developers.api.doc.ping' => 'فحص صلاحيّة المفتاح — يردّ OK باسم المفتاح.',
            'onboarding.account.email_invalid' => 'الشكل ده مش بريد صالح — راجع الكتابة.',
            'onboarding.account.email_taken' => 'البريد ده مستعمَل قبل كده — ادخل بيه أو استرجع كلمة السرّ.',
            'onboarding.account.phone_hint' => 'الرقم للتواصل بس — مش هنبعتلك عليه كود تحقّق.',
            'onboarding.account.step_label' => 'خطوة 1 من 2 — بيانات الدخول',
            'onboarding.account.title' => 'إنشاء حساب — بياناتك الأساسيّة',
            'onboarding.identity.governorate_failed' => 'تعذّر تحميل المحافظات — جرّب تاني',
            'onboarding.identity.name_ar_error' => 'اكتب اسمك ثلاثيًّا بالعربيّ — الشهادة هتطلع بالاسم ده.',
            'onboarding.identity.name_en_error' => 'اكتب اسمك ثلاثيًّا بالإنجليزيّ — النسخة الإنجليزيّة من الشهادة بتطلع بيه.',
            'onboarding.identity.step_label' => 'خطوة 2 من 2 — بيانات الشهادة',
            'onboarding.identity.subtitle' => 'البيانات دي هي اللي بتطلع على شهاداتك وإفاداتك — اكتبها زيّ ما تحبّ تشوفها عليها.',
            'onboarding.instructions.html' => '<h2>أهلًا بيك 👋</h2><p>قبل ما تبدأ، خُد دقيقتين تعرف المكان اللي إنت داخله وقوانينه.</p><h3>احترام أوّلًا</h3><p>كلّ اللي هنا زمايل. التعامل باحترام شرط بقاء، مش نصيحة.</p><h3>مجهودك ملكك</h3><p>شهاداتك ونقاطك بتتبني على شغلك إنت — والانتحال بيلغي الشهادة.</p><h3>التفعيل مجّانيّ</h3><p>مافيش رسوم تفعيل ولا اشتراك. حسابك بيتفعّل باعتماد من الإدارة بس.</p>',
            'onboarding.placement.admin.bad_html' => 'الكود فيه وسم مش مقفول — صلّحه قبل الحفظ عشان ما يكسرش شاشة المسجّلين.',
            'onboarding.placement.admin.delete_confirm' => 'هنشيل السؤال وإجاباته — نكمّل؟',
            'onboarding.placement.admin.empty' => 'لا أسئلة تمهيديّة — أضِف أوّل سؤال',
            'onboarding.placement.admin.field_embed_hint' => 'من أيّ مكان — وبنراجع اتّزان الوسوم قبل الحفظ.',
            'onboarding.placement.admin.field_options_hint' => 'خيار في كلّ سطر — تُترَك فاضية للإجابة النصّيّة.',
            'onboarding.placement.admin.skipped_warning' => 'الإعداد «مفعَّل» لكن ولا سؤال نشِط في البنك — فكلّ مُسجَّل جديد يتخطّى هذه الخطوة صامتًا. أضِف سؤالًا نشِطًا أو أوقف الخطوة من الإعدادات صراحةً.',
            'onboarding.placement.admin.subtitle' => 'أسئلة المسجّل الجديد ومكافأة كلّ سؤال — والترتيب بالسحب.',
            'onboarding.placement.empty_text' => 'مافيش أسئلة دلوقتي — كمّل على طول.',
            'onboarding.placement.intro_html' => '<p>مافيش رسوب هنا — ده مجرّد تعارف عشان نعرف نبدأ معاك منين. وكلّ سؤال ليه مكافأة.</p>',
            'onboarding.placement.result_text' => 'خلّصت ✓ إجاباتك الصحيحة :score من :total — وكسبت :xp XP و:tickets تذكرة.',
            'onboarding.referral.success_text' => 'تمام ✓ هديّتك اتفعّلت — كمّل تسجيلك.',
            'onboarding.review.html' => '<p>سجّلت بنجاح — والتفعيل <strong>مجّانيّ</strong> وبيتمّ باعتماد من الإدارة. هنبلّغك أوّل ما يتفعّل.</p>',
            'system.settings_registry.group_catalog_165' => 'المطوّرين — API',
            'system.settings_registry.tabs_49' => 'المطوّرين — API',
            'updates.failure_next_steps' => 'راجع سبب الفشل تحت، وابعته لمطوّر المنصّة مع اسم الهجرة. لو الاستعادة اشتغلت فالبيانات رجعت لحالتها قبل التحديث والمنصّة شغّالة عاديّ — متكرّرش التحديث قبل ما السبب يتصلّح.',
            'updates.maintenance_message' => 'بنحدّث المنصّة دلوقتي — دقايق ونرجع.',
            'wars.arena.focus.tagline' => 'عمل عميق بلا مقاطعة — والعدّ مبنيّ على أمانتك.',
            'wars.messages.focus_joined' => 'انضممت — تذكرتك راحت لصاحب التحدّي 🎟️',
            'wars.messages.focus_started' => 'التحدّي بدأ — ركّز وإحنا معاك 🧘',
            'wars.messages.ready_cancelled' => 'اتلغى استعدادك — ارجع للساحة وقت ما تحبّ.',
            'wars.messages.withdrew_match' => 'انسحبت من المواجهة — والخصم كسبها.',
            'wars.messages.withdrew_penalty' => 'انسحبت — والانسحاب بيكلّف، خلّي بالك المرّة الجاية.',
        ];
    }

    public function up(): void
    {
        $this->apply($this->previous(), $this->replacements());
    }

    public function down(): void
    {
        $this->apply($this->replacements(), $this->previous());
    }

    /** يكتب الجديد مكان القديم في `value` و`default_value` كلٌّ على حدة. */
    private function apply(array $from, array $to): void
    {
        foreach ($to as $key => $new) {
            $old = $from[$key] ?? null;

            if ($old === null) {
                continue;
            }

            DB::table('settings')->where('key', $key)->where('value', $old)->update(['value' => $new]);
            DB::table('settings')->where('key', $key)->where('default_value', $old)->update(['default_value' => $new]);
        }

        Cache::forget('settings');
    }
};
