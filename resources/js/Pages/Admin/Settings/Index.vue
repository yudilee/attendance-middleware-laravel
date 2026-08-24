<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Sliders,
    Clock,
    Smartphone,
    DownloadCloud,
    AlertTriangle,
    CheckCircle2,
    FileText,
    Save,
    Sparkles,
    ShieldCheck,
    Info,
    ExternalLink
} from 'lucide-vue-next';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    late_grace_period_minutes: props.settings.late_grace_period_minutes ?? 15,
    default_shift_start: props.settings.default_shift_start ?? '08:00',
    app_latest_version: props.settings.app_latest_version ?? '1.0.0',
    app_min_version: props.settings.app_min_version ?? '1.0.0',
    app_force_update: Boolean(props.settings.app_force_update),
    app_download_url: props.settings.app_download_url ?? '',
    app_changelog: props.settings.app_changelog ?? '',
});

const submit = () => {
    form.post('/admin/settings', {
        preserveScroll: true,
    });
};

const calculatedLateTime = computed(() => {
    const parts = (form.default_shift_start || '08:00').split(':');
    let hours = parseInt(parts[0], 10) || 8;
    let minutes = parseInt(parts[1], 10) || 0;
    
    minutes += parseInt(form.late_grace_period_minutes, 10) || 0;
    hours += Math.floor(minutes / 60);
    minutes = minutes % 60;
    hours = hours % 24;

    const hh = String(hours).padStart(2, '0');
    const mm = String(minutes).padStart(2, '0');
    return `${hh}:${mm}:00`;
});
</script>

<template>
    <AdminLayout title="System & App Settings">
        <Head title="System Settings" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <Sliders class="w-5 h-5 text-blue-400" />
                    System & Mobile App Configuration
                </h2>
                <p class="text-xs text-slate-400">Configure attendance calculation policies, late grace limits, and mobile in-app updates.</p>
            </div>
            <button
                @click="submit"
                :disabled="form.processing"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition disabled:opacity-50 self-start sm:self-auto"
            >
                <Save class="w-4 h-4" />
                {{ form.processing ? 'Saving Changes...' : 'Save Settings' }}
            </button>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Attendance & Shift Policies -->
            <div class="lg:col-span-6 space-y-6">
                <!-- Late Grace Period Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400">
                                <Clock class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">Late Arrival Grace Period</h3>
                                <p class="text-[11px] text-slate-400">Tolerated buffer before clock-in is flagged as Late</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                Default Shift Start Time
                            </label>
                            <input
                                v-model="form.default_shift_start"
                                type="time"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                            <p v-if="form.errors.default_shift_start" class="text-rose-400 text-[10px] mt-1">{{ form.errors.default_shift_start }}</p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-slate-300">
                                    Grace Buffer Duration (Minutes)
                                </label>
                                <span class="text-xs font-bold text-amber-400 font-mono bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                    {{ form.late_grace_period_minutes }} Mins
                                </span>
                            </div>
                            <input
                                v-model.number="form.late_grace_period_minutes"
                                type="range"
                                min="0"
                                max="60"
                                step="1"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                            <div class="flex justify-between text-[10px] text-slate-500 font-mono mt-1">
                                <span>0 min (Strict)</span>
                                <span>15 mins</span>
                                <span>30 mins</span>
                                <span>60 mins</span>
                            </div>
                        </div>

                        <!-- Dynamic Calculation Preview -->
                        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800/80 flex items-start gap-3">
                            <Info class="w-4 h-4 text-blue-400 mt-0.5 flex-shrink-0" />
                            <div class="text-xs text-slate-300 leading-relaxed">
                                Employees clocking in after
                                <span class="font-mono font-bold text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                    {{ calculatedLateTime }}
                                </span>
                                ({{ form.default_shift_start }} + {{ form.late_grace_period_minutes }}m) will be classified as <b>Late Arrival</b> on the Live Stream, Dashboard, and Attendance Reports.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Mobile App In-App Update Management -->
            <div class="lg:col-span-6 space-y-6">
                <!-- Mobile App Release Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                                <Smartphone class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">Flutter Mobile App OTA Updates</h3>
                                <p class="text-[11px] text-slate-400">Manage in-app version checks, changelogs & download links</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                    Latest App Version
                                </label>
                                <input
                                    v-model="form.app_latest_version"
                                    type="text"
                                    required
                                    placeholder="e.g. 1.1.0"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                                />
                                <p v-if="form.errors.app_latest_version" class="text-rose-400 text-[10px] mt-1">{{ form.errors.app_latest_version }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                    Minimum Supported Version
                                </label>
                                <input
                                    v-model="form.app_min_version"
                                    type="text"
                                    required
                                    placeholder="e.g. 1.0.0"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                                />
                                <p v-if="form.errors.app_min_version" class="text-rose-400 text-[10px] mt-1">{{ form.errors.app_min_version }}</p>
                            </div>
                        </div>

                        <!-- Enforce Mandatory Update Toggle -->
                        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <AlertTriangle class="w-3.5 h-3.5 text-amber-400" />
                                    Enforce Mandatory Update
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Blocks app usage and prevents skipping until updated
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.app_force_update"
                                    class="sr-only peer"
                                />
                                <div class="w-10 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                Direct APK Download URL
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.app_download_url"
                                    type="url"
                                    placeholder="https://attendance.hartonomotor-group.com/downloads/app-release.apk"
                                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                                />
                            </div>
                            <p v-if="form.errors.app_download_url" class="text-rose-400 text-[10px] mt-1">{{ form.errors.app_download_url }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                Release Changelog / What's New
                            </label>
                            <textarea
                                v-model="form.app_changelog"
                                rows="4"
                                placeholder="• Added smart auto-toggle for Clock In & Out&#10;• Added in-app changelog & update notifications&#10;• Enhanced biometric verification stability"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 leading-relaxed font-sans"
                            ></textarea>
                            <p v-if="form.errors.app_changelog" class="text-rose-400 text-[10px] mt-1">{{ form.errors.app_changelog }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
