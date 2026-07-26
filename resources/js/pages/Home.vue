<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';
import AppFooter from '@/components/AppFooter.vue';
import Icon from '@/components/Icon.vue';

interface ReciterT {
    id: number;
    name: string;
}
interface LangT {
    code: string;
    native: string;
    name: string;
    dir: string;
}
interface RiwT {
    slug: string;
    name: string;
    is_hafs: boolean;
    font: string;
    pages: number;
}

const props = defineProps<{
    stats: { surahs: number; ayahs: number; pages: number };
    reciters: ReciterT[];
    translationLangs: LangT[];
    riwayat: RiwT[];
}>();

const lastPage = ref<number | null>(null);
const reciterId = ref<number | null>(null);
const lang = ref<string>('none');
const riwayah = ref<string>('hafs');

onMounted(() => {
    const p = Number(localStorage.getItem('quran-last-page'));
    if (p > 0) lastPage.value = p;

    const r = Number(localStorage.getItem('quran-last-reciter'));
    reciterId.value =
        r > 0
            ? r
            : (props.reciters.find((x) => x.id === 101)?.id ??
              props.reciters[0]?.id ??
              null);

    const savedLang = localStorage.getItem('quran-trans-lang') || 'none';
    lang.value = props.translationLangs.some((l) => l.code === savedLang)
        ? savedLang
        : 'none';

    const savedRiw = localStorage.getItem('quran-riwayah') || 'hafs';
    riwayah.value = props.riwayat.some((r) => r.slug === savedRiw)
        ? savedRiw
        : 'hafs';
});

function persist() {
    try {
        if (reciterId.value)
            localStorage.setItem('quran-last-reciter', String(reciterId.value));
        localStorage.setItem('quran-trans-lang', lang.value);
        localStorage.setItem('quran-riwayah', riwayah.value);
    } catch {
        /* */
    }
}
function hrefFor(page: number): string {
    return `/mushaf/${page}${reciterId.value ? `?reciter=${reciterId.value}` : ''}`;
}
function start(page: number) {
    persist();
    router.visit(hrefFor(page));
}
const resumeLabel = computed(() =>
    lastPage.value ? `تابع · صفحة ${lastPage.value}` : '',
);
</script>

