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
 *
 * ⭐ الوصف نفسه يُقرَأ عبر `setting()` **حرفيًّا داخل `forDisplay()`** لا من
 * نصٍّ ثابتٍ في `ENDPOINTS` — فحارس `settings:hardcoded` (2.13-أ) يتعرّف على
 * نداء `setting('مفتاح', 'افتراضيّ')` وحده، لا على نصٍّ عربيّ يمرّ به لاحقًا.
 */
class ApiEndpointCatalog
{
    /** @var list<array{method:string,path:string,scope:?string,description_key:string}> */
    public const ENDPOINTS = [
        ['method' => 'GET', 'path' => '/api/v1/ping', 'scope' => null, 'description_key' => 'developers.api.doc.ping'],
        ['method' => 'GET', 'path' => '/api/v1/courses', 'scope' => 'read:courses', 'description_key' => 'developers.api.doc.courses'],
        ['method' => 'GET', 'path' => '/api/v1/certificates/{code}/verify', 'scope' => 'read:certificates', 'description_key' => 'developers.api.doc.certificates_verify'],
    ];

    /** الكتالوج بأوصافٍ مُترجَمة عبر `setting()` — جاهزٌ للعرض في الشاشة */
    public static function forDisplay(): array
    {
        return array_map(
            fn (array $endpoint) => [...$endpoint, 'description' => self::description($endpoint['description_key'])],
            self::ENDPOINTS,
        );
    }

    private static function description(string $key): string
    {
        return match ($key) {
            'developers.api.doc.ping' => (string) setting('developers.api.doc.ping', 'فحص صلاحيّة المفتاح — يردّ OK باسم المفتاح.'),
            'developers.api.doc.courses' => (string) setting('developers.api.doc.courses', 'قائمة التدريبات المنشورة (id · name_ar · slug) بصفحات.'),
            'developers.api.doc.certificates_verify' => (string) setting('developers.api.doc.certificates_verify', 'حالة شهادة بكودها: سارية/منتهية/مُلغاة/غير موجودة.'),
            default => '',
        };
    }
}
