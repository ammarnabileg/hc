<?php

namespace App\Services\Admin\Volunteer;

/**
 * كتالوج إعدادات مجال «إدارة التطوّع والتلعيب والمكافآت والفعاليّات».
 *
 * لماذا كتالوج واحد؟ لأنّ القاعدة الذهبيّة (2.13) تمنع أيّ رقم أو نصّ محروق،
 * وزرّ «Reset للافتراضيّ» يحتاج مرجعًا واحدًا للقيمة الافتراضيّة لكلّ مفتاح؛
 * فلو تفرّقت الافتراضيّات بين السيدر والكود اختلفت القيمتان وضاع معنى الـReset.
 *
 * الصيغة: key => [group, label_ar, type, default, hint?]
 */
class SettingsCatalog
{
    /** @return array<string, array{0:string,1:string,2:string,3:string,4?:string}> */
    public static function all(): array
    {
        $rows = array_merge(
            self::volunteerPage(),
            self::honorary(),
            self::org(),
            self::rep(),
            self::offboarding(),
            self::certificates(),
            self::analytics(),
            self::xpAndTickets(),
            self::rewardQuestions(),
            self::streaksAndLeaderboard(),
            self::wars(),
            self::celebrations(),
            self::rewards(),
            self::events(),
            self::availability(),
            self::kudos(),
            self::evaluations(),
            self::workflowWindows(),
        );

        return array_map(
            fn (array $row) => self::stringify($row, 2, [1, 4], 3),
            $rows,
        );
    }

    /**
     * ⚠️ الصيغة أعلاه **تَعِد بنصوص**، وقيمُ هذه الصفوف صارت تُقرأ من الإعدادات
     * (2.13). و`setting()` يحكمه **النوع المعلَن في صفّه بالقاعدة**: صفٌّ نوعه
     * `json` يعود **مصفوفةً** لا نصًّا، فينفجر `(string)` عند أوّل قارئ
     * (`SettingsWriter::groupRows()`).
     *
     * فالإرجاع هنا **بحسب النوع المعلَن في الصفّ نفسه** لا بقسرٍ أعمى:
     * `json`/`lines` صورتها النصّيّة هي ترميز JSON · والمنطقيّ «1»/«0» ·
     * وما عداهما نصٌّ كما هو. ولو قسرنا `(string)` على الكلّ انفجرنا، ولو
     * رمّزنا الكلّ JSON لحوّلنا النصّ العاديّ إلى نصٍّ بين علامتَي اقتباس —
     * وهي **قيمةٌ خاطئة صامتة**، وهي أسوأ من الانفجار.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    private static function stringify(array $row, int $typeIndex, array $textIndexes, int $valueIndex): array
    {
        $type = (string) ($row[$typeIndex] ?? 'string');

        foreach ($textIndexes as $i) {
            if (array_key_exists($i, $row)) {
                $row[$i] = self::plainText($row[$i]);
            }
        }

        if (array_key_exists($valueIndex, $row)) {
            $row[$valueIndex] = match (true) {
                in_array($type, ['json', 'lines'], true) => is_array($row[$valueIndex])
                    ? (string) json_encode($row[$valueIndex], JSON_UNESCAPED_UNICODE)
                    : self::plainText($row[$valueIndex]),
                $type === 'bool' => is_bool($row[$valueIndex]) ? ($row[$valueIndex] ? '1' : '0') : self::plainText($row[$valueIndex]),
                default => self::plainText($row[$valueIndex]),
            };
        }

        return $row;
    }

    /** لافتةٌ أو شرحٌ: نصٌّ دائمًا — والمصفوفة (نوعٌ مضروب) تُرمَّز بدل أن تنفجر */
    private static function plainText(mixed $value): string
    {
        return match (true) {
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            default => (string) $value,
        };
    }

    /** القيمة الافتراضيّة المعتمَدة لمفتاح — مرجع زرّ الـReset */
    public static function defaultOf(string $key): ?string
    {
        return self::all()[$key][3] ?? null;
    }

    /** مفاتيح مجموعة بعينها — لكلّ تاب مجموعته */
    public static function group(string $group): array
    {
        return array_filter(self::all(), fn ($row) => $row[0] === $group);
    }

