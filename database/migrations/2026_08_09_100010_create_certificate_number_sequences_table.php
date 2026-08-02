<?php

use App\Models\Certificate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عدّاد ترقيم الشهادات (12.5-ب): «بضمان **عدم التكرار** ولا الفجوات».
 *
 * كان التسلسل يُشتقّ من الصفوف القائمة، فحذفُ شهادةٍ أو إعادةُ بذرٍ تُعيد
 * إنتاج **نفس الكود** لشخصٍ آخر — والكود هو ما تتحقّق به الجهات (8.1)،
 * فتكرارُه بابُ تزوير. العدّاد المستقلّ لا يرجع للخلف أبدًا: الرقم الذي
 * صدر مرّةً لا يصدر ثانيةً ولو مُحِي صفّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_number_sequences', function (Blueprint $table) {
            $table->id();
            // نطاق الترقيم = البادئة + السنة (مثال: `HC-2026-`) — لكلّ نوع/اعتماد نطاقه
            $table->string('scope', 64)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // البدء من أعلى رقمٍ صادرٍ فعلًا كي لا يصطدم العدّاد بما سبقه
        $rows = [];

        foreach (Certificate::query()->pluck('code') as $code) {
            if (! preg_match('/^(.*[^0-9])(\d+)$/', (string) $code, $matches)) {
                continue;
            }

            $scope = $matches[1];
            $number = (int) $matches[2];
            $rows[$scope] = max($rows[$scope] ?? 0, $number);
        }

        foreach ($rows as $scope => $number) {
            DB::table('certificate_number_sequences')->insert([
                'scope' => $scope,
                'last_number' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_number_sequences');
    }
};
