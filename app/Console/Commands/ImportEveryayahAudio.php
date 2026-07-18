<?php

namespace App\Console\Commands;

use App\Models\AudioFile;
use App\Models\Ayah;
use App\Models\Reciter;
use Illuminate\Console\Command;

class ImportEveryayahAudio extends Command
{
    protected $signature = 'quran:audio-everyayah
        {--id= : معرّف القارئ في قاعدتنا}
        {--name= : اسم القارئ بالعربي}
        {--folder= : مجلّد everyayah مثل MaherAlMuaiqly128kbps}
        {--style=murattal : نوع التلاوة}';

    protected $description = 'استيراد تلاوة آيةً‑بآية من everyayah.com (روابط حتمية بلا API)';

    public function handle(): int
    {
        $id = (int) $this->option('id');
        $name = $this->option('name');
        $folder = $this->option('folder');

        if (! $id || ! $name || ! $folder) {
            $this->error('مطلوب: --id و --name و --folder');
            return self::FAILURE;
        }

        $base = "https://everyayah.com/data/{$folder}/";
        $reciter = Reciter::updateOrCreate(
            ['id' => $id],
            ['name' => $name, 'style' => $this->option('style'), 'base_url' => $base]
        );

        $this->info("استيراد تلاوة: {$name}…");
        $rows = [];
        foreach (Ayah::get(['id', 'verse_key']) as $a) {
            [$s, $v] = explode(':', $a->verse_key);
            $file = sprintf('%03d%03d.mp3', $s, $v);
            $rows[] = [
                'reciter_id' => $reciter->id,
                'ayah_id'    => $a->id,
                'url'        => $base.$file,
                'segments'   => null,
            ];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            AudioFile::upsert($chunk, ['reciter_id', 'ayah_id'], ['url']);
        }

        $this->info('✔ '.count($rows)." ملف صوتي ({$name})");
        return self::SUCCESS;
    }
}
