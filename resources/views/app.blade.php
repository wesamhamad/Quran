<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f8f7;
            }

            html.dark {
                background-color: #0d1210;
            }
        </style>

        <link rel="icon" href="/qu-logo.webp" type="image/webp">
        <link rel="apple-touch-icon" href="/og-image.png">

        {{-- معاينة المشاركة (Open Graph / Twitter) — شعار جامعة القصيم ووصف عربي --}}
        <meta name="description" content="المصحف الإلكتروني لجامعة القصيم — اقرأ القرآن الكريم بالرسم العثماني (مصحف المدينة) مع التلاوة الصوتية بأصوات مشاهير القرّاء، التفاسير وترجمة المعاني، البحث، ووضع الحفظ.">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="جامعة القصيم">
        <meta property="og:locale" content="ar_SA">
        <meta property="og:title" content="المصحف الإلكتروني — جامعة القصيم">
        <meta property="og:description" content="القرآن الكريم بالرسم العثماني (مصحف المدينة) مع التلاوة الصوتية، التفاسير والترجمة، البحث، ووضع الحفظ — مشروع جامعة القصيم.">
        <meta property="og:image" content="{{ url('/og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="المصحف الإلكتروني — جامعة القصيم">
        <meta name="twitter:description" content="القرآن الكريم بالرسم العثماني مع التلاوة والتفسير والبحث — جامعة القصيم.">
        <meta name="twitter:image" content="{{ url('/og-image.png') }}">

        @fonts

        {{-- خطوط QCF v2 (مصحف المدينة) --}}
        <link rel="stylesheet" href="/fonts/qcf/v2/qcf.css">

        {{-- خط أميري للنصوص القرآنية العامة (التفسير/البحث/صورة المشاركة) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
