<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use App\Models\Tafsir;
use App\Models\TafsirText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportTafsirSpa5k extends Command
{
    protected $signature = 'quran:tafsir-spa5k
        {slug : slug تفسيرنا في القاعدة (مثل ar-tafseer-al-saddi)}
        {edition : slug المصدر في spa5k (مثل ar-tafsir-as-saadi)}
        {--name= : تحديث اسم التفسير}';

    protected $description = 'استيراد تفسير كامل مجمّع من spa5k/tafsir_api (يعالج التفاسير المبتورة)';

    public function handle(): int
    {
        $tafsir = Tafsir::where('slug', $this->argument('slug'))->first();
        if (! $tafsir) {
            $this->error('لم يُعثر على التفسير: '.$this->argument('slug'));
            return self::FAILURE;
        }
        if ($name = $this->option('name')) {
            $tafsir->update(['name' => $name]);
        }

        $edition = $this->argument('edition');
        $base = "https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir/{$edition}";

        $ayahs = Ayah::get(['id', 'verse_key'])->all();
        $this->info("استيراد {$tafsir->name} من spa5k ({$edition})…");
        $bar = $this->output->createProgressBar(count($ayahs));
        $bar->start();
        $count = 0;

        foreach (array_chunk($ayahs, 40) as $chunk) {
            // طلبات متزامنة لتسريع الجلب
            $responses = Http::pool(function ($pool) use ($chunk, $base) {
                foreach ($chunk as $a) {
                    [$s, $v] = explode(':', $a->verse_key);
                    $pool->as($a->verse_key)->timeout(30)->get("{$base}/{$s}/{$v}.json");
                }
            });

            $rows = [];
            foreach ($chunk as $a) {
                $res = $responses[$a->verse_key] ?? null;
                $text = $res && $res->ok() ? (string) $res->json('text', '') : '';
                if ($text === '') {
                    continue;
                }
                // تحويل الأسطر إلى <br> للعرض
                $rows[] = [
                    'tafsir_id' => $tafsir->id,
                    'ayah_id'   => $a->id,
                    'text'      => nl2br(trim($text), false),
                ];
            }
            if ($rows) {
                TafsirText::upsert($rows, ['tafsir_id', 'ayah_id'], ['text']);
                $count += count($rows);
            }
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("✔ {$count} نص تفسير كامل ({$tafsir->name})");
        return self::SUCCESS;
    }
}
