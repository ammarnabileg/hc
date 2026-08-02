<?php

/*
|--------------------------------------------------------------------------
| معماريّة الصلاحيّات (الدستور 12.2.1)
|--------------------------------------------------------------------------
| الصلاحيّة تُسمّى «المورد.الفعل» — لا باسم دور ولا شاشة.
| والنطاق إلزاميّ ويُقيَّم داخل سياق العضويّة النشطة.
*/

return [

    // الأفعال القياسيّة الثلاثة عشر
    'actions' => [
        'view', 'list', 'create', 'edit', 'delete', 'approve', 'reject',
        'assign', 'archive', 'restore', 'export', 'import', 'manage',
    ],

    /*
     | ⭐ نصّ 12.2.1-د حرفيًّا: «`manage` تشمل `create/edit/delete/archive/assign`».
     | **خمسة لا اثنا عشر.** توسيعها إلى `export` و`approve` و`reject` و`import`
     | و`restore` كان يمنح — بضغطة واحدة وبلا سندٍ في الدستور — سلطةَ الاعتماد
     | والرفض وإخراج البيانات من المنصّة. والفرد يبقى **ظاهرًا** عند الحفظ لا صامتًا.
     */
    'manage_expands_to' => ['create', 'edit', 'delete', 'archive', 'assign'],

    /*
     | النطاقات الستّة مرتّبة من الأضيق إلى الأوسع.
     | الترتيب يُستخدَم في منع تصعيد الامتياز: لا يمنح أحدٌ نطاقًا أوسع ممّا يملك.
     */
    'scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],

    /*
     | ⭐ الشروط من **قائمة مقفولة** تقرأ حالاتٍ موجودة — لا محرّك قواعد موازٍ ولا
     | شروط حرّة (12.2.1-ج). وأيّ مفتاح خارج هذه القائمة (أو خارج `condition_states`)
     | **يُرفَض** في `ConditionEvaluator` — Fail closed لا Fail open.
     |
     | وترجمة نصوص المصفوفة العربيّة إلى هذه المفاتيح خريطةٌ **صريحة مكتوبة** في
     | App\Support\Access\ConditionMap — لا اجتهاد وقت التشغيل ولا نصٌّ بلا مفتاح.
     */
    'conditions' => [
        'platform_owner' => 'مالك المنصّة وحده',
        'is_owner' => 'المستخدم مالك السجلّ نفسه',
        'not_self' => 'الهدف ليس المستخدم نفسه',
        'assigned' => 'المستخدم مُسنَد إليه',
        'reviewer' => 'المستخدم هو المراجِع',
        'direct_upline' => 'المستخدم الأبلاين المباشر لصاحب السجلّ',
        'upline' => 'المستخدم أبلاين لصاحب السجلّ (لأيّ عمق)',
        'active_membership' => 'العضويّة النشطة سارية',
        'within_window' => 'داخل النافذة الزمنيّة',
        'before_deadline' => 'قبل الديدلاين',
        'feature_enabled' => 'الميزة مفعّلة',
        'not_locked' => 'السجلّ غير مقفول',
    ],

    /*
     | شروط الحالة — تُكتَب `state:<slug>` (نمط `state=pending` المنصوص في 12.2.1-ج).
     | القائمة مقفولة كذلك: `slug` غير مذكور هنا **يُرفَض**.
     |
     | `values` : قيم عمود الحالة المقبولة على الهدف.
     | `except` : قيم مرفوضة (شرط «قبل/غير») — يمرّ ما لم تكن الحالة إحداها.
     | `guard => 'domain'` : حالة **لا يقرأها عمود حالة** (رصيد · عدّاد · سلسلة أيّام ·
     |   مستوى وصول · عتبة مقيّمين)، فطبقة الوصول لا تملك ما تقيسه عليه ويحرسها
     |   المجال صاحب الشاشة. ومذكورة هنا **صراحةً** كي لا يمرّ نصٌّ بلا مفتاح،
     |   ولا يُخترَع شرطٌ حرّ، ويبقى الجرد كاملًا أمام المدقّق.
    */
    'condition_states' => [
        // ————————————————————————— حالات تُقرَأ من عمود الحالة على الهدف
        'published' => ['label' => 'منشور', 'values' => ['published']],
        'draft' => ['label' => 'مسودّة', 'values' => ['draft']],
        'draft_or_upcoming' => ['label' => 'مسودّة أو قادمة', 'values' => ['draft', 'upcoming', 'soon', 'scheduled']],
        'archived' => ['label' => 'مؤرشف', 'values' => ['archived']],
        'in_review' => ['label' => 'قيد المراجعة', 'values' => ['in_review', 'review', 'pending', 'submitted']],
        'under_review' => ['label' => 'تحت المراجعة', 'values' => ['under_review', 'pending', 'pending_review']],
        'awaiting_approval' => ['label' => 'بانتظار الاعتماد', 'values' => ['pending', 'submitted', 'requested']],
        'approved' => ['label' => 'معتمد', 'values' => ['approved', 'accepted']],
        'rejected' => ['label' => 'مرفوض', 'values' => ['rejected', 'denied']],
        'nominated' => ['label' => 'مرشّح', 'values' => ['nominated', 'candidate', 'applied']],
        'proposed' => ['label' => 'مقترح', 'values' => ['proposed']],
        'active' => ['label' => 'نشط', 'values' => ['active', 'open']],
        'inactive' => ['label' => 'غير نشطة', 'values' => ['inactive', 'paused', 'draft']],
        'paused' => ['label' => 'موقوفة', 'values' => ['paused', 'suspended']],
        'suspended' => ['label' => 'معلَّق', 'values' => ['suspended', 'banned', 'blocked']],
        'in_force' => ['label' => 'ساري', 'values' => ['active', 'valid', 'granted']],
        'valid' => ['label' => 'سارية', 'values' => ['valid', 'active']],
        'expired' => ['label' => 'منتهية الصلاحيّة', 'values' => ['expired']],
        'open' => ['label' => 'مفتوح', 'values' => ['open']],
        'case_open' => ['label' => 'قضيّة مفتوحة', 'values' => ['open']],
        'closed' => ['label' => 'مغلق/مغلقة', 'values' => ['closed']],
        'ended' => ['label' => 'منتهية', 'values' => ['ended', 'finished', 'completed']],
        'completed' => ['label' => 'مكتمل/مكتملة', 'values' => ['completed', 'delivered']],
        'in_progress' => ['label' => 'قيد التنفيذ', 'values' => ['in_progress']],
        'not_started' => ['label' => 'لم يبدأ', 'values' => ['not_started', 'scheduled', 'draft']],
        'scheduled' => ['label' => 'مجدولة', 'values' => ['scheduled']],
        'sent_to_execution' => ['label' => 'أُرسل للتنفيذ', 'values' => ['sent_to_execution']],
        'before_execution' => ['label' => 'قبل إرسال للتنفيذ', 'except' => ['sent_to_execution']],
        'passed' => ['label' => 'ناجح', 'values' => ['passed', 'accepted']],
        'failed' => ['label' => 'فشل', 'values' => ['failed']],
        'final_list' => ['label' => 'القائمة النهائيّة', 'values' => ['final_list']],
        'vacant' => ['label' => 'شاغر', 'values' => ['vacant', 'open']],
        'invited' => ['label' => 'مدعوّ', 'values' => ['invited']],
        'used' => ['label' => 'مستخدَم', 'values' => ['used']],
        'hidden' => ['label' => 'مخفيّ', 'values' => ['hidden']],
        'deleted' => ['label' => 'محذوف', 'values' => ['deleted']],
        'no_delivery' => ['label' => 'عدم تسليم', 'values' => ['no_delivery']],

        // ————————————————— حالات يحرسها المجال (لا عمود حالة على الهدف يقيسها)
        'no_active_members' => ['label' => 'بلا أعضاء نشطين', 'guard' => 'domain'],
        'no_open_tasks' => ['label' => 'بلا مهامّ مفتوحة', 'guard' => 'domain'],
        'tasks_settled' => ['label' => 'المهامّ محسومة', 'guard' => 'domain'],
        'rater_threshold_met' => ['label' => 'بلغت عتبة 3 مقيّمين', 'guard' => 'domain'],
        'final_review' => ['label' => 'المعاينة النهائيّة', 'guard' => 'domain'],
        'review_complete' => ['label' => 'المعاينة مكتملة', 'guard' => 'domain'],
        'review_submitted' => ['label' => 'رُفعت معاينة', 'guard' => 'domain'],
        'holding_layer' => ['label' => 'الطبقة الحائزة', 'guard' => 'domain'],
        'within_load_cap' => ['label' => 'ضمن سقف الانشغال', 'guard' => 'domain'],
        'access_level_matches' => ['label' => 'مستوى الوصول المطابق', 'guard' => 'domain'],
        'access_entity_only' => ['label' => 'مستوى الوصول «كيانه فقط»', 'guard' => 'domain'],
        'access_all_volunteers' => ['label' => 'مستوى الوصول «كلّ المتطوّعين»', 'guard' => 'domain'],
        'no_active_war' => ['label' => 'لا حرب نشطة', 'guard' => 'domain'],
        'not_installed' => ['label' => 'غير مُنصَّب', 'guard' => 'domain'],
        'previous_lesson_completed' => ['label' => 'الدرس السابق مكتمل', 'guard' => 'domain'],
        'lesson_available' => ['label' => 'الدرس متاح', 'guard' => 'domain'],
        'video_watched' => ['label' => 'الفيديو مُشاهَد', 'guard' => 'domain'],
        'course_lessons_completed' => ['label' => 'دروس التدريب مكتملة', 'guard' => 'domain'],
        'passed_final_exam' => ['label' => 'ناجح في الامتحان النهائيّ', 'guard' => 'domain'],
        'interaction_allowed' => ['label' => 'التفاعل مسموح', 'guard' => 'domain'],
        'interview_attended' => ['label' => 'حضر المقابلة', 'guard' => 'domain'],
        'awaiting_candidate' => ['label' => 'بانتظار موافقة المرشّح', 'guard' => 'domain'],
        'qualifying_not_started' => ['label' => 'لم يبدأ التأهيليّ', 'guard' => 'domain'],
        'position_confirmed' => ['label' => 'بوزشن جديد مثبَّت', 'guard' => 'domain'],
        'objection_accepted' => ['label' => 'اعتراض مقبول', 'guard' => 'domain'],
        'suspension_threshold' => ['label' => 'تعليق الحساب (−10)', 'guard' => 'domain'],
        'recommendation_raised' => ['label' => 'توصية مرفوعة', 'guard' => 'domain'],
        'decision_issued' => ['label' => 'صدر القرار', 'guard' => 'domain'],
        'linked_to_track' => ['label' => 'مربوط بمسار', 'guard' => 'domain'],
        'linked_to_entity' => ['label' => 'مربوطة بالكيان', 'guard' => 'domain'],
        'within_membership_scope' => ['label' => 'ضمن نطاق العضويّة', 'guard' => 'domain'],
        'package_filling' => ['label' => 'حزمة قيد الملء', 'guard' => 'domain'],
        'rollback_path' => ['label' => 'طريق الرجوع', 'guard' => 'domain'],
        'operational_project_open' => ['label' => 'مشروع تشغيليّ مفتوح', 'guard' => 'domain'],
        'within_operational_project' => ['label' => 'ضمن المشروع التشغيليّ', 'guard' => 'domain'],
        'first_approval' => ['label' => 'اعتماد أوّل', 'guard' => 'domain'],
        'subtask_approved' => ['label' => 'صب-تاسك معتمد', 'guard' => 'domain'],
        'task_approved' => ['label' => 'مهمّة معتمدة', 'guard' => 'domain'],
        'bound_to_task' => ['label' => 'منوط بالمهمّة', 'guard' => 'domain'],
        'contribution_invite' => ['label' => 'دعوة مساهمة', 'guard' => 'domain'],
        'delivery_discipline_breach' => ['label' => 'انضباط تسليم مخلّ', 'guard' => 'domain'],
        'current_arbiter' => ['label' => 'المحكّم الحالي', 'guard' => 'domain'],
        'registration_open' => ['label' => 'التسجيل مفتوح', 'guard' => 'domain'],
        'profile_complete' => ['label' => 'مكتمل البيانات', 'guard' => 'domain'],
        'ack_required' => ['label' => 'إقرار إلزاميّ', 'guard' => 'domain'],
        'tickets_balance_12' => ['label' => 'الرصيد ≥ 12 تذكرة', 'guard' => 'domain'],
        'tickets_balance_1' => ['label' => 'الرصيد ≥ 1 تذكرة', 'guard' => 'domain'],
        'under_challenge_cap' => ['label' => 'أقلّ من 5 تحدّيات نشطة', 'guard' => 'domain'],
        'streak_7_days' => ['label' => '7 أيّام متواصلة', 'guard' => 'domain'],
        'not_granted' => ['label' => 'غير ممنوحة', 'guard' => 'domain'],
        'withdrawable' => ['label' => 'متاح للسحب', 'guard' => 'domain'],
    ],

    // دور مالك المنصّة — أعلى الجميع ويملك المجموعة المحميّة
    'owner_role' => 'platform_owner',

    // أدوار طبقة الإدارة تعلو طبقة التطوّع دائمًا (12.2.1-ز-5)
    'platform_layer' => 'platform',

    /*
     | ⭐ باب لوحة الإدارة (12.2.1-أ): «**ممنوع صلاحيّة باسم شاشة** — الشاشة نتيجةٌ
     | للصلاحيّات لا صلاحيّةً بذاتها، ومنه: لوحة الإدارة تظهر لمن له **أيّ** صلاحيّة».
     |
     | فلا مفتاح `admin_panel.view` ولا قائمة سماح. الباب يُحسَب من جدول المسارات:
     |   صلاحيّة إداريّة = تحرس مسارًا داخل `admin.*`
     |                  − ولا تحرس أيّ مسارٍ خارجها (وإلّا فهي صلاحيّة صفحةٍ عامّة)
     |                  − ولا تدخل في قالب المستخدم النهائيّ (طبقة `user` — 12.2.3).
     | ثمّ **كلّ صفحة تُحرَس بصلاحيّتها هي**.
     */
    'panel' => [
        'route_prefix' => 'admin.',
        'end_user_layer' => 'user',
    ],

    // مفتاح الجلسة لسياق العضويّة النشطة
    'membership_session_key' => 'active_membership_id',
];
