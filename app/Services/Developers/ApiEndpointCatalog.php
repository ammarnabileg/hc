<?php

namespace App\Services\Developers;

/**
 * كتالوج نقاط النهاية (Endpoints) — **ثابتٌ في الكود لا نصّ حرّ يُكتَب يدويًّا
 * فيُنسى تحديثه** (12.15-أ). صفحة التوثيق المرجعيّة في تاب API تعرض هذا
 * الكتالوج كجدول Read-only، فمصدر الحقيقة الوحيد لما هو متاح فعلًا هو هذا
 * الصنف — لا وثيقةٌ منفصلة قد تشيخ عن الكود (2.11).
 *
 * أُضيفت هنا نقاط النهاية الثلاث الحقيقيّة فقط (لا شكليّة) التي تُثبت أنّ
 * النظام يعمل من طرفٍ لطرف؛ والبقيّة تُضاف لاحقًا بلا كسر التوافق.
 */
class ApiEndpointCatalog
{
    /**
     * @var list<array{method:string,path:string,scope:?string,description_key:string,description_default:string}>
     */
    public const ENDPOINTS = [
        [
            'method' => 'GET',
            'path' => '/api/v1/ping',
            'scope' => null,
            'description_key' => 'developers.api.doc.ping',
            'description_default' => 'فحص صلاحيّة المفتاح — يردّ OK باسم المفتاح.',
        ],
        [
            'method' => 'GET',
            'path' => '/api/v1/courses',
            'scope' => 'read:courses',
            'description_key' => 'developers.api.doc.courses',
            'description_default' => 'قائمة التدريبات المنشورة (id · name_ar · slug) بصفحات.',
        ],
        [
            'method' => 'GET',
            'path' => '/api/v1/certificates/{code}/verify',
            'scope' => 'read:certificates',
            'description_key' => 'developers.api.doc.certificates_verify',
            'description_default' => 'حالة شهادة بكودها: سارية/منتهية/مُلغاة/غير موجودة.',
        ],
    ];

    /** الكتالوج بأوصافٍ مُترجَمة عبر `setting()` — جاهزٌ للعرض في الشاشة */
    public static function forDisplay(): array
    {
        return array_map(
            fn (array $endpoint) => [
                ...$endpoint,
                'description' => (string) setting($endpoint['description_key'], $endpoint['description_default']),
            ],
            self::ENDPOINTS,
        );
    }
}