    /**
     * انتقاء مفاتيح صريحة عبر مجموعات متعدّدة بترتيبها المطلوب — للتابات
     * العرضيّة في الإدارة المركزيّة (24.2 · 13.4-ك) التي لا تطابق مجموعة
     * واحدة (مثل VXP والنوافذ والسلوك)، فلا تُنسَخ الصفوف ولا يتفرّق مصدرها.
     */
    public static function pick(array $keys): array
    {
        $all = self::all();
        $rows = [];

        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $rows[$key] = $all[$key];
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- صفحة التطوّع (13.4-أ)

    private static function volunteerPage(): array
    {
        return [
            'volunteer_page.hero_title' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_1', 'عنوان صفحة التطوّع'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_2', 'تطوّع معنا واصنع أثرًا')],
            'volunteer_page.hero_subtitle' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_3', 'السطر التعريفيّ'), 'text', setting('volunteer_page.settings_catalog.volunteer_page_4', 'وقتك يقدر يغيّر رحلة متدرّب كامل — ابدأ من هنا.')],
            'volunteer_page.hero_media' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_5', 'رابط فيديو/صورة الأثر'), 'string', ''],
            'volunteer_page.cta_label' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_6', 'نصّ الزرّ الرئيسيّ'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_7', 'ابدأ التدريب التأهيليّ')],
            'volunteer_page.charter_text' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_8', 'ميثاق المتطوّع'), 'text', setting('volunteer_page.settings_catalog.volunteer_page_9', 'أتعهّد بالالتزام بمواعيدي، وباحترام فريقي، وبالحفاظ على ما يُؤتمَن عليّ من بيانات.')],
            'volunteer_page.stats_enabled' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_10', 'إظهار الإحصائيّات الحيّة'), 'bool', '1'],
            'volunteer_page.stats_offset_volunteers' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_11', 'Offset عدّاد المتطوّعين'), 'number', '0'],
            'volunteer_page.stats_offset_trainees' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_12', 'Offset عدّاد المستفيدين'), 'number', '0'],
            // كتل المحتوى: تُضاف وتُعدَّل وتُحذَف كلّها من هنا (13.4-أ)
            'volunteer_page.blocks' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_13', 'كتل المحتوى (سؤال شائع · قصّة · أثر)'), 'json', '[]'],
            'volunteer_page.empty_message' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_14', 'رسالة الحالة الفارغة'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_15', 'لسّه محتوى الصفحة فاضي — ابدأ بأوّل كتلة.')],

            // عناوين السكاشن وأسطر الأثر — تُقرَأ في الصفحة الحيّة نفسها (13.4-أ)
            'volunteer_page.stats_volunteers_label' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_16', 'وصف عدّاد المتطوّعين'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_17', 'متطوّع معنا')],
            'volunteer_page.stats_trainees_label' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_18', 'وصف عدّاد المستفيدين'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_19', 'متدرّب مستفيد')],
            'volunteer_page.impact_line' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_20', 'سطر إبراز الأثر'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_21', 'بتطوّعك هتساعد :count متدرّب على إكمال رحلته.')],
            'volunteer_page.faq_title' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_22', 'عنوان الأسئلة الشائعة'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_23', 'أسئلة بتتسأل كتير')],
            'volunteer_page.stories_title' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_24', 'عنوان قصص المتطوّعين'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_25', 'حكايات من الفريق')],
            'volunteer_page.charter_title' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_26', 'عنوان الميثاق'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_27', 'ميثاق المتطوّع')],
            'volunteer_page.charter_agree_label' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_28', 'زرّ الموافقة على الميثاق'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_29', 'قرأت الميثاق وموافق عليه')],
            'volunteer_page.charter_required_message' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_30', 'رسالة الميثاق قبل البدء'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_31', 'اقرأ ميثاق المتطوّع ووافق عليه الأوّل — بعدها يفتح لك المسار التأهيليّ.')],
            'volunteer_page.charter_done_message' => ['volunteer_page', setting('volunteer_page.settings_catalog.volunteer_page_32', 'رسالة بعد الموافقة'), 'string', setting('volunteer_page.settings_catalog.volunteer_page_33', 'اتسجّل ✓ — المسار التأهيليّ بقى مفتوح ليك.')],
        ];
    }

    // ---------------------------------------------------------------- العنصر الشرفيّ (13.4-ص-د)

    /**
     * خمسة إعدادات لا اثنان: **تفعيل · اختيار الحساب · الوصف بنسختيه (ع/إ) ·
     * أماكن الظهور · شكل الإطار** — و**التعديل لمالك المنصّة وحده 🔒 مع Audit**
     * (13.4-ص-د)، ولذلك مجموعتها مستقلّة تُخفى كلّها عمّن ليس مالكًا (2.15-أ-7).
     */
    private static function honorary(): array
    {
        return [
            'volunteer.honorary.enabled' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_1', 'إظهار العنصر الشرفيّ «أخوكم»'), 'bool', '1'],
            // 0 = حساب مالك المنصّة (الافتراضيّ المنصوص عليه)
            'volunteer.honorary.user_id' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_2', 'الحساب المرتبط (0 = مالك المنصّة)'), 'number', '0'],
            'volunteer.honorary.label_ar' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_3', 'الوصف — عربيّ'), 'string', setting('volunteer_honorary.settings_catalog.honorary_4', 'أخوكم')],
            'volunteer.honorary.label_en' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_5', 'الوصف — إنجليزيّ'), 'string', 'Your brother'],
            'volunteer.honorary.places' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_6', 'أماكن الظهور (كانفاس · أعضاء · الصفحة التعريفيّة)'), 'json', '{"canvas":true,"members":true,"landing":false}'],
            'volunteer.honorary.frame_style' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_7', 'شكل الإطار (soft · gold · dashed · none)'), 'string', 'soft'],
            'volunteer.honorary.note' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_8', 'سطر التوضيح تحت الاسم'), 'string', setting('volunteer_honorary.settings_catalog.honorary_9', 'عنصر شرفيّ — بلا مؤشّرات ولا يدخل أيّ عدّاد')],
            // مُعلَن لا ليُعدَّل: لا بطاقة ولا شهادة ولا عدّاد ولا أوفبوردنج (13.4-ص-ج)
            'volunteer.honorary.excluded_from_counters_locked' => ['volunteer_honorary', setting('volunteer_honorary.settings_catalog.honorary_10', 'خارج كلّ العدّادات والشهادات والبطاقة — مقفول'), 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- الهيكل والسعة (13.4-ف)

    private static function org(): array
    {
        return [
            'volunteer.org.canvas_collapse_limit' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_1', 'حدّ الطيّ التلقائيّ للكانفاس (عقدة)'), 'number', '30'],
            'volunteer.org.canvas_open_levels' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_2', 'مستويات الفتح الافتراضيّة'), 'number', '2'],
            'volunteer.org.capacity_is_blocking' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_3', 'السعة مانعة؟ (مقفول: مؤشّرات لا موانع)'), 'bool', '0'],
            'volunteer.org.occupancy_warn_percent' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_4', 'عتبة اللون الأصفر للإشغال (%)'), 'number', '80'],
            'volunteer.org.occupancy_danger_percent' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_5', 'عتبة اللون الأحمر للإشغال (%)'), 'number', '100'],
            'volunteer.org.unhealthy_alert_enabled' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_6', 'تنبيه «كيان غير صحّيّ»'), 'bool', '1'],
            'volunteer.org.balancer_suggest_least_loaded' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_7', 'اقتراح الموازن للأقلّ إشغالًا'), 'bool', '1'],
            'volunteer.org.memberships_per_track' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_8', 'حدّ العضويّات لكلّ مسار'), 'number', '1'],
            // الملفّ المؤقّت: يفتحه ويُنهيه مشرف عام التطوّع وحده (23-0.2)
            'volunteer.org.case_file_opener_position' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_9', 'البوزشن الذي يفتح الملفّ المؤقّت ويُنهيه'), 'string', 'volunteer_gm'],
            'volunteer.org.member_cap_per_span_factor' => ['volunteer_org', setting('volunteer_org.settings_catalog.org_10', 'معامل احتساب سقف أعضاء الكيان من نطاق الإشراف'), 'number', '2'],
        ];
    }

    // ---------------------------------------------------------------- ضبط Rep (13.4-ن)

    private static function rep(): array
    {
        return [
            'rep.behavior.monthly_cap_per_granter' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_1', 'سقف معاملات السلوك شهريًّا لكلّ مانح'), 'number', '5'],
            'rep.behavior.severe_approval_window_hours' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_2', 'نافذة موافقة المستوى الأعلى على المخالفة الجسيمة (ساعة)'), 'number', '24'],
            'rep.behavior.justification_min_chars' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_3', 'الحدّ الأدنى لطول المبرّر (حرف)'), 'number', '10'],
            'rep.behavior.attachment_enabled' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_4', 'السماح بمرفق مع معاملة السلوك'), 'bool', '1'],
            'rep.behavior.show_granter_count_in_team_health' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_5', 'إظهار عدد معاملات المانح في صحّة فريقه'), 'bool', '1'],
            'rep.objection.window_days' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_6', 'مهلة الاعتراض (يوم)'), 'number', '5'],
            'rep.objection.sla_hours' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_15', 'مهلة ردّ المسؤول على الاعتراض (ساعة)'), 'number', '24'],
            'rep.inactivity.days_before_alert' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_7', 'أيّام الخمول قبل التنبيه'), 'number', '21'],
            'rep.reset.day_of_month' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_8', 'يوم التصفير الشهريّ'), 'number', '1'],
            'rep.reset.hour' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_9', 'ساعة التصفير'), 'number', '5'],
            'rep.reset.timezone' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_10', 'توقيت التصفير'), 'string', 'Africa/Cairo'],
            'rep.reset.enabled' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_11', 'تفعيل التصفير الشهريّ'), 'bool', '1'],
            'rep.display.min' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_12', 'أدنى الرقم الظاهر'), 'number', '-10'],
            'rep.display.max' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_13', 'أقصى الرقم الظاهر'), 'number', '10'],
            'rep.daily_gain_cap_enabled' => ['volunteer_rep', setting('volunteer_rep.settings_catalog.rep_14', 'تفعيل سقف المكسب اليوميّ (معطّل افتراضيًّا)'), 'bool', '0'],
        ];
    }

    // ---------------------------------------------------------------- الأوفبوردنج (13.4-س · 13.4-ق)

    private static function offboarding(): array
    {
        return [
            'volunteer.offboarding.notice_days' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_1', 'مهلة الإشعار لتسليم العمل (يوم)'), 'number', '7'],
            'volunteer.offboarding.cooldown_days.resignation' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_2', 'تبريد العودة بعد الاستقالة (يوم)'), 'number', '30'],
            'volunteer.offboarding.cooldown_days.entity_ended' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_3', 'تبريد العودة بعد انتهاء الملفّ (يوم)'), 'number', '30'],
            'volunteer.offboarding.cooldown_days.thresholds' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_4', 'تبريد العودة بعد الخروج عبر العتبات (يوم)'), 'number', '90'],
            'volunteer.offboarding.exclusion_allows_return' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_5', 'الإقصاء يسمح بالعودة؟ (بقرار مشرف عام التطوّع وحده)'), 'bool', '0'],
            'volunteer.offboarding.exit_interview_enabled' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_6', 'تفعيل مقابلة الخروج'), 'bool', '1'],
            'volunteer.offboarding.exit_interview_questions' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_7', 'أسئلة مقابلة الخروج'), 'json', setting('volunteer_offboarding.settings_catalog.offboarding_8', '["إيه أكتر حاجة عجبتك في تجربتك معنا؟","إيه اللي كان ممكن يخلّيك تكمّل؟","سبب المغادرة باختصار؟"]')],
            // ⭐ قائمة الأسباب المقنّنة (§س-ي) — نصّ حرّ سابقًا، والسبب أصلًا لا يُنشَر للفريق (٣-س-هـ)
            'volunteer.offboarding.reasons' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_reasons', 'قائمة أسباب إنهاء العضويّة المقنَّنة'), 'json', setting('volunteer_offboarding.settings_catalog.offboarding_reasons_default', '["لا وقت كافٍ","ظروف شخصيّة","عدم رضا عن التجربة","انتقال/سفر","أسباب صحّيّة","أخرى"]')],
            'volunteer.offboarding.honorable_certificate_enabled' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_9', 'شهادة خبرة عند الخروج المشرَّف'), 'bool', '1'],
            'volunteer.offboarding.reason_published_to_team' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_10', 'نشر سبب الخروج للفريق؟ (مقفول: لا يُنشَر)'), 'bool', '0'],
            'volunteer.offboarding.team_message' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_11', 'رسالة الفريق عند الإنهاء'), 'string', setting('volunteer_offboarding.settings_catalog.offboarding_12', 'انتهت عضويّة {name} — نتمنّى له كلّ التوفيق.')],
            'volunteer.offboarding.clearance_items' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_13', 'بنود التصفية الإلزاميّة'), 'json', setting('volunteer_offboarding.settings_catalog.offboarding_14', '["نقل المهامّ المفتوحة للأبلاين بنفس الديدلاينات","سحب المساهمات الجارية وتحرير الرصيد المعلَّق","حسم الاعتراضات والتحكيمات المفتوحة","تفويض الداونلاين للأبلاين فورًا","نقل الاجتماعات والبنود المتكرّرة","إبقاء مُدخَلات المكتبة الداخليّة للكيان"]')],
            'volunteer.offboarding.cumulative_window_days' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_15', 'نافذة المكتسَب التراكميّ (يوم)'), 'number', '90'],
            'volunteer.offboarding.reentry_starts_position' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_16', 'بوزشن العائد'), 'string', 'coordinator'],
            'volunteer.offboarding.reentry_exam_required' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_17', 'إلزام الامتحان للعائدين'), 'bool', '1'],
            'volunteer.offboarding.cooldown_copy' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_18', 'نصّ صفحة التطوّع داخل التبريد'), 'string', setting('volunteer_offboarding.settings_catalog.offboarding_19', 'أهلًا بعودتك 👋 مكانك محفوظ عندنا. تقدر تبدأ من جديد يوم {date}.')],
            'volunteer.offboarding.excluded_copy' => ['volunteer_offboarding', setting('volunteer_offboarding.settings_catalog.offboarding_20', 'نصّ صفحة التطوّع بعد الإقصاء'), 'string', setting('volunteer_offboarding.settings_catalog.offboarding_21', 'العودة بعد الاستبعاد بتحتاج قرارًا من مشرف عام التطوّع. تواصل معنا من صفحة الدعم.')],
        ];
    }

    // ---------------------------------------------------------------- شهادات التطوّع (13.4-ع)

    private static function certificates(): array
    {
        return [
            // ⭐ شرطا الاستحقاق المنصوصان
            'volunteer_cert.min_days_in_position' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_1', 'الحدّ الأدنى للمدّة في البوزشن (يوم)'), 'number', '30'],
            'volunteer_cert.require_non_negative_rep' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_2', 'اشتراط Rep غير سالب وقت الإصدار'), 'bool', '1'],
            'volunteer_cert.one_per_position_entity' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_3', 'شهادة واحدة لكلّ (بوزشن × كيان) — مقفول'), 'bool', '1'],
            'volunteer_cert.auto_issue' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_4', 'الإصدار التلقائيّ عند الاستيفاء'), 'bool', '1'],
            'volunteer_cert.notify_on_issue' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_5', 'إشعار عند الإصدار'), 'bool', '1'],
            'volunteer_cert.celebration_tier' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_6', 'مستوى الاحتفال عند الإصدار (3 = ذروة)'), 'number', '3'],
            'volunteer_cert.free_locked' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_7', 'مجّانيّة 100% — مقفول'), 'bool', '1'],
            'volunteer_cert.hide_internal_numbers' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_8', 'منع أيّ أرقام داخليّة على الشهادة — مقفول'), 'bool', '1'],
            'volunteer_cert.revoke_only_on_fraud' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_9', 'الإلغاء للتزوير المثبَت وحده — مقفول'), 'bool', '1'],
            'volunteer_cert.cumulative_duration_on_reentry' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_10', 'تجميع المدّة تراكميًّا عند العودة'), 'bool', '1'],
            'volunteer_cert.types' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_11', 'الأنواع الأربعة وتفعيلها'), 'json', '{"volunteer_position":true,"volunteer_experience":true,"volunteer_case_file":true,"volunteer_appreciation":true}'],
            'volunteer_cert.min_days_by_position' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_12', 'الحدّ الأدنى للمدّة لكلّ بوزشن (يتجاوز العامّ)'), 'json', '{}'],
            'volunteer_cert.show_in_library_and_cv' => ['volunteer_cert', setting('volunteer_cert.settings_catalog.certificates_13', 'الظهور التلقائيّ في مكتبتي والبروفايل والـCV'), 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- تحليلات التطوّع

    private static function analytics(): array
    {
        return [
            'volunteer.analytics.default_range_days' => ['volunteer_analytics', setting('volunteer_analytics.settings_catalog.analytics_1', 'المدى الزمنيّ الافتراضيّ (يوم)'), 'number', '30'],
            'volunteer.analytics.team_health_weights' => ['volunteer_analytics', setting('volunteer_analytics.settings_catalog.analytics_2', 'أوزان مؤشّر صحّة الفريق'), 'json', '{"commitment":40,"delay":20,"returns":20,"review_speed":20}'],
            'volunteer.analytics.attrition_top' => ['volunteer_analytics', setting('volunteer_analytics.settings_catalog.analytics_3', 'عدد أسباب التسرّب المعروضة'), 'number', '5'],
            'volunteer.analytics.load_alert_tasks' => ['volunteer_analytics', setting('volunteer_analytics.settings_catalog.analytics_4', 'عتبة تنبيه الحمل (عدد مهامّ)'), 'number', '10'],
            'volunteer.analytics.retention_risk_visible_to_volunteer' => ['volunteer_analytics', setting('volunteer_analytics.settings_catalog.analytics_5', 'عرض مخاطر الفقدان للمتطوّع؟ (مقفول: لا)'), 'bool', '0'],
        ];
    }

    // ---------------------------------------------------------------- XP والتذاكر (12.10 · 7 · 7.1)

    private static function xpAndTickets(): array
    {
        return [
            /*
             | ⭐ **لا صفّ بلا مستهلك** (2.13): كلّ مفتاح هنا يقرؤه الكود فعلًا،
             | والقائمة الحاكمة `EconomyRules::CONSUMED_EARN`. وكانت الافتراضيّات
             | تحمل صفوفًا لا يقرؤها أحد (`five_am_club` · `streak.day` ·
             | `referral.success` · `placement_test` …) فيضبطها
             | الأدمن بلا أثر: XP النادي من **سلّم الحضور** (7.2)، ومكافأة الدعوة
             | **تذكرة** لا XP (7.6)، ومكافأة الاختبار التمهيديّ **لكلّ سؤال على
             | حدة** (7.1). ولكلٍّ من هذه مصدرٌ واحد في مكانه.
             */
            'xp_rules.earn' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_1', 'مصادر كسب XP'), 'json', setting('gamification_xp.settings_catalog.xp_and_tickets_2', '[{"key":"lesson.completed","label":"إكمال درس","value":50,"daily_cap":0,"enabled":true},{"key":"reward.question","label":"سؤال مكافأة","value":50,"daily_cap":0,"enabled":true},{"key":"qualifying.completed","label":"إتمام المسار التأهيليّ","value":1000,"daily_cap":0,"enabled":true}]')],
            'xp_rules.spend' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_3', 'أوجه الصرف'), 'json', setting('gamification_xp.settings_catalog.xp_and_tickets_4', '[{"key":"course.exam","label":"الامتحان النهائيّ","currency":"tickets","cost":1,"moment":"on_enter","enabled":true},{"key":"cv.export","label":"السيرة الذاتيّة","currency":"tickets","cost":2,"moment":"on_export","enabled":true},{"key":"streak.freeze","label":"تجميد ستريك","currency":"tickets","cost":1,"moment":"on_use","enabled":true},{"key":"war.focus.create","label":"إنشاء حرب تركيز","currency":"tickets","cost":5,"moment":"on_create","enabled":true},{"key":"war.join","label":"الانضمام لحرب","currency":"tickets","cost":1,"moment":"on_join","enabled":true}]')],
            // ⭐ قيمتا التدريب قبل/بعد نصف المهلة (12.10 — بلوك الإعدادات)
            'tickets.before_half_deadline' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_5', 'تذاكر إتمام التدريب قبل نصف المهلة'), 'number', '2'],
            'tickets.after_half_deadline' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_6', 'تذاكر إتمام التدريب بعد نصف المهلة'), 'number', '1'],
            'tickets.midpoint_percent' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_7', 'نقطة المنتصف من المهلة (%)'), 'number', '50'],
            'xp_rules.decay_mode' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_8', 'نمط تناقص XP الدرس'), 'string', 'linear'],
            'xp_rules.decay_min' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_9', 'الحدّ الأدنى بعد التناقص'), 'number', '0'],
            'xp_rules.course_xp_once_locked' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_10', 'XP إكمال الكورس مرّة واحدة أيًّا كان السياق — مقفول'), 'bool', '1'],
            'levels.enabled' => ['gamification_xp', setting('gamification_xp.settings_catalog.xp_and_tickets_11', 'تفعيل المستويات'), 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- الستريك والليدر بورد (7.2 · 7.3)

    private static function streaksAndLeaderboard(): array
    {
        return [
            // ⭐ مفتاح واحد لكلّ معنًى (7.2 · 2.13): كانت الشاشة تكتب تهجئةً
            // ويقرأ الكود أخرى، فيعدّل الأدمن قيمةً لا يراها النظام. المعتمَد
            // هو ما تراه هنا، ومايجريشن `settings_unify_duplicates` يحذف اليتيم.
            'streaks.enabled' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_1', 'تفعيل الستريك ونادي الخامسة'), 'bool', '1'],
            'streaks.club5am.window_start' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_2', 'بداية نافذة نادي الخامسة (توقيت المستخدم)'), 'string', '04:50'],
            'streaks.club5am.window_end' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_3', 'نهاية نافذة نادي الخامسة'), 'string', '05:20'],
            'streaks.xp_ladder' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_4', 'سلّم XP الحضور المتدرّج'), 'json', '[{"from":1,"to":10,"xp":100},{"from":11,"to":25,"xp":150},{"from":26,"to":45,"xp":200},{"from":46,"to":75,"xp":250},{"from":76,"to":125,"xp":300},{"from":126,"to":0,"xp":350}]'],
            'streaks.reward_days' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_5', 'أيّام الستريك المتواصلة للمكافأة'), 'number', '7'],
            'streaks.reward_tickets' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_6', 'تذاكر مكافأة السلسلة'), 'number', '1'],
            'streaks.freeze_cost_tickets' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_7', 'تكلفة تجميد الستريك (تذاكر)'), 'number', '1'],
            'streaks.max_freezes_per_month' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_8', 'أقصى تجميدات شهريًّا'), 'number', '2'],
            'streaks.freeze_max_age_days' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_9', 'أقصى قِدَم لليوم الفايت القابل للتجميد'), 'number', '2'],
            'streaks.celebration_tier' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_10', 'مستوى الاحتفال بالستريك'), 'number', '2'],
            'streaks.heatmap.months' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_11', 'عدد شهور الخريطة الحراريّة'), 'number', '3'],

            // نصوص الشاشة والرسائل — لا نصّ محروق في الكود (2.13)
            'streaks.club5am.xp_reason' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_12', 'سبب معاملة XP الحضور'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_13', 'حضور نادي الخامسة صباحًا')],
            'streaks.checkin.message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_14', 'رسالة تسجيل الحضور'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_15', 'اتسجّل ✓ — ستريكك دلوقتي :days يوم.')],
            'streaks.checkin.club_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_16', 'رسالة الحضور داخل النافذة'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_17', 'اتسجّل في نادي الخامسة ✓ — +:xp XP وستريكك :days يوم.')],
            'streaks.reward.reason' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_18', 'سبب معاملة تذكرة المكافأة'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_19', 'مكافأة سلسلة الحضور')],
            'streaks.reward.claimed_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_20', 'رسالة استلام المكافأة'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_21', 'مبروك — :tickets تذكرة هدية اتضافت لصندوقك 🎟️')],
            'streaks.reward.not_due_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_22', 'رسالة المكافأة غير المستحقّة'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_23', 'المكافأة مش متاحة دلوقتي — كمّل سلسلتك وهتلاقيها في انتظارك.')],
            'streaks.reward.cta' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_24', 'زرّ استلام المكافأة'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_25', 'استلم تذكرة المكافأة')],
            'streaks.freeze.reason' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_26', 'سبب معاملة درع التجميد'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_27', 'درع تجميد السلسلة')],
            'streaks.freeze.cta' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_28', 'زرّ شراء الدرع'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_29', 'اشترِ درع تجميد')],
            'streaks.freeze.done_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_30', 'رسالة نجاح التجميد'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_31', 'الدرع حمى يوم :day — سلسلتك كمّلت 🛡️')],
            'streaks.freeze.nothing_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_32', 'رسالة لا يوم يحتاج حماية'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_33', 'مفيش يوم فايت محتاج حماية دلوقتي — سلسلتك سليمة.')],
            'streaks.freeze.cap_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_34', 'رسالة سقف التجميد الشهريّ'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_35', 'وصلت أقصى :cap تجميدات الشهر ده — الشهر الجاي يبدأ رصيد جديد.')],
            'streaks.freeze.no_tickets_message' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_36', 'رسالة نقص التذاكر'), 'string', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_37', 'التذاكر مش كفاية للدرع — اكسب تذاكر من دروسك وارجع.')],
            'streaks.freeze.day_format' => ['gamification_streaks', setting('gamification_streaks.settings_catalog.streaks_and_leaderboard_38', 'صيغة عرض اليوم المحميّ'), 'string', 'j F'],

            'leaderboard.enabled' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_39', 'تفعيل الليدر بورد'), 'bool', '1'],
            'leaderboard.ranges' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_40', 'النطاقات المفعّلة (أيّام)'), 'json', '[7,30]'],
            'leaderboard.custom_range_enabled' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_41', 'السماح بفترة مخصّصة'), 'bool', '1'],
            'leaderboard.min_participants' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_42', 'حدّ أدنى للمشاركين لإظهار اللوحة'), 'number', '5'],
            'leaderboard.rows_per_load' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_43', 'عدد الصفوف لكلّ تحميل'), 'number', '20'],
            'leaderboard.recalc_hours' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_44', 'دوريّة إعادة الاحتساب (ساعة)'), 'number', '6'],
            'leaderboard.frozen' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_45', 'تجميد اللوحة مؤقّتًا'), 'bool', '0'],
            'leaderboard.hide_suspended' => ['gamification_leaderboard', setting('gamification_leaderboard.settings_catalog.streaks_and_leaderboard_46', 'إخفاء الموقوفين والمحذوفين'), 'bool', '1'],

            'badges.enabled' => ['gamification_badges', setting('gamification_badges.settings_catalog.streaks_and_leaderboard_47', 'تفعيل نظام الشارات'), 'bool', '1'],
            'badges.max_on_profile' => ['gamification_badges', setting('gamification_badges.settings_catalog.streaks_and_leaderboard_48', 'أقصى شارات ظاهرة على البروفايل'), 'number', '6'],
            'badges.celebration_tier' => ['gamification_badges', setting('gamification_badges.settings_catalog.streaks_and_leaderboard_49', 'مستوى الاحتفال عند المنح'), 'number', '2'],
            'badges.show_holders_count' => ['gamification_badges', setting('gamification_badges.settings_catalog.streaks_and_leaderboard_50', 'إظهار عدد الحاصلين للمستخدم'), 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- أسئلة المكافأة (12.10-أ)

    /**
     * بنك أسئلة المكافأة: روابط مؤقّتة بتايمر نازل تمنح مكافأة عند الإجابة.
     * والقواعد المقفولة (إجابة واحدة · تصحيح Server-side) تظهر هنا **معلَنةً**
     * لا لتُعدَّل بل ليعرف الأدمن أنّها مضمونة (12.10-أ).
     */
    private static function rewardQuestions(): array
    {
        return [
            'reward_questions.enabled' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_1', 'تفعيل أسئلة المكافأة'), 'bool', '1'],
            'reward_questions.default_minutes' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_2', 'مدّة التفعيل الافتراضيّة (دقيقة)'), 'number', '60'],
            'reward_questions.token_length' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_3', 'طول مفتاح الرابط'), 'number', '12'],
            'reward_questions.one_answer_per_user_locked' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_4', 'إجابة واحدة لكلّ مستخدم ومنع تكرار الصرف — مقفول'), 'bool', '1'],
            'reward_questions.server_side_locked' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_5', 'التصحيح في الخادم والإجابة لا تُرسَل للمتصفّح — مقفول'), 'bool', '1'],
            'reward_questions.autoschedule_enabled' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_6', 'جدولة الفتح التلقائيّ'), 'bool', '1'],
            'reward_questions.notify_on_open' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_7', 'إشعار/Toast بفتح سؤال جديد'), 'bool', '1'],
            'reward_questions.show_timer' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_8', 'إظهار التايمر فوق السؤال'), 'bool', '1'],
            'reward_questions.csv_import_enabled' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_9', 'السماح باستيراد CSV'), 'bool', '1'],
            'reward_questions.page_title' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_10', 'عنوان صفحة السؤال'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_11', 'سؤال المكافأة')],
            'reward_questions.page_intro' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_12', 'شرح صفحة السؤال'), 'text', setting('gamification_reward_questions.settings_catalog.reward_questions_13', 'جاوب صحّ قبل ما الوقت يخلص وتكسب مكافأتك فورًا.')],
            'reward_questions.closed_text' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_14', 'نصّ انتهاء الوقت'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_15', 'انتهى وقت الإجابة')],
            'reward_questions.correct_message' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_16', 'رسالة الإجابة الصحيحة'), 'text', setting('gamification_reward_questions.settings_catalog.reward_questions_17', 'إجابة صحيحة 🎉 — مكافأتك اتضافت لحسابك.')],
            'reward_questions.wrong_message' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_18', 'رسالة الإجابة الخاطئة (تشجّع ولا تعاتب)'), 'text', setting('gamification_reward_questions.settings_catalog.reward_questions_19', 'مش الإجابة الصحيحة المرّة دي — بس شكرًا إنك جاوبت بسرعة.')],
            'reward_questions.already_message' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_20', 'رسالة مَن جاوب قبل كده'), 'text', setting('gamification_reward_questions.settings_catalog.reward_questions_21', 'جاوبت على السؤال ده قبل كده — مكافأتك اتصرفت مرّة واحدة.')],
            'reward_questions.ledger_reason' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_22', 'وصف المعاملة في المحفظة'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_23', 'إجابة صحيحة على سؤال مكافأة')],
            'reward_questions.whatsapp_text' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_24', 'نصّ رسالة الواتساب'), 'text', setting('gamification_reward_questions.settings_catalog.reward_questions_25', 'سؤال المكافأة النهارده — جاوب قبل ما الوقت يخلص:')],
            'reward_questions.submit_label' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_26', 'زرّ الإرسال'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_27', 'أرسل إجابتي')],
            'reward_questions.empty_message' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_28', 'الحالة الفارغة'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_29', 'لا أسئلة مكافآت بعد.')],
            'reward_questions.labels.draft' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_30', 'وسم المسودّة'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_31', 'مسودّة')],
            'reward_questions.labels.scheduled' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_32', 'وسم المجدول'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_33', 'مجدول')],
            'reward_questions.labels.active' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_34', 'وسم النشط'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_35', 'نشط')],
            'reward_questions.labels.closed' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_36', 'وسم المغلق'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_37', 'مغلق')],
            'reward_questions.labels.archived' => ['gamification_reward_questions', setting('gamification_reward_questions.settings_catalog.reward_questions_38', 'وسم المؤرشف'), 'string', setting('gamification_reward_questions.settings_catalog.reward_questions_39', 'مؤرشف')],
        ];
    }

    // ---------------------------------------------------------------- الحروب (12.10-ج · 15.0)

    private static function wars(): array
    {
        return [
            'wars.shared.arena_ratio' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_1', 'نسبة أسئلة الساحة (%)'), 'number', '70'],
            'wars.shared.training_ratio' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_2', 'نسبة أسئلة التدريبات (%)'), 'number', '30'],
            'wars.shared.ready_tickets' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_3', 'شرط الاستعداد (تذاكر)'), 'number', '12'],
            'wars.shared.win' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_4', 'مكافأة الفوز'), 'number', '2'],
            'wars.shared.loss' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_5', 'خصم الخسارة'), 'number', '-2'],
            'wars.shared.withdraw' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_6', 'خصم الانسحاب'), 'number', '-10'],
            'wars.shared.loss_rule_count' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_7', 'قاعدة عدد الخسارات'), 'number', '3'],
            'wars.shared.decision_seconds' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_8', 'مؤقّت الحسم (ثانية)'), 'number', '20'],
            'wars.shared.question_seconds' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_9', 'وقت السؤال (ثانية)'), 'number', '15'],
            'wars.shared.focus_durations' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_10', 'مدد التركيز (دقيقة)'), 'json', '[5,15,25,50]'],
            'wars.shared.max_visible_fighters' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_11', 'أقصى محاربين ظاهرين'), 'number', '10'],
            'wars.shared.max_active_focus' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_12', 'أقصى تحديات تركيز نشطة'), 'number', '5'],
            'wars.shared.create_focus_tickets' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_13', 'تكلفة إنشاء حرب تركيز (تذاكر)'), 'number', '5'],
            'wars.shared.join_tickets' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_14', 'تكلفة الانضمام (تذاكر)'), 'number', '1'],
            'wars.shared.min_active_questions' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_15', 'حدّ أدنى للأسئلة المفعّلة قبل التشغيل'), 'number', '20'],
            // ⭐ قفل الإعدادات أثناء حرب نشطة (12.10-ج)
            'wars.lock_while_active' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_16', 'قفل الإعدادات أثناء حرب نشطة'), 'bool', '1'],
            'wars.lock_message' => ['gamification_wars', setting('gamification_wars.settings_catalog.wars_17', 'رسالة القفل'), 'string', setting('gamification_wars.settings_catalog.wars_18', 'تعذّر الحفظ — حرب نشطة الآن، حاول بعد انتهائها.')],
        ];
    }

    // ---------------------------------------------------------------- الاحتفالات (2.14)

    private static function celebrations(): array
    {
        return [
            'celebrations.enabled' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_1', 'تفعيل نظام الاحتفالات'), 'bool', '1'],
            // نفس مفاتيح خدمة الاحتفالات المشتركة — مصدر واحد لا نسختان (2.14-ب)
            'celebrations.peak.daily_cap' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_2', 'الحدّ اليوميّ لمستوى الذروة'), 'number', '3'],
            'celebrations.auto_dismiss_seconds' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_3', 'الانتهاء التلقائيّ (ثانية)'), 'number', '6'],
            // ⭐ الأنيميشن دائم بلا توجل — الصوت وحده له توجل (2.14-ب)
            'celebrations.animation_always_on' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_4', 'الأنيميشن حاضر دائمًا — مقفول'), 'bool', '1'],
            'celebrations.sound.enabled' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_5', 'تفعيل الصوت (يخضع لتوجل البروفايل)'), 'bool', '1'],
            'celebrations.tiers_locked' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_6', 'ثلاثة مستويات لا رابع — مقفول'), 'bool', '1'],
            'celebrations.once_per_event' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_7', 'مرّة واحدة لكلّ حدث (Server-side) — مقفول'), 'bool', '1'],
            'celebrations.share_button' => ['gamification_celebrations', setting('gamification_celebrations.settings_catalog.celebrations_8', 'زرّ المشاركة في مستوى الذروة'), 'bool', '1'],
        ];
    }

    // ---------------------------------------------------------------- إدارة المكافآت (12.9)

    private static function rewards(): array
    {
        return [
            // ⭐ الخصم ينزل تحت الصفر مسموح صراحةً (12.9)
            'rewards.allow_negative_balance' => ['rewards', setting('rewards.settings_catalog.rewards_1', 'السماح بالنزول تحت الصفر في الخصم — مقفول ON'), 'bool', '1'],
            'rewards.batch_size' => ['rewards', setting('rewards.settings_catalog.rewards_2', 'حجم دفعة المعالجة (كود)'), 'number', '500'],
            'rewards.max_codes' => ['rewards', setting('rewards.settings_catalog.rewards_3', 'أقصى عدد أكواد في العمليّة الواحدة'), 'number', '2000'],
            'rewards.notify_recipient' => ['rewards', setting('rewards.settings_catalog.rewards_4', 'إشعار المستلِم'), 'bool', '1'],
            'rewards.celebration_tier' => ['rewards', setting('rewards.settings_catalog.rewards_5', 'مستوى الاحتفال عند المنح'), 'number', '2'],
            'rewards.grant_message' => ['rewards', setting('rewards.settings_catalog.rewards_6', 'نصّ إشعار المنح'), 'string', setting('rewards.settings_catalog.rewards_7', 'وصلك رصيد جديد: {amount} {currency} — {reason}')],
            'rewards.deduct_message' => ['rewards', setting('rewards.settings_catalog.rewards_8', 'نصّ إشعار الخصم'), 'string', setting('rewards.settings_catalog.rewards_9', 'اتخصم من رصيدك {amount} {currency} — {reason}')],
            'rewards.card_enabled' => ['rewards', setting('rewards.settings_catalog.rewards_10', 'بطاقة التهنئة بعد المنح'), 'bool', '1'],
            'rewards.card_title' => ['rewards', setting('rewards.settings_catalog.rewards_11', 'عنوان بطاقة التهنئة'), 'string', setting('rewards.settings_catalog.rewards_12', 'مبروك يا {name} 🎉')],
            'rewards.currencies' => ['rewards', setting('rewards.settings_catalog.rewards_13', 'العملات المسموح منحها'), 'json', '["xp","coins","tickets"]'],
            'rewards.reasons' => ['rewards', setting('rewards.settings_catalog.rewards_14', 'قائمة أسباب التحويل'), 'json', setting('rewards.settings_catalog.rewards_15', '{"bonus":"بونص/ماينص","purchases":"مشتريات","transfers":"تحويلات","tech_fix":"تصحيح خطأ تقنيّ"}')],
            'rewards.reasons_requiring_reference' => ['rewards', setting('rewards.settings_catalog.rewards_16', 'الأسباب التي تُظهر حقل المرجع'), 'json', '["purchases","transfers","tech_fix"]'],
            'rewards.reasons_requiring_reference_strict' => ['rewards', setting('rewards.settings_catalog.rewards_17', 'الأسباب التي المرجع فيها إلزاميّ'), 'json', '["tech_fix"]'],
            'rewards.notes' => ['rewards', setting('rewards.settings_catalog.rewards_18', 'قائمة الملاحظات'), 'json', setting('rewards.settings_catalog.rewards_19', '{"rewards":"مكافآت","violations":"مخالفات","participation":"مشاركات","other":"أخرى"}')],
            'rewards.segments' => ['rewards', setting('rewards.settings_catalog.rewards_20', 'الشرائح الجاهزة للاستهداف'), 'json', setting('rewards.settings_catalog.rewards_21', '{"all_active":"كلّ الحسابات النشطة","volunteers":"المتطوّعون النشطون","zero_balance":"أصحاب الرصيد صفر"}')],
        ];
    }

    // ---------------------------------------------------------------- الفعاليّات (12.11 · 13.3)

    private static function events(): array
    {
        return [
            'events.default_view' => ['events', setting('events.settings_catalog.events_1', 'العرض الافتراضيّ'), 'string', 'table'],
            'events.default_tab' => ['events', setting('events.settings_catalog.events_2', 'التبويب الافتراضيّ'), 'string', 'upcoming'],
            'events.modes' => ['events', setting('events.settings_catalog.events_3', 'أنواع الفعاليّة'), 'json', setting('events.settings_catalog.events_4', '{"offline":"أوفلاين","online":"أونلاين","hybrid":"هجين"}')],
            'events.reminders' => ['events', setting('events.settings_catalog.events_5', 'مواعيد التذكير قبل الموعد (ساعة)'), 'json', '[24,1]'],
            'events.reminder_channels' => ['events', setting('events.settings_catalog.events_6', 'قنوات التذكير'), 'json', '["bell","toast"]'],
            'events.qr_refresh_seconds' => ['events', setting('events.settings_catalog.events_7', 'ثوانٍ تجديد QR التشيك-إن'), 'number', '30'],
            'events.online_link_minutes_before' => ['events', setting('events.settings_catalog.events_8', 'ظهور رابط الأونلاين قبل الموعد (دقيقة)'), 'number', '30'],
            'events.attendance_code_persistent' => ['events', setting('events.settings_catalog.events_9', 'كود الحضور مستمرّ لا يقفل — مقفول'), 'bool', '1'],
            'events.attendance.code_length' => ['events', setting('events.settings_catalog.events_10', 'طول كود الحضور'), 'number', '6'],
            'events.reward_tiers_default' => ['events', setting('events.settings_catalog.events_11', 'جدول المكافأة المتدرّجة الافتراضيّ'), 'json', '[{"hours":24,"xp":200,"tickets":1},{"hours":72,"xp":100,"tickets":0},{"hours":168,"xp":50,"tickets":0}]'],
            'events.close_registration_when_full' => ['events', setting('events.settings_catalog.events_12', 'إغلاق التسجيل عند الاكتمال (بلا قائمة انتظار)'), 'bool', '1'],
            'events.show_registrant_avatars' => ['events', setting('events.settings_catalog.events_13', 'أفاتارات المسجّلين (دليل اجتماعيّ)'), 'bool', '1'],
            'events.add_to_calendar' => ['events', setting('events.settings_catalog.events_14', 'زرّ «أضِف لتقويمي»'), 'bool', '1'],
            'events.friend_invite' => ['events', setting('events.settings_catalog.events_15', 'دعوة صديق'), 'bool', '1'],
            'events.reward_countdown' => ['events', setting('events.settings_catalog.events_16', 'عدّاد المكافأة النازل عند المستخدم'), 'bool', '1'],
            'events.empty_message' => ['events', setting('events.settings_catalog.events_17', 'رسالة الحالة الفارغة'), 'string', setting('events.settings_catalog.events_18', 'لا فعاليّات — أنشئ أوّل لقاء.')],
        ];
    }

    // ---------------------------------------------------------------- الإتاحة والتوقيت (5)

    /**
     * كلّ ما يحكم فتح/غلق التدريبات بتوقيت المستخدم — لا رقم منها في الكود (2.13).
     * وترويسات الدولة إعداد لأنّها تختلف باختلاف الاستضافة (Cloudflare · GAE · بروكسي).
     */
    private static function availability(): array
    {
        return [
            'availability.lookahead_days' => ['availability', setting('availability.settings_catalog.availability_1', 'أقصى أيّام البحث عن الفتحة القادمة'), 'number', '400'],
            'availability.detect.enabled' => ['availability', setting('availability.settings_catalog.availability_2', 'كشف المنطقة الزمنيّة تلقائيًّا'), 'bool', '1'],
            'availability.detect.client_field' => ['availability', setting('availability.settings_catalog.availability_3', 'اسم حقل تلميح المتصفّح'), 'string', 'timezone'],
            'availability.detect.country_headers' => ['availability', setting('availability.settings_catalog.availability_4', 'ترويسات دولة الزائر من الطبقة الأماميّة'), 'json', '["CF-IPCountry","X-AppEngine-Country","X-Geo-Country","X-Country-Code"]'],
            'availability.timezone.preferred' => ['availability', setting('availability.settings_catalog.availability_5', 'المناطق الزمنيّة المقترحة أوّلًا'), 'json', '["Africa/Cairo","Asia/Riyadh","Asia/Dubai","Asia/Amman","Africa/Khartoum","Africa/Casablanca","Europe/London"]'],
            'availability.timezone.saved_message' => ['availability', setting('availability.settings_catalog.availability_6', 'رسالة حفظ التوقيت'), 'string', setting('availability.settings_catalog.availability_7', 'اتحفظ ✓ — كلّ المواعيد دلوقتي بتوقيتك.')],
            'availability.admin.per_page' => ['availability', setting('availability.settings_catalog.availability_8', 'صفوف شاشة الإتاحة'), 'number', '20'],
            'availability.admin.max_periods' => ['availability', setting('availability.settings_catalog.availability_9', 'أقصى فترات إتاحة للتدريب الواحد'), 'number', '24'],

            // نصوص شريحة «توقيتك» عند المتدرّب
            'availability.timezone.chip_label' => ['availability', setting('availability.settings_catalog.availability_10', 'عنوان شريحة التوقيت'), 'string', setting('availability.settings_catalog.availability_11', 'توقيتك')],
            'availability.timezone.modal_title' => ['availability', setting('availability.settings_catalog.availability_12', 'عنوان بوب-أب التوقيت'), 'string', setting('availability.settings_catalog.availability_13', 'التوقيت المحلّيّ')],
            'availability.timezone.modal_hint' => ['availability', setting('availability.settings_catalog.availability_14', 'شرح بوب-أب التوقيت'), 'text', setting('availability.settings_catalog.availability_15', 'كلّ مواعيد التدريبات وفتح الكورسات بتتحسب بتوقيتك أنت. بنكتشفه تلقائيًّا حسب مكانك، وتقدر تظبطه بنفسك.')],
            'availability.timezone.field_label' => ['availability', setting('availability.settings_catalog.availability_16', 'عنوان حقل الاختيار'), 'string', setting('availability.settings_catalog.availability_17', 'اختر منطقتك الزمنيّة')],
            'availability.timezone.auto_option' => ['availability', setting('availability.settings_catalog.availability_18', 'خيار الكشف التلقائيّ'), 'string', setting('availability.settings_catalog.availability_19', 'تلقائيًّا حسب مكاني')],
            'availability.timezone.save_cta' => ['availability', setting('availability.settings_catalog.availability_20', 'زرّ حفظ التوقيت'), 'string', setting('availability.settings_catalog.availability_21', 'احفظ التوقيت')],
            'availability.timezone.source_manual' => ['availability', setting('availability.settings_catalog.availability_22', 'وصف المصدر: يدويّ'), 'string', setting('availability.settings_catalog.availability_23', 'التوقيت ده أنت اللي اخترته.')],
            'availability.timezone.source_auto' => ['availability', setting('availability.settings_catalog.availability_24', 'وصف المصدر: تلقائيّ'), 'string', setting('availability.settings_catalog.availability_25', 'اتكتشف تلقائيًّا حسب مكانك دلوقتي.')],
            'availability.timezone.source_country' => ['availability', setting('availability.settings_catalog.availability_26', 'وصف المصدر: دولتك'), 'string', setting('availability.settings_catalog.availability_27', 'مأخوذ من دولتك في ملفّك.')],
            'availability.timezone.source_platform' => ['availability', setting('availability.settings_catalog.availability_28', 'وصف المصدر: المنصّة'), 'string', setting('availability.settings_catalog.availability_29', 'توقيت المنصّة الافتراضيّ — ظبّطه عشان مواعيدك تبقى مضبوطة.')],
        ];
    }

    // ---------------------------------------------------------------- Kudos ونادي +9.5 (24.2 — التاب 4)

    private static function kudos(): array
    {
        return [
            'kudos.daily_limit' => ['kudos', setting('kudos.settings_catalog.kudos_1', 'حدّ Kudos اليوميّ'), 'number', '2'],
            'kudos.weekly_people_limit' => ['kudos', setting('kudos.settings_catalog.kudos_2', 'حدّ الأشخاص المختلفين أسبوعيًّا'), 'number', '7'],
            'kudos.vxp_value' => ['kudos', setting('kudos.settings_catalog.kudos_3', 'قيمة الشكر بالـVXP'), 'number', '20'],
            'kudos.search_limit' => ['kudos', setting('kudos.settings_catalog.kudos_4', 'عدد نتائج البحث عن زميل'), 'number', '8'],
            'kudos.reason.placeholder' => ['kudos', setting('kudos.settings_catalog.kudos_5', 'تلميح سبب الشكر'), 'string', setting('kudos.settings_catalog.kudos_5_v', 'احكِ الموقف نفسه — الحكاية هي اللي بتفضل.')],
            'kudos.reason_required.message' => ['kudos', setting('kudos.settings_catalog.kudos_6', 'رسالة السبب الإلزاميّ'), 'string', setting('kudos.settings_catalog.kudos_6_v', 'اكتب سبب الشكر — القصّة هي اللي بتفرق مش الرقم.')],
            'kudos.daily_limit.message' => ['kudos', setting('kudos.settings_catalog.kudos_7', 'رسالة بلوغ حدّ اليوم'), 'string', setting('kudos.settings_catalog.kudos_7_v', 'وصلت لحدّ اليوم — بكرة تقدر تشكر تاني.')],
            'kudos.weekly_limit.message' => ['kudos', setting('kudos.settings_catalog.kudos_8', 'رسالة بلوغ حدّ الأسبوع'), 'string', setting('kudos.settings_catalog.kudos_8_v', 'وصلت لحدّ الأسبوع — الأسبوع الجاي مفتوح.')],
            'kudos.duplicate.message' => ['kudos', setting('kudos.settings_catalog.kudos_9', 'رسالة تكرار نفس الشخص'), 'string', setting('kudos.settings_catalog.kudos_9_v', 'شكرت الشخص ده الأسبوع ده بالفعل — دوّر على حد تاني يستاهل.')],
            'kudos.self.message' => ['kudos', setting('kudos.settings_catalog.kudos_10', 'رسالة شكر النفس'), 'string', setting('kudos.settings_catalog.kudos_10_v', 'الشكر بيروح لغيرك — اختر زميلًا 🙂')],
            'kudos.screen.store_msg' => ['kudos', setting('kudos.settings_catalog.kudos_11', 'اسم حقل «الزميل» في رسائل التحقّق'), 'string', setting('kudos.settings_catalog.kudos_11_v', 'الزميل')],
            'kudos.screen.store_msg_2' => ['kudos', setting('kudos.settings_catalog.kudos_12', 'اسم حقل «سبب الشكر» في رسائل التحقّق'), 'string', setting('kudos.settings_catalog.kudos_12_v', 'سبب الشكر')],
            'kudos.screen.store_ok' => ['kudos', setting('kudos.settings_catalog.kudos_13', 'رسالة إرسال الشكر بنجاح'), 'string', setting('kudos.settings_catalog.kudos_13_v', 'اتبعت ✓ وصلت لزميلك.')],
            'kudos.screen.post_msg' => ['kudos', setting('kudos.settings_catalog.kudos_14', 'اسم حقل «النصّ» في رسائل التحقّق'), 'string', setting('kudos.settings_catalog.kudos_14_v', 'النصّ')],
            'kudos.screen.post_ok' => ['kudos', setting('kudos.settings_catalog.kudos_15', 'رسالة نشر البوست بنجاح'), 'string', setting('kudos.settings_catalog.kudos_15_v', 'اتنشر ✓')],
            // ⭐ حائط الشكر جزء من نفس التاب دستوريًّا (24.2 — التاب 4)
            'thanks_wall.approaching_gap' => ['kudos', setting('kudos.settings_catalog.kudos_16', 'فجوة بلوك «اقتربت» تحت عتبة نادي +9.5'), 'number', '1.5'],
        ];
    }

    // ---------------------------------------------------------------- التقييم — مؤشّر القيادة (24.2 — التاب 3)

    private static function evaluations(): array
    {
        // ⭐ المجموعة 'performance' — الوسم الفعليّ لهذه الصفوف في قاعدة البيانات
        // (VolunteerGoalsDemoSeeder) والمسجَّل شاشةً في SettingsRegistry بالفعل؛
        // وسمٌ جديد هنا كان يفصل الصفوف عن شاشتها المسجَّلة (2.13).
        return [
            'evaluations.min_raters' => ['performance', setting('evaluations.settings_catalog.evaluations_1', 'الحدّ الأدنى لعدد المقيّمين لإظهار المتوسّط'), 'number', '3'],
            'evaluations.window_weeks' => ['performance', setting('evaluations.settings_catalog.evaluations_2', 'نافذة عرض التقييمات (أسبوع)'), 'number', '12'],
            'evaluations.max_score' => ['performance', setting('evaluations.settings_catalog.evaluations_3', 'أقصى درجة للمنزلق'), 'number', '10'],
        ];
    }

    // ---------------------------------------------------------------- النوافذ والمهل (24.2 — التاب 8)

    /**
     * ⭐ المجموعة 'workflow' مزروعةٌ ومسجَّلة شاشةً في SettingsRegistry منذ
     * دورة العمل (23)، لكن بلا كتالوج هنا فبقيت بلا شاشة إدارة فعليّة — هذا
     * سطحها الحقيقيّ فقط، لا اختراع لبنودٍ لم تُبرمَج بعد.
     */
    private static function workflowWindows(): array
    {
        return [
            'workflow.escalation.window_hours' => ['workflow', setting('workflow.settings_catalog.workflow_1', 'نافذة القرار لكلّ مستوى (ساعة)'), 'number', '24'],
            'workflow.escalation.top_window_hours' => ['workflow', setting('workflow.settings_catalog.workflow_2', 'نافذة السقف (ساعة)'), 'number', '48'],
            'workflow.escalation.max_attempts' => ['workflow', setting('workflow.settings_catalog.workflow_3', 'محاولات معالجة الحالة قبل عزلها'), 'number', '3'],
            'workflow.activity_window.start' => ['workflow', setting('workflow.settings_catalog.workflow_4', 'بداية نافذة النشاط اليوميّة (القاهرة)'), 'string', '09:00'],
            'workflow.activity_window.end' => ['workflow', setting('workflow.settings_catalog.workflow_5', 'نهاية نافذة النشاط اليوميّة (القاهرة)'), 'string', '00:00'],
            'workflow.contribution.owner_review_hours' => ['workflow', setting('workflow.settings_catalog.workflow_6', 'مهلة مراجعة المالك للمساهم (ساعة)'), 'number', '24'],
            'workflow.checkpoint.response_hours' => ['workflow', setting('workflow.settings_catalog.workflow_7', 'مهلة ردّ نقطة التفتيش (ساعة)'), 'number', '2'],
            'workflow.blocked.max_days' => ['workflow', setting('workflow.settings_catalog.workflow_8', 'أقصى مدّة تعثّر (يوم)'), 'number', '3'],
            'workflow.vxp.parent_min_share_percent' => ['workflow', setting('workflow.settings_catalog.workflow_9', 'أدنى شريحة VXP محفوظة للأب (%)'), 'number', '10'],
            // ⭐ معامل جودة الإنجاز ⟵ VXP (24.2 التاب 2 · مبدأ الفصل §6): جدول ثلاثيّ
            // قابل للتحرير — والجودة الضعيفة تحجِّم VXP وحده، ولا تمسّ Rep إطلاقًا.
            'workflow.vxp.quality_tier_low' => ['workflow', setting('workflow.settings_catalog.workflow_10', 'معامل الجودة — المستوى الأدنى (%)'), 'number', '60'],
            'workflow.vxp.quality_tier_mid' => ['workflow', setting('workflow.settings_catalog.workflow_11', 'معامل الجودة — المستوى المتوسّط (%)'), 'number', '80'],
            'workflow.vxp.quality_tier_high' => ['workflow', setting('workflow.settings_catalog.workflow_12', 'معامل الجودة — المستوى الكامل (%)'), 'number', '100'],
        ];
    }
}
