<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    FileSpreadsheet,
    Download,
    Search,
    Filter,
    Calendar,
    ListFilter,
    Layers,
    Clock,
    CheckCircle2,
    AlertCircle,
    HelpCircle
} from 'lucide-vue-next';

const props = defineProps({
    view_mode: String,
    punches: Object,
    summaries: Object,
    departments: Array,
    filters: Object,
});

const viewMode = ref(props.filters.view_mode || 'raw');
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const department = ref(props.filters.department);
const employeeSearch = ref(props.filters.employee_search || '');

const applyFilters = () => {
    router.get('/admin/reports', {
        view_mode: viewMode.value,
        start_date: startDate.value,
        end_date: endDate.value,
        department: department.value,
        employee_search: employeeSearch.value,
    }, { preserveState: true, preserveScroll: true });
};

const setDatePreset = (preset) => {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        startDate.value = formatDate(today);
        endDate.value = formatDate(today);
    } else if (preset === 'yesterday') {
        const yest = new Date(today);
        yest.setDate(yest.getDate() - 1);
        startDate.value = formatDate(yest);
        endDate.value = formatDate(yest);
    } else if (preset === 'week') {
        const weekAgo = new Date(today);
        weekAgo.setDate(weekAgo.getDate() - 7);
        startDate.value = formatDate(weekAgo);
        endDate.value = formatDate(today);
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startDate.value = formatDate(firstDay);
        endDate.value = formatDate(today);
    } else if (preset === 'last_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
        startDate.value = formatDate(firstDay);
        endDate.value = formatDate(lastDay);
    }

    applyFilters();
};

const switchViewMode = (mode) => {
    viewMode.value = mode;
    applyFilters();
};

const downloadCsv = () => {
    window.location.href = `/admin/reports/export-csv?view_mode=${viewMode.value}&start_date=${startDate.value}&end_date=${endDate.value}&department=${department.value}&employee_search=${encodeURIComponent(employeeSearch.value)}`;
};
</script>

<template>
    <AdminLayout title="Attendance Reports">
        <Head title="Attendance Reports" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white">Attendance Logs &amp; Analytics</h2>
                <p class="text-xs text-slate-400">Generate raw punch records or consolidated daily summaries with 1-click CSV exports</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- View Mode Switcher -->
                <div class="p-1 bg-slate-900 border border-slate-800 rounded-xl flex items-center gap-1">
                    <button
                        @click="switchViewMode('raw')"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            viewMode === 'raw'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-slate-400 hover:text-white'
                        ]"
                    >
                        Raw Punches
                    </button>
                    <button
                        @click="switchViewMode('daily_summary')"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            viewMode === 'daily_summary'
                                ? 'bg-emerald-600 text-white shadow-sm'
                                : 'text-slate-400 hover:text-white'
                        ]"
                    >
                        Daily Summary
                    </button>
                </div>

                <button
                    @click="downloadCsv"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 transition"
                >
                    <Download class="w-4 h-4" />
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 space-y-3 shadow-sm">
            <!-- Top Row: Date Presets -->
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800/80">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-400 font-semibold uppercase text-[10px] tracking-wider mr-1">Quick Dates:</span>
                    <button
                        @click="setDatePreset('today')"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 text-xs transition"
                    >
                        Today
                    </button>
                    <button
                        @click="setDatePreset('yesterday')"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 text-xs transition"
                    >
                        Yesterday
                    </button>
                    <button
                        @click="setDatePreset('week')"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 text-xs transition"
                    >
                        Last 7 Days
                    </button>
                    <button
                        @click="setDatePreset('month')"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 text-xs transition"
                    >
                        This Month
                    </button>
                    <button
                        @click="setDatePreset('last_month')"
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 text-xs transition"
                    >
                        Last Month
                    </button>
                </div>
            </div>

            <!-- Bottom Row: Specific Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Start Date</label>
                    <input
                        v-model="startDate"
                        @change="applyFilters"
                        type="date"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">End Date</label>
                    <input
                        v-model="endDate"
                        @change="applyFilters"
                        type="date"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Department</label>
                    <select
                        v-model="department"
                        @change="applyFilters"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="all">All Departments</option>
                        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Employee Search</label>
                    <div class="relative">
                        <Search class="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
                        <input
                            v-model="employeeSearch"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="PIN or Name..."
                            class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Daily Summary Table -->
        <div v-if="viewMode === 'daily_summary'" class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Date</th>
                        <th class="px-5 py-3.5">PIN &amp; Employee</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">First In</th>
                        <th class="px-5 py-3.5">Last Out</th>
                        <th class="px-5 py-3.5">Work Hours</th>
                        <th class="px-5 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="s in summaries.data" :key="`${s.date}-${s.employee_id}`" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-mono text-xs text-slate-300">{{ s.date }}</td>
                        <td class="px-5 py-3.5 font-medium text-white">
                            <div>{{ s.employee_name }}</div>
                            <div class="text-xs text-slate-500 font-mono">PIN: {{ s.employee_id }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-400">{{ s.department }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs font-semibold text-emerald-400">{{ s.first_in }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs font-semibold text-indigo-400">{{ s.last_out }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs text-slate-200">{{ s.work_hours }}</td>
                        <td class="px-5 py-3.5 text-xs">
                            <span v-if="s.status === 'normal'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                On Time
                            </span>
                            <span v-else-if="s.status === 'late'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Late Arrival
                            </span>
                            <span v-else class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                Incomplete
                            </span>
                        </td>
                    </tr>
                    <tr v-if="summaries.data.length === 0">
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500 text-xs">
                            No attendance records match the specified filters.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="summaries.links"
                :from="summaries.from"
                :to="summaries.to"
                :total="summaries.total"
            />
        </div>

        <!-- 2. Raw Punches Table -->
        <div v-else class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">PIN</th>
                        <th class="px-5 py-3.5">Employee Name</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5">Timestamp</th>
                        <th class="px-5 py-3.5">Biometric</th>
                        <th class="px-5 py-3.5">ADMS Sync</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="p in punches.data" :key="p.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-mono text-blue-400 font-bold">{{ p.employee_id }}</td>
                        <td class="px-5 py-3.5 font-medium text-white">{{ p.employee_name }}</td>
                        <td class="px-5 py-3.5 text-xs text-slate-400">{{ p.department }}</td>
                        <td class="px-5 py-3.5">
                            <span :class="[
                                'px-2.5 py-0.5 rounded-full text-xs font-semibold',
                                p.punch_type === 'In' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                            ]">
                                {{ p.punch_type }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs font-mono text-slate-300">{{ p.timestamp }}</td>
                        <td class="px-5 py-3.5 text-xs">
                            <span v-if="p.biometric_verified" class="text-emerald-400 font-semibold">Verified</span>
                            <span v-else class="text-slate-500">Standard</span>
                        </td>
                        <td class="px-5 py-3.5 text-xs">
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700 font-mono">
                                {{ p.adms_status }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="punches.data.length === 0">
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500 text-xs">
                            No punch logs found for this period.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="punches.links"
                :from="punches.from"
                :to="punches.to"
                :total="punches.total"
            />
        </div>
    </AdminLayout>
</template>
