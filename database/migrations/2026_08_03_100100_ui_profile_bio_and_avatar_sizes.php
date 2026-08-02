<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // نبذة البروفايل (10) — قابلة للتعديل من الهيدر بحفظ تلقائيّ
            $table->text('bio')->nullable()->after('avatar_path');

            /*
             * مقاسات الأفاتار الثلاثة (2.7): 500 للعرض الكبير · 150 للكروت
             * · 50 للأكوام. نخزّن المسارات لأنّ الاشتقاق بالاسم يكسر مع أيّ
             * تغيير في التخزين، والقراءة يجب أن تكون بلا فحص ملفّات.
             */
            $table->json('avatar_sizes')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'avatar_sizes']);
        });
    }
};
