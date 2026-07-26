<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppNav from '@/components/AppNav.vue';
import AppFooter from '@/components/AppFooter.vue';
import Icon from '@/components/Icon.vue';

// المصدر الرسمي المعتمد (جهة حكومية)
const featured = {
    name: 'مجمع الملك فهد لطباعة المصحف الشريف',
    place: 'المدينة المنوّرة — المملكة العربية السعودية',
    desc: 'الجهة الحكومية الرسمية المعتمدة. عبر «منصة مطوّري برمجيات القرآن الكريم» نأخذ مباشرةً: خطّ الرسم العثماني الرسمي (رواية حفص)، والنصّ العثماني، والتفسير الميسّر، وغريب القرآن، وترجمات معاني القرآن الكريم — مطابقةً لرسم مصحف المدينة.',
    links: [
        {
            label: 'منصة مطوّري برمجيات القرآن · quran-dev',
            url: 'https://qurancomplex.gov.sa/quran-dev/',
            gov: true,
        },
        {
            label: 'الموقع الرسمي · qurancomplex.gov.sa',
            url: 'https://qurancomplex.gov.sa',
            gov: true,
        },
        {
            label: 'موسوعة ترجمات القرآن · QuranEnc.com',
            url: 'https://quranenc.com',
            gov: false,
        },
    ],
};

// بقية المصادر التقنية
const sources = [
    {
        icon: 'type',
        name: 'المكتبة القرآنية الشاملة — QUL / Quran.com',
        desc: 'رموز خط QCF لرسم الصفحة سطراً بسطر، وتوقيت التلاوة لتظليل الكلمات، والنصّ المُعلَّم بقواعد التجويد.',
        links: [
            { label: 'qul.tarteel.ai', url: 'https://qul.tarteel.ai' },
            { label: 'quran.com', url: 'https://quran.com' },
        ],
    },
    {
        icon: 'sparkles',
        name: 'خط الرسم العثماني الرسمي (رواية حفص)',
        desc: 'خط «مصحف المدينة» الحاسوبي الرسمي (KFGQPC Uthmanic Hafs v2.0) — مُنزَّل مباشرةً من منصة مطوّري المجمع، ويُستخدم في وضع «القراءة بالتجويد».',
        links: [
            {
                label: 'download.qurancomplex.gov.sa',
                url: 'https://qurancomplex.gov.sa/quran-dev/',
            },
        ],
    },
    {
        icon: 'headphones',
        name: 'التلاوات الصوتية — EveryAyah',
        desc: 'ملفات التلاوة آيةً بآية لنخبةٍ من القرّاء المعتمدين.',
        links: [{ label: 'everyayah.com', url: 'https://everyayah.com' }],
    },
    {
        icon: 'book-open',
        name: 'تفسير ابن كثير وتفسير السعدي',
        desc: 'نصوص التفسير عبر مشروع tafsir_api مفتوح المصدر.',
        links: [
            {
                label: 'spa5k/tafsir_api',
                url: 'https://github.com/spa5k/tafsir_api',
            },
        ],
    },
    {
        icon: 'repeat',
        name: 'المتشابهات اللفظية — Quran Mutashabihat',
        desc: 'مواضع التشابه اللفظي التي يلتبس فيها الحفظ، مبنيّة على عمل القارئ إدريس العاصم رحمه الله.',
        links: [
            {
                label: 'Waqar144/Quran_Mutashabihat_Data',
                url: 'https://github.com/Waqar144/Quran_Mutashabihat_Data',
            },
        ],
    },
    {
        icon: 'book',
        name: 'أسباب النزول الصحيحة',
        desc: 'أسباب النزول المُصفّاة على الصحّة الحديثية — من كتاب «صحيح أسباب النزول، دراسة حديثية» للدكتور إبراهيم محمد العلي.',
        links: [
            {
                label: 'asbab-al-nuzul-dataset',
                url: 'https://github.com/mostafaahmed97/asbab-al-nuzul-dataset',
            },
        ],
    },
];
</script>

