<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use App\Models\AudioFile;
use App\Models\Reciter;
use App\Models\Surah;
use App\Models\Tafsir;
use App\Models\TafsirText;
use App\Models\Translation;
use App\Models\Word;
use App\Support\ArabicText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportQuran extends Command
{
    protected $signature = 'quran:import
        {--surahs-only : استيراد السور فقط}
        {--pages= : نطاق الصفحات مثل 1-10 (الافتراضي 1-604)}
        {--tafsir= : معرّف تفسير لاستيراده (14=ابن كثير، 91=السعدي، 16=الميسّر)}
        {--translation= : معرّف ترجمة لاستيرادها (20=صحيح انترناشونال)}
        {--audio= : معرّف قارئ لاستيراد صوتياته (7=العفاسي، 2=عبدالباسط)}
        {--search : بناء فهرس البحث (نص مبسّط بدون تشكيل)}';

    protected $description = 'استيراد القرآن (رسم عثماني + رموز QCF + صفحات) من api.quran.com';

    private string $api = 'https://api.quran.com/api/v4';

    public function handle(): int
    {
        $this->importSurahs();

        if (! $this->option('surahs-only')) {
            [$from, $to] = $this->pageRange();
            $this->importPages($from, $to);
        }

        if ($tafsirId = $this->option('tafsir')) {
            $this->importTafsir((int) $tafsirId);
        }

        if ($translationId = $this->option('translation')) {
            $this->importTranslation((int) $translationId);
        }

        if ($audioId = $this->option('audio')) {
            $this->importAudio((int) $audioId);
        }

        if ($this->option('search')) {
            $this->buildSearchIndex();
        }

        $this->newLine();
        $this->info('✔ تم الاستيراد بنجاح.');
        return self::SUCCESS;
    }

    private function get(string $path, array $query = []): array
    {
        $res = Http::retry(3, 1500)->timeout(30)->acceptJson()
            ->get("{$this->api}/{$path}", $query);
        $res->throw();
        return $res->json();
    }

    private function importSurahs(): void
    {
        $this->info('استيراد السور…');
        $data = $this->get('chapters', ['language' => 'ar']);
        $rows = [];
        foreach ($data['chapters'] as $c) {
            $rows[] = [
                'id'               => $c['id'],
                'name_arabic'      => $c['name_arabic'],
                'name_simple'      => $c['name_simple'],
                'name_complex'     => $c['name_complex'] ?? null,
                'translated_name'  => $c['translated_name']['name'] ?? null,
                'revelation_place' => $c['revelation_place'] ?? null,
                'revelation_order' => $c['revelation_order'] ?? null,
                'bismillah_pre'    => $c['bismillah_pre'] ?? true,
                'verses_count'     => $c['verses_count'],
                'page_start'       => $c['pages'][0] ?? null,
                'page_end'         => $c['pages'][1] ?? null,
            ];
        }
        Surah::upsert($rows, ['id']);
        $this->line('  ✔ '.count($rows).' سورة');
    }

    private function pageRange(): array
    {
        if ($opt = $this->option('pages')) {
            $parts = array_map('intval', explode('-', $opt));
            return [$parts[0], $parts[1] ?? $parts[0]];
        }
        return [1, 604];
    }

    private function importPages(int $from, int $to): void
    {
        $this->info("استيراد الصفحات {$from}–{$to} (رسم عثماني + رموز QCF)…");
        $bar = $this->output->createProgressBar($to - $from + 1);
        $bar->start();

        for ($page = $from; $page <= $to; $page++) {
            $this->importPage($page);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line('  ✔ الآيات: '.Ayah::count().' | الكلمات: '.Word::count());
    }

    private function importPage(int $page): void
    {
        $ayahRows = [];
        $wordRows = [];
        $ayahIds  = [];
        $nextPage = 1;

        do {
            $data = $this->get("verses/by_page/{$page}", [
                'words'       => 'true',
                'word_fields' => 'code_v2,text_uthmani,char_type_name,page_number,line_number',
                'fields'      => 'text_uthmani,page_number,juz_number,hizb_number,sajdah_number,chapter_id',
                'per_page'    => 50,
                'page'        => $nextPage,
            ]);

            foreach ($data['verses'] as $v) {
                [$surahId, $numInSurah] = array_map('intval', explode(':', $v['verse_key']));
                $ayahRows[] = [
                    'id'               => $v['id'],
                    'surah_id'         => $surahId,
                    'number_in_surah'  => $numInSurah,
                    'verse_key'        => $v['verse_key'],
                    'text_uthmani'     => $v['text_uthmani'] ?? '',
                    'page_number'      => $v['page_number'] ?? $page,
                    'juz_number'       => $v['juz_number'] ?? null,
                    'hizb_number'      => $v['hizb_number'] ?? null,
                    'sajdah_number'    => $v['sajdah_number'] ?? null,
                ];
                $ayahIds[] = $v['id'];

                foreach ($v['words'] as $w) {
                    $wordRows[] = [
                        'ayah_id'         => $v['id'],
                        'position'        => $w['position'],
                        'text_uthmani'    => $w['text_uthmani'] ?? null,
                        'code_v2'         => $w['code_v2'] ?? null,
                        'char_type'       => $w['char_type_name'] ?? 'word',
                        'page_number'     => $w['page_number'] ?? $page,
                        'line_number'     => $w['line_number'] ?? null,
                        'translation'     => $w['translation']['text'] ?? null,
                        'transliteration' => $w['transliteration']['text'] ?? null,
                    ];
                }
            }

            $nextPage = $data['pagination']['next_page'] ?? null;
        } while ($nextPage);

        DB::transaction(function () use ($ayahRows, $wordRows, $ayahIds) {
            Ayah::upsert($ayahRows, ['id']);
            // إعادة إدخال كلمات هذه الآيات (idempotent عند إعادة التشغيل)
            Word::whereIn('ayah_id', $ayahIds)->delete();
            foreach (array_chunk($wordRows, 500) as $chunk) {
                Word::insert($chunk);
            }
        });
    }

    private function importTafsir(int $tafsirId): void
    {
        $this->info("استيراد التفسير #{$tafsirId}…");

        // بيانات المورد
        $resources = $this->get('resources/tafsirs', ['language' => 'ar'])['tafsirs'];
        $meta = collect($resources)->firstWhere('id', $tafsirId);
        if (! $meta) {
            $this->error("  لم يُعثر على التفسير #{$tafsirId}");
            return;
        }

        // أسماء عربية موحّدة
        $arabicNames = [
            'ar-tafsir-ibn-kathir' => 'تفسير ابن كثير',
            'ar-tafseer-al-saddi'  => 'تفسير السعدي',
            'ar-tafsir-muyassar'   => 'التفسير الميسّر',
        ];
        $name = $arabicNames[$meta['slug']] ?? $meta['name'];

        $tafsir = Tafsir::updateOrCreate(
            ['slug' => $meta['slug']],
            ['name' => $name, 'author_name' => $meta['author_name'] ?? null, 'language' => 'ar']
        );

        $keyToId = Ayah::pluck('id', 'verse_key'); // verse_key => ayah_id
        $bar = $this->output->createProgressBar(114);
        $bar->start();
        $count = 0;

        for ($surah = 1; $surah <= 114; $surah++) {
            $nextPage = 1;
            do {
                $data = $this->get("tafsirs/{$tafsirId}/by_chapter/{$surah}", [
                    'per_page' => 50,
                    'page'     => $nextPage,
                ]);
                $rows = [];
                foreach ($data['tafsirs'] ?? [] as $t) {
                    $ayahId = $keyToId[$t['verse_key'] ?? ''] ?? null;
                    if (! $ayahId || empty($t['text'])) {
                        continue;
                    }
                    $rows[] = [
                        'tafsir_id' => $tafsir->id,
                        'ayah_id'   => $ayahId,
                        'text'      => $t['text'],
                    ];
                }
                if ($rows) {
                    TafsirText::upsert($rows, ['tafsir_id', 'ayah_id'], ['text']);
                    $count += count($rows);
                }
                $nextPage = $data['pagination']['next_page'] ?? null;
            } while ($nextPage);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  ✔ {$count} نص تفسير");
    }

    private function importTranslation(int $id): void
    {
        $this->info("استيراد الترجمة #{$id}…");

        $resources = $this->get('resources/translations')['translations'] ?? [];
        $meta = collect($resources)->firstWhere('id', $id);
        $name = $meta['name'] ?? "translation-{$id}";
        $lang = $meta['language_name'] ?? 'en';

        $keyToId = Ayah::pluck('id', 'verse_key');
        $bar = $this->output->createProgressBar(114);
        $bar->start();
        $count = 0;

        for ($surah = 1; $surah <= 114; $surah++) {
            $data = $this->get("quran/translations/{$id}", ['chapter_number' => $surah]);
            $rows = [];
            foreach ($data['translations'] ?? [] as $i => $t) {
                $ayahId = $keyToId[$surah.':'.($i + 1)] ?? null;
                if (! $ayahId || empty($t['text'])) {
                    continue;
                }
                $rows[] = [
                    'resource_id' => $id,
                    'name'        => $name,
                    'language'    => substr($lang, 0, 8),
                    'ayah_id'     => $ayahId,
                    'text'        => strip_tags(preg_replace('/<sup[^>]*>.*?<\/sup>/u', '', $t['text'])),
                ];
            }
            if ($rows) {
                DB::table('translations')->where('resource_id', $id)
                    ->whereIn('ayah_id', array_column($rows, 'ayah_id'))->delete();
                Translation::insert($rows);
                $count += count($rows);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  ✔ {$count} ترجمة ({$name})");
    }

    private function importAudio(int $reciterId): void
    {
        $this->info("استيراد صوتيات القارئ #{$reciterId}…");

        $resources = $this->get('resources/recitations', ['language' => 'ar'])['recitations'] ?? [];
        $meta = collect($resources)->firstWhere('id', $reciterId);
        $arabicReciters = [
            7 => 'مشاري راشد العفاسي',
            2 => 'عبد الباسط عبد الصمد',
            1 => 'عبد الباسط عبد الصمد (مجوّد)',
        ];
        $reciter = Reciter::updateOrCreate(
            ['id' => $reciterId],
            [
                'name'     => $arabicReciters[$reciterId] ?? ($meta['reciter_name'] ?? "reciter-{$reciterId}"),
                'style'    => $meta['style'] ?? null,
                'base_url' => 'https://verses.quran.com/',
            ]
        );

        $keyToId = Ayah::pluck('id', 'verse_key');
        $bar = $this->output->createProgressBar(114);
        $bar->start();
        $count = 0;

        for ($surah = 1; $surah <= 114; $surah++) {
            $nextPage = 1;
            do {
                $data = $this->get("recitations/{$reciterId}/by_chapter/{$surah}", [
                    'per_page' => 50,
                    'page'     => $nextPage,
                ]);
                $rows = [];
                foreach ($data['audio_files'] ?? [] as $a) {
                    $ayahId = $keyToId[$a['verse_key'] ?? ''] ?? null;
                    if (! $ayahId || empty($a['url'])) {
                        continue;
                    }
                    $rows[] = [
                        'reciter_id' => $reciter->id,
                        'ayah_id'    => $ayahId,
                        'url'        => $reciter->base_url.$a['url'],
                        'segments'   => null,
                    ];
                }
                if ($rows) {
                    AudioFile::upsert($rows, ['reciter_id', 'ayah_id'], ['url']);
                    $count += count($rows);
                }
                $nextPage = $data['pagination']['next_page'] ?? null;
            } while ($nextPage);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  ✔ {$count} ملف صوتي ({$reciter->name})");
    }

    private function buildSearchIndex(): void
    {
        $this->info('بناء فهرس البحث (تطبيع النص)…');
        $bar = $this->output->createProgressBar(Ayah::count());
        $bar->start();

        Ayah::select('id', 'text_uthmani')->chunkById(500, function ($ayahs) use ($bar) {
            foreach ($ayahs as $a) {
                DB::table('ayahs')->where('id', $a->id)
                    ->update(['text_plain' => ArabicText::normalize($a->text_uthmani)]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->line('  ✔ تم بناء فهرس البحث');
    }
}
