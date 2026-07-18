<?php

namespace App\Console\Commands;

use App\Models\AudioFile;
use App\Models\Ayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportAudioSegments extends Command
{
    protected $signature = 'quran:audio-segments {reciter : معرّف القارئ في quran.com (7=العفاسي)}';

    protected $description = 'استيراد توقيت الكلمات (segments) من quran.com لتظليل الكلمة أثناء التلاوة';

    public function handle(): int
    {
        $reciterId = (int) $this->argument('reciter');
        $keyToId = Ayah::pluck('id', 'verse_key');

        $this->info("استيراد توقيت الكلمات للقارئ #{$reciterId}…");
        $bar = $this->output->createProgressBar(604);
        $bar->start();
        $count = 0;

        for ($page = 1; $page <= 604; $page++) {
            $res = Http::retry(3, 1500)->timeout(30)->acceptJson()
                ->get("https://api.quran.com/api/v4/recitations/{$reciterId}/by_page/{$page}", [
                    'fields'   => 'segments',
                    'per_page' => 50,
                ]);
            if ($res->ok()) {
                foreach ($res->json('audio_files', []) as $a) {
                    $ayahId = $keyToId[$a['verse_key'] ?? ''] ?? null;
                    if (! $ayahId || empty($a['segments'])) {
                        continue;
                    }
                    AudioFile::where('reciter_id', $reciterId)->where('ayah_id', $ayahId)
                        ->update(['segments' => $a['segments']]);
                    $count++;
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✔ {$count} آية بتوقيت كلمات");
        return self::SUCCESS;
    }
}
