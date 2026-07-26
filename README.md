# المصحف الإلكتروني — جامعة القصيم

تطبيق ويب لعرض القرآن الكريم بالرسم العثماني (مصحف المدينة / خطوط QCF) مع التلاوة الصوتية،
تظليل الكلمة أثناء التلاوة، التفاسير والترجمة، البحث، وضع الحفظ، خطة الختمة، ومشاركة الآية كصورة.

**التقنيات:** Laravel 11 · Inertia.js · Vue 3 · MySQL · Tailwind — بهوية جامعة القصيم وألوان DGA (`#25935F`) مع وضع ليلي.

## التشغيل محلياً

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# اضبط بيانات قاعدة MySQL في .env
php artisan migrate
php artisan storage:link
```

### استيراد بيانات القرآن (مطلوب — البيانات ليست في المستودع)

```bash
php artisan quran:import --tafsir=14 --audio=7 --search                     # نص عثماني + رموز QCF + ابن كثير + العفاسي + فهرس بحث
php artisan quran:tafsir-spa5k ar-tafseer-al-saddi ar-tafsir-as-saadi --name="تفسير السعدي"   # السعدي (كامل)
# مصادر رسمية مباشرة من موسوعة مجمع الملك فهد (QuranEnc) — أضف --offline للاستيراد من ملف sqlite محلي بلا شبكة:
php artisan quran:import-quranenc                                           # التفسير الميسّر
php artisan quran:import-quranenc --key=arabic_seraj --slug=ar-gharib-seraj --name="غريب القرآن (السراج في بيان غريب القرآن)"   # غريب القرآن
php artisan quran:import-trans-quranenc --key=english_saheeh --lang=english --name="Saheeh International"   # ترجمة إنجليزية
php artisan quran:import-trans-quranenc --key=french_rashid  --lang=french  --name="Rachid Maach"          # ترجمة فرنسية
php artisan quran:import-tajweed                                            # النص المُعلَّم بالتجويد (من Quran.com — QPC Hafs)
php artisan quran:import-mutashabihat                                       # المتشابهات اللفظية (من مشروع Waqar144)
php artisan quran:fonts                                                     # تحميل 604 خط QCF (مضمَّنة في المستودع مسبقاً)
php artisan quran:audio-segments 7                                          # توقيت الكلمات (العفاسي)
# قرّاء إضافيون (اختياري):
php artisan quran:audio-everyayah --id=101 --name="ماهر المعيقلي" --folder=MaherAlMuaiqly128kbps
php artisan quran:audio-everyayah --id=102 --name="عبد الرحمن السديس" --folder=Abdurrahmaan_As-Sudais_192kbps
php artisan quran:audio-everyayah --id=103 --name="محمود خليل الحصري" --folder=Husary_128kbps
php artisan quran:audio-everyayah --id=104 --name="محمد صديق المنشاوي" --folder=Minshawy_Murattal_128kbps
php artisan quran:audio-everyayah --id=105 --name="سعود الشريم" --folder=Saood_ash-Shuraym_128kbps
```

```bash
composer run dev   # أو: php artisan serve + npm run dev
```

## النشر على Laravel Cloud

1. اربط المستودع في <https://cloud.laravel.com> وأنشئ قاعدة MySQL مُدارة.
2. اضبط متغيّرات البيئة (`APP_KEY`, `DB_*`, `APP_URL`) من لوحة Laravel Cloud.
3. أوامر النشر (Deploy): `php artisan migrate --force` ثم `php artisan storage:link`.
4. **بعد أول نشر**، شغّل أوامر الاستيراد أعلاه مرة واحدة (عبر أمر لمرة واحدة / One-off command في Laravel Cloud) لتعبئة القاعدة.

> البيانات ضخمة وتُجلب من مصادر خارجية (Quran.Foundation / Quran.com / everyayah / spa5k / QuranEnc / Waqar144)، لذا تُستورد مرة واحدة بعد النشر وليست جزءاً من كل نشر.

### المصادر والإسناد

- **النص العثماني ورموز خط QCF**: مصحف المدينة (رواية حفص) — مجمع الملك فهد لطباعة المصحف الشريف، عبر Quran.Foundation.
  (ملاحظة: رموز خط QCF لرسم الصفحة سطر‑بسطر، وتوقيت الكلمات في الصوت، لا يوفّرهما المجمع مباشرةً — فهما من نظام Quran.com/\u200FQUL؛ والمعروض بصرياً هو رسم مصحف المدينة نفسه.)
- **التفسير الميسّر + غريب القرآن (السراج)**: **مصدر رسمي مباشر عبر موسوعة [QuranEnc.com](https://quranenc.com)** (مشروع مجمع الملك فهد) — `quran:import-quranenc`.
- **الترجمات — صحيح إنترناشونال (إنجليزي) و Rachid Maach (فرنسي)**: **مباشرةً عبر [QuranEnc.com](https://quranenc.com)** — `quran:import-trans-quranenc`.
- **تفسيرا ابن كثير والسعدي**: عبر spa5k/tafsir_api.
- **التلاوات الصوتية**: everyayah.com، وتوقيت الكلمات عبر Quran.Foundation.
- **تلوين التجويد**: النص العثماني المُعلَّم بقواعد التجويد (`text_uthmani_tajweed`) عبر [Quran.com](https://quran.com) / Tarteel (QUL) — مشتق من رسم QPC Hafs (مصحف المدينة) — `quran:import-tajweed`.
- **المتشابهات اللفظية**: مشروع [Quran Mutashabihat Data](https://github.com/Waqar144/Quran_Mutashabihat_Data) (مبني على عمل القارئ إدريس العاصم رحمه الله) — `quran:import-mutashabihat`.

> **الترخيص/الإسناد**: مصادر QuranEnc تشترط ذكر الناشر والمصدر (QuranEnc.com) عند إعادة النشر. تحميلات sqlite الرسمية تُحفظ في `storage/app/quranenc/` (خارج المستودع) عند استخدام `--offline`.

جامعة القصيم.
