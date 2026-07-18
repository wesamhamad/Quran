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
php artisan quran:import --tafsir=14 --translation=20 --audio=7 --search   # نص عثماني + رموز QCF + ابن كثير + ترجمة + العفاسي + فهرس بحث
php artisan quran:tafsir-spa5k ar-tafseer-al-saddi ar-tafsir-as-saadi --name="تفسير السعدي"   # السعدي (كامل)
php artisan quran:import --surahs-only --tafsir=16                          # الميسّر
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

> البيانات ضخمة وتُجلب من مصادر خارجية (Quran.Foundation / everyayah / spa5k)، لذا تُستورد مرة واحدة بعد النشر وليست جزءاً من كل نشر.

بيانات القرآن من مجمع الملك فهد عبر Quran.Foundation.  جامعة القصيم.
