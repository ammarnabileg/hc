<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة سلّم الخمول لكلّ متطوّع (13.4-س-ب).
 *
 * لماذا جدول مستقلّ؟ لأنّ السلّم منصوص بخطوتين متتاليتين — **تنبيه واحد** ثمّ
 * **خصم أسبوعيّ متكرّر** — ولا يمكن استنتاج «هل نُبِّه من قبل؟» ولا «متى كان
 * آخر خصم؟» من سجلّ الحركات وحده بلا تخمين. فهنا مرساة الحلقة، وبها يتوقّف
 * كلّ شيء **فور عودة النشاط** (يُمسَح الصفّ فيبدأ السلّم من أوّله لو خمل ثانيةً).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_inactivity_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            // لحظة التنبيه الواحد — وجودها يمنع تكراره يوميًّا
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('last_deduction_at')->nullable();
            $table->unsignedSmallInteger('deductions_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_inactivity_states');
    }
};
