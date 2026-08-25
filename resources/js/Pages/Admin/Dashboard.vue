<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Users,
    Smartphone,
    Clock,
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle2,
    ShieldAlert,
    AlertCircle,
    UserX,
    Building,
    TrendingUp,
    Activity,
    Calendar,
    RefreshCw
} from 'lucide-vue-next';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    stats: Object,
    charts: Object,
    recent_punches: Array,
});

const syncingNames = ref(false);
const autoSyncEnabled = ref(props.stats.auto_sync_enabled);
const togglingAutoSync = ref(false);

function syncNamesToAdms() {
    syncingNames.value = true;
    router.post(route('admin.sync-adms-push-names'), {}, {
        onFinish: () => { syncingNames.value = false; },
    });
}

function toggleAutoSync() {
    togglingAutoSync.value = true;
    router.post(route('admin.settings.adms-auto-sync'), {
        enabled: !autoSyncEnabled.value,
    }, {
        onSuccess: () => {
            autoSyncEnabled.value = !autoSyncEnabled.value;
            togglingAutoSync.value = false;
        },
        onError: () => {
            togglingAutoSync.value = false;
        },
        onFinish: () => {
            togglingAutoSync.value = false;
        },
    });
}

const chartCanvas = ref(null);
let chartInstance = null;

