<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('users', function (Blueprint $table) {
            // الهويّة
            $table->string('code', 16)->nullable()->unique()->after('id');
            $table->string('phone', 32)->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->date('birthdate')->nullable();
            $table->string('gender', 16)->nullable();

            // الحالة: التفعيل باعتماد إداريّ ومجّانيّ (2.5-د)
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();

            // التلعيب (الطبقة الأساسيّة)
            $table->unsignedBigInteger('xp')->default(0)->index();
            $table->unsignedInteger('level')->default(1);

            // التفضيلات (2.15 · 2.14)
            $table->string('locale', 5)->default('ar');
            $table->string('theme', 16)->default('dark');
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('simple_mode')->default(true);
            $table->boolean('advanced_mode')->default(false);
            $table->json('pinned_pages')->nullable();
            $table->json('table_columns')->nullable();
            $table->json('last_tabs')->nullable();

            // التتبّع والموافقة (21.3)
            $table->string('tracking_consent', 16)->nullable();
            $table->timestamp('tracking_consent_at')->nullable();

            // النشاط
            $table->timestamp('last_seen_at')->nullable();
            $table->softDeletes();
        });
    }
};
