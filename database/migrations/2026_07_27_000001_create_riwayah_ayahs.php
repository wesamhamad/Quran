<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // آيات الروايات غير حفص (ورش، قالون…) — نص يونيكود بخط الرواية، صفحة بصفحة.
        // حفص يبقى بنظام QCF (جدول words) ولا يُخزَّن هنا.
        Schema::create('riwayah_ayahs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('riwayah', 16)->index();       // warsh | qaloon | shouba | …
            $table->unsignedSmallInteger('page')->index();
            $table->unsignedTinyInteger('jozz')->nullable();
            $table->unsignedSmallInteger('sura_no');
            $table->unsignedSmallInteger('aya_no');
            $table->unsignedTinyInteger('line_start')->nullable();
            $table->unsignedTinyInteger('line_end')->nullable();
            $table->string('sura_name_ar')->nullable();
            $table->text('aya_text');
            $table->index(['riwayah', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayah_ayahs');
    }
};
