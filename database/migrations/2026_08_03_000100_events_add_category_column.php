<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فلتر «التصنيف» في شاشة الفعاليّات (24.5) يحتاج عمودًا للتصنيف،
     * والمخطّط الأصليّ لا يحمله — فيُضاف بمايجريشن جديد بلا مساس بالقديم.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('category', 48)->nullable()->index()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
