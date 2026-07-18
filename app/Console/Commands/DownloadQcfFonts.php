<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DownloadQcfFonts extends Command
{
    protected $signature = 'quran:fonts
        {--pages= : نطاق الصفحات مثل 1-604 (الافتراضي كامل)}
        {--force : إعادة التحميل حتى لو الملف موجود}';

    protected $description = 'تحميل خطوط QCF v2 (604 خط) وتوليد ملف @font-face';

    private string $cdn = 'https://quran.com/fonts/quran/hafs/v2/woff2';

    public function handle(): int
    {
        $dir = public_path('fonts/qcf/v2');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        [$from, $to] = $this->pageRange();
        $this->info("تحميل خطوط QCF v2 للصفحات {$from}–{$to}…");
        $bar = $this->output->createProgressBar($to - $from + 1);
        $bar->start();

        $downloaded = 0;
        $skipped = 0;
        for ($p = $from; $p <= $to; $p++) {
            $path = "{$dir}/p{$p}.woff2";
            if (! $this->option('force') && file_exists($path) && filesize($path) > 0) {
                $skipped++;
                $bar->advance();
                continue;
            }
            $res = Http::retry(3, 1500)->timeout(30)->get("{$this->cdn}/p{$p}.woff2");
            if ($res->successful()) {
                file_put_contents($path, $res->body());
                $downloaded++;
            } else {
                $this->newLine();
                $this->warn("  تعذّر تحميل الصفحة {$p} (HTTP {$res->status()})");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  ✔ حُمِّل: {$downloaded} | موجود مسبقاً: {$skipped}");

        $this->generateCss();
        return self::SUCCESS;
    }

    private function pageRange(): array
    {
        if ($opt = $this->option('pages')) {
            $parts = array_map('intval', explode('-', $opt));
            return [$parts[0], $parts[1] ?? $parts[0]];
        }
        return [1, 604];
    }

    private function generateCss(): void
    {
        $css = "/* توليد تلقائي — خطوط QCF v2 (مصحف المدينة، مجمع الملك فهد) */\n";
        for ($p = 1; $p <= 604; $p++) {
            // font-display: block — يُخفي النص حتى يجهز خط QCF (يتفادى ظهور الرموز المنفصلة أولاً)
            $css .= sprintf(
                "@font-face{font-family:'p%d';src:url('/fonts/qcf/v2/p%d.woff2') format('woff2');font-display:block;}\n",
                $p, $p
            );
        }
        file_put_contents(public_path('fonts/qcf/v2/qcf.css'), $css);
        $this->line('  ✔ وُلِّد ملف qcf.css (604 @font-face)');
    }
}