<template>
    <Head title="المصادر والإسناد — المصحف الإلكتروني" />
    <div class="sources" dir="rtl">
        <AppNav active="sources" />

        <header class="head">
            <span class="eyebrow"><Icon name="book" :size="16" /> الإسناد والموثوقية</span>
            <h1>المصادر والإسناد</h1>
            <p class="sub">
                يحرص المصحف الإلكتروني على إسناد كلّ محتواه إلى مصادره الرسمية
                الموثوقة. فيما يلي مراجع كل مكوّن من مكوّنات التطبيق.
            </p>
        </header>

        <!-- المصدر الحكومي الرسمي المعتمد -->
        <section class="featured">
            <div class="feat-badge">
                <Icon name="circle-check" :size="15" /> جهة حكومية رسمية معتمدة
            </div>
            <h2>{{ featured.name }}</h2>
            <p class="feat-place">{{ featured.place }}</p>
            <p class="feat-desc">{{ featured.desc }}</p>
            <div class="links">
                <a
                    v-for="l in featured.links"
                    :key="l.url"
                    :href="l.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="src-link"
                    :class="{ gov: l.gov }"
                >
                    <Icon :name="l.gov ? 'globe' : 'arrow-left'" :size="15" />
                    {{ l.label }}
                </a>
            </div>
        </section>

        <!-- بقية المصادر -->
        <section class="grid">
            <article v-for="s in sources" :key="s.name" class="card">
                <div class="card-ico"><Icon :name="s.icon" :size="20" /></div>
                <h3>{{ s.name }}</h3>
                <p class="card-desc">{{ s.desc }}</p>
                <div class="links">
                    <a
                        v-for="l in s.links"
                        :key="l.url"
                        :href="l.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="src-link sm"
                    >
                        <Icon name="arrow-left" :size="14" /> {{ l.label }}
                    </a>
                </div>
            </article>
        </section>

        <p class="license">
            <Icon name="book" :size="14" /> يُلتزم بذكر المصدر عند إعادة النشر وفق
            تراخيص هذه المصادر (QuranEnc.com، QUL، والمشاريع مفتوحة المصدر).
        </p>

        <AppFooter />
    </div>
</template>

<style scoped>
.sources {
    min-height: 100vh;
    background: var(--canvas);
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.head {
    text-align: center;
    max-width: 46rem;
    margin: 0 auto;
    padding: 2.6rem 1.2rem 1.4rem;
}
.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--brand);
    background: var(--brand-soft);
    padding: 0.3rem 0.9rem;
    border-radius: 999px;
}
.head h1 {
    margin: 0.8rem 0 0.4rem;
    font-size: clamp(1.6rem, 5vw, 2.2rem);
}
.head .sub {
    color: var(--text-muted);
    line-height: 1.9;
    margin: 0;
}

/* بطاقة المصدر الحكومي البارزة */
.featured {
    max-width: 52rem;
    margin: 1.2rem auto;
    padding: 1.6rem 1.6rem 1.4rem;
    border: 1px solid color-mix(in srgb, var(--brand) 35%, var(--border));
    border-radius: 20px;
    background:
        linear-gradient(
            180deg,
            color-mix(in srgb, var(--brand) 8%, var(--surface)),
            var(--surface)
        );
    box-shadow: 0 8px 30px -18px var(--brand);
}
.feat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #fff;
    background: var(--brand);
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
}
.featured h2 {
    margin: 0.8rem 0 0.2rem;
    font-size: 1.3rem;
}
.feat-place {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}
.feat-desc {
    margin: 0.8rem 0 1rem;
    line-height: 1.9;
}

/* شبكة المصادر */
.grid {
    max-width: 52rem;
    margin: 0 auto;
    padding: 0.6rem 1.2rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
    gap: 0.9rem;
}
.card {
    padding: 1.2rem;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface);
    display: flex;
    flex-direction: column;
}
.card-ico {
    width: 2.5rem;
    height: 2.5rem;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: var(--brand);
    background: var(--brand-soft);
    margin-bottom: 0.7rem;
}
.card h3 {
    margin: 0 0 0.4rem;
    font-size: 1rem;
    line-height: 1.5;
}
.card-desc {
    margin: 0 0 0.9rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1.8;
    flex: 1;
}

/* روابط المصادر */
.links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.src-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.9rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.15s;
}
.src-link:hover {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}
.src-link.sm {
    font-size: 0.78rem;
    padding: 0.4rem 0.75rem;
    font-weight: 500;
}
.src-link.gov {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
}
.src-link.gov:hover {
    filter: brightness(1.08);
    color: #fff;
}
.license {
    max-width: 52rem;
    margin: 1.4rem auto 0;
    padding: 0 1.2rem;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.8rem;
    line-height: 1.8;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.foot {
    text-align: center;
    padding: 2rem 1.2rem;
    margin-top: 1rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}
</style>
