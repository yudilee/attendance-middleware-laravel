<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { Sun, Moon, ShieldCheck } from 'lucide-vue-next';

const isDarkMode = ref(true);

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDarkMode.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
    }
});

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};
</script>

<template>
    <div
        :class="[
            'min-h-screen relative flex flex-col justify-center items-center px-4 py-12 selection:bg-teal-500 selection:text-white transition-colors duration-300 font-sans',
            isDarkMode ? 'bg-slate-950 text-slate-100 dark' : 'bg-slate-50 text-slate-900'
        ]"
    >
        <!-- Ambient Glow Orbs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-teal-500/10 blur-3xl"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 rounded-full bg-indigo-500/10 blur-3xl"></div>
            <div class="absolute -bottom-40 left-1/3 w-96 h-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
        </div>

        <!-- Top Header / Theme Toggle -->
        <div class="absolute top-6 right-6 z-20">
            <button
                @click="toggleTheme"
                :class="[
                    'p-2.5 rounded-xl border transition-all duration-200 flex items-center gap-2 text-xs font-medium backdrop-blur-md',
                    isDarkMode
                        ? 'bg-slate-900/80 hover:bg-slate-800 text-amber-300 border-slate-800 shadow-lg'
                        : 'bg-white/80 hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
                ]"
                :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            >
                <Sun v-if="isDarkMode" class="w-4 h-4" />
                <Moon v-else class="w-4 h-4" />
                <span class="text-xs">{{ isDarkMode ? 'Dark' : 'Light' }}</span>
            </button>
        </div>

        <!-- Logo & Brand Header -->
        <div class="relative z-10 flex flex-col items-center mb-8 text-center">
            <Link href="/" class="group flex flex-col items-center">
                <div class="p-3 rounded-2xl bg-slate-900/60 dark:bg-slate-900/80 border border-slate-800/80 backdrop-blur-xl shadow-xl transition-transform duration-300 group-hover:scale-105">
                    <ApplicationLogo class="h-12 w-auto" />
                </div>
                <div class="mt-4">
                    <h1 class="text-xl font-bold tracking-tight bg-gradient-to-r from-teal-400 via-cyan-300 to-blue-400 bg-clip-text text-transparent">
                        Attendance HRM
                    </h1>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-500/90 mt-0.5">
                        Enterprise Pro
                    </p>
                </div>
            </Link>
        </div>

        <!-- Card Container -->
        <div
            :class="[
                'relative z-10 w-full max-w-md backdrop-blur-2xl rounded-3xl p-8 sm:p-10 border transition-all duration-300 shadow-2xl',
                isDarkMode
                    ? 'bg-slate-900/85 border-slate-800/80 text-slate-200 shadow-black/50'
                    : 'bg-white/95 border-slate-200/90 text-slate-800 shadow-slate-300/40'
            ]"
        >
            <slot />
        </div>

        <!-- Footer -->
        <div class="relative z-10 mt-8 text-center text-xs text-slate-400">
            <div class="flex items-center justify-center gap-1.5 font-medium">
                <ShieldCheck class="w-3.5 h-3.5 text-teal-400" />
                <span>Enterprise Grade Security • 256-Bit SSL</span>
            </div>
        </div>
    </div>
</template>