<template>
    <Head title="المصحف الإلكتروني" />
    <div class="home" dir="rtl">
        <AppNav />

        <header class="hero">
            <img
                src="/storage/Images/logo/qu-logo-v4.webp"
                alt="جامعة القصيم"
                class="logo-lg"
            />
            <span class="eyebrow">جامعة القصيم</span>
            <h1>المصحف الإلكتروني</h1>
            <p class="sub">
                القرآن الكريم بالرسم العثماني — مصحف المدينة النبوية
            </p>

            <!-- اختيار ما قبل القراءة: الرواية + القارئ + لغة الترجمة -->
            <div class="chooser">
                <label v-if="riwayat.length > 1" class="ch-field ch-full">
                    <span class="ch-label"
                        ><Icon name="book-open" :size="16" /> الرواية</span
                    >
                    <div class="ch-select">
                        <select v-model="riwayah" @change="persist">
                            <option
                                v-for="r in riwayat"
                                :key="r.slug"
                                :value="r.slug"
                            >
                                {{ r.name }}
                            </option>
                        </select>
                        <Icon name="chevron-down" :size="16" class="ch-caret" />
                    </div>
                </label>
                <div class="ch-row">
                    <label class="ch-field">
                        <span class="ch-label"
                            ><Icon name="mic" :size="16" /> القارئ</span
                        >
                        <div class="ch-select">
                            <select
                                v-model.number="reciterId"
                                @change="persist"
                            >
                                <option
                                    v-for="r in reciters"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </select>
                            <Icon
                                name="chevron-down"
                                :size="16"
                                class="ch-caret"
                            />
                        </div>
                    </label>

                    <label class="ch-field">
                        <span class="ch-label"
                            ><Icon name="languages" :size="16" /> الترجمة</span
                        >
                        <div class="ch-select">
                            <select v-model="lang" @change="persist">
                                <option value="none">
                                    بدون ترجمة (عربي فقط)
                                </option>
                                <option
                                    v-for="l in translationLangs"
                                    :key="l.code"
                                    :value="l.code"
                                >
                                    {{ l.native }}
                                </option>
                            </select>
                            <Icon
                                name="chevron-down"
                                :size="16"
                                class="ch-caret"
                            />
                        </div>
                    </label>
                </div>

                <div class="cta">
                    <button
                        v-if="lastPage"
                        class="btn primary"
                        @click="start(lastPage!)"
                    >
                        <Icon name="book-open" :size="18" /> {{ resumeLabel }}
                    </button>
                    <button
                        class="btn"
                        :class="{ primary: !lastPage }"
                        @click="start(1)"
                    >
                        <Icon name="play" :size="16" />
                        {{ lastPage ? 'من البداية' : 'ابدأ القراءة' }}
                    </button>
                    <Link href="/surahs" class="btn ghost"
                        ><Icon name="list" :size="16" /> فهرس السور</Link
                    >
                </div>
            </div>

            <div class="stats">
                <div>
                    <b>{{ stats.surahs }}</b
                    ><span>سورة</span>
                </div>
                <div class="sep"></div>
                <div>
                    <b>{{ stats.ayahs.toLocaleString('ar') }}</b
                    ><span>آية</span>
                </div>
                <div class="sep"></div>
                <div>
                    <b>{{ stats.pages }}</b
                    ><span>صفحة</span>
                </div>
                <div class="sep"></div>
                <div>
                    <b>{{ translationLangs.length }}</b
                    ><span>لغة ترجمة</span>
                </div>
            </div>
        </header>

        <section class="features">
            <article>
                <div class="ico">
                    <Icon name="book" :size="23" :stroke="1.8" />
                </div>
                <h3>الرسم العثماني</h3>
                <p>
                    عرض صفحي مطابق لمصحف المدينة بخطوط QCF المعتمدة من مجمع
                    الملك فهد.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="headphones" :size="23" :stroke="1.8" />
                </div>
                <h3>التلاوة الصوتية</h3>
                <p>
                    استماع للتلاوة مع تظليل الكلمة الجاري تلاوتها بتوقيت دقيق.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="graduation-cap" :size="23" :stroke="1.8" />
                </div>
                <h3>نمط المُعلّم</h3>
                <p>
                    تكرار وحفظ ومراجعة لحظية وجدولة — لحلقات التحفيظ والأطفال.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="languages" :size="23" :stroke="1.8" />
                </div>
                <h3>تفسير وترجمات</h3>
                <p>
                    أربعة تفاسير رسمية وترجمات معاني القرآن بـ{{
                        translationLangs.length
                    }}
                    لغة بلمسة واحدة.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="layers" :size="23" :stroke="1.8" />
                </div>
                <h3>تعدد الروايات</h3>
                <p>
                    قراءة المصحف بعدّة روايات — ورش وقالون وشعبة وغيرها — بخطوط
                    KFGQPC الرسمية، لكل رواية ترقيم صفحاتها.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="droplet" :size="23" :stroke="1.8" />
                </div>
                <h3>تلوين التجويد</h3>
                <p>
                    إظهار أحكام التجويد بالألوان المعتمدة مع مفتاح يوضّح كل حكم
                    لتيسير القراءة الصحيحة.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="book-open" :size="23" :stroke="1.8" />
                </div>
                <h3>معاني الكلمات</h3>
                <p>
                    شرح مختصر لمعاني المفردات الغريبة عند كل آية لفهم المعنى
                    مباشرةً أثناء التلاوة.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="repeat" :size="23" :stroke="1.8" />
                </div>
                <h3>المتشابهات اللفظية</h3>
                <p>
                    إبراز المواضع المتشابهة التي يلتبس فيها الحفظ مع الانتقال
                    السريع بينها — عوناً للحُفّاظ.
                </p>
            </article>
            <article>
                <div class="ico">
                    <Icon name="pause" :size="23" :stroke="1.8" />
                </div>
                <h3>دليل علامات الوقف والابتداء</h3>
                <p>
                    مرجع مبسّط لعلامات الوقف في مصحف المدينة — اللازم والممنوع
                    والجائز والوقف أَولى وغيرها — لقراءةٍ سليمة.
                </p>
            </article>
        </section>

        <AppFooter />
    </div>
</template>

