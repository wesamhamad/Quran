<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('surahs', fn (Blueprint $t) => $t->string('name_uthmani')->nullable()->after('name_arabic'));
    }
    public function down(): void {
        Schema::table('surahs', fn (Blueprint $t) => $t->dropColumn('name_uthmani'));
    }
};
