<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';

defineProps<{ stats: { surahs: number; ayahs: number; pages: number } }>();

const lastPage = ref<number | null>(null);
onMounted(() => {
    const p = Number(localStorage.getItem('quran-last-page'));
    if (p > 0) lastPage.value = p;
});
const resumeHref = computed(() => {
    const r = localStorage.getItem('quran-last-reciter');
    return `/mushaf/${lastPage.value}${r ? `?reciter=${r}` : ''}`;
});
</script>

<template>
    <Head title="المصحف الإلكتروني" />
    <div class="home" dir="rtl">
        <AppNav />

        <header class="hero">
            <img src="/storage/Images/logo/qu-logo-v4.webp" alt="جامعة القصيم" class="logo-lg" />
            <span class="eyebrow">جامعة القصيم</span>
            <h1>المصحف الإلكتروني</h1>
            <p class="sub">القرآن الكريم بالرسم العثماني — مصحف المدينة النبوية</p>

            <div class="cta">
                <Link v-if="lastPage" :href="resumeHref" class="btn primary">تابع القراءة · صفحة {{ lastPage }}</Link>
                <Link :href="lastPage ? '/mushaf/1' : '/mushaf/1'" class="btn" :class="{ primary: !lastPage }">
                    {{ lastPage ? 'من البداية' : 'ابدأ القراءة' }}
                </Link>
                <Link href="/surahs" class="btn">فهرس السور</Link>
            </div>

            <div class="stats">
                <div><b>{{ stats.surahs }}</b><span>سورة</span></div>
                <div class="sep"></div>
                <div><b>{{ stats.ayahs.toLocaleString('ar') }}</b><span>آية</span></div>
                <div class="sep"></div>
                <div><b>{{ stats.pages }}</b><span>صفحة</span></div>
            </div>
        </header>

        <section class="features">
            <article>
                <div class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                </div>
                <h3>الرسم العثماني</h3>
                <p>عرض صفحي مطابق لمصحف المدينة بخطوط QCF المعتمدة من مجمع الملك فهد.</p>
            </article>
            <article>
                <div class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
                    </svg>
                </div>
                <h3>التلاوة الصوتية</h3>
                <p>استماع للتلاوة مع تظليل الآية الجاري تلاوتها آيةً بآية.</p>
            </article>
            <article>
                <div class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                    </svg>
                </div>
                <h3>التفسير والترجمة</h3>
                <p>ثلاثة تفاسير وترجمة المعاني بلمسة واحدة على أي آية.</p>
            </article>
            <article>
                <div class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <path d="M21 21l-4.35-4.35" />
                    </svg>
                </div>
                <h3>بحث ذكي</h3>
                <p>بحث في نص القرآن يتجاوز اختلاف التشكيل والهمزات.</p>
            </article>
        </section>

        <footer class="foot"> تطوير عمادة تقنية المعلومات . جميع الحقوق محفوظة لجامعة القصيم </footer>
    </div>
</template>

<style scoped>
.home { min-height: 100vh; background: var(--canvas); color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; }

.hero {
    display: flex; flex-direction: column; align-items: center; text-align: center;
    padding: 4rem 1.2rem 3rem; position: relative;
}
.hero::before {
    content: ""; position: absolute; inset: 0; z-index: 0;
    background: radial-gradient(60% 60% at 50% 0%, var(--brand-soft), transparent 70%);
    pointer-events: none;
}
.hero > * { position: relative; z-index: 1; }
.logo-lg {
    width: 84px; height: 84px; object-fit: contain; margin-bottom: 1.2rem;
    background: var(--surface); border: 1px solid var(--border); border-radius: 22px; padding: 12px;
    box-shadow: var(--shadow-md);
}
.eyebrow {
    font-size: 0.8rem; font-weight: 600; letter-spacing: 0.02em; color: var(--brand);
    background: var(--brand-soft); padding: 0.3rem 0.9rem; border-radius: 999px;
}
.hero h1 { font-size: clamp(2rem, 7vw, 3.2rem); margin: 1rem 0 0.4rem; font-weight: 800; letter-spacing: -0.02em; }
.sub { color: var(--text-muted); font-size: clamp(0.95rem, 3vw, 1.15rem); max-width: 560px; margin: 0 0 2rem; }

.cta { display: flex; gap: 0.7rem; justify-content: center; flex-wrap: wrap; }
.btn {
    padding: 0.75rem 1.6rem; border-radius: 14px; text-decoration: none; font-weight: 600; font-size: 0.95rem;
    background: var(--surface); color: var(--text); border: 1px solid var(--border);
    transition: transform 0.12s, box-shadow 0.15s, background 0.15s;
}
.btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.btn.primary { background: var(--brand); color: #fff; border-color: var(--brand); box-shadow: 0 6px 20px rgba(37,147,95,0.28); }

.stats { display: flex; align-items: center; gap: 1.6rem; margin-top: 2.6rem; }
.stats > div:not(.sep) { display: flex; flex-direction: column; }
.stats b { font-size: 1.7rem; font-weight: 800; color: var(--text); }
.stats span { font-size: 0.8rem; color: var(--text-muted); }
.sep { width: 1px; height: 34px; background: var(--border); }

.features {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1rem; max-width: 1040px; margin: 1.5rem auto 2rem; padding: 0 1.2rem;
}
.features article {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1.5rem; transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.features article:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--brand-200); }
.features .ico {
    width: 46px; height: 46px; display: grid; place-items: center;
    background: var(--brand-soft); color: var(--brand); border-radius: 14px; margin-bottom: 0.9rem;
}
.features .ico svg { width: 23px; height: 23px; }
.features h3 { color: var(--text); margin: 0 0 0.4rem; font-size: 1.05rem; }
.features p { font-size: 0.9rem; line-height: 1.65; color: var(--text-muted); margin: 0; }

.foot { text-align: center; padding: 2rem 1.2rem; font-size: 0.82rem; color: var(--text-muted); }

@media (max-width: 560px) {
    .hero { padding: 2.5rem 1.2rem 2rem; }
    .stats { gap: 1rem; }
    .stats b { font-size: 1.35rem; }
}
</style>
