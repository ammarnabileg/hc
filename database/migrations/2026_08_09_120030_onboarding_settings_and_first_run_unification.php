<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إعدادات رحلة التسجيل كاملةً (2.13) + **توحيد مصدر «شاشة أوّل مرّة»**.
 *
 * أوّلًا — الإعدادات: كلّ نصّ في الرحلة (الدعوة · التعليمات · الاختبار التمهيديّ ·
 * تحت المراجعة · تمّ قبول حسابك) يكتبه الأدمن ولا يُنشَر كود لتغيير كلمة. وهي
 * تُزرَع هنا لا في سيدر العرض، لأنّ **الإنتاج يشغّل الهجرات ولا يشغّل بذور العرض**.
 *
 * ثانيًا — التوحيد: كان للشاشة مصدرا حقيقة؛ الأدمن يكتب في جدول `onboarding_slides`
 * (له شاشة إدارة كاملة: CRUD وترتيب وتفعيل وقوالب ومعاينة) والمستخدم يقرأ من إعداد
 * `ux.first_time.content` — فلا يرى المستخدمُ شيئًا ممّا كتبه الأدمن أبدًا. المصدر
 * الآن **الجدول وحده**، ومحتوى المفتاح القديم يُرحَّل إليه ثمّ يُحذَف المفتاح من
 * الكتالوج كي لا يبقى يتيمًا يوهم المالك أنّه يُقرَأ.
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] */
    private const ROWS = [
        // ---------------- أ) شاشة «هل دعاك شخص؟» (2.5-أ)
        ['onboarding.referral.enabled', 'onboarding', 'عرض شاشة «هل دعاك شخص؟»', 'bool', '1'],
        ['onboarding.referral.title', 'onboarding', 'عنوان شاشة الدعوة', 'string', 'هل دعاك شخص ما؟'],
        ['onboarding.referral.body', 'onboarding', 'سطر شاشة الدعوة', 'text', 'لو حد من أصحابك دعاك، اكتب كوده وخُد الهديّة. ولو لأ، كمّل عادي.'],
        ['onboarding.referral.field_label', 'onboarding', 'تسمية حقل كود الصديق', 'string', 'كود الصديق'],
        ['onboarding.referral.activate_label', 'onboarding', 'زرّ تفعيل الهديّة', 'string', 'فعّل الهديّة'],
        ['onboarding.referral.skip_label', 'onboarding', 'زرّ التخطّي', 'string', 'مافيش حد دعاني'],
        ['onboarding.referral.invalid_text', 'onboarding', 'رسالة الكود غير الصحيح', 'string', 'الكود ده مش موجود. راجعه مع صاحبك أو اتخطّى الخطوة.'],
        ['onboarding.referral.success_text', 'onboarding', 'رسالة تفعيل الهديّة', 'string', 'تمام ✓ هديّتك اتفعّلت — كمّل تسجيلك.'],

        // ---------------- ج) بيانات الشهادات والإفادات (2.5-ج)
        ['onboarding.identity.titles', 'onboarding', 'ألقاب صفحة المعلومات (مجمَّعة)', 'json', '{"ألقاب عامّة":["السيد","السيدة","الآنسة"],"ألقاب مهنيّة":["المعلم","المعلمة","المدرب","المهندس","المحاسب","المحامي","الطبيب","الطبيبة","الأخصائي","المستشار"],"ألقاب أكاديميّة":["رئيس الجامعة","نائب رئيس الجامعة","العميد","الأستاذ الدكتور"]}'],
        ['onboarding.identity.name_words', 'onboarding', 'أقلّ عدد كلمات في الاسم (ثلاثيّ)', 'number', '3'],
        ['onboarding.identity.name_ar_error', 'onboarding', 'خطأ الاسم بالعربيّ', 'string', 'اكتب اسمك ثلاثيًّا بالعربيّ — الشهادة هتطلع بالاسم ده.'],
        ['onboarding.identity.name_en_error', 'onboarding', 'خطأ الاسم بالإنجليزيّ', 'string', 'اكتب اسمك ثلاثيًّا بالإنجليزيّ — النسخة الإنجليزيّة من الشهادة بتطلع بيه.'],
        ['onboarding.identity.location_required', 'onboarding', 'الدولة والمحافظة مطلوبتان', 'bool', '1'],
        ['onboarding.identity.address_label', 'onboarding', 'تسمية العنوان الفرعيّ', 'string', 'العنوان الفرعيّ (المنطقة والشارع)'],

        // ---------------- د-1) صفحة «تعليمات» (2.5-د-1)
        ['onboarding.instructions.title', 'onboarding', 'عنوان صفحة التعليمات', 'string', 'تعليمات المنصّة'],
        ['onboarding.instructions.html', 'onboarding', 'محتوى صفحة التعليمات (HTML: نصوص وصور وفيديو)', 'text',
            '<h2>أهلًا بيك 👋</h2><p>قبل ما تبدأ، خُد دقيقتين تعرف المكان اللي إنت داخله وقوانينه.</p>'
            .'<h3>احترام أوّلًا</h3><p>كلّ اللي هنا زمايل. التعامل باحترام شرط بقاء، مش نصيحة.</p>'
            .'<h3>مجهودك ملكك</h3><p>شهاداتك ونقاطك بتتبني على شغلك إنت — والانتحال بيلغي الشهادة.</p>'
            .'<h3>التفعيل مجّانيّ</h3><p>مافيش رسوم تفعيل ولا اشتراك. حسابك بيتفعّل باعتماد من الإدارة بس.</p>'],
        ['onboarding.instructions.scroll_label', 'onboarding', 'زرّ التمرير لأسفل', 'string', 'كمّل قراية ↓'],
        ['onboarding.instructions.agree_label', 'onboarding', 'زرّ الموافقة', 'string', 'موافق'],
        ['onboarding.instructions.agree_hint', 'onboarding', 'سطر تحت زرّ الموافقة', 'string', 'الزرّ بيشتغل لمّا توصل لآخر الصفحة.'],

        // ---------------- د-2) الاختبار التمهيديّ (2.5-د-2)
        ['onboarding.placement.enabled', 'onboarding', 'تفعيل الاختبار التمهيديّ', 'bool', '1'],
        ['onboarding.placement.title', 'onboarding', 'عنوان الاختبار التمهيديّ', 'string', 'اختبار تمهيديّ سريع'],
        ['onboarding.placement.intro_html', 'onboarding', 'مقدّمة الاختبار التمهيديّ (HTML)', 'text',
            '<p>مافيش رسوب هنا — ده مجرّد تعارف عشان نعرف نبدأ معاك منين. وكلّ سؤال ليه مكافأة.</p>'],
        ['onboarding.placement.submit_label', 'onboarding', 'زرّ تسليم الاختبار', 'string', 'سلّم إجاباتي'],
        ['onboarding.placement.empty_text', 'onboarding', 'نصّ الاختبار بلا أسئلة', 'string', 'مافيش أسئلة دلوقتي — كمّل على طول.'],
        ['onboarding.placement.result_text', 'onboarding', 'نصّ النتيجة (:score و:total و:xp و:tickets)', 'string', 'خلّصت ✓ إجاباتك الصحيحة :score من :total — وكسبت :xp XP و:tickets تذكرة.'],
        ['onboarding.placement.reveal_correct', 'onboarding', 'إظهار الإجابة الصحيحة بعد التسليم', 'bool', '0'],

        // ---------------- د-3) صفحة «تحت المراجعة» (2.5-د-3)
        ['onboarding.review.html', 'onboarding', 'محتوى صفحة «تحت المراجعة» (HTML)', 'text',
            '<p>سجّلت بنجاح — والتفعيل <strong>مجّانيّ</strong> وبيتمّ باعتماد من الإدارة. هنبلّغك أوّل ما يتفعّل.</p>'],

        // ---------------- د-4) صفحة «تمّ قبول حسابك» (2.5-د-4)
        ['onboarding.accepted.title', 'onboarding', 'عنوان صفحة القبول', 'string', 'تمّ قبول حسابك 🎉'],
        ['onboarding.accepted.html', 'onboarding', 'محتوى صفحة القبول (HTML يضيفه الأدمن)', 'text',
            '<p>أهلًا بيك رسميًّا معانا. حسابك بقى مفعَّل، وكلّ حاجة في المنصّة بقت قدّامك.</p>'],
        ['onboarding.accepted.cta_label', 'onboarding', 'زرّ الدخول للمنصّة', 'string', 'يلا ندخل'],

        // ---------------- القالب الجاهز العامّ لـ«أوّل مرّة» — بديل مفتاح `ux` اليتيم
        ['onboarding.first_time.default_template', 'onboarding', 'القالب الجاهز الافتراضيّ لأيّ شاشة', 'json', '[]'],

        // ---------------- الجلسة مدى الحياة (2.3)
        ['auth.session.lifetime_minutes', 'accounts', 'مدّة الجلسة بالدقائق (مدى الحياة)', 'number', '2628000'],
        ['auth.session.remember_always', 'accounts', 'إبقاء الدخول مفتوحًا دائمًا (فكّرني تلقائيّ)', 'bool', '1'],
        ['auth.session.persistent_hint', 'accounts', 'سطر تحت زرّ الدخول', 'string', 'هتفضل داخل على طول — لحدّ ما تعمل «تسجيل خروج» بنفسك.'],
        ['auth.logout.all_devices_label', 'accounts', 'زرّ الخروج من كلّ الأجهزة', 'string', 'تسجيل الخروج من كلّ الأجهزة'],
        ['auth.logout.all_devices_done', 'accounts', 'رسالة الخروج من كلّ الأجهزة', 'string', 'قفلنا كلّ الجلسات ✓ — سجّل دخولك تاني.'],

        // ---------------- اسم صاحب الشهادة (12.5-ب)
        ['certificates.render.title_with_name', 'certificates', 'طباعة اللقب قبل الاسم على الشهادة', 'bool', '1'],
    ];

    /** مفاتيح فقدت قارئها بعد التوحيد — تُحذَف كي لا يظنّها المالك مؤثّرة */
    private const RETIRED = ['ux.first_time.content', 'ux.first_time.default_template'];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$key, $group, $label, $type, $default]) {
            // `insertOrIgnore` عمدًا: لا نلمس قيمةً عدّلها المالك بالفعل
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->migrateFirstRunContent();
        $this->migrateDefaultTemplate();

        DB::table('settings')->whereIn('key', self::RETIRED)->delete();
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::ROWS, 0))->delete();
    }

    /** محتوى «أوّل مرّة» القديم ⟵ شرائح حقيقيّة في الجدول الذي يملك شاشة إدارة */
    private function migrateFirstRunContent(): void
    {
        $raw = DB::table('settings')->where('key', 'ux.first_time.content')->value('value');
        $content = json_decode((string) $raw, true);

        if (! is_array($content)) {
            return;
        }

        foreach ($content as $screen => $steps) {
            $screen = (string) $screen;

            if (! is_array($steps) || $steps === [] || $screen === '') {
                continue;
            }

            // ما كتبه الأدمن في الجدول أحدث وأولى — فلا نضيف فوقه ولا نكرّره
            if (DB::table('onboarding_slides')->where('screen', $screen)->exists()) {
                continue;
            }

            foreach (array_values($steps) as $index => $step) {
                if (! is_array($step) || trim((string) ($step['title'] ?? '')) === '') {
                    continue;
                }

                DB::table('onboarding_slides')->insert([
                    'screen' => $screen,
                    'title_ar' => (string) $step['title'],
                    'body_ar' => (string) ($step['body'] ?? ''),
                    'image_path' => null,
                    'action_label' => $step['action_label'] ?? null,
                    'action_url' => $step['action_url'] ?? null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'from_template' => false,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** القالب الجاهز العامّ ينتقل لمجموعة `onboarding` مع بقيّة قوالبها */
    private function migrateDefaultTemplate(): void
    {
        $old = DB::table('settings')->where('key', 'ux.first_time.default_template')->value('value');

        if (! is_string($old) || ! is_array(json_decode($old, true))) {
            return;
        }

        DB::table('settings')
            ->where('key', 'onboarding.first_time.default_template')
            ->where('value', '[]')
            ->update(['value' => $old, 'default_value' => $old, 'updated_at' => now()]);
    }
};
