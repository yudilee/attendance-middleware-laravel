<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    kanban: Object,
    canvassPlans: Array,
    mechanicLeaderboard: Array,
    salesLeaderboard: Array,
    employees: Array,
    customers: Array,
    filters: Object,
});

const currentTab = ref('board'); // 'board', 'plans', 'scoreboard'

// Filter states
const employeeFilter = ref(props.filters.employee_id || '');
const taskTypeFilter = ref(props.filters.task_type || '');

function applyFilters() {
    router.get(route('admin.field-tasks'), {
        employee_id: employeeFilter.value || undefined,
        task_type: taskTypeFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

watch([employeeFilter, taskTypeFilter], () => {
    applyFilters();
});

// Create Task Modal
const showTaskModal = ref(false);
const taskForm = ref({
    employee_id: '',
    customer_id: '',
    title: '',
    description: '',
    task_type: 'storing',
    priority: 'medium',
    due_date: new Date().toISOString().split('T')[0],
});
const taskLoading = ref(false);

function openCreateTaskModal() {
    taskForm.value = {
        employee_id: props.employees.length > 0 ? props.employees[0].employee_id : '',
        customer_id: '',
        title: '',
        description: '',
        task_type: 'storing',
        priority: 'medium',
        due_date: new Date().toISOString().split('T')[0],
    };
    showTaskModal.value = true;
}

function saveTask() {
    if (!taskForm.value.title || !taskForm.value.employee_id) return;
    taskLoading.value = true;
    router.post(route('admin.field-tasks.store'), taskForm.value, {
        onSuccess: () => {
            showTaskModal.value = false;
            taskLoading.value = false;
        },
        onError: () => { taskLoading.value = false; }
    });
}

function updateTaskStatus(task, newStatus) {
    router.put(route('admin.field-tasks.update', task.id), {
        status: newStatus,
    }, {
        preserveState: true,
    });
}

// Create Canvass Plan Modal
const showPlanModal = ref(false);
const planForm = ref({
    employee_id: '',
    plan_date: new Date().toISOString().split('T')[0],
    target_visits: 5,
    customer_ids: [],
    notes: '',
});
const planLoading = ref(false);

function openCreatePlanModal() {
    planForm.value = {
        employee_id: props.employees.length > 0 ? props.employees[0].employee_id : '',
        plan_date: new Date().toISOString().split('T')[0],
        target_visits: 5,
        customer_ids: [],
        notes: '',
    };
    showPlanModal.value = true;
}

function savePlan() {
    if (!planForm.value.employee_id || planForm.value.customer_ids.length === 0) return;
    planLoading.value = true;
    router.post(route('admin.canvass-plans.store'), planForm.value, {
        onSuccess: () => {
            showPlanModal.value = false;
            planLoading.value = false;
        },
        onError: () => { planLoading.value = false; }
    });
}

function getPriorityBadge(p) {
    switch (p) {
        case 'urgent': return 'bg-red-500/10 text-red-400 border-red-500/20';
        case 'high': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        case 'medium': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        default: return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
}
</script>

<template>
    <AdminLayout>
        <Head title="Tasks & Canvass Planner" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-purple-500/10 text-purple-500 dark:bg-purple-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </span>
                        Field Tasks & Canvass Planner
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Dispatch external storing work to mechanics and assign targeted canvassing visit plans to sales teams.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Tab switcher -->
                    <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                        <button @click="currentTab = 'board'" :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', currentTab === 'board' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400']">
                            📋 Task Board
                        </button>
                        <button @click="currentTab = 'plans'" :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', currentTab === 'plans' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400']">
                            🗓️ Canvass Plans
                        </button>
                        <button @click="currentTab = 'scoreboard'" :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', currentTab === 'scoreboard' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400']">
                            🏆 Scoreboard
                        </button>
                    </div>

                    <button v-if="currentTab === 'board'" @click="openCreateTaskModal" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs shadow-sm flex items-center gap-2 transition">
                        <span>+</span> Dispatch Task
                    </button>
                    <button v-if="currentTab === 'plans'" @click="openCreatePlanModal" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-medium text-xs shadow-sm flex items-center gap-2 transition">
                        <span>+</span> Create Canvass Plan
                    </button>
                </div>
            </div>

            <!-- TAB 1: KANBAN TASK BOARD -->
            <div v-show="currentTab === 'board'" class="space-y-4">
                <!-- Filters -->
                <div class="flex items-center gap-3">
                    <select v-model="employeeFilter" class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none">
                        <option value="">All Assignees</option>
                        <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                            {{ emp.full_name }}
                        </option>
                    </select>

                    <select v-model="taskTypeFilter" class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none">
                        <option value="">All Task Types</option>
                        <option value="storing">Mechanic Storing</option>
                        <option value="delivery">Delivery</option>
                        <option value="canvass">Sales Canvass</option>
                        <option value="repair">Service / Repair</option>
                        <option value="inspection">Inspection</option>
                    </select>
                </div>

                <!-- 4 Columns Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <!-- Column 1: Pending -->
                    <div class="bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                Pending Dispatch
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ kanban.pending.length }}
                            </span>
                        </div>

                        <div class="space-y-3 flex-1 overflow-y-auto">
                            <div v-for="t in kanban.pending" :key="t.id" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/70 rounded-xl p-3.5 shadow-sm space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider', getPriorityBadge(t.priority)]">
                                        {{ t.priority }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">Due: {{ t.due_date || 'ASAP' }}</span>
                                </div>

                                <div class="font-semibold text-sm text-slate-900 dark:text-white">{{ t.title }}</div>
                                <p v-if="t.description" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{{ t.description }}</p>

                                <div class="pt-2 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between text-xs">
                                    <div class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                        <span>👤</span> {{ t.employee ? t.employee.full_name : t.employee_id }}
                                    </div>
                                    <button @click="updateTaskStatus(t, 'in_progress')" class="px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold text-[11px] hover:bg-blue-100 transition">
                                        Start →
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: In Progress -->
                    <div class="bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                In Progress
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                {{ kanban.in_progress.length }}
                            </span>
                        </div>

                        <div class="space-y-3 flex-1 overflow-y-auto">
                            <div v-for="t in kanban.in_progress" :key="t.id" class="bg-white dark:bg-slate-800 border border-blue-200 dark:border-blue-800/50 rounded-xl p-3.5 shadow-sm space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider', getPriorityBadge(t.priority)]">
                                        {{ t.priority }}
                                    </span>
                                    <span class="text-[11px] text-blue-500 font-medium">In Field</span>
                                </div>

                                <div class="font-semibold text-sm text-slate-900 dark:text-white">{{ t.title }}</div>
                                <div v-if="t.customer" class="text-xs text-slate-500">📍 {{ t.customer.name }}</div>

                                <div class="pt-2 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between text-xs">
                                    <div class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                        <span>👤</span> {{ t.employee ? t.employee.full_name : t.employee_id }}
                                    </div>
                                    <button @click="updateTaskStatus(t, 'completed')" class="px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] hover:bg-emerald-100 transition">
                                        Done ✓
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Completed -->
                    <div class="bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Completed
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                                {{ kanban.completed.length }}
                            </span>
                        </div>

                        <div class="space-y-3 flex-1 overflow-y-auto">
                            <div v-for="t in kanban.completed" :key="t.id" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/70 rounded-xl p-3.5 shadow-sm space-y-2 opacity-80">
                                <div class="font-semibold text-sm text-slate-900 dark:text-white line-through">{{ t.title }}</div>
                                <div class="text-xs text-slate-500">Completed by: {{ t.employee ? t.employee.full_name : t.employee_id }}</div>
                                <div v-if="t.completed_at" class="text-[10px] text-slate-400">At: {{ new Date(t.completed_at).toLocaleString('id-ID') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 4: Cancelled -->
                    <div class="bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                Cancelled
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ kanban.cancelled.length }}
                            </span>
                        </div>

                        <div class="space-y-3 flex-1 overflow-y-auto">
                            <div v-for="t in kanban.cancelled" :key="t.id" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/70 rounded-xl p-3 shadow-sm text-xs text-slate-500">
                                <div class="font-medium text-slate-700 dark:text-slate-300">{{ t.title }}</div>
                                <div class="text-[11px] text-slate-400 mt-1">Assignee: {{ t.employee ? t.employee.full_name : t.employee_id }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CANVASS PLANS -->
            <div v-show="currentTab === 'plans'" class="space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">This Week's Canvassing Plans</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="p in canvassPlans" :key="p.id" class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-sm text-slate-900 dark:text-white">
                                    {{ p.employee ? p.employee.full_name : p.employee_id }}
                                </div>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-400">
                                    {{ p.plan_date }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div>
                                <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                                    <span>Target: {{ p.target_visits }} visits</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ p.actual_visits }} completed</span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                    <div :style="{ width: `${Math.min(100, Math.round((p.actual_visits / (p.target_visits || 1)) * 100))}%` }" class="h-full bg-purple-500 rounded-full transition-all"></div>
                                </div>
                            </div>

                            <!-- Planned Target Customer Badges -->
                            <div v-if="p.customer_list && p.customer_list.length > 0" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-slate-700">
                                <div class="text-[11px] font-semibold text-slate-400 uppercase">Target Route</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="c in p.customer_list" :key="c.id" class="px-2 py-0.5 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300">
                                        📍 {{ c.name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="canvassPlans.length === 0" class="col-span-3 text-center py-12 text-slate-400">
                            No canvass plans created for this week yet. Click "+ Create Canvass Plan" to assign visit routes.
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PERFORMANCE SCOREBOARD -->
            <div v-show="currentTab === 'scoreboard'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Mechanic Leaderboard -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🔧</span> Mechanic Field Leaderboard (Last 30 Days)
                    </h3>

                    <div class="space-y-2.5">
                        <div v-for="(m, idx) in mechanicLeaderboard" :key="m.employee_id" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-3">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold', idx === 0 ? 'bg-amber-400 text-amber-950 shadow-sm' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300']">
                                    {{ idx + 1 }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ m.name }}</div>
                                    <div class="text-xs text-slate-400">{{ m.department || 'Mechanic Team' }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ m.visits_count }} visits</div>
                                <div class="text-[11px] text-slate-400">{{ m.completed_tasks }} tasks completed</div>
                            </div>
                        </div>

                        <div v-if="mechanicLeaderboard.length === 0" class="text-center py-8 text-xs text-slate-400">
                            No mechanic field records yet. Tag employees with role 'mechanic' to show on leaderboard.
                        </div>
                    </div>
                </div>

                <!-- Sales Leaderboard -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>💼</span> Sales Canvassing Leaderboard (Last 30 Days)
                    </h3>

                    <div class="space-y-2.5">
                        <div v-for="(s, idx) in salesLeaderboard" :key="s.employee_id" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-3">
                                <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold', idx === 0 ? 'bg-amber-400 text-amber-950 shadow-sm' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300']">
                                    {{ idx + 1 }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ s.name }}</div>
                                    <div class="text-xs text-slate-400">{{ s.department || 'Sales Canvassing' }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ s.visits_count }} visits</div>
                                <div class="text-[11px] text-slate-400">{{ s.completed_plans }} plans fulfilled</div>
                            </div>
                        </div>

                        <div v-if="salesLeaderboard.length === 0" class="text-center py-8 text-xs text-slate-400">
                            No sales canvassing records yet. Tag employees with role 'sales' to show on leaderboard.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispatch Task Modal -->
        <div v-if="showTaskModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Dispatch Field Task</h2>
                    <button @click="showTaskModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveTask" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Task Title *</label>
                        <input v-model="taskForm.title" required type="text" placeholder="e.g. Storing 5 Units at Dealer X" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Assign To *</label>
                            <select v-model="taskForm.employee_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                    {{ emp.full_name }} ({{ emp.employee_type || 'regular' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Customer / Location</label>
                            <select v-model="taskForm.customer_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Optional Location --</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.city || c.customer_type }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Type</label>
                            <select v-model="taskForm.task_type" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none">
                                <option value="storing">Mechanic Storing</option>
                                <option value="delivery">Delivery</option>
                                <option value="canvass">Sales Canvass</option>
                                <option value="repair">Repair</option>
                                <option value="inspection">Inspection</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Priority</label>
                            <select v-model="taskForm.priority" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Due Date</label>
                            <input v-model="taskForm.due_date" type="date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Instructions / Description</label>
                        <textarea v-model="taskForm.description" rows="3" placeholder="Provide details, item counts, contacts..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showTaskModal = false" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="taskLoading" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm transition flex items-center gap-2">
                            <span v-if="taskLoading" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            Dispatch Task
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Canvass Plan Modal -->
        <div v-if="showPlanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Create Canvass Plan</h2>
                    <button @click="showPlanModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="savePlan" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Sales Staff *</label>
                            <select v-model="planForm.employee_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-purple-500">
                                <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                    {{ emp.full_name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Plan Date *</label>
                            <input v-model="planForm.plan_date" required type="date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-purple-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Target Customers / Route *</label>
                        <select v-model="planForm.customer_ids" multiple required class="w-full h-36 px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-purple-500">
                            <option v-for="c in customers" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.city || c.customer_type }})
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Hold Ctrl / Cmd to select multiple destinations.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Target Visit Count</label>
                        <input v-model="planForm.target_visits" type="number" min="1" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showPlanModal = false" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="planLoading" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm transition flex items-center gap-2">
                            <span v-if="planLoading" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            Save Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
