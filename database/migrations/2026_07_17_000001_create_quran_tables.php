<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // السور — المفتاح هو رقم السورة 1..114
        Schema::create('surahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name_arabic');
            $table->string('name_simple');
            $table->string('name_complex')->nullable();
            $table->string('translated_name')->nullable();
            $table->string('revelation_place')->nullable();  // makkah | madinah
            $table->unsignedSmallInteger('revelation_order')->nullable();
            $table->boolean('bismillah_pre')->default(true);
            $table->unsignedSmallInteger('verses_count');
            $table->unsignedSmallInteger('page_start')->nullable();
            $table->unsignedSmallInteger('page_end')->nullable();
        });

        // الآيات — المفتاح هو الرقم العالمي 1..6236
        Schema::create('ayahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary(); // number_global
            $table->unsignedSmallInteger('surah_id');
            $table->unsignedSmallInteger('number_in_surah');
            $table->string('verse_key', 12)->unique();      // مثال: "2:255"
            $table->text('text_uthmani');
            $table->unsignedSmallInteger('page_number')->index();
            $table->unsignedTinyInteger('juz_number')->nullable();
            $table->unsignedTinyInteger('hizb_number')->nullable();
            $table->unsignedTinyInteger('sajdah_number')->nullable();
            $table->foreign('surah_id')->references('id')->on('surahs')->cascadeOnDelete();
            $table->index(['surah_id', 'number_in_surah']);
        });

        // الكلمات — لرسم الصفحة سطر‑بسطر بخط QCF + الكلمة‑بكلمة
        Schema::create('words', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('ayah_id');
            $table->unsignedSmallInteger('position');        // ترتيب الكلمة داخل الآية
            $table->string('text_uthmani')->nullable();
            $table->string('code_v2', 16)->nullable();       // رمز glyph لخط QCF v2
            $table->string('char_type', 16)->default('word'); // word | end
            $table->unsignedSmallInteger('page_number')->index();
            $table->unsignedTinyInteger('line_number')->nullable();
            $table->string('translation')->nullable();
            $table->string('transliteration')->nullable();
            $table->foreign('ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->index(['page_number', 'line_number']);
        });

        // التفاسير
        Schema::create('tafsirs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('author_name')->nullable();
            $table->string('language', 8)->default('ar');
        });

        Schema::create('tafsir_texts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tafsir_id');
            $table->unsignedSmallInteger('ayah_id');
            $table->longText('text');
            $table->foreign('tafsir_id')->references('id')->on('tafsirs')->cascadeOnDelete();
            $table->foreign('ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->unique(['tafsir_id', 'ayah_id']);
        });

        // الترجمات
        Schema::create('translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('resource_id')->nullable();
            $table->string('name')->nullable();
            $table->string('language', 8)->default('en');
            $table->unsignedSmallInteger('ayah_id');
            $table->longText('text');
            $table->foreign('ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->index(['resource_id', 'ayah_id']);
        });

        // القرّاء والصوتيات
        Schema::create('reciters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('style')->nullable();     // murattal | mujawwad
            $table->string('base_url')->nullable();
        });

        Schema::create('audio_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reciter_id');
            $table->unsignedSmallInteger('ayah_id');
            $table->string('url');
            $table->json('segments')->nullable();     // توقيت الكلمات للتظليل
            $table->foreign('reciter_id')->references('id')->on('reciters')->cascadeOnDelete();
            $table->foreign('ayah_id')->references('id')->on('ayahs')->cascadeOnDelete();
            $table->unique(['reciter_id', 'ayah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_files');
        Schema::dropIfExists('reciters');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('tafsir_texts');
        Schema::dropIfExists('tafsirs');
        Schema::dropIfExists('words');
        Schema::dropIfExists('ayahs');
        Schema::dropIfExists('surahs');
    }
};
