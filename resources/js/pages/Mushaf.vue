<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';

interface WordT { code: string; type: string; verse_key: string; pos: number }
interface LineT {
    line_number: number;
    start_surah: { id: number; name_arabic: string; bismillah_pre: boolean } | null;
    words: WordT[];
}
interface SurahRef { id: number; name_arabic: string }
interface AudioT { verse_key: string; url: string; segments: number[][] | null }
interface ReciterT { id: number; name: string }

const props = defineProps<{
    page: number;
    prev: number | null;
    next: number | null;
    juz: number | null;
    surahs: SurahRef[];
    lines: LineT[];
    reciter: string | null;
    reciters: ReciterT[];
    reciterId: number | null;
    audio: AudioT[];
}>();

const pageFont = `p${props.page}`;
// صفحتا الفاتحة وبداية البقرة تُعرضان موسّطتين (كالمصحف) لتفادي الفراغات الكبيرة
const centeredPage = props.page <= 2;

/* ---------- وضع الحفظ ---------- */
const memoMode = ref(false);
const revealed = ref<Set<string>>(new Set());
function toggleMemo() {
    memoMode.value = !memoMode.value;
    revealed.value = new Set();
}
function revealVerse(key: string) {
    const s = new Set(revealed.value);
    s.has(key) ? s.delete(key) : s.add(key);
    revealed.value = s;
}

