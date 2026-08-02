@props(['name' => 'spark', 'size' => 18, 'label' => null])

@php
    /*
     | ⭐ قاموس الأيقونات **المقفول** (2.16-ج): أيقونة واحدة لكلّ مفهوم في كلّ
     | الشاشات بلا استثناء — **وممنوع أيّ مكتبة أيقونات جاهزة**.
     |
     | لماذا SVG مرسومة لا إيموجي؟ لأنّ الإيموجي يرسمه **خطّ نظام التشغيل**:
     | فلا يتبع `currentColor` ولا سُمك الخطّ، ويختلف شكله بين ويندوز وأندرويد
     | وiOS — فينكسر «سُمك خطّ موحّد · زوايا موحّدة · شبكة مقاس واحدة».
     |
     | النمط: Feather — `fill:none` · `stroke:currentColor` · شبكة 24×24 ·
     | أطراف مستديرة (2.10.1-21).
     |
     | المرادفات (`$aliases`) تضمن أنّ «مهمّة» و«task» و«todo» تعطي **نفس**
     | الأيقونة، فيبقى القاموس مقفولًا مهما تعدّدت أسماء النداء.
     */
    $paths = [
        // ---------------------------------------------- التدريب والمحتوى
        'course' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H11v16H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H13v16h5.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
        'lesson' => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="m10.5 8.5 4 2.5-4 2.5zM8 20h8"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>',
        'document' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h4"/>',
        'article' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8 10h8M8 14h8M8 18h5"/>',
        'path' => '<path d="M6 4v6a3 3 0 0 0 3 3h6a3 3 0 0 1 3 3v4"/><circle cx="6" cy="4" r="1.6"/><circle cx="18" cy="20" r="1.6"/>',
        'exam' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="m9.5 13.5 1.8 1.8 3.5-3.6"/>',
        'library' => '<path d="M4 5h5v15H4zM10 5h4v15h-4z"/><path d="m16 6 4 1-3 13-4-1z"/>',
        'note' => '<path d="M5 4h11l3 3v13H5z"/><path d="M9 11h6M9 15h4"/>',

        // ---------------------------------------------- التطوّع والتنظيم
        'task' => '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 3h6v3H9z"/><path d="m8.5 12.5 2 2 4.5-4.5"/>',
        'meeting' => '<circle cx="9" cy="9" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="7" r="2.2"/><path d="M15.5 13.2A5 5 0 0 1 21 18"/>',
        'entity' => '<path d="M4 21V8l8-4 8 4v13"/><path d="M9 21v-6h6v6M9 11h.01M15 11h.01"/>',
        'department' => '<rect x="3" y="10" width="6" height="6" rx="1"/><rect x="15" y="10" width="6" height="6" rx="1"/><path d="M9 13h6M12 4v9M9 4h6"/>',
        'escalation' => '<path d="M12 4 3 20h18z"/><path d="M12 10v4M12 17h.01"/>',
        'contribution' => '<path d="M12 21s-7-4.4-7-9.5A3.9 3.9 0 0 1 12 8a3.9 3.9 0 0 1 7 3.5C19 16.6 12 21 12 21z"/>',
        'placement' => '<path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="11" r="2.2"/>',
        'kudos' => '<path d="M7 21V10l4-7a2 2 0 0 1 3 2l-1 5h5a2 2 0 0 1 2 2.4l-1.4 6A2 2 0 0 1 16.6 21z"/><path d="M4 10h3v11H4z"/>',
        'evaluation' => '<path d="M4 19V6M4 19h16"/><path d="M8 16V11M12 16V8M16 16v-3"/>',
        'org' => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-2h12v2"/>',
        'training' => '<path d="M12 3 3 8l9 5 9-5z"/><path d="M6.5 10.5V15c0 1.5 2.6 3 5.5 3s5.5-1.5 5.5-3v-4.5"/>',

        // ---------------------------------------------- المال والمعاملات
        'wallet' => '<path d="M3 8a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v2"/><rect x="3" y="8" width="18" height="11" rx="2"/><path d="M16 13.5h2.5"/>',
        'transaction' => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'money' => '<circle cx="12" cy="12" r="8"/><path d="M12 7v10M14.5 9.5A2.5 2.5 0 0 0 12 8h-.5a2 2 0 0 0 0 4h1a2 2 0 0 1 0 4H12a2.5 2.5 0 0 1-2.5-1.5"/>',
        'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M6.5 14.5h3"/>',
        'store' => '<path d="M4 9h16l-1 11H5z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
        'bundle' => '<path d="M12 3 3 7.5V16l9 4.5 9-4.5V7.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v8.5"/>',
        'ticket' => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1.5a2.5 2.5 0 0 0 0 5V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1.5a2.5 2.5 0 0 0 0-5z"/><path d="M13 6v12"/>',
        'withdraw' => '<path d="M12 4v11M8 11l4 4 4-4"/><path d="M4 19h16"/>',
        'topup' => '<path d="M12 20V9M8 13l4-4 4 4"/><path d="M4 5h16"/>',

        // ---------------------------------------------- التلعيب والشرف
        'xp' => '<path d="M12 3.5 14.4 9l6 .5-4.6 3.9 1.4 5.9L12 16.2 6.8 19.3l1.4-5.9L3.6 9.5 9.6 9z"/>',
        'badge' => '<circle cx="12" cy="9" r="5"/><path d="m8.5 13.5-1 7.5 4.5-2.5 4.5 2.5-1-7.5"/>',
        'trophy' => '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3M10 19h4M12 14v5M8 21h8"/>',
        'crown' => '<path d="M4 17h16M4 17 3 8l5 3.5L12 5l4 6.5L21 8l-1 9z"/>',
        'certificate' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M7 8h10M7 11h5"/><path d="m12 16-2 5 2-1.2L14 21z"/>',
        'streak' => '<path d="M12 3s5 4.2 5 9a5 5 0 0 1-10 0c0-2 1-3.6 2-4.6 0 1.8 1 2.6 2 2.6 1.4 0 1-4.2 1-7z"/>',
        'war' => '<path d="m5 4 9 9M4 14l6 6M19 4l-9 9M20 14l-6 6"/><path d="m10 13 1 1"/>',
        'game' => '<rect x="2.5" y="7" width="19" height="10" rx="4"/><path d="M7 10v4M5 12h4M15.5 11h.01M18 13.5h.01"/>',
        'level' => '<path d="M4 20V4M4 20h16"/><path d="M8 20v-5h3v5M13 20V9h3v11"/>',

        // ---------------------------------------------- الحساب والتواصل
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'people' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19a6 6 0 0 1 12 0"/><circle cx="17.5" cy="7" r="2.4"/><path d="M15.8 12.8A5.2 5.2 0 0 1 21 18"/>',
        'envelope' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="m3.5 7.5 8.5 6 8.5-6"/>',
        'bell' => '<path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 14 6 10z"/><path d="M10.5 19a1.8 1.8 0 0 0 3 0"/>',
        'phone' => '<path d="M5 4h4l1.6 4-2.2 1.6a12 12 0 0 0 6 6L16 13.4 20 15v4a1 1 0 0 1-1.1 1A16 16 0 0 1 4 5.1 1 1 0 0 1 5 4z"/>',
        'complaint' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M12 8v3.5M12 13.5h.01"/>',
        'announcement' => '<path d="M4 10v4h3l7 4V6l-7 4z"/><path d="M17.5 9.5a4 4 0 0 1 0 5"/>',
        'referral' => '<circle cx="7" cy="12" r="2.5"/><circle cx="17" cy="6.5" r="2.5"/><circle cx="17" cy="17.5" r="2.5"/><path d="m9.2 10.8 5.6-3M9.2 13.2l5.6 3"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m15.5 15.5 4 4"/>',

        // ---------------------------------------------- الوقت والحالة
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.5V12l3 1.8"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'event' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'hourglass' => '<path d="M7 3h10M7 21h10"/><path d="M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9"/>',
        'deadline' => '<circle cx="12" cy="13" r="7"/><path d="M12 9.5V13l2.5 1.5M9 2h6"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'warning' => '<path d="M12 4 3 20h18z"/><path d="M12 10v4M12 17h.01"/>',
        'blocked' => '<circle cx="12" cy="12" r="8"/><path d="m6.5 6.5 11 11"/>',
        'lock' => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'unlock' => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 7.5-1.8"/>',
        'shield' => '<path d="M12 3 5 6v6c0 4.2 3 7.5 7 9 4-1.5 7-4.8 7-9V6z"/>',
        'eye' => '<path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.6"/>',

        // ---------------------------------------------- التنقّل والأفعال
        'home' => '<path d="m4 11 8-7 8 7v9H4z"/><path d="M10 20v-6h4v6"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="8" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="11" width="7" height="10" rx="1.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M4.2 7.5l2.2 1.3M17.6 15.2l2.2 1.3M4.2 16.5l2.2-1.3M17.6 8.8l2.2-1.3"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="m7 15 3.5-4 3 2.5L20 7"/>',
        'refresh' => '<path d="M20 11a8 8 0 1 0-.9 4.6"/><path d="M20 5v6h-6"/>',
        'link' => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.3-2.3a4 4 0 0 0-5.7-5.7L11.7 6.6"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.3 2.3a4 4 0 0 0 5.7 5.7l1.1-1.1"/>',
        'download' => '<path d="M12 4v10M8 10.5l4 4 4-4"/><path d="M5 19h14"/>',
        'upload' => '<path d="M12 20V10M8 13.5l4-4 4 4"/><path d="M5 5h14"/>',
        'top' => '<path d="M12 19V6M6 12l6-6 6 6"/>',
        'arrow' => '<path d="M14 6l-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash' => '<path d="M5 7h14M10 7V5h4v2"/><path d="M6.5 7 7.5 20h9l1-13M10 11v5M14 11v5"/>',
        'attachment' => '<path d="M20 11.5 12 19.5a4.5 4.5 0 0 1-6.4-6.4L14 4.7a3 3 0 0 1 4.3 4.3L10 17.3a1.5 1.5 0 0 1-2.2-2.1l7.4-7.4"/>',
        'question' => '<circle cx="12" cy="12" r="8"/><path d="M9.8 9.5A2.3 2.3 0 0 1 14 10.4c0 1.6-2 1.9-2 3.3M12 16.6h.01"/>',
        'info' => '<circle cx="12" cy="12" r="8"/><path d="M12 11v5M12 8h.01"/>',
        'celebrate' => '<path d="m4 20 5-12 7 7z"/><path d="M15 4.5v2M19.5 9h-2M18.5 5.5 17 7"/>',
        'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.5 2.6 2.5 12.4 0 16-2.5-3.6-2.5-13.4 0-16z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'spark' => '<path d="M12 4v4M12 16v4M4 12h4M16 12h4M6.5 6.5 9 9M15 15l2.5 2.5M17.5 6.5 15 9M9 15l-2.5 2.5"/>',
    ];

    /* المرادفات: أسماءٌ عربيّة/إنجليزيّة مختلفة ⟵ **مفهوم واحد** ⟵ أيقونة واحدة */
    $aliases = [
        'مهمة' => 'task', 'مهمّة' => 'task', 'todo' => 'task',
        'اجتماع' => 'meeting', 'meetings' => 'meeting',
        'معاملة' => 'transaction', 'transactions' => 'transaction', 'ledger' => 'transaction',
        'كيان' => 'entity', 'entities' => 'entity', 'building' => 'entity',
        'شهادة' => 'certificate', 'certificates' => 'certificate', 'diploma' => 'certificate',
        'تصعيد' => 'escalation', 'escalate' => 'escalation',
        'مساهمة' => 'contribution', 'contributions' => 'contribution', 'heart' => 'contribution',
        'تذكرة' => 'ticket', 'tickets' => 'ticket',
        'محفظة' => 'wallet', 'balance' => 'wallet',
        'تدريب' => 'course', 'courses' => 'course',
        'درس' => 'lesson', 'lessons' => 'lesson', 'play' => 'lesson',
        'حرب' => 'war', 'wars' => 'war', 'challenge' => 'war', 'challenges' => 'war', 'swords' => 'war',
        'شارة' => 'badge', 'badges' => 'badge', 'medal' => 'badge',
        'إشعار' => 'bell', 'notification' => 'bell', 'notifications' => 'bell',
        'users' => 'people', 'team' => 'people', 'group' => 'people', 'network' => 'people',
        'free' => 'xp', 'star' => 'xp', 'points' => 'xp',
        'file' => 'document', 'doc' => 'document', 'pdf' => 'document',
        'time' => 'clock', 'timer' => 'clock', 'pending' => 'hourglass',
        'stats' => 'chart', 'analytics' => 'chart', 'report' => 'chart', 'reports' => 'chart',
        'gear' => 'settings', 'system' => 'settings', 'tools' => 'settings',
        'mail' => 'envelope', 'message' => 'envelope', 'inbox' => 'envelope',
        'cross' => 'close', 'x' => 'close',
        'danger' => 'warning', 'alert' => 'warning',
        'gift' => 'ticket', 'invite' => 'referral', 'invites' => 'referral',
        'club' => 'streak', 'fire' => 'streak', 'sunrise' => 'streak',
        'focus' => 'shield', 'guard' => 'shield', 'security' => 'shield',
        'cart' => 'store', 'shop' => 'store', 'product' => 'store',
        'seat' => 'placement', 'pin' => 'placement', 'location' => 'placement',
        'cv' => 'document', 'attestation' => 'certificate',
        'balance-scale' => 'evaluation', 'scale' => 'evaluation', 'judge' => 'evaluation',
        'first' => 'trophy', 'winner' => 'trophy', 'leaderboard' => 'trophy',
    ];

    $key = $aliases[$name] ?? $name;
    $body = $paths[$key] ?? $paths['spark'];
    $px = (int) $size;
@endphp

<svg viewBox="0 0 24 24" width="{{ $px }}" height="{{ $px }}" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif
     {{ $attributes->merge(['class' => 'inline-block shrink-0 align-middle']) }}>
    {!! $body !!}
</svg>
