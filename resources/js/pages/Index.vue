<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';

interface SurahT {
    id: number;
    name_arabic: string;
    name_simple: string;
    translated_name: string | null;
    revelation_place: string;
    verses_count: number;
    page_start: number;
}

const props = defineProps<{ surahs: SurahT[] }>();
const q = ref('');

const filtered = computed(() => {
    const t = q.value.trim();
    if (!t) return props.surahs;
    return props.surahs.filter(
        (s) =>
            s.name_arabic.includes(t) ||
            s.name_simple.toLowerCase().includes(t.toLowerCase()) ||
            String(s.id) === t,
    );
});

const place = (p: string) => (p === 'makkah' ? 'مكية' : 'مدنية');
</script>

<template>
    <Head title="فهرس السور" />
    <div class="index" dir="rtl">
        <AppNav active="surahs" />

        <div class="head">
            <h1>فهرس السور</h1>
            <input v-model="q" class="filter" placeholder="ابحث باسم السورة أو رقمها…" />
        </div>

        <div class="grid">
            <Link
                v-for="s in filtered"
                :key="s.id"
                :href="`/mushaf/${s.page_start}`"
                class="card"
            >
                <span class="num">{{ s.id }}</span>
                <span class="info">
                    <b class="name">{{ s.name_arabic }}</b>
                    <small>{{ place(s.revelation_place) }} · {{ s.verses_count }} آية</small>
                </span>
                <span class="pg">ص {{ s.page_start }}</span>
            </Link>
        </div>
    </div>
</template>

<style scoped>
.index { min-height: 100vh; background: var(--canvas); color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; }
.head { max-width: 1040px; margin: 0 auto; padding: 1.8rem 1.2rem 0.6rem; }
.head h1 { color: var(--text); margin: 0 0 1rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.01em; }
.filter {
    width: 100%; padding: 0.85rem 1.1rem; border-radius: 14px;
    border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 1rem; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.filter::placeholder { color: var(--text-muted); }
.filter:focus { border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-soft); }
.grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 0.7rem; max-width: 1040px; margin: 1.2rem auto 3rem; padding: 0 1.2rem;
}
.card {
    display: flex; align-items: center; gap: 0.9rem; text-decoration: none; color: inherit;
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 0.85rem 1rem;
    transition: transform 0.13s, box-shadow 0.13s, border-color 0.13s;
}
.card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--brand-200); }
.num {
    flex: none; width: 42px; height: 42px; display: grid; place-items: center;
    border-radius: 13px; background: var(--brand-soft); color: var(--brand); font-weight: 700; font-size: 0.95rem;
}
.info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.name { font-size: 1.35rem; color: var(--text); font-family: 'Amiri Quran', 'Traditional Arabic', serif; }
.info small { color: var(--text-muted); font-size: 0.78rem; }
.pg { font-size: 0.72rem; color: var(--text-muted); background: var(--surface-2); padding: 0.2rem 0.55rem; border-radius: 8px; }
</style>
