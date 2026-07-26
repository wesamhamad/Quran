<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المتشابهات اللفظية — روابط بين مقطع (آية أو مقطع متتابع) ونظيره المشابه.
        // كل صف يُسند إلى آية «المصدر» المرساة (أول آية في المقطع).
        Schema::create('ayah_similarities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('ayah_id');          // مرساة المصدر (1..6236)
            $table->unsignedSmallInteger('similar_ayah_id');  // مرساة المقطع المشابه
            $table->unsignedTinyInteger('src_span')->default(1);     // عدد آيات مقطع المصدر
            $table->unsignedTinyInteger('similar_span')->default(1); // عدد آيات المقطع المشابه
            $table->boolean('show_context')->default(false);  // تلميح ببداية الآية التالية (ctx)

            $table->foreign('ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->foreign('similar_ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->unique(['ayah_id', 'similar_ayah_id']);
            $table->index('ayah_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayah_similarities');
    }
};