/* ---------- التنقّل ---------- */
function go(target: number | null) {
    if (target) router.visit(`/mushaf/${target}${props.reciterId ? `?reciter=${props.reciterId}` : ''}`);
}
function changeReciter(e: Event) {
    const id = (e.target as HTMLSelectElement).value;
    stop();
    router.visit(`/mushaf/${props.page}?reciter=${id}`, { preserveScroll: true });
}
function onKey(e: KeyboardEvent) {
    if ((e.target as HTMLElement)?.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight') go(props.prev);
    if (e.key === 'ArrowLeft') go(props.next);
    if (e.key === ' ') { e.preventDefault(); toggle(); }
}

/* ---------- الصوت + التظليل ---------- */
const playing = ref(false);
const activeVerse = ref<string | null>(null);
const activeWord = ref<number | null>(null); // رقم الكلمة الجاري تلاوتها (توقيت)
let audioEl: HTMLAudioElement | null = null;   // العنصر الجاري تشغيله
let preloadEl: HTMLAudioElement | null = null; // العنصر التالي مُحمَّل مسبقاً
let currentSegments: number[][] | null = null; // مقاطع توقيت الآية الحالية
let idx = 0;

function onEnded() { playAt(idx + 1); }
// تظليل الكلمة حسب توقيت التلاوة
function onTimeUpdate() {
    if (!audioEl || !currentSegments) return;
    const t = audioEl.currentTime * 1000;
    for (const seg of currentSegments) {
        // seg = [i, wordNo, startMs, endMs]
        if (t >= seg[2] && t < seg[3]) { activeWord.value = seg[1]; return; }
    }
}

// أنشئ عنصر صوت مُحمَّلاً مسبقاً (يبدأ جلب الملف فوراً)
function makeEl(url: string): HTMLAudioElement {
    const a = new Audio();
    a.preload = 'auto';
    a.src = url;
    a.load();
    return a;
}
function absUrl(u: string): string {
    return new URL(u, window.location.href).href;
}

function playAt(i: number) {
    if (i < 0 || i >= props.audio.length) { stop(); return; }
    idx = i;
    const item = props.audio[i];
    activeVerse.value = item.verse_key;
    activeWord.value = null;
    currentSegments = item.segments ?? null;

    // استخدم العنصر المُحمَّل مسبقاً إن طابق (تلاوة متواصلة بلا فجوة)
    let a: HTMLAudioElement;
    if (preloadEl && preloadEl.src === absUrl(item.url)) {
        a = preloadEl;
        preloadEl = null;
    } else {
        a = makeEl(item.url);
    }

    // بدّل العنصر الحالي
    if (audioEl && audioEl !== a) {
        audioEl.pause();
        audioEl.removeEventListener('ended', onEnded);
        audioEl.removeEventListener('timeupdate', onTimeUpdate);
    }
    audioEl = a;
    a.removeEventListener('ended', onEnded);
    a.removeEventListener('timeupdate', onTimeUpdate);
    a.addEventListener('ended', onEnded);
    a.addEventListener('timeupdate', onTimeUpdate);
    void a.play();
    playing.value = true;
    scrollToVerse(item.verse_key);

    // جهّز الآية التالية مسبقاً أثناء تشغيل الحالية
    const nx = props.audio[i + 1];
    preloadEl = nx ? makeEl(nx.url) : null;
}
function toggle() {
    if (!props.audio.length) return;
    if (playing.value) { audioEl?.pause(); playing.value = false; }
    else if (activeVerse.value && audioEl) { void audioEl.play(); playing.value = true; }
    else playAt(0);
}
function stop() {
    audioEl?.pause();
    playing.value = false;
    activeVerse.value = null;
    activeWord.value = null;
    currentSegments = null;
}
function scrollToVerse(key: string) {
    document.querySelector(`[data-verse="${key}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

/* ---------- لوحة التفسير ---------- */
const drawerOpen = ref(false);
const loadingVerse = ref(false);
const selectedVerse = ref<string | null>(null);
interface VerseData {
    verse_key: string; number_in_surah: number; text_uthmani: string;
    surah: { id: number; name: string };
    tafsirs: { name: string; text: string }[];
    translations: { name: string; language: string; text: string }[];
}
const verseData = ref<VerseData | null>(null);
const activeTafsir = ref(0); // فهرس التفسير المعروض في القائمة
const showTranslation = ref(false);

async function openVerse(key: string) {
    selectedVerse.value = key;
    drawerOpen.value = true;
    loadingVerse.value = true;
    verseData.value = null;
    activeTafsir.value = 0;
    try {
        const res = await fetch(`/api/verse/${key}`, { headers: { Accept: 'application/json' } });
        verseData.value = await res.json();
    } finally {
        loadingVerse.value = false;
    }
}
function closeDrawer() {
    drawerOpen.value = false;
    selectedVerse.value = null;
}
function onWordClick(w: WordT) {
    if (memoMode.value) revealVerse(w.verse_key);
    else openVerse(w.verse_key);
}
function playVerseFromDrawer(key: string) {
    const i = props.audio.findIndex((a) => a.verse_key === key);
    if (i >= 0) playAt(i);
}

/* ---------- مشاركة كصورة ---------- */
let logoImg: HTMLImageElement | null = null;
function loadLogo(): Promise<HTMLImageElement | null> {
    if (logoImg) return Promise.resolve(logoImg);
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => { logoImg = img; resolve(img); };
        img.onerror = () => resolve(null);
        img.src = '/storage/Images/logo/qu-logo-v4.webp';
    });
}
function toArabicNum(n: number): string {
    return String(n).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[+d]);
}
function stripHtml(html: string): string {
    const d = document.createElement('div');
    d.innerHTML = html.replace(/<br\s*\/?>/gi, ' ');
    return (d.textContent || '').replace(/\s+/g, ' ').trim();
}
function roundRect(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}
function wrapArabic(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] {
    const words = text.split(' ');
    const lines: string[] = [];
    let cur = '';
    for (const w of words) {
        const test = cur ? `${cur} ${w}` : w;
        if (ctx.measureText(test).width > maxWidth && cur) { lines.push(cur); cur = w; }
        else cur = test;
    }
    if (cur) lines.push(cur);
    return lines;
}
function drawFrame(ctx: CanvasRenderingContext2D, W: number, H: number) {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, '#25935f'); g.addColorStop(1, '#0e3a27');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    roundRect(ctx, 70, 70, W - 140, H - 140, 40);
    ctx.fillStyle = '#fdfbf4'; ctx.fill();
    ctx.strokeStyle = '#c6a15a'; ctx.lineWidth = 3; ctx.stroke();
}
async function drawLogo(ctx: CanvasRenderingContext2D, W: number, topY: number): Promise<number> {
    const logo = await loadLogo();
    if (!logo) return topY;
    const h = 88, w = h * (logo.width / logo.height);
    ctx.drawImage(logo, (W - w) / 2, topY, w, h);
    return topY + h;
}
async function exportCanvas(c: HTMLCanvasElement, name: string, title: string) {
    return new Promise<void>((resolve) => {
        c.toBlob(async (blob) => {
            if (!blob) { resolve(); return; }
            const file = new File([blob], name, { type: 'image/png' });
            const nav = navigator as any;
            if (nav.canShare && nav.canShare({ files: [file] })) {
                try { await nav.share({ files: [file], title }); resolve(); return; } catch { /* */ }
            }
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = name; a.click();
            URL.revokeObjectURL(url);
            resolve();
        }, 'image/png');
    });
}

// صورة الآية (رسم عثماني + رقم الآية + الشعار)
async function shareAyahImage() {
    const v = verseData.value;
    if (!v) return;
    try { await document.fonts.load('64px "Amiri Quran"'); await document.fonts.ready; } catch { /* */ }

    const W = 1080, H = 1080, m = 70;
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const ctx = c.getContext('2d');
    if (!ctx) return;

    drawFrame(ctx, W, H);
    const afterLogo = await drawLogo(ctx, W, m + 55);
    ctx.direction = 'rtl'; ctx.textAlign = 'center';

    ctx.fillStyle = '#25935f';
    ctx.font = 'bold 46px "Amiri Quran", serif';
    const ySurah = afterLogo + 78;
    ctx.fillText(`سورة ${v.surah.name}`, W / 2, ySurah);

    // نص الآية + رمز نهاية الآية بالرقم
    ctx.fillStyle = '#1b1b1b';
    const fs = 64; ctx.font = `${fs}px "Amiri Quran", serif`;
    const text = `${v.text_uthmani} ﴿${toArabicNum(v.number_in_surah)}﴾`;
    const lines = wrapArabic(ctx, text, W - 2 * m - 130);
    const lh = fs * 1.95;
    const top = ySurah + 40, bottom = H - m - 110;
    let y = (top + bottom) / 2 - (lines.length * lh) / 2 + lh / 2;
    for (const ln of lines) { ctx.fillText(ln, W / 2, y); y += lh; }

    ctx.fillStyle = '#25935f';
    ctx.font = '600 30px "Segoe UI", Tahoma, sans-serif';
    ctx.fillText('المصحف الإلكتروني · جامعة القصيم', W / 2, H - m - 52);

    await exportCanvas(c, `ayah-${v.verse_key.replace(':', '-')}.png`, `آية ${v.verse_key}`);
}

// صورة التفسير (الآية + نص التفسير المختار)
function canvasToBlob(c: HTMLCanvasElement): Promise<Blob | null> {
    return new Promise((res) => c.toBlob((b) => res(b), 'image/png'));
}
async function shareFiles(files: File[], title: string) {
    const nav = navigator as any;
    if (nav.canShare && nav.canShare({ files })) {
        try { await nav.share({ files, title }); return; } catch { /* ألغى/فشل — نُنزّل */ }
    }
    for (const f of files) {
        const url = URL.createObjectURL(f);
        const a = document.createElement('a');
        a.href = url; a.download = f.name; a.click();
        URL.revokeObjectURL(url);
        await new Promise((r) => setTimeout(r, 350));
    }
}

async function shareTafsirImage() {
    const v = verseData.value;
    if (!v || !v.tafsirs.length) return;
    const t = v.tafsirs[activeTafsir.value] || v.tafsirs[0];
    try { await document.fonts.load('48px "Amiri Quran"'); await document.fonts.ready; } catch { /* */ }

    const W = 1080, m = 70, pad = m + 55;
    const ayahFs = 50, ayLH = ayahFs * 1.9;
    const tafFs = 33, tfLH = tafFs * 1.75;
    const measure = document.createElement('canvas').getContext('2d')!;
    measure.direction = 'rtl';

    measure.font = `${ayahFs}px "Amiri Quran", serif`;
    const ayahText = `${v.text_uthmani} ﴿${toArabicNum(v.number_in_surah)}﴾`;
    const ayahLines = wrapArabic(measure, ayahText, W - 2 * pad);

    measure.font = `${tafFs}px "Segoe UI", Tahoma, sans-serif`;
    const allTaf = wrapArabic(measure, stripHtml(t.text), W - 2 * pad);

    // تقسيم التفسير إلى أجزاء (~26 سطراً لكل صورة) مع «يتبع»
    const perPart = 26;
    const parts: string[][] = [];
    for (let i = 0; i < allTaf.length; i += perPart) parts.push(allTaf.slice(i, i + perPart));
    if (parts.length === 0) parts.push([]);
    const total = parts.length;

    async function buildPart(partLines: string[], idx: number): Promise<File | null> {
        const isFirst = idx === 0;
        const isLast = idx === total - 1;

        // ارتفاع مطابق للرسم
        let yy = m + 45 + 88 + 62; // شعار + ترويسة
        yy += isFirst ? (95 + ayahLines.length * ayLH + 18 + 52 + 54) : (66 + 40);
        yy += partLines.length * tfLH;
        if (!isLast) yy += 56; // سطر «يتبع»
        const H = Math.round(yy + 60 + 40 + m);

        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const ctx = c.getContext('2d');
        if (!ctx) return null;

        drawFrame(ctx, W, H);
        const afterLogo = await drawLogo(ctx, W, m + 45);
        ctx.direction = 'rtl'; ctx.textAlign = 'center';

        ctx.fillStyle = '#25935f';
        ctx.font = 'bold 40px "Amiri Quran", serif';
        let y = afterLogo + 62;
        const suffix = total > 1 ? ` (${toArabicNum(idx + 1)}/${toArabicNum(total)})` : '';
        ctx.fillText(`سورة ${v!.surah.name} · الآية ${v!.number_in_surah}${suffix}`, W / 2, y);

        if (isFirst) {
            ctx.fillStyle = '#1b1b1b';
            ctx.font = `${ayahFs}px "Amiri Quran", serif`;
            y += 95;
            for (const ln of ayahLines) { ctx.fillText(ln, W / 2, y); y += ayLH; }
            y += 18;
            ctx.strokeStyle = '#e2d5ac'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W - pad, y); ctx.stroke();
            y += 52;
            ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 33px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText(`التفسير — ${t.name}`, W / 2, y);
            y += 54;
        } else {
            y += 66;
            ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 32px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText(`تتمة التفسير — ${t.name}`, W / 2, y);
            y += 40;
        }

        ctx.textAlign = 'right'; ctx.fillStyle = '#2c3a29';
        ctx.font = `${tafFs}px "Segoe UI", Tahoma, sans-serif`;
        for (const ln of partLines) { ctx.fillText(ln, W - pad, y); y += tfLH; }

        if (!isLast) {
            ctx.textAlign = 'left'; ctx.fillStyle = '#8a6d2f';
            ctx.font = 'bold 30px "Segoe UI", Tahoma, sans-serif';
            ctx.fillText('يتبع ⟵', pad, y + 32);
        }

        ctx.textAlign = 'center'; ctx.fillStyle = '#25935f';
        ctx.font = '600 28px "Segoe UI", Tahoma, sans-serif';
        ctx.fillText('المصحف الإلكتروني · جامعة القصيم', W / 2, H - m - 40);

        const blob = await canvasToBlob(c);
        if (!blob) return null;
        const key = v!.verse_key.replace(':', '-');
        const name = total > 1 ? `tafsir-${key}-${idx + 1}.png` : `tafsir-${key}.png`;
        return new File([blob], name, { type: 'image/png' });
    }

    const files: File[] = [];
    for (let i = 0; i < total; i++) {
        const f = await buildPart(parts[i], i);
        if (f) files.push(f);
    }
    await shareFiles(files, `تفسير ${v.verse_key}`);
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    // حفظ آخر صفحة توقّف عندها القارئ تلقائياً
    try {
        localStorage.setItem('quran-last-page', String(props.page));
        if (props.reciterId) localStorage.setItem('quran-last-reciter', String(props.reciterId));
    } catch { /* */ }
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    audioEl?.pause();
});
</script>

<template>
    <Head :title="`المصحف — صفحة ${page}`">
        <!-- تحميل مسبق لخط صفحة QCF ليظهر النص العثماني بشكله النهائي مباشرة -->
        <link rel="preload" as="font" type="font/woff2" crossorigin :href="`/fonts/qcf/v2/p${page}.woff2`" />
    </Head>

    <div class="mushaf-screen" dir="rtl">
        <AppNav active="mushaf" />

        <!-- شريط أدوات صغير -->
        <header class="topbar">
            <button class="chip toggle" :class="{ on: memoMode }" @click="toggleMemo">
                {{ memoMode ? '✓ وضع الحفظ' : 'وضع الحفظ' }}
            </button>
        </header>
        <p v-if="memoMode" class="memo-hint">وضع الحفظ مُفعّل — الكلمات مخفية، اضغط أي كلمة لكشف آيتها.</p>

        <!-- صفحة المصحف -->
        <main class="page-wrap">
            <button class="nav next" :disabled="!next" @click="go(next)" aria-label="التالية">‹</button>

            <div class="mushaf-page">
                <!-- ترويسة داخل الإطار: اسم السورة (يمين) والجزء (يسار) — كمصحف المدينة -->
                <div class="page-head">
                    <span class="ph-surah">{{ surahs.map(s => 'سورة ' + s.name_arabic).join(' · ') }}</span>
                    <span class="ph-juz" v-if="juz">الجزء {{ juz }}</span>
                </div>
                <div class="ph-divider"></div>

                <template v-for="line in lines" :key="line.line_number">
                    <div v-if="line.start_surah" class="surah-banner">
                        <div class="surah-name">سورة {{ line.start_surah.name_arabic }}</div>
                        <div v-if="line.start_surah.bismillah_pre" class="basmalah">
                            بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ
                        </div>
                    </div>

                    <div class="qline" :class="{ centered: centeredPage }" :style="{ fontFamily: pageFont }">
                        <span
                            v-for="(w, i) in line.words"
                            :key="i"
                            class="word"
                            :class="{
                                end: w.type === 'end',
                                playing: w.verse_key === activeVerse,
                                wordlit: w.verse_key === activeVerse && w.pos === activeWord,
                                selected: w.verse_key === selectedVerse,
                                blurred: memoMode && w.type !== 'end' && !revealed.has(w.verse_key),
                            }"
                            :data-verse="w.verse_key"
                            @click="onWordClick(w)"
                        >{{ w.code }}</span>
                    </div>
                </template>
            </div>

            <button class="nav prev" :disabled="!prev" @click="go(prev)" aria-label="السابقة">›</button>
        </main>

        <!-- مشغّل الصوت — رصيف عائم -->
        <footer class="player">
            <div class="dock">
                <button class="ico" :disabled="!prev" @click="go(prev)" aria-label="السابقة">›</button>
                <span class="page-num">{{ page }}</span>
                <button class="ico" :disabled="!next" @click="go(next)" aria-label="التالية">‹</button>

                <div class="divider"></div>

                <button class="play" @click="toggle" :disabled="!audio.length">
                    {{ playing ? '❚❚' : '▶' }}
                </button>
                <button class="ico stop" @click="stop" v-if="activeVerse" aria-label="إيقاف">◼</button>
                <div class="pinfo">
                    <select
                        v-if="reciters.length > 1"
                        class="reciter-sel"
                        :value="reciterId ?? undefined"
                        @change="changeReciter"
                        aria-label="اختيار القارئ"
                    >
                        <option v-for="r in reciters" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <b v-else-if="reciter">{{ reciter }}</b>
                    <small v-if="activeVerse">يتلو الآية {{ activeVerse }}</small>
                    <small v-else>اضغط للاستماع لتلاوة الصفحة</small>
                </div>
            </div>
        </footer>

        <!-- لوحة التفسير/الترجمة -->
        <transition name="slide">
            <aside v-if="drawerOpen" class="drawer">
                <button class="close" @click="closeDrawer">✕</button>
                <div v-if="loadingVerse" class="loading">…جارٍ التحميل</div>
                <div v-else-if="verseData" class="vcontent">
                    <div class="vhead">
                        <span class="vbadge">{{ verseData.surah.name }} : {{ verseData.number_in_surah }}</span>
                        <div class="vactions">
                            <button class="mini-btn" @click="() => playVerseFromDrawer(verseData!.verse_key)">▶ استماع</button>
                            <button class="mini-btn" @click="shareAyahImage">⬇ صورة</button>
                        </div>
                    </div>
                    <p class="vtext">{{ verseData.text_uthmani }}</p>

                    <template v-if="verseData.translations.length">
                        <button class="section-toggle" @click="showTranslation = !showTranslation">
                            <span>ترجمة المعاني</span>
                            <span class="chev">{{ showTranslation ? '▾' : '▸' }}</span>
                        </button>
                        <p v-show="showTranslation" v-for="(t, i) in verseData.translations" :key="i" class="trans">
                            {{ t.text }}<em class="src">— {{ t.name }}</em>
                        </p>
                    </template>

                    <template v-if="verseData.tafsirs.length">
                        <div class="tafsir-head">
                            <h4>التفسير</h4>
                            <div class="thead-actions">
                                <select v-if="verseData.tafsirs.length > 1" v-model="activeTafsir" class="tafsir-select">
                                    <option v-for="(t, i) in verseData.tafsirs" :key="i" :value="i">{{ t.name }}</option>
                                </select>
                                <button class="mini-btn" @click="shareTafsirImage" title="مشاركة التفسير كصورة">⬇ صورة</button>
                            </div>
                        </div>
                        <div class="tafsir" v-html="verseData.tafsirs[activeTafsir]?.text"></div>
                    </template>
                </div>
            </aside>
        </transition>
        <div v-if="drawerOpen" class="backdrop" @click="closeDrawer"></div>
    </div>
</template>

<style scoped>
.mushaf-screen { min-height: 100vh; background: var(--canvas); display: flex; flex-direction: column; color: var(--text); }

/* شريط أدوات صغير */
.topbar { display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.7rem 1.4rem 0.2rem; }
.chip { font-size: 0.8rem; color: var(--text-muted); background: var(--surface); border: 1px solid var(--border); padding: 0.3rem 0.9rem; border-radius: 999px; }
.chip.toggle { cursor: pointer; font-family: inherit; }
.chip.toggle:hover { color: var(--text); }
.chip.toggle.on { background: var(--brand-soft); color: var(--brand); border-color: var(--brand-200); font-weight: 600; }
.memo-hint { text-align: center; margin: 0.2rem 1rem 0; font-size: 0.8rem; color: var(--brand); }
.word.blurred { color: transparent !important; text-shadow: 0 0 12px var(--paper-ink); user-select: none; }
.word.blurred.end { color: var(--gold) !important; text-shadow: none; }

.page-wrap { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 1rem 1rem 7rem; }

/* ورقة المصحف العائمة */
.mushaf-page {
    container-type: inline-size;
    position: relative;
    width: min(720px, 94vw);
    background: var(--paper);
    border: 1.5px solid var(--gold);
    border-radius: 22px;
    padding: clamp(2rem, 5.5vw, 3.2rem) clamp(1.7rem, 5vw, 2.8rem);
    box-shadow: var(--shadow-md);
}
/* برواز بنقش هندسي (هوية جامعة القصيم) حول الصفحة */
.mushaf-page::before {
    content: "";
    position: absolute;
    inset: 7px;
    border-radius: 15px;
    padding: 13px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 18 18'%3E%3Cpath d='M9 1 L17 9 L9 17 L1 9 Z' fill='none' stroke='%2325935f' stroke-width='1'/%3E%3Cpath d='M9 5.2 L12.8 9 L9 12.8 L5.2 9 Z' fill='none' stroke='%23c6a15a' stroke-width='0.9'/%3E%3C/svg%3E") repeat;
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    mask-composite: exclude;
    opacity: 0.85;
    pointer-events: none;
}
/* ترويسة الصفحة داخل الإطار (اسم السورة + الجزء) */
.page-head {
    display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;
    font-family: 'Segoe UI', Tahoma, sans-serif; margin-bottom: 0.5rem;
}
.ph-surah { font-size: 0.95rem; font-weight: 700; color: var(--brand); }
.ph-juz { font-size: 0.85rem; font-weight: 600; color: var(--gold); }
.ph-divider { height: 1.5px; background: linear-gradient(90deg, transparent, var(--gold) 20%, var(--gold) 80%, transparent); opacity: 0.6; margin-bottom: 1rem; }

.qline {
    /* محاذاة QCF الطبيعية: خط المصحف مصمّم ليملأ السطر بمسافاته الخاصة —
       بلا space-between حتى تتّصل خطوط مواضع السجدة والوصلات فوق الكلمات */
    display: flex; justify-content: flex-start; align-items: center; direction: rtl;
    font-size: clamp(0.85rem, 5.7cqw, 2.3rem);
    line-height: 2.0; color: var(--paper-ink); white-space: nowrap;
}
/* صفحات موسّطة (الفاتحة/بداية البقرة) */
.qline.centered { justify-content: center; }
.word { cursor: pointer; transition: background 0.15s, color 0.15s; border-radius: 8px; padding: 0 0.04em; }
.word:hover { background: var(--brand-soft); }
.word.end { color: var(--gold); }
.word.playing { background: var(--brand-soft); }
.word.wordlit { background: var(--brand); color: #fff; }
.word.selected { background: var(--brand-soft); box-shadow: 0 0 0 1.5px var(--brand-200); }

/* لافتة السورة — خطوط رفيعة أنيقة */
.surah-banner { text-align: center; margin: 1.1rem 0 0.3rem; }
.surah-name {
    display: flex; align-items: center; gap: 0.9rem; justify-content: center;
    font-family: 'Amiri Quran', 'Traditional Arabic', serif; font-size: 1.5rem; font-weight: 700; color: var(--brand);
}
.surah-name::before, .surah-name::after { content: ""; flex: 1; height: 1px; background: var(--border); max-width: 120px; }
.basmalah { font-family: 'Amiri Quran', serif; font-size: 1.35rem; margin-top: 0.5rem; color: var(--paper-ink); opacity: 0.85; }

/* أزرار التنقّل الجانبية — دائرية شبحية عائمة */
.nav {
    position: fixed; top: 50%; transform: translateY(-50%); z-index: 15;
    width: 46px; height: 46px; border-radius: 50%;
    background: var(--surface); color: var(--text-muted); border: 1px solid var(--border);
    font-size: 1.4rem; cursor: pointer; box-shadow: var(--shadow-sm);
    display: grid; place-items: center; transition: color 0.15s, border-color 0.15s, transform 0.15s;
}
.nav.next { left: max(1rem, calc(50% - 410px)); }
.nav.prev { right: max(1rem, calc(50% - 410px)); }
/* منع انعكاس رموز الأسهم في السياق العربي */
.nav, .dock .ico { direction: ltr; }
.nav:hover:not(:disabled) { color: var(--brand); border-color: var(--brand-200); transform: translateY(-50%) scale(1.08); }
.nav:disabled { opacity: 0.3; cursor: default; }

/* رصيف المشغّل العائم */
.player { position: fixed; bottom: 1.1rem; left: 0; right: 0; z-index: 20; display: flex; justify-content: center; pointer-events: none; padding: 0 1rem; }
.dock {
    pointer-events: auto; display: flex; align-items: center; gap: 0.45rem;
    background: var(--glass); backdrop-filter: saturate(180%) blur(18px); -webkit-backdrop-filter: saturate(180%) blur(18px);
    border: 1px solid var(--glass-brd); border-radius: 999px; padding: 0.45rem 0.65rem;
    box-shadow: var(--shadow-lg); max-width: min(580px, 96vw);
}
.dock .ico {
    width: 36px; height: 36px; border-radius: 50%; border: none; background: transparent; color: var(--text-muted);
    font-size: 1.2rem; cursor: pointer; display: grid; place-items: center; transition: background 0.15s, color 0.15s;
}
.dock .ico:hover:not(:disabled) { background: var(--surface-2); color: var(--text); }
.dock .ico:disabled { opacity: 0.3; cursor: default; }
.dock .ico.stop { font-size: 0.9rem; }
.page-num { min-width: 2.2rem; text-align: center; font-weight: 700; font-size: 0.9rem; color: var(--text); }
.divider { width: 1px; height: 26px; background: var(--border); margin: 0 0.2rem; }
.play {
    width: 44px; height: 44px; border-radius: 50%; border: none; cursor: pointer;
    background: var(--brand); color: #fff; font-size: 0.95rem; display: grid; place-items: center;
    box-shadow: 0 4px 14px rgba(37,147,95,0.35); transition: transform 0.12s;
}
.play:hover:not(:disabled) { transform: scale(1.07); }
.play:disabled { opacity: 0.5; cursor: default; }
.pinfo { display: flex; flex-direction: column; line-height: 1.2; padding: 0 0.5rem; min-width: 0; }
.pinfo b { font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pinfo small { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; }
.reciter-sel {
    font-family: inherit; font-size: 0.85rem; font-weight: 700; color: var(--text);
    background: transparent; border: none; cursor: pointer; padding: 0; margin: 0;
    max-width: 40vw; outline: none; -webkit-appearance: none; appearance: none;
}
.reciter-sel option { color: #000; }

/* اللوحة الجانبية */
.drawer { position: fixed; top: 0; bottom: 0; left: 0; z-index: 60; width: min(460px, 94vw); background: var(--surface); box-shadow: var(--shadow-lg); padding: 1.5rem; overflow-y: auto; color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; }
.backdrop { position: fixed; inset: 0; background: rgba(10,20,15,0.42); backdrop-filter: blur(2px); z-index: 55; }
.close { position: sticky; top: 0; float: left; border: 1px solid var(--border); background: var(--surface); color: var(--text-muted); border-radius: 50%; width: 34px; height: 34px; cursor: pointer; }
.vhead { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
.vbadge { background: var(--brand-soft); color: var(--brand); padding: 0.3rem 0.9rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
.vactions { display: flex; gap: 0.4rem; }
.mini-btn { border: 1px solid var(--brand-200); color: var(--brand); background: transparent; border-radius: 999px; padding: 0.3rem 0.8rem; cursor: pointer; font-family: inherit; font-size: 0.82rem; white-space: nowrap; }
.mini-btn:hover { background: var(--brand-soft); }
.vtext { font-family: 'Amiri Quran', serif; font-size: 1.7rem; line-height: 2.2; color: var(--paper-ink); background: var(--surface-2); padding: 1rem 1.2rem; border-radius: 16px; }
.section-toggle { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-top: 1.1rem; padding: 0.75rem 1rem; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); border-radius: 12px; font-size: 0.95rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.section-toggle .chev { color: var(--brand); }
.trans { line-height: 1.8; color: var(--text); margin: 0.6rem 0.2rem; }
.trans .src { display: block; font-style: normal; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
.tafsir-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin: 1.3rem 0 0.7rem; }
.tafsir-head h4 { margin: 0; color: var(--text); font-size: 1.05rem; }
.thead-actions { display: flex; align-items: center; gap: 0.4rem; }
.tafsir-select { font-family: inherit; font-size: 0.85rem; padding: 0.4rem 0.7rem; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--text); cursor: pointer; max-width: 60%; }
.tafsir { line-height: 2; font-size: 1rem; color: var(--text); }
.tafsir :deep(.green), .tafsir :deep(b) { color: var(--brand); font-weight: 700; }
.tafsir :deep(sup) { display: none; }
.loading { padding: 2rem; text-align: center; color: var(--text-muted); }

.slide-enter-active, .slide-leave-active { transition: transform 0.28s cubic-bezier(0.4,0,0.2,1); }
.slide-enter-from, .slide-leave-to { transform: translateX(-100%); }

/* الجوال */
@media (max-width: 640px) {
    .topbar { padding: 0.5rem 0.9rem; }
    .chip { font-size: 0.72rem; padding: 0.2rem 0.6rem; }
    .page-wrap { padding: 0.9rem 0.5rem 6rem; }
    .nav { display: none; }
    .mushaf-page { width: 96vw; border-radius: 18px; }
    .surah-name { font-size: 1.2rem; }
    .basmalah { font-size: 1.1rem; }
    .dock { gap: 0.3rem; padding: 0.4rem 0.5rem; }
    .pinfo { max-width: 40vw; }
    .drawer { width: 100vw; padding: 1.1rem; }
    .vtext { font-size: 1.45rem; }
}
</style>
