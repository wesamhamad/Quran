<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ayahs', function (Blueprint $table) {
            // نص عثماني مُعلَّم بقواعد التجويد (<tajweed class=...>) لتلوينه بالـ CSS
            $table->longText('text_tajweed')->nullable()->after('text_uthmani');
        });
    }

    public function down(): void
    {
        Schema::table('ayahs', function (Blueprint $table) {
            $table->dropColumn('text_tajweed');
        });
    }
};
