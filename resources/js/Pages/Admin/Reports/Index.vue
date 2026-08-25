<script setup>
import { ref, computed } from 'vue';
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
    HelpCircle,
    Utensils,
    Building2,
    Printer,
    Users,
    UserX,
    Sparkles,
    Smartphone,
    Fingerprint,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    view_mode: String,
    punches: Object,
    summaries: Object,
    catering: Object,
    departments: Array,
    branches: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const viewMode = ref(props.filters.view_mode || 'catering');
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const cutoffTime = ref(props.filters.cutoff_time || '10:00');
const branchId = ref(props.filters.branch_id || 'all');
const department = ref(props.filters.department || 'all');
const employeeSearch = ref(props.filters.employee_search || '');
const cateringStatus = ref(props.filters.catering_status || 'all');

const applyFilters = () => {
    router.get('/admin/reports', {
        view_mode: viewMode.value,
        start_date: startDate.value,
        end_date: endDate.value,
        cutoff_time: cutoffTime.value,
        branch_id: branchId.value,
        department: department.value,
        employee_search: employeeSearch.value,
        catering_status: cateringStatus.value,
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
    }

    applyFilters();
};

const switchViewMode = (mode) => {
    viewMode.value = mode;
    applyFilters();
};

const downloadCsv = () => {
    window.location.href = `/admin/reports/export-csv?view_mode=${viewMode.value}&start_date=${startDate.value}&end_date=${endDate.value}&cutoff_time=${cutoffTime.value}&branch_id=${branchId.value}&department=${department.value}&employee_search=${encodeURIComponent(employeeSearch.value)}`;
};

const printCateringSlip = () => {
    window.print();
};
</script>

<template>
    <AdminLayout title="Attendance &amp; Catering Reports">
        <Head title="Attendance Reports" />

        <!-- Print-Only Official Catering Slip -->
        <div class="hidden print:block p-8 bg-white text-black">
            <div class="text-center border-b-2 border-black pb-4 mb-6">
                <h1 class="text-2xl font-black uppercase tracking-wider">Daily Employee Lunch Catering Order</h1>
                <p class="text-sm font-semibold text-gray-700">Hartono Motor Group • HRD Attendance Management</p>
                <div class="flex justify-between items-center mt-4 text-xs font-mono">
                    <span>Date: <strong>{{ catering?.target_date }}</strong></span>
                    <span>Cutoff Time: <strong>{{ catering?.cutoff_time }} WIB</strong></span>
                    <span>Total Portions: <strong class="text-base">{{ catering?.total_eligible }} Pax</strong></span>
                </div>
            </div>

            <!-- Branch Portions Summary Table -->
            <div class="mb-6">
                <h2 class="text-sm font-bold uppercase mb-2 border-b border-gray-400 pb-1">1. Lunch Portions Breakdown by Branch</h2>
                <table class="w-full text-xs text-left border-collapse border border-gray-400">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-400 p-2">Branch / Delivery Location</th>
                            <th class="border border-gray-400 p-2 text-right">Required Lunch Portions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in catering?.branch_breakdown" :key="b.name">
                            <td class="border border-gray-400 p-2 font-bold">{{ b.name }}</td>
                            <td class="border border-gray-400 p-2 text-right font-mono font-bold">{{ b.count }} Pax</td>
                        </tr>
                        <tr class="bg-gray-100 font-bold">
                            <td class="border border-gray-400 p-2 uppercase">Total Lunch Order</td>
                            <td class="border border-gray-400 p-2 text-right font-mono text-sm">{{ catering?.total_eligible }} Pax</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sign-off block -->
            <div class="grid grid-cols-3 gap-8 mt-12 text-center text-xs pt-8 border-t border-gray-300">
                <div>
                    <p class="text-gray-500 mb-14">Prepared by HRD:</p>
                    <p class="border-t border-gray-500 font-bold pt-1">HR Administration</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-14">Verified by General Affairs:</p>
                    <p class="border-t border-gray-500 font-bold pt-1">GA / Catering In-Charge</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-14">Received by Vendor:</p>
                    <p class="border-t border-gray-500 font-bold pt-1">Catering Vendor Rep</p>
                </div>
            </div>
        </div>

        <!-- On-Screen Report UI (Hidden during print) -->
        <div class="print:hidden">
            <!-- Top Header & View Modes -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Attendance Reports &amp; Analytics</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Generate daily catering lunch headcount, consolidated timesheets, or raw punch records</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- View Mode Selector -->
                    <div class="p-1 bg-slate-200/80 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl flex items-center gap-1 shadow-xs">
                        <button
                            @click="switchViewMode('catering')"
                            :class="[
                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all',
                                viewMode === 'catering'
                                    ? 'bg-amber-600 text-white shadow-md'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <Utensils class="w-3.5 h-3.5" />
                            <span>Daily Catering</span>
                        </button>
                        <button
                            @click="switchViewMode('daily_summary')"
                            :class="[
                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all',
                                viewMode === 'daily_summary'
                                    ? 'bg-blue-600 text-white shadow-md'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <Calendar class="w-3.5 h-3.5" />
                            <span>Consolidated Timesheet</span>
                        </button>
                        <button
                            @click="switchViewMode('raw')"
                            :class="[
                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all',
                                viewMode === 'raw'
                                    ? 'bg-slate-700 text-white shadow-md'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <Clock class="w-3.5 h-3.5" />
                            <span>Raw Punches</span>
                        </button>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="viewMode === 'catering'"
                            @click="printCateringSlip"
                            class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-semibold border border-slate-300 dark:border-slate-700 shadow-xs transition"
                            title="Print Catering Order Slip"
                        >
                            <Printer class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            Print Order Slip
                        </button>

                        <button
                            @click="downloadCsv"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 transition"
                        >
                            <Download class="w-4 h-4" />
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 mb-6 space-y-3 shadow-xs">
                <!-- Top Row: Date Presets -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-slate-400 font-semibold uppercase text-[10px] tracking-wider mr-1">Quick Dates:</span>
                        <button
                            @click="setDatePreset('today')"
                            class="px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs transition"
                        >
                            Today
                        </button>
                        <button
                            @click="setDatePreset('yesterday')"
                            class="px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs transition"
                        >
                            Yesterday
                        </button>
                        <button
                            @click="setDatePreset('week')"
                            class="px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs transition"
                        >
                            Last 7 Days
                        </button>
                        <button
                            @click="setDatePreset('month')"
                            class="px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-950 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs transition"
                        >
                            This Month
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-500 font-mono">
                        {{ viewMode === 'catering' ? `Target Date: ${startDate}` : `Range: ${startDate} to ${endDate}` }}
                    </div>
                </div>

                <!-- Bottom Row: Filter Controls -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                    <!-- Date Input -->
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">
                            {{ viewMode === 'catering' ? 'Attendance Date' : 'From Date' }}
                        </label>
                        <input
                            v-model="startDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div v-if="viewMode !== 'catering'">
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">To Date</label>
                        <input
                            v-model="endDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <!-- Cutoff Time (Only in Catering Mode) -->
                    <div v-if="viewMode === 'catering'">
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Morning Cutoff</label>
                        <input
                            v-model="cutoffTime"
                            @change="applyFilters"
                            type="time"
                            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-mono"
                        />
                    </div>

                    <!-- Branch Selector -->
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Branch</label>
                        <select
                            v-model="branchId"
                            @change="applyFilters"
                            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="all">All Branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <!-- Department Selector -->
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Department</label>
                        <select
                            v-model="department"
                            @change="applyFilters"
                            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="all">All Departments</option>
                            <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Search Employee</label>
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                            <input
                                v-model="employeeSearch"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="PIN or Name..."
                                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- 🍱 VIEW 1: DAILY CATERING & LUNCH MANIFEST                     -->
            <!-- ============================================================== -->
            <div v-if="viewMode === 'catering'" class="space-y-6">
                <!-- Catering Executive KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Total Portions Card -->
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-xl shadow-amber-500/10 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-100">Total Catering Portions</span>
                            <div class="p-2 rounded-xl bg-white/20 backdrop-blur-md">
                                <Utensils class="w-5 h-5" />
                            </div>
                        </div>
                        <div class="text-3xl font-black">{{ catering?.total_eligible ?? 0 }} <span class="text-sm font-normal text-amber-100">Pax</span></div>
                        <p class="text-[11px] text-amber-100 mt-2">
                            Based on confirmed morning attendance by {{ catering?.cutoff_time }} WIB
                        </p>
                    </div>

                    <!-- Present Rate Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Present Headcount</span>
                            <Users class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">
                            {{ catering?.total_eligible ?? 0 }} / {{ catering?.total_headcount ?? 0 }}
                        </div>
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-2">
                            {{ catering?.total_headcount ? Math.round((catering.total_eligible / catering.total_headcount) * 100) : 0 }}% Attendance Rate
                        </p>
                    </div>

                    <!-- On Leave Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">On Leave / Sick</span>
                            <UserX class="w-5 h-5 text-rose-500" />
                        </div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">
                            {{ catering?.total_on_leave ?? 0 }} <span class="text-xs font-normal text-slate-400">Pax</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">
                            Automatically excluded from catering orders
                        </p>
                    </div>

                    <!-- Not Clocked In Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Not Clocked In</span>
                            <AlertCircle class="w-5 h-5 text-amber-500" />
                        </div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">
                            {{ catering?.total_not_in ?? 0 }} <span class="text-xs font-normal text-slate-400">Pax</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">
                            Unconfirmed attendance by cutoff time
                        </p>
                    </div>
                </div>

                <!-- Branch & Department Portions Breakdown (2-Column Grid) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <!-- Left: Branch Portions Breakdown (7 Cols) -->
                    <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <Building2 class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                Lunch Portions by Branch (Delivery Manifest)
                            </span>
                            <span class="text-xs font-mono text-slate-400">{{ catering?.branch_breakdown?.length ?? 0 }} Active Branches</span>
                        </h3>

                        <div class="space-y-3">
                            <div
                                v-for="b in catering?.branch_breakdown"
                                :key="b.name"
                                class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 flex items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                                        🏢
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white text-xs">{{ b.name }}</div>
                                        <span class="text-[10px] text-slate-400 font-mono">
                                            {{ catering?.total_eligible ? Math.round((b.count / catering.total_eligible) * 100) : 0 }}% of total order
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-base font-black text-amber-600 dark:text-amber-400 font-mono">{{ b.count }} Pax</div>
                                    <span class="text-[10px] text-slate-400 font-semibold uppercase">Portions</span>
                                </div>
                            </div>

                            <div v-if="!catering?.branch_breakdown || catering.branch_breakdown.length === 0" class="p-6 text-center text-xs text-slate-400">
                                No branch punch data found for this date.
                            </div>
                        </div>
                    </div>

                    <!-- Right: Department Breakdown (5 Cols) -->
                    <div class="lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <Users class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                Portions by Department
                            </span>
                        </h3>

                        <div class="space-y-2.5 max-h-[380px] overflow-y-auto sidebar-scroll">
                            <div
                                v-for="d in catering?.dept_breakdown"
                                :key="d.name"
                                class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs"
                            >
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ d.name }}</span>
                                <span class="font-bold font-mono text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950 px-2 py-0.5 rounded-lg border border-blue-200 dark:border-blue-800/60">
                                    {{ d.count }} Pax
                                </span>
                            </div>

                            <div v-if="!catering?.dept_breakdown || catering.dept_breakdown.length === 0" class="p-6 text-center text-xs text-slate-400">
                                No department data available.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Catering Headcount List -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Utensils class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            Employee Catering Eligibility Roster
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="px-5 py-3.5">Employee</th>
                                    <th class="px-5 py-3.5">Branch</th>
                                    <th class="px-5 py-3.5">Department</th>
                                    <th class="px-5 py-3.5">Clock In Time</th>
                                    <th class="px-5 py-3.5">Scan Source</th>
                                    <th class="px-5 py-3.5 text-right">Catering Lunch Decision</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                <tr v-for="emp in catering?.roster?.data" :key="emp.employee_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-medium text-slate-900 dark:text-white">
                                        <div>{{ emp.full_name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">PIN: {{ emp.employee_id }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-slate-800 dark:text-slate-200 font-medium">
                                        🏢 {{ emp.branch_name }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ emp.department }}
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs font-semibold">
                                        <span v-if="emp.clock_in_time !== '-'" class="text-slate-900 dark:text-white">
                                            ⏰ {{ emp.clock_in_time }}
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs">
                                        <span v-if="emp.in_source.toLowerCase().includes('finger')" class="inline-flex items-center gap-1 text-[11px] font-medium text-purple-600 dark:text-purple-400">
                                            <Fingerprint class="w-3.5 h-3.5" />
                                            {{ emp.device_name || 'Fingerprint' }}
                                        </span>
                                        <span v-else-if="emp.in_source.toLowerCase().includes('mobile')" class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-600 dark:text-blue-400">
                                            <Smartphone class="w-3.5 h-3.5" />
                                            Mobile GPS
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <span v-if="emp.is_eligible" class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 inline-flex items-center gap-1.5">
                                            <Check class="w-3.5 h-3.5" />
                                            Order Portion
                                        </span>
                                        <span v-else-if="emp.status === 'on_leave'" class="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 inline-flex items-center gap-1.5">
                                            <UserX class="w-3.5 h-3.5" />
                                            {{ emp.status_label }}
                                        </span>
                                        <span v-else class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700">
                                            Not Clocked In
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!catering?.roster?.data || catering.roster.data.length === 0">
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                        No employee records found matching current filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        v-if="catering?.roster"
                        :links="catering.roster.links"
                        :from="catering.roster.from"
                        :to="catering.roster.to"
                        :total="catering.roster.total"
                    />
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- 📋 VIEW 2: DAILY SUMMARY CONSOLIDATED TIMESHEET                -->
            <!-- ============================================================== -->
            <div v-else-if="viewMode === 'daily_summary'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">Date</th>
                                <th class="px-5 py-3.5">Employee</th>
                                <th class="px-5 py-3.5">First In</th>
                                <th class="px-5 py-3.5">In Origin / Branch</th>
                                <th class="px-5 py-3.5">Last Out</th>
                                <th class="px-5 py-3.5">Out Origin / Branch</th>
                                <th class="px-5 py-3.5">Work Hours</th>
                                <th class="px-5 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="item in summaries.data" :key="item.date + '-' + item.employee_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
                                    {{ item.date }}
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">
                                    <div>{{ item.employee_name }}</div>
                                    <div class="text-xs text-slate-400 font-mono">PIN: {{ item.employee_id }} ({{ item.department }})</div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ item.first_in }}
                                </td>
                                <td class="px-5 py-4 text-xs">
                                    <span v-if="item.in_device" class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-700 dark:text-slate-300">
                                        <Fingerprint v-if="item.in_device.source === 'adms_fingerprint'" class="w-3 h-3 text-purple-600 dark:text-purple-400" />
                                        <Smartphone v-else class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                        <span>{{ item.in_device.device_name }}</span>
                                        <span class="text-slate-400">({{ item.in_device.branch_name }})</span>
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                    {{ item.last_out }}
                                </td>
                                <td class="px-5 py-4 text-xs">
                                    <span v-if="item.out_device" class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-700 dark:text-slate-300">
                                        <Fingerprint v-if="item.out_device.source === 'adms_fingerprint'" class="w-3 h-3 text-purple-600 dark:text-purple-400" />
                                        <Smartphone v-else class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                        <span>{{ item.out_device.device_name }}</span>
                                        <span class="text-slate-400">({{ item.out_device.branch_name }})</span>
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                                    {{ item.work_hours }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span v-if="item.status === 'on_time'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        On Time
                                    </span>
                                    <span v-else-if="item.status === 'late'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Late
                                    </span>
                                    <span v-else class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        Incomplete
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="summaries.data.length === 0">
                                <td colspan="8" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No daily summary records found for this period.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="summaries.links"
                    :from="summaries.from"
                    :to="summaries.to"
                    :total="summaries.total"
                />
            </div>

            <!-- ============================================================== -->
            <!-- ⚡ VIEW 3: RAW PUNCH LOGS                                       -->
            <!-- ============================================================== -->
            <div v-else-if="viewMode === 'raw'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">Employee</th>
                                <th class="px-5 py-3.5">Punch Type</th>
                                <th class="px-5 py-3.5">Timestamp</th>
                                <th class="px-5 py-3.5">Device &amp; Branch</th>
                                <th class="px-5 py-3.5">Source &amp; SN</th>
                                <th class="px-5 py-3.5 text-right">Biometric</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="punch in punches.data" :key="punch.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">
                                    <div>{{ punch.employee_name }}</div>
                                    <div class="text-xs text-slate-400 font-mono">PIN: {{ punch.employee_id }} ({{ punch.department }})</div>
                                </td>
                                <td class="px-5 py-4">
                                    <span :class="[
                                        'px-2.5 py-1 rounded-full text-xs font-semibold',
                                        punch.punch_type.toLowerCase() === 'in'
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                            : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                                    ]">
                                        {{ punch.punch_type }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-800 dark:text-slate-200">
                                    {{ punch.timestamp }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">🏢 {{ punch.branch_name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ punch.device_name }}</div>
                                </td>
                                <td class="px-5 py-4 text-xs font-mono">
                                    <span v-if="punch.punch_source === 'adms_fingerprint'" class="text-purple-600 dark:text-purple-400 font-semibold">
                                        🏢 Biometric ({{ punch.device_sn }})
                                    </span>
                                    <span v-else class="text-blue-600 dark:text-blue-400 font-semibold">
                                        📱 Mobile GPS
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span v-if="punch.biometric_verified" class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Verified
                                    </span>
                                    <span v-else class="text-slate-400 text-xs">Standard</span>
                                </td>
                            </tr>
                            <tr v-if="punches.data.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No punch logs match the current filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="punches.links"
                    :from="punches.from"
                    :to="punches.to"
                    :total="punches.total"
                />
            </div>
        </div>
    </AdminLayout>
</template>
