<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: طلبٌ بمفتاح مفقود أو خاطئ كان لا يُسجَّل إطلاقًا (12.15-ج:
 * «كلّ طلب API مُسجَّل — لا استثناء صامت») لأنّ `api_key_id` كان غير Nullable
 * فيمنع تسجيل أيّ محاولة بلا مفتاح مُتحقَّقٍ منه بنيويًّا. سجلّ القرارات 25.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table): void {
            $table->foreignId('api_key_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table): void {
            $table->foreignId('api_key_id')->nullable(false)->change();
        });
    }
};
