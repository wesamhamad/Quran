<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({ password: '' });
const show = ref(false);

function submit() {
    form.post('/gate', {
        preserveScroll: true,
        onError: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="الدخول" />

    <div class="gate" dir="rtl">
        <div class="card">
            <div class="mark" aria-hidden="true">﷽</div>

            <h1 class="title">القرآن الكريم</h1>
            <p class="sub">هذه المنصّة محميّة — أدخل كلمة المرور للمتابعة</p>

            <form class="form" @submit.prevent="submit">
                <label class="label" for="password">كلمة المرور</label>

                <div class="field" :class="{ error: !!form.errors.password }">
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        v-model="form.password"
                        class="input"
                        autocomplete="current-password"
                        autofocus
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        class="toggle"
                        :aria-label="show ? 'إخفاء' : 'إظهار'"
                        @click="show = !show"
                    >
                        {{ show ? 'إخفاء' : 'إظهار' }}
                    </button>
                </div>

                <p v-if="form.errors.password" class="err">
                    {{ form.errors.password }}
                </p>

                <button
                    type="submit"
                    class="btn"
                    :disabled="form.processing || !form.password"
                >
                    {{ form.processing ? '...' : 'دخول' }}
                </button>
            </form>

            <p class="foot">جامعة القصيم — Qassim University</p>
        </div>
    </div>
</template>

<style scoped>
.gate {
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background:
        radial-gradient(
            1200px 600px at 50% -10%,
            var(--brand-soft),
            transparent 60%
        ),
        var(--canvas);
    font-family: inherit;
}
.card {
    width: 100%;
    max-width: 380px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    padding: 2.2rem 1.8rem;
    text-align: center;
}
.mark {
    font-size: 2.6rem;
    line-height: 1;
    color: var(--brand);
    margin-bottom: 0.9rem;
}
.title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text);
    margin: 0;
}
.sub {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0.4rem 0 1.6rem;
}
.form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    text-align: right;
}
.label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text);
}
.field {
    display: flex;
    align-items: center;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: border-color 0.15s;
}
.field:focus-within {
    border-color: var(--brand);
}
.field.error {
    border-color: #d9534f;
}
.input {
    flex: 1;
    background: transparent;
    border: 0;
    outline: none;
    padding: 0.7rem 0.85rem;
    font-family: inherit;
    font-size: 0.95rem;
    color: var(--text);
    letter-spacing: 0.08em;
}
.toggle {
    background: transparent;
    border: 0;
    color: var(--text-muted);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0 0.85rem;
    cursor: pointer;
}
.err {
    color: #d9534f;
    font-size: 0.78rem;
    margin: 0.1rem 0 0;
}
.btn {
    margin-top: 0.8rem;
    padding: 0.75rem 1rem;
    border: 0;
    border-radius: 14px;
    background: var(--brand);
    color: #fff;
    font-family: inherit;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(37, 147, 95, 0.28);
    transition:
        transform 0.12s,
        opacity 0.15s;
}
.btn:hover:not(:disabled) {
    transform: translateY(-2px);
}
.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}
.foot {
    margin: 1.6rem 0 0;
    font-size: 0.72rem;
    color: var(--text-muted);
}
</style>
