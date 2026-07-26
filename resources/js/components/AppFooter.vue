<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/components/Icon.vue';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="app-footer" dir="rtl">
        <div class="af-inner">
            <!-- الهوية -->
            <div class="af-brand">
                <img
                    src="/storage/Images/logo/qu-logo-v4.webp"
                    alt="جامعة القصيم"
                    class="af-logo"
                />
                <div class="af-brand-text">
                    <strong>القرآن الكريم</strong>
                    <span
                        >عمادة التعلم الإلكتروني وتقنية المعلومات · جامعة
                        القصيم</span
                    >
                </div>
            </div>

            <!-- روابط سريعة -->
            <nav class="af-links">
                <Link href="/surahs">فهرس السور</Link>
                <Link href="/mushaf/1">المصحف</Link>
                <Link href="/khatmah">ختمة</Link>
                <Link href="/search">بحث</Link>
                <Link href="/sources">المصادر</Link>
            </nav>

            <!-- شارة المصدر الحكومي المعتمد -->
            <a
                href="https://qurancomplex.gov.sa/quran-dev/"
                target="_blank"
                rel="noopener noreferrer"
                class="af-gov"
            >
                <Icon name="circle-check" :size="15" />
                <span>مصدر معتمد · مجمع الملك فهد</span>
            </a>
        </div>

        <div class="af-copy">
            <span>© {{ year }} جميع الحقوق محفوظة لجامعة القصيم</span>
            <Link href="/sources" class="af-src-link">المصادر والإسناد</Link>
        </div>
    </footer>
</template>

<style scoped>
.app-footer {
    margin-top: 2rem;
    border-top: 1px solid var(--border);
    background: var(--surface);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}
.af-inner {
    max-width: 68rem;
    margin: 0 auto;
    padding: 1.6rem 1.4rem 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.2rem 2rem;
    flex-wrap: wrap;
}
/* الهوية */
.af-brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
}
.af-logo {
    height: 44px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    flex: none;
}
.af-brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.5;
}
.af-brand-text strong {
    font-size: 0.98rem;
    color: var(--text);
    font-weight: 700;
}
.af-brand-text span {
    font-size: 0.76rem;
    color: var(--text-muted);
}
/* الروابط */
.af-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.2rem 0.4rem;
}
.af-links a {
    color: var(--text-muted);
    text-decoration: none;
    padding: 0.4rem 0.8rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.15s;
}
.af-links a:hover {
    color: var(--brand);
    background: var(--brand-soft);
}
/* شارة المصدر الحكومي */
.af-gov {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.9rem;
    border: 1px solid color-mix(in srgb, var(--brand) 40%, var(--border));
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 8%, var(--surface));
    color: var(--brand);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.15s;
}
.af-gov:hover {
    background: var(--brand-soft);
    border-color: var(--brand);
}
/* شريط الحقوق السفلي */
.af-copy {
    border-top: 1px solid var(--border);
}
.af-copy {
    max-width: 68rem;
    margin: 0 auto;
    padding: 0.9rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    flex-wrap: wrap;
    font-size: 0.76rem;
    color: var(--text-muted);
}
.af-src-link {
    color: var(--brand);
    text-decoration: none;
    font-weight: 600;
}
.af-src-link:hover {
    text-decoration: underline;
}
@media (max-width: 640px) {
    .af-inner {
        justify-content: center;
        text-align: center;
    }
    .af-brand {
        flex-direction: column;
        text-align: center;
    }
    .af-copy {
        justify-content: center;
    }
}
</style>
