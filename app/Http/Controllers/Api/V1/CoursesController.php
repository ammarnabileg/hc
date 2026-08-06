<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/courses` — Scope `read:courses` (12.15-أ).
 * التدريبات **المنشورة فقط**، بحقولٍ عامّة لا حسّاسة، بصفحات.
 */
class CoursesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // مصدر الحقيقة الوحيد لـ«منشور»: نفس الشرط المستخدَم في المتجر ولوحة القيادة (2.11)
        $courses = Course::query()
            ->where('status', 'published')
            ->orderBy('name_ar')
            ->paginate((int) $request->integer('per_page', 20), ['id', 'name_ar', 'slug']);

        return response()->json($courses);
    }
}
