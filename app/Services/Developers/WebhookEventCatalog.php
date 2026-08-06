<?php

namespace App\Services\Developers;

/**
 * كتالوج الأحداث المقفول (12.15-ب) — **لا حدث حرّ يُكتَب يدويًّا**، بنفس
 * فلسفة `ApiEndpointCatalog` لتاب API: مصدر الحقيقة الوحيد لِما يمكن أن
 * يشترك فيه ويب-هوك هو هذا الصنف، لا نصٌّ في الفورم قد يشيخ عن الكود (2.11).
 *
 * الأحداث الخمسة المنصوصة حرفيًّا (12.15-ب) — توسيعها لاحقًا لا يكسر التوافق
 * (ويب-هوكات قديمة تبقى مشتركةً بأحداثها كما اختيرت وقتها).
 */
class WebhookEventCatalog
{
    /** @var list<string> */
    public const EVENT_KEYS = [
        'user.registered',
        'certificate.issued',
        'badge.awarded',
        'membership.activated',
        'order.paid',
    ];

    /** تسميات الأحداث كما يقرؤها المسؤول — عبر `setting()` لا نصًّا محروقًا (2.13-أ) */
    public static function labels(): array
    {
        return [
            'user.registered' => (string) setting('developers.webhooks.events.user_registered', 'مستخدم جديد سجّل حسابًا'),
            'certificate.issued' => (string) setting('developers.webhooks.events.certificate_issued', 'شهادة صدرت'),
            'badge.awarded' => (string) setting('developers.webhooks.events.badge_awarded', 'شارة مُنِحت'),
            'membership.activated' => (string) setting('developers.webhooks.events.membership_activated', 'حساب فُعِّل'),
            'order.paid' => (string) setting('developers.webhooks.events.order_paid', 'طلب دُفع'),
        ];
    }
}
