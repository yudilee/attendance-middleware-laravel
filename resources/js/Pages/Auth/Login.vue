<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {
    Mail,
    Lock,
    Eye,
    EyeOff,
    ArrowRight,
    ShieldCheck,
    Smartphone,
    Clock,
    MapPin,
    Radio,
    Sun,
    Moon,
    CheckCircle2,
    AlertCircle,
    Loader2
} from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const isDarkMode = ref(true);

// Live Clock
const currentTime = ref('');
const currentDate = ref('');
let clockInterval = null;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', { hour12: false });
    currentDate.value = now.toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

onMounted(() => {
    updateTime();
    clockInterval = setInterval(updateTime, 1000);

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDarkMode.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
    }
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
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

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In - Hartono Motor Group Attendance" />

    <div
        :class="[
            'min-h-screen flex flex-col lg:flex-row transition-colors duration-300 font-sans relative overflow-hidden selection:bg-teal-500 selection:text-white',
            isDarkMode ? 'bg-slate-950 text-slate-100 dark' : 'bg-slate-50 text-slate-900'
        ]"
    >
        <!-- Background Ambient Glows -->
        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/10 blur-[120px]"></div>
            <div class="absolute top-1/2 -right-40 w-[600px] h-[600px] rounded-full bg-indigo-500/10 blur-[140px]"></div>
            <div class="absolute -bottom-40 left-1/3 w-[500px] h-[500px] rounded-full bg-cyan-500/10 blur-[120px]"></div>
        </div>

        <!-- Top Right Theme Switcher -->
        <div class="absolute top-5 right-5 z-50">
            <button
                @click="toggleTheme"
                type="button"
                :class="[
                    'p-2.5 rounded-xl border transition-all duration-200 flex items-center gap-2 text-xs font-semibold backdrop-blur-xl shadow-md cursor-pointer',
                    isDarkMode
                        ? 'bg-slate-900/90 hover:bg-slate-800 text-amber-300 border-slate-700/80'
                        : 'bg-white/90 hover:bg-slate-100 text-slate-700 border-slate-200'
                ]"
                :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            >
                <Sun v-if="isDarkMode" class="w-4 h-4 text-amber-400" />
                <Moon v-else class="w-4 h-4 text-slate-700" />
                <span>{{ isDarkMode ? 'Dark' : 'Light' }}</span>
            </button>
        </div>

        <!-- Left Showcase Panel (Desktop lg+) -->
        <div
            :class="[
                'relative z-10 hidden lg:flex lg:w-7/12 flex-col justify-between p-10 xl:p-14 border-r transition-colors duration-300',
                isDarkMode ? 'border-slate-800/80 bg-slate-950/50' : 'border-slate-200 bg-white/70'
            ]"
        >
            <!-- Brand & Badge Header -->
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="p-2.5 px-3 rounded-2xl bg-white border border-slate-200 shadow-md flex items-center justify-center flex-shrink-0">
                        <ApplicationLogo class="h-8 w-auto max-w-[160px]" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400 bg-teal-500/10 border border-teal-500/20 px-2.5 py-0.5 rounded-full">
                                Hartono Motor Group
                            </span>
                            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                ADMS Online
                            </span>
                        </div>
                        <h2 :class="['text-lg font-bold tracking-tight mt-1', isDarkMode ? 'text-white' : 'text-slate-900']">
                            Attendance HRM Enterprise
                        </h2>
                    </div>
                </div>

                <!-- Hero Copy -->
                <div class="pt-4 max-w-xl">
                    <h1 class="text-2xl xl:text-3xl font-extrabold tracking-tight leading-tight">
                        Intelligent Workforce & <br />
                        <span class="bg-gradient-to-r from-teal-500 via-cyan-500 to-blue-500 bg-clip-text text-transparent">
                            Smart Attendance System
                        </span>
                    </h1>
                    <p :class="['mt-3 text-xs xl:text-sm leading-relaxed', isDarkMode ? 'text-slate-400' : 'text-slate-600']">
                        Unified biometric hardware synchronization, real-time geofenced mobile check-ins, automated multi-shift scheduling, and complete audit tracking across all Hartono Motor Group branches.
                    </p>
                </div>
            </div>

            <!-- Feature Cards Grid -->
            <div class="grid grid-cols-2 gap-3.5 my-6 max-w-xl">
                <!-- Card 1 -->
                <div
                    :class="[
                        'p-3.5 rounded-2xl border backdrop-blur-xl transition-all duration-300 hover:border-teal-500/40 group',
                        isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
                    ]"
                >
                    <div class="w-7 h-7 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-500 mb-2.5 group-hover:scale-110 transition-transform">
                        <Radio class="w-3.5 h-3.5" />
                    </div>
                    <h3 :class="['text-xs font-bold', isDarkMode ? 'text-slate-200' : 'text-slate-800']">
                        Live Hardware Push
                    </h3>
                    <p :class="['text-[11px] mt-1 leading-snug', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                        Instant punch sync via ADMS protocol across all branch biometric terminals.
                    </p>
                </div>

                <!-- Card 2 -->
                <div
                    :class="[
                        'p-3.5 rounded-2xl border backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40 group',
                        isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
                    ]"
                >
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-500 mb-2.5 group-hover:scale-110 transition-transform">
                        <MapPin class="w-3.5 h-3.5" />
                    </div>
                    <h3 :class="['text-xs font-bold', isDarkMode ? 'text-slate-200' : 'text-slate-800']">
                        Geofence & Field Visits
                    </h3>
                    <p :class="['text-[11px] mt-1 leading-snug', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                        High-accuracy GPS boundary validation and mobile canvass task logging.
                    </p>
                </div>

                <!-- Card 3 -->
                <div
                    :class="[
                        'p-3.5 rounded-2xl border backdrop-blur-xl transition-all duration-300 hover:border-indigo-500/40 group',
                        isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
                    ]"
                >
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500 mb-2.5 group-hover:scale-110 transition-transform">
                        <Clock class="w-3.5 h-3.5" />
                    </div>
                    <h3 :class="['text-xs font-bold', isDarkMode ? 'text-slate-200' : 'text-slate-800']">
                        Automated Rostering
                    </h3>
                    <p :class="['text-[11px] mt-1 leading-snug', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                        Dynamic overtime calculations, cross-midnight shifts, and leaves workflow.
                    </p>
                </div>

                <!-- Card 4 -->
                <div
                    :class="[
                        'p-3.5 rounded-2xl border backdrop-blur-xl transition-all duration-300 hover:border-blue-500/40 group',
                        isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
                    ]"
                >
                    <div class="w-7 h-7 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500 mb-2.5 group-hover:scale-110 transition-transform">
                        <Smartphone class="w-3.5 h-3.5" />
                    </div>
                    <h3 :class="['text-xs font-bold', isDarkMode ? 'text-slate-200' : 'text-slate-800']">
                        Mobile App & QR Punch
                    </h3>
                    <p :class="['text-[11px] mt-1 leading-snug', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                        Secure rotating dynamic QR validation and offline-resilient sync.
                    </p>
                </div>
            </div>

            <!-- Bottom Live Clock & Security Status -->
            <div
                :class="[
                    'flex items-center justify-between p-3.5 rounded-2xl border backdrop-blur-xl max-w-xl',
                    isDarkMode ? 'bg-slate-900/50 border-slate-800/80' : 'bg-white border-slate-200 shadow-sm'
                ]"
            >
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-teal-500/10 flex items-center justify-center text-teal-500">
                        <Clock class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="font-mono text-xs font-bold tracking-tight text-teal-600 dark:text-teal-400">
                            {{ currentTime || '00:00:00' }}
                        </div>
                        <div :class="['text-[10px]', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                            {{ currentDate }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    <ShieldCheck class="w-3.5 h-3.5 text-teal-500" />
                    <span>256-Bit SSL Secured</span>
                </div>
            </div>
        </div>

        <!-- Right Login Panel -->
        <div class="relative z-10 flex-1 flex flex-col justify-center items-center px-6 py-10 lg:px-10 xl:px-14 min-h-screen">
            <div class="w-full max-w-md">
                <!-- Mobile Brand Header (Visible on smaller screens lg:hidden) -->
                <div class="flex lg:hidden flex-col items-center mb-6 text-center">
                    <div class="p-3 px-4 rounded-2xl bg-white border border-slate-200 shadow-lg flex items-center justify-center mb-3">
                        <ApplicationLogo class="h-10 w-auto max-w-[180px]" />
                    </div>
                    <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                        Hartono Motor Group
                    </h2>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400 mt-0.5">
                        Attendance HRM Enterprise
                    </p>
                </div>

                <!-- Form Card -->
                <div
                    :class="[
                        'backdrop-blur-2xl rounded-3xl p-8 sm:p-9 border transition-all duration-300 shadow-xl',
                        isDarkMode
                            ? 'bg-slate-900/90 border-slate-800 shadow-black/50 ring-1 ring-white/5'
                            : 'bg-white border-slate-200/90 shadow-slate-200/80'
                    ]"
                >
                    <!-- Form Title & Branding -->
                    <div class="mb-6">
                        <div class="hidden lg:flex items-center justify-between mb-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-teal-600 dark:text-teal-400">
                                    Hartono Motor Group
                                </span>
                                <p :class="['text-xs font-semibold', isDarkMode ? 'text-slate-300' : 'text-slate-700']">
                                    Attendance Portal
                                </p>
                            </div>
                            <div class="p-1.5 px-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center">
                                <ApplicationLogo class="h-5 w-auto" />
                            </div>
                        </div>

                        <h2 :class="['text-xl font-bold tracking-tight', isDarkMode ? 'text-white' : 'text-slate-900']">
                            Sign In
                        </h2>
                        <p :class="['text-xs mt-1', isDarkMode ? 'text-slate-400' : 'text-slate-500']">
                            Enter your credentials to access the enterprise dashboard.
                        </p>
                    </div>

                    <!-- Status Alert Banner -->
                    <div
                        v-if="status"
                        class="mb-5 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center gap-2.5 animate-fadeIn"
                    >
                        <CheckCircle2 class="w-4 h-4 flex-shrink-0" />
                        <span>{{ status }}</span>
                    </div>

                    <!-- General Form Errors Banner -->
                    <div
                        v-if="Object.keys(form.errors).length > 0"
                        class="mb-5 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-medium flex items-start gap-2.5 animate-fadeIn"
                    >
                        <AlertCircle class="w-4 h-4 flex-shrink-0 mt-0.5 text-rose-500" />
                        <div class="space-y-0.5">
                            <p class="font-semibold">Authentication Failed</p>
                            <p v-if="form.errors.email">{{ form.errors.email }}</p>
                            <p v-if="form.errors.password">{{ form.errors.password }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Email Field -->
                        <div>
                            <label
                                for="email"
                                :class="['block text-[11px] font-bold uppercase tracking-wider mb-1.5', isDarkMode ? 'text-slate-300' : 'text-slate-700']"
                            >
                                Email Address
                            </label>
                            <div class="relative rounded-xl shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <Mail class="w-4 h-4" />
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="admin@hartonomotor-group.com"
                                    :class="[
                                        'block w-full pl-10 pr-4 py-2.5 text-xs rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2',
                                        form.errors.email
                                            ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500'
                                            : isDarkMode
                                                ? 'bg-slate-950/80 border-slate-700/80 text-white placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500/20'
                                                : 'bg-slate-50 hover:bg-white focus:bg-white border-slate-300 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-teal-500/20'
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label
                                    for="password"
                                    :class="['block text-[11px] font-bold uppercase tracking-wider', isDarkMode ? 'text-slate-300' : 'text-slate-700']"
                                >
                                    Password
                                </label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    :class="['text-[11px] font-semibold transition hover:underline', isDarkMode ? 'text-teal-400 hover:text-teal-300' : 'text-teal-600 hover:text-teal-700']"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            <div class="relative rounded-xl shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <Lock class="w-4 h-4" />
                                </div>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••••••"
                                    :class="[
                                        'block w-full pl-10 pr-10 py-2.5 text-xs rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2',
                                        form.errors.password
                                            ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500'
                                            : isDarkMode
                                                ? 'bg-slate-950/80 border-slate-700/80 text-white placeholder-slate-500 focus:border-teal-500 focus:ring-teal-500/20'
                                                : 'bg-slate-50 hover:bg-white focus:bg-white border-slate-300 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-teal-500/20'
                                    ]"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
                                    tabindex="-1"
                                >
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me Checkbox -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="form.remember"
                                    class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-teal-600 focus:ring-teal-500/30 cursor-pointer"
                                />
                                <span :class="['text-xs font-medium', isDarkMode ? 'text-slate-300' : 'text-slate-600']">
                                    Remember me on this device
                                </span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full relative group overflow-hidden py-2.5 px-4 rounded-xl text-xs font-bold text-white transition-all duration-200 bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-600 hover:from-teal-500 hover:to-cyan-500 shadow-md shadow-teal-500/20 active:scale-[0.99] disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer mt-2"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <template v-else>
                                <span>Sign In to Dashboard</span>
                                <ArrowRight class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" />
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Footer Security Badge -->
                <div class="mt-6 text-center">
                    <div :class="['inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-medium transition-colors', isDarkMode ? 'bg-slate-900/60 border-slate-800 text-slate-400' : 'bg-slate-100 border-slate-200 text-slate-600']">
                        <ShieldCheck class="w-3.5 h-3.5 text-teal-500" />
                        <span>Hartono Motor Group • Attendance HRM v2.4.0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.25s ease-out forwards;
}
</style>