<style scoped>
.home {
    min-height: 100vh;
    background: var(--canvas);
    color: var(--text);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 3.5rem 1.2rem 3rem;
    position: relative;
}
.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 0;
    background: radial-gradient(
        60% 60% at 50% 0%,
        var(--brand-soft),
        transparent 70%
    );
    pointer-events: none;
}
.hero > * {
    position: relative;
    z-index: 1;
}
.logo-lg {
    width: 84px;
    height: 84px;
    object-fit: contain;
    margin-bottom: 1.2rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 22px;
    padding: 12px;
    box-shadow: var(--shadow-md);
}
.eyebrow {
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: var(--brand);
    background: var(--brand-soft);
    padding: 0.3rem 0.9rem;
    border-radius: 999px;
}
.hero h1 {
    font-size: clamp(2rem, 7vw, 3.2rem);
    margin: 1rem 0 0.4rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.sub {
    color: var(--text-muted);
    font-size: clamp(0.95rem, 3vw, 1.15rem);
    max-width: 560px;
    margin: 0 0 1.8rem;
}

/* لوحة اختيار ما قبل القراءة */
.chooser {
    width: min(560px, 94vw);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.1rem;
    box-shadow: var(--shadow-md);
    margin-bottom: 2rem;
}
.ch-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.7rem;
    margin-bottom: 0.9rem;
}
.ch-full {
    margin-bottom: 0.7rem;
}
.ch-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    text-align: right;
    min-width: 0;
}
.ch-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}
.ch-label :deep(.lc-icon) {
    color: var(--brand);
}
.ch-select {
    position: relative;
}
.ch-select select {
    width: 100%;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.6rem 2.2rem 0.6rem 0.8rem;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
}
.ch-select select:focus {
    outline: none;
    border-color: var(--brand-200);
}
.ch-caret {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}

.cta {
    display: flex;
    gap: 0.6rem;
    justify-content: center;
    flex-wrap: wrap;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.7rem 1.3rem;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    background: var(--surface-2);
    color: var(--text);
    border: 1px solid var(--border);
    cursor: pointer;
    font-family: inherit;
    transition:
        transform 0.12s,
        box-shadow 0.15s,
        background 0.15s;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}
.btn.primary {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
    box-shadow: 0 6px 20px rgba(37, 147, 95, 0.28);
}
.btn.ghost {
    background: transparent;
}

.stats {
    display: flex;
    align-items: center;
    gap: 1.4rem;
    margin-top: 1.4rem;
    flex-wrap: wrap;
    justify-content: center;
}
.stats > div:not(.sep) {
    display: flex;
    flex-direction: column;
}
.stats b {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
}
.stats span {
    font-size: 0.78rem;
    color: var(--text-muted);
}
.sep {
    width: 1px;
    height: 32px;
    background: var(--border);
}

.features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1rem;
    max-width: 1040px;
    margin: 1.5rem auto 2rem;
    padding: 0 1.2rem;
}
.features article {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition:
        transform 0.15s,
        box-shadow 0.15s,
        border-color 0.15s;
}
.features article:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: var(--brand-200);
}
.features .ico {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    background: var(--brand-soft);
    color: var(--brand);
    border-radius: 14px;
    margin-bottom: 0.9rem;
}
.features h3 {
    color: var(--text);
    margin: 0 0 0.4rem;
    font-size: 1.05rem;
}
.features p {
    font-size: 0.9rem;
    line-height: 1.65;
    color: var(--text-muted);
    margin: 0;
}

.foot {
    text-align: center;
    padding: 2rem 1.2rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}
.foot p {
    margin: 0;
}
.foot-src {
    margin-top: 0.6rem;
    max-width: 46rem;
    margin-inline: auto;
    font-size: 0.72rem;
    line-height: 1.7;
    opacity: 0.85;
}
.foot-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--brand);
    font-weight: 600;
    text-decoration: none;
}
.foot-link:hover {
    text-decoration: underline;
}

@media (max-width: 560px) {
    .hero {
        padding: 2.5rem 1.2rem 2rem;
    }
    .ch-row {
        grid-template-columns: 1fr;
    }
    .stats {
        gap: 1rem;
    }
    .stats b {
        font-size: 1.35rem;
    }
}
</style>
