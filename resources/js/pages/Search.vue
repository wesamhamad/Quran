<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppNav from '@/components/AppNav.vue';

interface ResultT {
    verse_key: string;
    surah_name: string;
    number: number;
    page: number;
    text_uthmani: string;
}

const props = defineProps<{ query: string; results: ResultT[]; count: number }>();
const q = ref(props.query);

function submit() {
    router.get('/search', { q: q.value }, { preserveState: true });
}
</script>

<template>
    <Head title="بحث في القرآن" />
    <div class="search" dir="rtl">
        <AppNav active="search" />

        <div class="head">
            <h1>البحث في القرآن الكريم</h1>
            <form class="box" @submit.prevent="submit">
                <input v-model="q" placeholder="اكتب كلمة أو جملة… (مثال: الصبر)" autofocus />
                <button type="submit">بحث</button>
            </form>
            <p v-if="query" class="count">
                {{ count }} نتيجة لـ «{{ query }}»<span v-if="count === 100"> (عرض أول 100)</span>
            </p>
        </div>

        <div class="results">
            <Link
                v-for="r in results"
                :key="r.verse_key"
                :href="`/mushaf/${r.page}`"
                class="result"
            >
                <div class="rmeta">
                    <span class="badge">{{ r.surah_name }} : {{ r.number }}</span>
                    <span class="pg">صفحة {{ r.page }}</span>
                </div>
                <p class="ayah">{{ r.text_uthmani }}</p>
            </Link>

            <p v-if="query && count === 0" class="empty">لا توجد نتائج مطابقة.</p>
        </div>
    </div>
</template>

<style scoped>
.search { min-height: 100vh; background: var(--canvas); color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; }
.head { max-width: 820px; margin: 0 auto; padding: 1.8rem 1.2rem 0.5rem; }
.head h1 { color: var(--text); margin: 0 0 1.1rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.01em; }
.box { display: flex; gap: 0.5rem; }
.box input {
    flex: 1; padding: 0.9rem 1.2rem; border-radius: 14px; border: 1px solid var(--border);
    background: var(--surface); color: var(--text); font-size: 1.05rem; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.box input::placeholder { color: var(--text-muted); }
.box input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-soft); }
.box button {
    padding: 0.9rem 1.7rem; border: none; border-radius: 14px;
    background: var(--brand); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; transition: transform 0.12s;
}
.box button:hover { transform: translateY(-1px); }
.count { margin: 1rem 0 0; color: var(--text-muted); font-size: 0.9rem; }

.results { max-width: 820px; margin: 1rem auto 3rem; padding: 0 1.2rem; display: flex; flex-direction: column; gap: 0.7rem; }
.result {
    display: block; text-decoration: none; color: inherit;
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.1rem 1.3rem;
    transition: border-color 0.13s, transform 0.13s, box-shadow 0.13s;
}
.result:hover { border-color: var(--brand-200); transform: translateX(-3px); box-shadow: var(--shadow-sm); }
.rmeta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; }
.badge { background: var(--brand-soft); color: var(--brand); padding: 0.25rem 0.8rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.pg { font-size: 0.72rem; color: var(--text-muted); background: var(--surface-2); padding: 0.2rem 0.55rem; border-radius: 8px; }
.ayah {
    margin: 0; font-family: 'Amiri Quran', 'Traditional Arabic', serif;
    font-size: 1.6rem; line-height: 2.2; color: var(--paper-ink);
}
.empty { text-align: center; color: var(--text-muted); padding: 2.5rem; }
</style>
