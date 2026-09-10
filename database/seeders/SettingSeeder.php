<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * القاعدة الذهبيّة 2.13: لكلّ ميزة إعدادات كاملة في لوحة الإدارة (No Hard-coding).
 * نمط المفتاح: «المجال.الميزة.المفتاح» — ولكلّ إعداد قيمة افتراضيّة قابلة للاسترجاع (Reset).
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ---------------- الحساب والتفعيل (2.5-د)
            ['accounts.activation.is_free', 'accounts', 'تفعيل الحساب مجّانيّ', 'bool', '1'],
            ['accounts.activation.requires_admin_approval', 'accounts', 'التفعيل باعتماد إداريّ', 'bool', '1'],
            ['accounts.code.prefix', 'accounts', 'بادئة كود المستخدم', 'string', 'U'],

            // ---------------- البساطة أوّلًا (2.15)
            ['ux.simple_mode.default_on', 'ux', 'الوضع المبسّط مفعَّل افتراضيًّا', 'bool', '1'],
            ['ux.kpi.max_cards', 'ux', 'أقصى عدد كروت KPI', 'number', '4'],
            ['ux.filters.max_visible', 'ux', 'أقصى فلاتر ظاهرة', 'number', '3'],
            ['ux.tables.default_columns', 'ux', 'عدد الأعمدة الافتراضيّة', 'number', '6'],
            ['ux.forms.max_fields_before_stepper', 'ux', 'حدّ حقول الفورم قبل التقسيم لخطوات', 'number', '7'],
            ['ux.lists.default_range_days', 'ux', 'المدى الزمنيّ الافتراضيّ للقوائم (أيّام)', 'number', '30'],
            // ⭐ زرّ التمرير التدريجيّ العامّ (13.1 · قرار §25 — ⛔ ممنوع ترقيم الصفحات)
            ['ux.lists.load_more', 'ux', 'نصّ زرّ «عرض المزيد» في التمرير التدريجيّ', 'string', 'عرض المزيد'],
            ['ux.undo.seconds', 'ux', 'مدّة التراجع (ثوانٍ)', 'number', '5'],
            ['ux.first_time.enabled_screens', 'ux', 'شاشات «أوّل مرّة» المفعَّلة', 'json', '[]'],
            ['ux.settings_search.max_results', 'ux', 'أقصى نتائج البحث الموحّد في الإعدادات', 'number', '40'],
            // ⭐ حجم دفعة التحميل الكسول في شاشة الإعدادات (2.15-ب) — لا رقم محروق
            ['ux.settings_batch_size', 'ux', 'عدد مفاتيح الدفعة الواحدة في شاشة الإعدادات', 'number', '25'],

            // ---------------- حدود شاشات كانت أرقامًا محروقة (2.13)
            ['admin_dashboard.online_window_minutes', 'admin_dashboard', 'نافذة «النشطون الآن» (دقيقة)', 'number', '15'],
            ['stats.top_list_size', 'stats', 'عدد صفوف قوائم «الأعلى»', 'number', '8'],
            ['maintenance.windows_history_limit', 'maintenance', 'عدد نوافذ الصيانة الظاهرة في السجلّ', 'number', '10'],

            // ---------------- قاموس الحالة (2.16): لكلّ لون رمزٌ وتسمية — ولا لون بلا رمز
            ['ux.state.ok.icon', 'ux', 'رمز حالة «سليم»', 'string', '●'],
            ['ux.state.ok.label', 'ux', 'تسمية حالة «سليم»', 'string', 'سليم'],
            ['ux.state.warn.icon', 'ux', 'رمز حالة «انتبه»', 'string', '▲'],
            ['ux.state.warn.label', 'ux', 'تسمية حالة «انتبه»', 'string', 'انتبه'],
            ['ux.state.danger.icon', 'ux', 'رمز حالة «خطر»', 'string', '◉'],
            ['ux.state.danger.label', 'ux', 'تسمية حالة «خطر»', 'string', 'خطر'],
            ['ux.state.honor.icon', 'ux', 'رمز حالة «تميّز»', 'string', '★'],
            ['ux.state.honor.label', 'ux', 'تسمية حالة «تميّز»', 'string', 'تميّز'],
            ['ux.state.idle.icon', 'ux', 'رمز حالة «غير نشط»', 'string', '○'],
            ['ux.state.idle.label', 'ux', 'تسمية حالة «غير نشط»', 'string', 'غير نشط'],

            // ---------------- رادار الإنجازات (10.1): مصدر واحد للبروفايل واللوحة معًا
            ['dashboard.achievements.tracks', 'dashboard', 'مسارات الإنجاز وترتيبها', 'json', '["account","club_5am","referrals","tickets","learning"]'],
            ['dashboard.achievements.radar_max_level', 'dashboard', 'أقصى مستوى يرسمه الرادار', 'number', '6'],
            ['dashboard.achievements.account.label', 'dashboard', 'عنوان مسار مستوى الحساب', 'string', 'مستوى الحساب'],
            ['dashboard.achievements.account.unit', 'dashboard', 'وحدة مسار مستوى الحساب', 'string', 'XP'],
            ['dashboard.achievements.account.base', 'dashboard', 'عتبة مستوى الحساب — الأساس', 'number', '500'],
            ['dashboard.achievements.account.step', 'dashboard', 'عتبة مستوى الحساب — الزيادة', 'number', '250'],
            ['dashboard.achievements.club_5am.label', 'dashboard', 'عنوان مسار نادي الخامسة', 'string', 'نادي الخامسة صباحًا'],
            ['dashboard.achievements.club_5am.unit', 'dashboard', 'وحدة مسار نادي الخامسة', 'string', 'يوم'],
            ['dashboard.achievements.club_5am.base', 'dashboard', 'عتبة نادي الخامسة — الأساس', 'number', '3'],
            ['dashboard.achievements.club_5am.step', 'dashboard', 'عتبة نادي الخامسة — الزيادة', 'number', '2'],
            ['dashboard.achievements.referrals.label', 'dashboard', 'عنوان مسار الدعوات', 'string', 'دعوة الأصدقاء'],
            ['dashboard.achievements.referrals.unit', 'dashboard', 'وحدة مسار الدعوات', 'string', 'دعوة'],
            ['dashboard.achievements.referrals.base', 'dashboard', 'عتبة الدعوات — الأساس', 'number', '5'],
            ['dashboard.achievements.referrals.step', 'dashboard', 'عتبة الدعوات — الزيادة', 'number', '2'],
            // «التذاكر — إجمالي التذاكر **المكتسبة**» (10): والاسم يقولها فلا يُقرَأ رصيدًا
            ['dashboard.achievements.tickets.label', 'dashboard', 'عنوان مسار التذاكر', 'string', 'التذاكر المكتسبة'],
            ['dashboard.achievements.tickets.unit', 'dashboard', 'وحدة مسار التذاكر', 'string', 'تذكرة'],
            ['dashboard.achievements.tickets.base', 'dashboard', 'عتبة التذاكر — الأساس', 'number', '15'],
            ['dashboard.achievements.tickets.step', 'dashboard', 'عتبة التذاكر — الزيادة', 'number', '10'],
            ['dashboard.achievements.learning.label', 'dashboard', 'عنوان مسار استمراريّة التعلّم', 'string', 'استمراريّة التعلّم'],
            ['dashboard.achievements.learning.unit', 'dashboard', 'وحدة مسار استمراريّة التعلّم', 'string', 'درس'],
            ['dashboard.achievements.learning.base', 'dashboard', 'عتبة استمراريّة التعلّم — الأساس', 'number', '5'],
            ['dashboard.achievements.learning.step', 'dashboard', 'عتبة استمراريّة التعلّم — الزيادة', 'number', '3'],

            // ---------------- تاب المعاملات: عمود «من ← إلى» وعمود «ملاحظات» (19.2)
            ['wallet.flow.self_label', 'wallet', 'تسمية طرف صاحب المحفظة', 'string', 'محفظتي'],
            ['wallet.flow.platform_label', 'wallet', 'تسمية الطرف الآخر الافتراضيّ', 'string', 'المنصّة'],
            ['wallet.flow.transfer_prefixes', 'wallet', 'بادئات نصّ الحوالة لاستخراج الطرف الآخر', 'json', '["حوالة إلى","حوالة من"]'],
            ['wallet.notes.capped', 'wallet', 'ملاحظة تجاوز الحدّ اليوميّ', 'string', 'تعدّت الحدّ اليوميّ — اتطبّق منها المسموح.'],
            ['wallet.notes.correction', 'wallet', 'ملاحظة حركة التصحيح', 'string', 'حركة تصحيح موثّقة.'],
            // ⭐ «لا خصم آليّ على VXP إطلاقًا» (13.4-ن · 23-القسم 5) — والقفل معلَن:
            // خصمُ العملة التراكميّة بتوقيع صاحبها نفسه لا يمرّ إلّا من هذه المصادر،
            // وهي التي نصّ الدستور على أنّ صاحبها يصرف فيها من جيبه بموافقته الصريحة.
            ['wallet.cumulative.self_spend_sources', 'wallet', 'مصادر الصرف الذاتيّ من العملة التراكميّة (سطر لكلّ مصدر)', 'lines', "contribution.hold\ntask"],

            // ---------------- كروت داشبورد المستخدم الستّة (14-أ) — والحدّ يقصّ لا يحذف
            // ⭐ ثلاثة أرقامٍ للتذاكر على لوحةٍ واحدة معناها ثلاثة **مقادير** لا تناقض
            // (رصيد 10.0-أ · مكتسب 10 · حركة المدى 24.5) — والتسميات تفرّقها صراحةً
            ['dashboard.kpi.level_label', 'dashboard', 'عنوان كارت مستوى الحساب', 'string', 'مستوى الحساب و XP'],
            ['dashboard.kpi.tickets_label', 'dashboard', 'عنوان كارت التذاكر', 'string', 'رصيد التذاكر'],
            ['dashboard.kpi.tickets_hint', 'dashboard', 'شرح كارت التذاكر', 'string', 'رصيدك المتاح للصرف'],
            ['dashboard.level.prefix', 'dashboard', 'بادئة رقم مستوى الحساب', 'string', 'المستوى'],
            ['dashboard.tickets.sheet_label', 'dashboard', 'تسمية ميزان التذاكر', 'string', 'الميزان: مكتسب :earned − مصروف :spent = رصيد :balance'],
            ['dashboard.kpi.courses_label', 'dashboard', 'عنوان كارت التدريبات', 'string', 'التدريبات (مكتملة / جارية)'],
            ['dashboard.kpi.courses_hint', 'dashboard', 'شرح كارت التدريبات', 'string', 'إجماليّ تدريباتك: :total'],
            ['dashboard.kpi.rank_label', 'dashboard', 'عنوان كارت ترتيب الليدر بورد', 'string', 'ترتيب الليدر بورد'],
            ['dashboard.kpi.rank_hint', 'dashboard', 'شرح كارت ترتيب الليدر بورد', 'string', 'من بين :peers متدرّبًا'],

            // ---------------- الاحتفالات (2.14)
            ['celebrations.peak.daily_cap', 'gamification_celebrations', 'الحدّ اليوميّ لاحتفالات الذروة', 'number', '3'],
            ['celebrations.sound.enabled', 'gamification_celebrations', 'تفعيل الصوت (والأنيميشن دائم)', 'bool', '1'],

            /*
             | 🎉 الكونفيتي — عدده ومدّته وشدّته بيد المالك لا محروقة في القالب (2.13).
             | «كونفيتي بينزل من فوق لتحت (Confetti Rain) لحظة الإكمال» (4.1)،
             | و«كونفيتي خفيف» للمستوى 2 و«كونفيتي غزير» للمستوى 3 (2.14-أ).
             */
            ['celebrations.confetti.rain_pieces', 'gamification_celebrations', 'عدد قطع كونفيتي إنهاء الدرس (4.1)', 'number', '48'],
            ['celebrations.confetti.light_pieces', 'gamification_celebrations', 'عدد قطع الكونفيتي الخفيف (المستوى 2)', 'number', '20'],
            ['celebrations.confetti.fall_ms', 'gamification_celebrations', 'زمن نزول قطعة الكونفيتي (ملّي ثانية)', 'number', '2800'],
            ['celebrations.confetti.stagger_ms', 'gamification_celebrations', 'تباعد إطلاق قطع الكونفيتي (ملّي ثانية)', 'number', '60'],
            ['celebrations.confetti.piece_width_px', 'gamification_celebrations', 'عرض قطعة الكونفيتي (بكسل)', 'number', '8'],
            ['celebrations.confetti.piece_height_px', 'gamification_celebrations', 'ارتفاع قطعة الكونفيتي (بكسل)', 'number', '14'],

            // ---------------- طبقة الإحساس (2.17)
            ['feel.counter.animate_numbers', 'feel', 'عدّاد تصاعديّ للأرقام', 'bool', '1'],
            ['feel.haptics.enabled', 'feel', 'اهتزاز خفيف على الموبايل', 'bool', '1'],
            ['feel.signature_sound.path', 'feel', 'صوت التوقيع', 'media', ''],

            // ---------------- درجة الالتزام (13.4-ن)
            ['rep.reset.day_of_month', 'volunteer_rep', 'يوم التصفير الشهريّ', 'number', '1'],
            ['rep.reset.hour', 'volunteer_rep', 'ساعة التصفير بتوقيت القاهرة', 'number', '5'],
            ['rep.behavior.monthly_cap_per_granter', 'volunteer_rep', 'سقف معاملات السلوك شهريًّا للمانح', 'number', '5'],
            ['rep.objection.window_days', 'volunteer_rep', 'مهلة الاعتراض (أيّام)', 'number', '5'],

            // ---------------- دورة العمل (23)
            ['workflow.escalation.window_hours', 'workflow', 'نافذة القرار لكلّ مستوى (ساعات)', 'number', '24'],
            ['workflow.escalation.top_window_hours', 'workflow', 'نافذة السقف (ساعات)', 'number', '48'],
            ['workflow.escalation.max_attempts', 'workflow', 'محاولات معالجة الحالة قبل عزلها', 'number', '3'],
            ['workflow.activity_window.start', 'workflow', 'بداية نافذة النشاط', 'string', '09:00'],
            ['workflow.activity_window.end', 'workflow', 'نهاية نافذة النشاط', 'string', '00:00'],
            ['workflow.contribution.owner_review_hours', 'workflow', 'مهلة مراجعة المالك للمساهم (ساعات)', 'number', '24'],
            ['workflow.checkpoint.response_hours', 'workflow', 'مهلة الردّ على نقطة تفتيش (ساعات)', 'number', '2'],
            ['workflow.blocked.max_days', 'workflow', 'أقصى مدّة تعثّر (أيّام)', 'number', '3'],
            ['workflow.vxp.parent_min_share_percent', 'workflow', 'أدنى شريحة محفوظة للأب (%)', 'number', '10'],

            // ---------------- الأوفبوردنج والعودة (13.4-س · 13.4-ق)
            ['rep.inactivity.days_before_alert', 'volunteer_rep', 'أيّام الخمول قبل التنبيه', 'number', '21'],

            // ---------------- شهادات التطوّع والبطاقة (13.4-ع · 13.4-ر)
            ['volunteer_cert.min_days_in_position', 'volunteer_cert', 'أدنى مدّة في البوزشن (أيّام)', 'number', '30'],
            // عناوين أنواع الشهادات (CertificateEligibility::types — 2.13-ب)
            ['volunteer_cert.type.volunteer_position', 'volunteer_cert', 'نوع شهادة: بوزشن', 'string', 'شهادة بوزشن'],
            ['volunteer_cert.type.volunteer_experience', 'volunteer_cert', 'نوع شهادة: خبرة تطوّع', 'string', 'شهادة خبرة تطوّع'],
            ['volunteer_cert.type.volunteer_case_file', 'volunteer_cert', 'نوع شهادة: مشاركة في ملفّ', 'string', 'شهادة مشاركة في ملفّ'],
            ['volunteer_cert.type.volunteer_appreciation', 'volunteer_cert', 'نوع شهادة: تقدير استثنائيّة', 'string', 'شهادة تقدير استثنائيّة'],
            ['volunteer_card.enabled', 'volunteer', 'تفعيل بطاقة المتطوّع الرقميّة', 'bool', '1'],
            ['volunteer_card.show_rep', 'volunteer', 'إظهار Rep على البطاقة العامّة', 'bool', '0'],
            ['volunteer.honorary.enabled', 'volunteer', 'إظهار العنصر الشرفيّ «أخوكم»', 'bool', '1'],
            ['volunteer.honorary.label_ar', 'volunteer', 'وصف العنصر الشرفيّ', 'string', 'أخوكم'],
            // عناوين أماكن الظهور وأشكال الإطار (HonoraryElement::placeLabels/frameLabels — 2.13-ب)
            ['volunteer.honorary.place.canvas', 'volunteer', 'مكان ظهور: الهيكل التنظيميّ (الكانفاس)', 'string', 'الهيكل التنظيميّ (الكانفاس)'],
            ['volunteer.honorary.place.members', 'volunteer', 'مكان ظهور: صفحة الأعضاء والبوزشنز', 'string', 'صفحة الأعضاء والبوزشنز'],
            ['volunteer.honorary.place.landing', 'volunteer', 'مكان ظهور: صفحة التطوّع التعريفيّة', 'string', 'صفحة التطوّع التعريفيّة'],
            ['volunteer.honorary.frame.soft', 'volunteer', 'شكل إطار: هادئ', 'string', 'إطار هادئ'],
            ['volunteer.honorary.frame.gold', 'volunteer', 'شكل إطار: ذهبيّ', 'string', 'إطار ذهبيّ'],
            ['volunteer.honorary.frame.dashed', 'volunteer', 'شكل إطار: متقطّع', 'string', 'إطار متقطّع'],
            ['volunteer.honorary.frame.none', 'volunteer', 'شكل إطار: بلا إطار', 'string', 'بلا إطار'],

            // ---------------- التقدير (13.4-ي)
            ['kudos.daily_limit', 'kudos', 'حدّ Kudos اليوميّ', 'number', '2'],
            ['kudos.weekly_people_limit', 'kudos', 'حدّ الأشخاص أسبوعيًّا', 'number', '7'],
            ['kudos.vxp_value', 'kudos', 'قيمة Kudos بالـVXP', 'number', '20'],

            // ---------------- المتجر: التسعير متعدّد العملات (17) والبندل (18)
            ['store.currency.default', 'store', 'عملة المتجر الافتراضيّة', 'string', 'coins'],
            ['store.currencies', 'store', 'العملات المقبولة في تسعير المنتجات', 'json', '["coins","tickets","xp"]'],
            ['store.currency.labels', 'store', 'اسم كلّ عملة بجوار الرقم', 'json', '{"coins":"كوين","tickets":"تذكرة","xp":"XP"}'],
            ['store.buy.label', 'store', 'نصّ زرّ الشراء الافتراضيّ', 'string', 'إتمام الشراء'],
            ['store.buy.labels', 'store', 'نصّ زرّ الشراء لكلّ عملة', 'json', '{"coins":"شراء بالكوينز","tickets":"شراء بالتذاكر","xp":"شراء بالـXP"}'],
            ['store.filters.currency_label', 'store', 'عنوان فلتر نوع العملة', 'string', 'نوع العملة'],
            ['store.filters.price_max', 'store', 'سقف منزلق السعر لكلّ عملة', 'json', '{"coins":100000,"tickets":1000,"xp":300000}'],
            ['store.cart.currency_mismatch_text', 'store', 'رسالة اختلاف عملة السلّة', 'string', 'سلّتك دلوقتي بالـ{cart} والعنصر ده بالـ{item} — كمّل طلبك الأوّل وابدأ سلّة جديدة بيه.'],
            ['store.includes.title', 'store', 'عنوان سكشن «ما يشمله»', 'string', 'ما يشمله'],
            ['store.bundle.bonus_text', 'store', 'صياغة البونص في صفحة الباقة (18)', 'string', '🎁 بونص: {item} بقيمة {amount} — مجّانًا مع الباقة'],
            ['store.bundle.total_value_label', 'store', 'تسمية القيمة الإجماليّة للباقة', 'string', 'القيمة الإجماليّة'],

            // ---------------- المتجر والاسترجاع (19.4 · 19.5)
            ['store.refund.policy_text', 'store', 'نصّ سياسة الاسترجاع (HTML أو نصّ)', 'text', 'لا يوجد استرجاع نقديّ للمدفوعات، ويبقى رصيدك في محفظتك تشتري به ما تشاء من الموقع.'],
            ['topup.default_tab', 'store', 'التاب الافتراضيّ للشحن', 'string', 'manual'],
            ['topup.manual.enabled', 'store', 'تفعيل التحويل اليدويّ', 'bool', '1'],
            ['topup.gateway.enabled', 'store', 'تفعيل بوّابة الدفع', 'bool', '1'],
            ['topup.gateway.provider', 'store', 'مزوّد البوّابة', 'string', 'fawaterk'],
            ['topup.gateway.sandbox', 'store', 'وضع الاختبار (Sandbox)', 'bool', '1'],
            ['topup.gateway.api_key', 'store', 'مفتاح API', 'string', ''],
            ['topup.gateway.vendor_key', 'store', 'مفتاح التاجر (للتحقّق من الهاش)', 'string', ''],
            ['topup.receipt.max_size_kb', 'store', 'أقصى حجم للإيصال (KB)', 'number', '4096'],

            // ---------------- الريفيرال وحلقات النموّ (7.6 · 21.1)
            ['referral.commission_percent', 'growth', 'نسبة عمولة الإحالة (%)', 'number', '7'],
            ['referral.welcome_tickets', 'growth', 'تذاكر ترحيب للمدعوّ', 'number', '1'],
            // ⭐ 7.6: «كلٌ من الداعي والمدعو» — فللداعي تذكرته كذلك
            ['referral.referrer_tickets', 'growth', 'تذاكر الداعي عن الدعوة الناجحة', 'number', '1'],
            // ⭐ مكافأة السفراء المفاجئة المتغيّرة (7.6.1) — احتمال حقيقيّ لا موجَّه (2.9)
            ['referral.variable_reward.chance_percent', 'growth', 'احتمال المكافأة المفاجئة (%)', 'number', '20'],
            ['referral.variable_reward.tickets', 'growth', 'قيمة المكافأة المفاجئة (تذاكر)', 'number', '3'],
            ['growth.profile_completion.reward_tickets', 'growth', 'مكافأة إكمال الملفّ (تذاكر)', 'number', '3'],
            ['growth.seo.index_certificates', 'growth', 'فهرسة صفحات الشهادات', 'bool', '1'],
            ['growth.seo.index_courses', 'growth', 'فهرسة صفحات التدريبات', 'bool', '1'],
            ['growth.linkedin.share_text', 'growth', 'نصّ منشور لينكدإن', 'text', 'أتممتُ [المسار] وحصلتُ على شهادة معتمدة.'],

            // ---------------- الإعلان المدفوع (21.3)
            ['ads.tracking.enabled', 'ads', 'تفعيل التتبّع كلّيًّا', 'bool', '0'],
            ['ads.pixel.meta_id', 'ads', 'معرّف بكسل Meta', 'string', ''],
            ['ads.pixel.google_id', 'ads', 'معرّف Google', 'string', ''],
            ['ads.capi.token', 'ads', 'توكن أحداث الخادم', 'string', ''],
            ['ads.consent.banner_text', 'ads', 'نصّ بانر الموافقة', 'text', 'نستخدم ملفّات تعريف الارتباط لتحسين تجربتك. تقدر تقبل أو ترفض.'],
            ['ads.best_user.rule', 'ads', 'تعريف «أفضل مستخدم»', 'json', '{"completed_course":true,"purchased":true,"returned":true}'],

            // ---------------- استوديو الصور (12.14)
            ['images.rate_limit_per_minute', 'images', 'حدّ التوليد في الدقيقة', 'number', '30'],
            ['images.cache_days', 'images', 'مدّة الكاش (أيّام)', 'number', '30'],
            ['images.watermark.show_logo', 'images', 'إظهار الشعار على الصور المستخرَجة', 'bool', '1'],
            ['images.watermark.show_date', 'images', 'إظهار تاريخ اللقطة', 'bool', '1'],

            // ---------------- النظام والصيانة (12.7)
            ['system.maintenance.enabled', 'maintenance', 'وضع الصيانة العامّ', 'bool', '0'],
            ['system.maintenance.message', 'maintenance', 'رسالة الصيانة', 'text', 'بنطوّر حاجة حلوة — هنرجع قريب.'],
            ['system.maintenance.freeze_deadlines', 'maintenance', 'تجميد كلّ المهل أثناء الصيانة', 'bool', '1'],
            ['system.timezone', 'system', 'المنطقة الزمنيّة', 'string', 'Africa/Cairo'],

            // ---------------- وضع «متقدّم» (2.15-أ-9 · 2.15-هـ)
            // السويتش الحاضر في كلّ صفحة، وتفعيله لكلّ دور — بلا رقم محروق.
            ['ux.advanced_mode.enabled', 'ux', 'تفعيل «وضع متقدّم» في الصفحات', 'bool', '1'],
            ['ux.advanced_mode.roles', 'ux', 'الأدوار التي يظهر لها «وضع متقدّم» (فاضي = الكلّ)', 'json', '[]'],
            ['ux.advanced_mode.label', 'ux', 'تسمية سويتش الوضع المتقدّم', 'string', 'وضع متقدّم'],

            // ---------------- تقدّم مُهدى (2.9-2)
            // المؤشّر يبدأ من نقطة **مُنجَزة** لا من صفر — والرصيد معلَن لا مخفيّ.
            ['growth.profile_completion.endowed_percent', 'growth', 'بداية مؤشّر اكتمال الملفّ (%) — تقدّم مُهدى', 'number', '20'],

            // ---------------- الدليل الاجتماعيّ الحيّ (2.9-7) والمقارنة القريبة (2.9-5)
            // الحدود المعتمَدة نصًّا: الدرس 20 · نادي الخامسة 10 · الحروب 3 · النسبة 20.
            // فوق الحدّ نعرض الرقم الحقيقيّ، وتحته نؤطّر بالريادة — بلا أرقام وهميّة.
            ['engagement.social_proof.enabled', 'engagement', 'تفعيل الدليل الاجتماعيّ الحيّ', 'bool', '1'],
            ['engagement.social_proof.lesson.min', 'engagement', 'حدّ عدّاد «أنهى الدرس اليوم»', 'number', '20'],
            ['engagement.social_proof.lesson.count_text', 'engagement', 'نصّ عدّاد الدرس', 'string', 'أنهى :count النهارده'],
            ['engagement.social_proof.lesson.lead_text', 'engagement', 'تأطير الريادة تحت حدّ الدرس', 'string', 'كن أوّل من ينهي الدرس ده النهارده!'],
            ['engagement.social_proof.club_5am.min', 'engagement', 'حدّ عدّاد نادي الخامسة', 'number', '10'],
            ['engagement.social_proof.club_5am.count_text', 'engagement', 'نصّ عدّاد نادي الخامسة', 'string', ':count صحيوا معاك'],
            ['engagement.social_proof.club_5am.lead_text', 'engagement', 'تأطير الريادة تحت حدّ النادي', 'string', 'كن من أوائل الصاحيين النهارده!'],
            ['engagement.social_proof.war.min', 'engagement', 'حدّ عدّاد المحاربين الجاهزين', 'number', '3'],
            ['engagement.social_proof.war.count_text', 'engagement', 'نصّ عدّاد الحروب', 'string', ':count جاهزين دلوقتي'],
            ['engagement.social_proof.war.lead_text', 'engagement', 'تأطير الريادة تحت حدّ الحروب', 'string', 'كن أوّل محارب في الساحة'],
            ['engagement.social_proof.leaderboard.min', 'engagement', 'حدّ نسبة «أفضل من X%» في نطاق المقارنة', 'number', '20'],
            ['engagement.social_proof.better_than_text', 'engagement', 'نصّ «أفضل من X%»', 'string', 'أنت أفضل من :percent% من المتدرّبين في النطاق ده'],
            ['engagement.social_proof.gap_text', 'engagement', 'نصّ «محتاج N XP تتخطّى»', 'string', 'محتاج :gap XP تتخطّى :rival'],

            // ---------------- الشكاوى والسيرة والإفادة والبروفايل (9 · 9.1 · 10 · 11)
            // ⭐ نصوصٌ كانت **محروقة** في الواجهات والخدمات — والمالك يملك تعديلها
            // كلّها من اللوحة الآن (2.13)، وهي مزروعة في **مسار الإنتاج** لا في سيدر عرض.
            ['complaints.reasons', 'complaints', 'أسباب الشكاوى والمقترحات', 'json', '["أحد المشرفين","الهيكل الإداريّ وأسلوب الإدارة","اللقاءات المباشرة","اللوائح والقوانين","المحتوى التدريبيّ","خدمة العملاء","المنصّة","أخرى"]'],
            ['complaints.status.open_label', 'complaints', 'تسمية حالة «مفتوحة»', 'string', 'مفتوحة'],
            ['complaints.status.in_review_label', 'complaints', 'تسمية حالة «قيد المراجعة»', 'string', 'قيد المراجعة'],
            ['complaints.status.answered_label', 'complaints', 'تسمية حالة «تمّ الردّ»', 'string', 'تمّ الردّ'],
            ['complaints.status.closed_label', 'complaints', 'تسمية حالة «مغلقة»', 'string', 'مغلقة'],
            ['complaints.type.complaint_label', 'complaints', 'تسمية النوع «شكوى»', 'string', 'شكوى'],
            ['complaints.type.suggestion_label', 'complaints', 'تسمية النوع «مقترح»', 'string', 'مقترح'],
            ['complaints.field.wants_contact_label', 'complaints', 'سؤال الرغبة في التواصل', 'string', 'هل ترغب في التواصل معك؟'],
            ['complaints.field.wants_contact_yes', 'complaints', 'خيار «نعم»', 'string', 'نعم'],
            ['complaints.field.wants_contact_no', 'complaints', 'خيار «لا»', 'string', 'لا'],
            ['complaints.field.reason_label', 'complaints', 'تسمية حقل السبب', 'string', 'السبب'],
            ['complaints.field.reason_placeholder', 'complaints', 'نصّ اختيار السبب', 'string', 'اختر السبب'],
            ['complaints.field.type_label', 'complaints', 'تسمية حقل النوع', 'string', 'النوع'],
            ['complaints.field.title_label', 'complaints', 'تسمية العنوان المختصر', 'string', 'العنوان المختصر'],
            ['complaints.field.title_placeholder', 'complaints', 'مثال العنوان المختصر', 'string', 'مثال: اقتراح تحسين المنصّة'],
            ['complaints.field.body_label', 'complaints', 'تسمية نصّ الرسالة', 'string', 'نصّ الشكوى أو المقترح'],
            ['complaints.field.body_placeholder', 'complaints', 'نصّ إرشاديّ للرسالة', 'string', 'اكتب رسالتك هنا…'],
            ['complaints.field.attachment_label', 'complaints', 'تسمية المرفق', 'string', 'مرفق (اختياريّ)'],
            ['complaints.field.attachment_hint', 'complaints', 'سطر حدّ حجم المرفق', 'string', 'أقصى حجم :n كيلوبايت.'],
            ['complaints.field.submit_label', 'complaints', 'زرّ الإرسال', 'string', 'إرسال'],
            ['complaints.field.reply_label', 'complaints', 'تسمية حقل الردّ', 'string', 'الردّ'],
            ['complaints.page.title', 'complaints', 'عنوان صفحة الشكاوى', 'string', 'الشكاوى والمقترحات'],
            ['complaints.page.subtitle', 'complaints', 'سطر صفحة الشكاوى', 'text', 'اكتب لنا، وهنتابع معاك لحدّ ما تتحلّ.'],
            ['complaints.page.section_label', 'complaints', 'اسم القسم في مسار التنقّل', 'string', 'الدعم'],
            ['complaints.new_ticket_label', 'complaints', 'زرّ تذكرة جديدة', 'string', 'تذكرة جديدة'],
            ['complaints.empty_message', 'complaints', 'رسالة القائمة الفارغة', 'text', 'مفيش تذاكر لسّه — واحنا مستنّيين نسمع منك.'],
            ['complaints.filter.status_label', 'complaints', 'تسمية فلتر الحالة', 'string', 'الحالة'],
            ['complaints.filter.type_label', 'complaints', 'تسمية فلتر النوع', 'string', 'النوع'],
            ['complaints.filter.search_label', 'complaints', 'تسمية حقل البحث', 'string', 'بحث بالرقم أو العنوان'],
            ['complaints.filter.submit_label', 'complaints', 'زرّ الفلترة', 'string', 'فلترة'],
            ['complaints.filter.all_label', 'complaints', 'خيار «الكلّ» في الفلاتر', 'string', 'الكلّ'],
            ['complaints.thread_label', 'complaints', 'وصف سلسلة الردود', 'string', 'سلسلة الردود'],
            ['complaints.back_label', 'complaints', 'زرّ الرجوع على الموبايل', 'string', 'رجوع'],
            ['complaints.closed_badge', 'complaints', 'شارة التذكرة المغلقة', 'string', 'مغلقة — قراءة فقط'],
            ['complaints.closed_hint', 'complaints', 'سطر التذكرة المغلقة', 'text', 'لو ظهرت حاجة تانية، افتح تذكرة جديدة وهنكمّل معاك.'],
            ['complaints.reply_placeholder', 'complaints', 'نصّ إرشاديّ للردّ', 'string', 'اكتب ردّك هنا…'],
            ['complaints.close_label', 'complaints', 'زرّ إغلاق التذكرة', 'string', 'إغلاق التذكرة'],
            ['complaints.sent_message', 'complaints', 'رسالة تأكيد الإرسال', 'text', 'وصلتنا رسالتك — رقم التذكرة :number. هنردّ عليك في أقرب وقت.'],
            ['complaints.reply_on_closed_message', 'complaints', 'رسالة الردّ على تذكرة مغلقة', 'text', 'التذكرة دي مقفولة. لو لسّه محتاج مساعدة افتح تذكرة جديدة.'],
            ['complaints.reply_sent_message', 'complaints', 'رسالة نجاح الردّ', 'string', 'اتبعت ✓'],
            ['complaints.closed_message', 'complaints', 'رسالة إغلاق التذكرة', 'text', 'قفلنا التذكرة. شكرًا إنّك كلّمتنا.'],
            ['complaints.error.required', 'complaints', 'رسالة الحقل المطلوب', 'text', 'الحقل ده مطلوب — اكتبه وجرّب تاني.'],
            ['complaints.error.category_required', 'complaints', 'رسالة السبب المطلوب', 'string', 'اختار السبب من القائمة.'],
            ['complaints.error.wants_contact_required', 'complaints', 'رسالة الرغبة في التواصل', 'text', 'قول لنا: ترحب إننا نتواصل معاك ولا لأ؟'],
            ['complaints.error.title_min', 'complaints', 'رسالة العنوان القصير', 'text', 'العنوان قصيّر — اكتب جملة توضّح الموضوع.'],
            ['complaints.error.body_min', 'complaints', 'رسالة النصّ القصير', 'text', 'اكتب تفاصيل أكتر شوية عشان نقدر نساعدك.'],
            ['complaints.error.not_allowed', 'complaints', 'رسالة الاختيار غير المتاح', 'text', 'الاختيار ده مش من الخيارات المتاحة.'],
            ['complaints.error.attachment_max', 'complaints', 'رسالة المرفق الكبير', 'text', 'المرفق كبير — اختار ملفّ أصغر.'],
            ['complaints.error.reply_required', 'complaints', 'رسالة الردّ المطلوب', 'string', 'اكتب ردّك الأوّل.'],
            ['complaints.error.reply_min', 'complaints', 'رسالة الردّ القصير', 'string', 'اكتب كلمتين على الأقلّ.'],
            ['complaints.reasons.page_title', 'complaints', 'عنوان شاشة تحرير الأسباب', 'string', 'أسباب الشكاوى والمقترحات'],
            ['complaints.reasons.page_subtitle', 'complaints', 'سطر شاشة تحرير الأسباب', 'text', 'دي القائمة اللي بيختار منها المستخدم — عدّلها زيّ ما تحبّ.'],
            ['complaints.reasons.in_use_suffix', 'complaints', 'لاحقة عدّاد استعمال السبب', 'string', 'تذكرة مرتبطة'],
            ['complaints.reasons.remove_label', 'complaints', 'زرّ حذف السبب', 'string', 'حذف السبب'],
            ['complaints.reasons.new_placeholder', 'complaints', 'نصّ صفّ السبب الجديد', 'string', 'سبب جديد'],
            ['complaints.reasons.add_label', 'complaints', 'زرّ إضافة سبب', 'string', 'إضافة سبب'],
            ['complaints.reasons.save_label', 'complaints', 'زرّ حفظ الأسباب', 'string', 'حفظ الأسباب'],
            ['complaints.reasons.defaults_hint', 'complaints', 'بادئة قائمة الأسباب الافتراضيّة', 'string', 'الافتراضيّ:'],
            ['complaints.admin.section_label', 'complaints', 'اسم قسم التوجيه والدعم', 'string', 'التوجيه والدعم'],
            ['complaints.admin.queue_label', 'complaints', 'اسم طابور الشكاوى', 'string', 'الشكاوى'],
            ['cv.step.volunteering_label', 'cv', 'عنوان خطوة الخبرة التطوّعيّة', 'string', 'الخبرة التطوّعيّة'],
            ['cv.step.courses_label', 'cv', 'عنوان خطوة الدورات التدريبيّة', 'string', 'الدورات التدريبيّة'],
            ['cv.section.contact_label', 'cv', 'عنوان قسم بيانات التواصل', 'string', 'بيانات التواصل'],
            ['cv.section.summary_label', 'cv', 'عنوان قسم النبذة', 'string', 'نبذة مهنيّة'],
            ['cv.section.experience_label', 'cv', 'عنوان قسم الخبرة العمليّة', 'string', 'الخبرة العمليّة'],
            ['cv.section.volunteering_label', 'cv', 'عنوان قسم الخبرة التطوّعيّة', 'string', 'الخبرة التطوّعيّة'],
            ['cv.section.education_label', 'cv', 'عنوان قسم رحلة التعلّم', 'string', 'رحلة التعلّم'],
            ['cv.section.courses_label', 'cv', 'عنوان قسم الدورات التدريبيّة', 'string', 'الدورات التدريبيّة'],
            ['cv.section.skills_label', 'cv', 'عنوان قسم المهارات', 'string', 'المهارات'],
            ['cv.section.languages_label', 'cv', 'عنوان قسم اللغات', 'string', 'اللغات'],
            ['cv.section.trainings_label', 'cv', 'عنوان قسم تدريبات المنصّة', 'string', 'تدريبات المنصّة المكتملة'],
            ['cv.section.certificates_label', 'cv', 'عنوان قسم الشهادات', 'string', 'الشهادات'],
            ['cv.section.certificate_fallback', 'cv', 'تسمية الشهادة بلا نوع', 'string', 'شهادة'],
            ['cv.section.certificate_number_prefix', 'cv', 'بادئة رقم الشهادة', 'string', 'رقم'],
            ['cv.field.volunteer_role_label', 'cv', 'تسمية دور التطوّع', 'string', 'الدور'],
            ['cv.field.organization_label', 'cv', 'تسمية المنظمة', 'string', 'المنظمة'],
            ['cv.field.course_name_label', 'cv', 'تسمية اسم الدورة', 'string', 'اسم الدورة'],
            ['cv.field.provider_label', 'cv', 'تسمية جهة الإصدار', 'string', 'جهة الإصدار'],
            ['cv.field.course_date_label', 'cv', 'تسمية تاريخ الحصول', 'string', 'تاريخ الحصول'],
            ['cv.field.course_serial_label', 'cv', 'تسمية رقم شهادة الدورة', 'string', 'رقم الشهادة (اختياريّ)'],
            ['cv.field.course_url_label', 'cv', 'تسمية رابط شهادة الدورة', 'string', 'رابط الشهادة (اختياريّ)'],
            ['cv.field.certificate_url_label', 'cv', 'نصّ رابط الشهادة على البروفايل', 'string', 'رابط الشهادة'],
            ['cv.field.description_en_label', 'cv', 'تسمية الوصف بالإنجليزيّة', 'string', 'Description (English) — optional'],
            ['cv.field.job_title_en_label', 'cv', 'تسمية المسمّى بالإنجليزيّة', 'string', 'Job title (English) — optional'],
            ['cv.field.summary_en_label', 'cv', 'تسمية النبذة بالإنجليزيّة', 'string', 'Professional summary (English) — optional'],
            ['cv.volunteering.add_label', 'cv', 'زرّ إضافة تجربة تطوّعيّة', 'string', 'إضافة تجربة تطوّعيّة'],
            ['cv.volunteering.empty_hint', 'cv', 'سطر الحالة الفارغة للتطوّع', 'text', 'أيّ مبادرة أو عمل مجتمعيّ بيفرق — سجّله.'],
            ['cv.courses.add_label', 'cv', 'زرّ إضافة دورة جديدة', 'string', 'إضافة دورة جديدة'],
            ['cv.courses.empty_hint', 'cv', 'سطر الحالة الفارغة للدورات', 'text', 'الدورات اللي خدتها بره المنصّة كمان بتتحسب.'],
            ['cv.row.reorder_label', 'cv', 'وصف مقبض إعادة الترتيب', 'string', 'اسحب لإعادة الترتيب'],
            ['cv.save_label', 'cv', 'زرّ «حفظ CV» في الشريط العائم', 'string', 'حفظ CV'],
            ['cv.lang.switch_label', 'cv', 'وصف مفتاح تبديل اللغة', 'string', 'لغة العرض'],
            ['cv.lang.ar_label', 'cv', 'تسمية العربيّة', 'string', 'عربي'],
            ['cv.lang.en_label', 'cv', 'تسمية الإنجليزيّة', 'string', 'English'],
            ['cv.export.confirm_notice', 'cv', 'سطر المعاينة الموسومة قبل الخصم', 'text', 'دي معاينة بعلامة مائيّة. التحميل النهائيّ بالقالب ده هيخصم :price تذكرة (رصيدك :before ⟵ :after).'],
            ['cv.export.confirm_label', 'cv', 'زرّ تأكيد الخصم والتحميل', 'string', 'أكّد وحمّل النسخة النظيفة'],
            ['cv.watermark.opacity_percent', 'cv', 'شفافيّة العلامة المائيّة (%)', 'number', '10'],
            ['cv.photo.size_px', 'cv', 'مقاس الصورة الشخصيّة على الـCV (بكسل)', 'number', '300'],
            ['cv.templates.charge_hint', 'cv', 'سطر توضيح لحظة الخصم', 'text', 'اختيار القالب مجّانيّ — والتذاكر بتتخصم لمّا تحمّل النسخة النظيفة.'],
            ['cv.template.selected_paid_message', 'cv', 'رسالة اختيار قالب مدفوع', 'text', 'اتغيّر القالب — المعاينة بعلامة مائيّة، و:price تذكرة هتتخصم عند التحميل.'],
            ['cv.template.max_price_tickets', 'cv', 'أقصى سعر قالب بالتذاكر', 'number', '100'],
            ['cv.tools.title', 'cv', 'عنوان أدوات السيرة', 'string', 'أدوات السيرة'],
            ['cv.tools.ats_label', 'cv', 'زرّ تحميل PDF متوافق مع ATS', 'string', 'تحميل PDF متوافق مع ATS'],
            ['cv.tools.import_summary', 'cv', 'عنوان استيراد CV جاهز', 'string', 'ارفع CV جاهز وهنملّي بدالك'],
            ['cv.tools.file_label', 'cv', 'تسمية ملفّ السيرة', 'string', 'ملفّ السيرة'],
            ['cv.tools.parse_label', 'cv', 'زرّ تحليل الملفّ', 'string', 'حلّل الملفّ'],
            ['cv.tools.import_hint', 'cv', 'سطر معاينة الاستيراد', 'text', 'راجع الأرقام — والحفظ مش هيحصل غير لما تختار.'],
            ['cv.tools.replace_label', 'cv', 'زرّ تبديل البيانات', 'string', 'بدّل بياناتي'],
            ['cv.tools.append_label', 'cv', 'زرّ الإضافة على البيانات', 'string', 'أضف عليها'],
            ['cv.tools.public_toggle_label', 'cv', 'مفتاح الرابط العامّ للسيرة', 'string', 'شغّل الرابط العامّ للسيرة'],
            ['cv.template.admin.page_title', 'cv', 'عنوان شاشة إدارة القوالب', 'string', 'قوالب السيرة الذاتيّة'],
            ['cv.template.admin.page_subtitle', 'cv', 'سطر شاشة إدارة القوالب', 'text', 'أضِف قوالب، وحدّد تذاكر كلّ قالب، ووقّف اللي مش عايزه.'],
            ['cv.template.admin.section_label', 'cv', 'اسم قسم الإعدادات والنظام', 'string', 'الإعدادات والنظام'],
            ['cv.template.admin.add_label', 'cv', 'زرّ قالب جديد', 'string', 'قالب جديد'],
            ['cv.template.admin.create_label', 'cv', 'زرّ إضافة القالب', 'string', 'إضافة'],
            ['cv.template.admin.save_label', 'cv', 'زرّ حفظ القالب', 'string', 'حفظ'],
            ['cv.template.admin.delete_label', 'cv', 'زرّ حذف القالب', 'string', 'حذف'],
            // 'cv.template.admin.download_label' مزروعٌ في database/seeders/LibraryDemoSeeder.php::settings()
            // (مسار الإنتاج عبر SettingDefinitionsSeeder) — لا مصدر ثانٍ هنا (2.13-د).
            ['cv.template.admin.name_label', 'cv', 'تسمية اسم القالب', 'string', 'اسم القالب'],
            ['cv.template.admin.description_label', 'cv', 'تسمية وصف القالب', 'string', 'وصف مختصر'],
            ['cv.template.admin.view_label', 'cv', 'تسمية ملفّ العرض', 'string', 'ملفّ العرض'],
            ['cv.template.admin.price_label', 'cv', 'تسمية سعر القالب', 'string', 'التذاكر (فاضي = جدول أوجه الصرف)'],
            ['cv.template.admin.effective_price_prefix', 'cv', 'بادئة السعر الفعليّ', 'string', 'الفعليّ الآن:'],
            ['cv.template.admin.preview_label', 'cv', 'تسمية صورة المعاينة', 'string', 'مسار صورة المعاينة'],
            ['cv.template.admin.sort_label', 'cv', 'تسمية ترتيب القالب', 'string', 'الترتيب'],
            ['cv.template.admin.is_free_label', 'cv', 'تسمية القالب المجّانيّ', 'string', 'القالب المجّانيّ'],
            ['cv.template.admin.is_active_label', 'cv', 'تسمية إتاحة القالب', 'string', 'متاح للمستخدمين'],
            ['cv.template.admin.usage_suffix', 'cv', 'لاحقة عدّاد استعمال القالب', 'string', 'سيرة تستعمله'],
            ['cv.template.admin.inactive_badge', 'cv', 'شارة القالب الموقوف', 'string', 'موقوف'],
            ['cv.template.admin.empty_message', 'cv', 'رسالة لا قوالب', 'text', 'مفيش قوالب لسّه — ابدأ بواحد.'],
            ['cv.template.admin.created_message', 'cv', 'رسالة إضافة قالب', 'string', 'اتضاف القالب ✓'],
            ['cv.template.admin.saved_message', 'cv', 'رسالة حفظ قالب', 'string', 'اتحفظ ✓'],
            // 'cv.template.admin.decor_saved_message' و'cv.template.decor.max_layers'
            // مزروعان في database/seeders/LibraryDemoSeeder.php::settings()
            // (مسار الإنتاج عبر SettingDefinitionsSeeder) — لا مصدر ثانٍ هنا (2.13-د).
            ['cv.template.admin.deleted_message', 'cv', 'رسالة حذف قالب', 'string', 'اتشال القالب ✓'],
            ['cv.template.admin.archived_message', 'cv', 'رسالة إيقاف قالب مستعمَل', 'text', 'القالب مستعمَل في سِيَر قايمة — وقّفناه بدل ما نحذفه.'],
            ['cv.template.admin.ats_summary', 'cv', 'عنوان إعدادات مخرَج الـATS', 'string', 'تباعد وأحجام مخرَج الـATS'],
            ['cv.template.admin.margin_label', 'cv', 'تسمية الهامش', 'string', 'الهامش'],
            ['cv.template.admin.body_label', 'cv', 'تسمية حجم النصّ', 'string', 'حجم النصّ'],
            ['cv.template.admin.heading_label', 'cv', 'تسمية حجم العنوان', 'string', 'حجم العنوان'],
            ['cv.template.admin.title_label', 'cv', 'تسمية حجم الاسم', 'string', 'حجم الاسم'],
            ['cv.template.admin.leading_label', 'cv', 'تسمية تباعد السطور', 'string', 'تباعد السطور'],
            ['attestations.public.slug_length', 'attestations', 'طول رابط الإفادة العشوائيّ', 'number', '12'],
            ['attestations.public.toggle_label', 'attestations', 'مفتاح الرابط العامّ للإفادة', 'string', 'شغّل الرابط العامّ للإفادة'],
            ['attestations.public.hint', 'attestations', 'سطر شرح رابط الإفادة', 'text', 'الرابط مقفول لحدّ ما تشغّله بنفسك — وتقدر تقفله في أيّ وقت.'],
            ['attestations.public.opened_message', 'attestations', 'رسالة فتح الرابط', 'string', 'الرابط شغّال ✓'],
            ['attestations.public.closed_message', 'attestations', 'رسالة قفل الرابط', 'string', 'الرابط اتقفل ✓'],
            ['attestations.public.error_message', 'attestations', 'رسالة تعذّر تغيير الحالة', 'text', 'مقدرناش نغيّر الحالة — راجع النت وجرّب تاني.'],
            ['account.profile.tab.overview_label', 'account', 'تسمية تاب نظرة عامّة', 'string', 'نظرة عامّة'],
            ['account.profile.tab.details_label', 'account', 'تسمية تاب التفاصيل', 'string', 'تفاصيل'],
            ['account.profile.tab.achievements_label', 'account', 'تسمية تاب الإنجازات', 'string', 'الإنجازات'],
            ['account.profile.tab.certificates_label', 'account', 'تسمية تاب الشهادات', 'string', 'الشهادات'],
            ['account.profile.tab.experience_label', 'account', 'تسمية تاب خبراتي', 'string', 'خبراتي'],
            ['account.profile.kpi.level_label', 'account', 'تسمية كرت مستوى الحساب', 'string', 'مستوى الحساب + XP'],
            ['account.profile.kpi.tickets_label', 'account', 'تسمية كرت رصيد التذاكر', 'string', 'رصيد التذاكر'],
            ['account.profile.kpi.streak_label', 'account', 'تسمية كرت ستريك الخامسة', 'string', 'ستريك نادي الخامسة'],
            ['account.profile.kpi.certificates_label', 'account', 'تسمية كرت الشهادات', 'string', 'الشهادات'],
            ['account.profile.kpi.courses_label', 'account', 'تسمية كرت التدريبات', 'string', 'التدريبات (مكتملة/جارية)'],
            ['account.profile.kpi.rank_label', 'account', 'تسمية كرت ترتيب الليدربورد', 'string', 'ترتيب الليدر بورد'],
            ['account.profile.kpi.ambassador_label', 'account', 'تسمية كرت لقب السفير', 'string', 'لقب السفير'],
            ['account.profile.kpi.no_ambassador', 'account', 'نصّ بلا لقب سفير', 'string', 'لسّه'],
            ['account.profile.kpi.unranked', 'account', 'نصّ خارج الليدربورد', 'string', 'خارج اللوحة'],
            ['account.profile.kpi.more_link', 'account', 'رابط بقيّة الكروت', 'string', 'باقي أرقامك في تاب «تفاصيل»'],
            ['account.profile.overview.general_title', 'account', 'عنوان بطاقة البيانات العامّة', 'string', 'بيانات عامّة'],
            ['account.profile.overview.contact_title', 'account', 'عنوان بطاقة التواصل', 'string', 'التواصل'],
            ['account.profile.overview.joined_label', 'account', 'تسمية تاريخ الانضمام', 'string', 'تاريخ الانضمام'],
            ['account.profile.overview.governorate_label', 'account', 'تسمية المحافظة', 'string', 'المحافظة'],
            ['account.profile.overview.country_label', 'account', 'تسمية الدولة', 'string', 'الدولة'],
            ['account.profile.overview.phone_label', 'account', 'تسمية رقم الموبايل', 'string', 'رقم الموبايل'],
            ['account.profile.overview.email_label', 'account', 'تسمية البريد', 'string', 'البريد الإلكترونيّ'],
            ['account.profile.overview.missing_label', 'account', 'نصّ البيان الغائب (مؤنّث)', 'string', 'مش مضافة'],
            ['account.profile.overview.missing_male_label', 'account', 'نصّ البيان الغائب (مذكّر)', 'string', 'مش مضاف'],
            ['account.profile.overview.locked_label', 'account', 'نصّ الحقل المقفول', 'string', 'مش متاح'],
            ['account.profile.overview.privacy_link', 'account', 'رابط إعدادات الخصوصيّة', 'string', 'اتحكّم في مين يشوف بياناتك'],
            ['account.profile.details.badges_title', 'account', 'عنوان الشارات في التفاصيل', 'string', 'الشارات'],
            ['account.profile.details.badges_suffix', 'account', 'لاحقة عدّاد الشارات', 'string', 'شارة مفتوحة'],
            ['account.profile.achievements.hidden_message', 'account', 'رسالة إخفاء الإنجازات', 'text', 'الإنجازات مش متاحة على البروفايل ده.'],
            ['account.profile.achievements.export_title', 'account', 'عنوان بطاقة الإنجازات المستخرَجة', 'string', 'إنجازاتي'],
            ['account.profile.achievements.export_subtitle', 'account', 'سطر بطاقة الإنجازات المستخرَجة', 'string', 'مستوى الحساب'],
            ['account.profile.achievements.level_prefix', 'account', 'بادئة المستوى', 'string', 'مستوى'],
            ['account.profile.achievements.next_prefix', 'account', 'بادئة العتبة التالية', 'string', 'الجاي عند'],
            ['account.profile.certificates.hidden_message', 'account', 'رسالة إخفاء الشهادات', 'text', 'الشهادات مش متاحة على البروفايل ده.'],
            ['account.profile.certificates.empty_message', 'account', 'رسالة لا شهادات', 'text', 'لسّه بدري — أوّل شهادة مستنّياك.'],
            ['account.profile.certificates.verified_badge', 'account', 'شارة الشهادة المعتمدة', 'string', 'شهادة معتمدة'],
            ['account.profile.certificates.issued_prefix', 'account', 'بادئة تاريخ الإصدار', 'string', 'صدرت'],
            ['account.profile.certificates.verify_label', 'account', 'زرّ التحقّق من الشهادة', 'string', 'تحقّق من الشهادة'],
            ['account.profile.certificates.linkedin_label', 'account', 'زرّ المشاركة على LinkedIn', 'string', 'شارك على LinkedIn'],
            ['account.profile.experience.hidden_message', 'account', 'رسالة إخفاء خبراتي', 'text', 'الخبرات مش متاحة على البروفايل ده.'],
            ['account.profile.experience.empty_message', 'account', 'رسالة لا سيرة ذاتيّة', 'text', 'لسّه مفيش سيرة ذاتيّة هنا.'],
            ['account.profile.experience.empty_action', 'account', 'زرّ بدء السيرة', 'string', 'ابدأ سيرتك'],
            // 'account.profile.experience.attestation_title'/'attestation_link_label' مزروعان في
            // database/seeders/AccountDemoSeeder.php::settings() (مسار الإنتاج) — لا مصدر ثانٍ هنا (2.13-د).
            ['leaderboard.profile_range_days', 'leaderboard', 'مدى ترتيب الليدربورد على البروفايل (أيّام)', 'number', '30'],
            ['cv.public.empty_message', 'cv', 'رسالة السيرة العامّة الفارغة', 'text', 'السيرة لسّه فاضية — صاحبها بيجهّزها.'],
            ['account.profile.header.online_label', 'account', 'تسمية «نشط دلوقتي»', 'string', 'نشط دلوقتي'],
            ['account.profile.header.supervisor_label', 'account', 'لقب المشرف في الهيدر', 'string', 'مشرف'],
            ['account.profile.header.bio_label', 'account', 'تسمية النبذة الشخصيّة', 'string', 'النبذة الشخصيّة'],
            ['account.profile.header.bio_placeholder', 'account', 'نصّ إرشاديّ للنبذة', 'text', 'اكتب نبذة قصيرة عنك — سطر واحد يكفي.'],
            ['account.profile.header.copy_link_label', 'account', 'زرّ نسخ رابط البروفايل', 'string', 'نسخ رابطي'],
            ['account.profile.header.edit_label', 'account', 'زرّ تعديل البروفايل', 'string', 'تعديل البروفايل'],
            // صفحة البحث الكبيرة (13.1 · 24.5) — والحالة الوحيدة على الكارت «فعّال»
            ['account.search.active_label', 'account', 'تسمية حالة النتيجة في البحث', 'string', 'فعّال'],
            ['account.profile.badges.title', 'account', 'عنوان سكشن الشارات', 'string', 'الشارات'],
            ['account.profile.badges.empty_message', 'account', 'رسالة لا شارات', 'text', 'لسّه بدري — أوّل شارة مستنّياك.'],
            ['account.profile.badges.unlocked_label', 'account', 'وصف الشارة المفتوحة', 'string', 'مفتوحة'],
            ['account.profile.badges.locked_label', 'account', 'وصف الشارة المقفولة', 'string', 'مقفولة'],
            ['account.profile.badges.modal_title', 'account', 'عنوان بوب-أب الشارة', 'string', 'الشارة'],
            ['account.profile.share.title', 'account', 'عنوان بوب-أب المشاركة', 'string', 'مشاركة الحساب'],
            ['account.profile.share.link_label', 'account', 'تسمية رابط الحساب العامّ', 'string', 'رابط الحساب العامّ'],
            ['account.profile.share.copy_label', 'account', 'زرّ نسخ الرابط', 'string', 'انسخ'],
            ['platform.identity.logo_path', 'platform', 'مسار لوجو المنصّة (للعلامة المائيّة)', 'string', ''],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
                'is_sensitive' => str_contains($key, 'api_key') || str_contains($key, 'vendor_key') || str_contains($key, 'token'),
                'is_owner_only' => str_contains($key, 'gateway.api_key') || str_contains($key, 'gateway.vendor_key'),
            ]);
        }

        $this->command?->info('الإعدادات: '.Setting::count());
    }
}