// Live Real-Time Clock
const currentTime = ref('');
const currentDate = ref('');
let clockTimer = null;

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false });
    currentDate.value = now.toLocaleDateString('en-US', {
        timeZone: 'Asia/Jakarta',
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

onMounted(() => {
    updateClock();
    clockTimer = setInterval(updateClock, 1000);

    if (chartCanvas.value && props.charts) {
        const ctx = chartCanvas.value.getContext('2d');
        chartInstance = new ChartJS(ctx, {
            type: 'line',
            data: {
                labels: props.charts.trend_dates,
                datasets: [
                    {
                        label: 'Clock In',
                        data: props.charts.trend_in,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#0f172a',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Clock Out',
                        data: props.charts.trend_out,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#0f172a',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 12,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, family: 'inherit' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#1e293b', drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        grid: { color: '#1e293b', drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 11 }, precision: 0 }
                    }
                }
            }
        });
    }
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (chartInstance) chartInstance.destroy();
});
</script>

<template>
    <AdminLayout title="Attendance Dashboard">
        <Head title="Admin Dashboard" />

        <!-- Live Clock & Welcome Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-gradient-to-r from-slate-900 via-slate-900/90 to-blue-950/40 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>Good Day, Administrator</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
                        ADMS Online
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Overview of employee attendance, device health, and real-time punch feeds.</p>
            </div>

            <!-- Live Digital Clock Widget -->
            <div class="flex items-center gap-3 bg-slate-950/80 border border-slate-800/80 px-4 py-2 rounded-xl self-start sm:self-auto">
                <Clock class="w-4 h-4 text-blue-400" />
                <div>
                    <div class="text-sm font-mono font-bold text-white">{{ currentTime }} <span class="text-[10px] text-blue-400 font-sans">WIB</span></div>
                    <div class="text-[10px] text-slate-400">{{ currentDate }}</div>
                </div>
            </div>
        </div>

        <!-- Top Metrics Cards (5-Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <!-- 1. Total Employees -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Staff</p>
                        <h3 class="text-2xl font-bold text-white mt-1 font-mono">{{ stats.total_employees }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                        <Users class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-center text-[11px] text-slate-400">
                    <span>Synced from ADMS</span>
                </div>
            </div>

            <!-- 2. Present Today -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Present Today</p>
                        <h3 class="text-2xl font-bold text-emerald-400 mt-1 font-mono">{{ stats.today_unique_in }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-center text-[11px] text-emerald-400 font-semibold">
                    <span>{{ stats.attendance_rate }}% attendance rate</span>
                </div>
            </div>

            <!-- 3. Late Arrivals -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Late Clock-Ins</p>
                        <h3 class="text-2xl font-bold text-amber-400 mt-1 font-mono">{{ stats.today_late_count }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <AlertCircle class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-center text-[11px] text-slate-400">
                    <span>After 08:15 grace limit</span>
                </div>
            </div>

            <!-- 4. Absent / Off -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Not Clocked In</p>
                        <h3 class="text-2xl font-bold text-rose-400 mt-1 font-mono">{{ stats.today_absent_count }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                        <UserX class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-center text-[11px] text-slate-400">
                    <span>No punch recorded today</span>
                </div>
            </div>

            <!-- 5. Active Devices -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Devices</p>
                        <h3 class="text-2xl font-bold text-indigo-400 mt-1 font-mono">{{ stats.active_devices }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <Smartphone class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 flex items-center text-[11px]">
                    <span v-if="stats.pending_approvals > 0" class="text-amber-400 font-semibold flex items-center gap-1">
                        <ShieldAlert class="w-3.5 h-3.5" />
                        {{ stats.pending_approvals }} pending
                    </span>
                    <span v-else class="text-slate-400">All bindings approved</span>
                </div>
            </div>
        </div>

        <!-- ADMS Sync Actions -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <button
                @click="syncNamesToAdms"
                :disabled="syncingNames"
                class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold text-xs shadow-sm flex items-center gap-2 transition"
            >
                <span v-if="syncingNames" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ syncingNames ? 'Pushing Names...' : 'Sync Names to ADMS' }}
            </button>
            <span class="text-[11px] text-slate-500">Push employee names to ADMS via OPERLOG USER records</span>
        </div>

        <!-- Auto ADMS Sync Toggle -->
        <div class="mb-6 p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <RefreshCw class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Auto Sync Employees from ADMS</h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Automatically sync employee data from ADMS every 24 hours
                        </p>
                    </div>
                </div>
                <button
                    @click="toggleAutoSync"
                    :disabled="togglingAutoSync"
                    class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                    :class="autoSyncEnabled ? 'bg-emerald-500' : 'bg-slate-700'"
                    role="switch"
                    :aria-checked="autoSyncEnabled"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                        :class="autoSyncEnabled ? 'translate-x-5' : 'translate-x-0.5'"
                    ></span>
                </button>
            </div>
            <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" :class="autoSyncEnabled ? 'bg-emerald-400' : 'bg-slate-600'"></span>
                    <span>{{ autoSyncEnabled ? 'Enabled — sync runs daily at midnight' : 'Disabled — use --force flag to run manually' }}</span>
                </span>
            </div>
        </div>

        <!-- Analytics Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- 7-Day Attendance Trend Chart (8 Cols) -->
            <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <TrendingUp class="w-4 h-4 text-emerald-400" />
                        <h3 class="text-sm font-bold text-white">7-Day Attendance Velocity</h3>
                    </div>
                    <span class="text-xs text-slate-400">Daily Punch Volume</span>
                </div>

                <div class="h-64 w-full relative">
                    <canvas ref="chartCanvas"></canvas>
                </div>
            </div>

            <!-- Department Attendance Breakdown (4 Cols) -->
            <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <Building class="w-4 h-4 text-blue-400" />
                        <h3 class="text-sm font-bold text-white">Department Today</h3>
                    </div>
                    <span class="text-xs text-slate-400">Attendance Rate</span>
                </div>

                <div class="space-y-3.5 flex-1 overflow-y-auto">
                    <div v-for="dept in charts.dept_breakdown" :key="dept.department" class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-200 truncate max-w-[150px]">{{ dept.department }}</span>
                            <span class="font-mono text-slate-400">
                                <b class="text-white">{{ dept.checked_in }}</b> / {{ dept.total }}
                                <span class="text-emerald-400 ml-1 font-bold">({{ dept.percentage }}%)</span>
                            </span>
                        </div>
                        <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-slate-800">
                            <div
                                class="h-full bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full transition-all duration-500"
                                :style="{ width: `${dept.percentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <div v-if="charts.dept_breakdown.length === 0" class="py-12 text-center text-xs text-slate-500">
                        No department attendance records available.
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Punch Stream Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <Activity class="w-4 h-4 text-blue-400" />
                    <div>
                        <h2 class="text-sm font-bold text-white">Live Attendance Stream</h2>
                        <p class="text-[11px] text-slate-400">Real-time mobile clock-in/out records with GPS and biometric verification</p>
                    </div>
                </div>
                <Link href="/admin/reports" class="text-xs font-semibold text-blue-400 hover:text-blue-300">
                    View Full History →
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-y border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Punch Type</th>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">GPS Location</th>
                            <th class="px-4 py-3">Biometric</th>
                            <th class="px-4 py-3">ADMS Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="punch in recent_punches" :key="punch.id" class="hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-medium text-white">
                                <div>{{ punch.employee_name }}</div>
                                <div class="text-xs text-slate-500 font-mono">PIN: {{ punch.employee_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ punch.department }}</td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase',
                                    punch.punch_type === 'In' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20'
                                ]">
                                    {{ punch.punch_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono text-xs">{{ punch.timestamp }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                {{ punch.latitude ? `${punch.latitude.toFixed(4)}, ${punch.longitude.toFixed(4)}` : '-' }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span v-if="punch.biometric_verified" class="text-emerald-600 dark:text-emerald-400 font-semibold">Verified</span>
                                <span v-else class="text-slate-500">Standard</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-mono font-medium shadow-2xs">
                                    {{ punch.adms_status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="recent_punches.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-xs">
                                No punches recorded today yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
