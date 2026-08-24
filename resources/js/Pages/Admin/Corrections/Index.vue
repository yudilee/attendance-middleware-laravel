<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Clock,
    Plus,
    CheckCircle2,
    XCircle,
    Search,
    AlertCircle,
    X,
    Filter
} from 'lucide-vue-next';

const props = defineProps({
    corrections: Object,
    stats: Object,
    filters: Object,
});

const status = ref(props.filters.status || 'all');
const search = ref(props.filters.search || '');

const isAddModalOpen = ref(false);
const form = ref({
    employee_id: '',
    punch_type: 'In',
    timestamp: '',
    notes: '',
});

const applyFilters = () => {
    router.get('/admin/corrections', {
        status: status.value,
        search: search.value,
    }, { preserveState: true });
};

const approveCorrection = (id) => {
    router.post(`/admin/corrections/${id}/approve`);
};

const rejectCorrection = (id) => {
    router.post(`/admin/corrections/${id}/reject`);
};

const openAddModal = () => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    form.value = {
        employee_id: '',
        punch_type: 'In',
        timestamp: now.toISOString().slice(0, 16),
        notes: '',
    };
    isAddModalOpen.value = true;
};

const saveManualPunch = () => {
    router.post('/admin/corrections/manual-punch', form.value, {
        onSuccess: () => isAddModalOpen.value = false,
    });
};
</script>

<template>
    <AdminLayout title="Attendance Corrections">
        <Head title="Attendance Corrections" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <AlertCircle class="w-5 h-5 text-amber-400" />
                    Attendance Corrections &amp; Manual Punches
                </h2>
                <p class="text-xs text-slate-400">Review missed punch adjustment requests or manually record attendance</p>
            </div>

            <button
                @click="openAddModal"
                class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition"
            >
                <Plus class="w-4 h-4" />
                Add Manual Punch
            </button>
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Pending Review</span>
                    <h3 class="text-2xl font-bold text-amber-400 font-mono mt-1">{{ stats.pending }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <Clock class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Approved Adjustments</span>
                    <h3 class="text-2xl font-bold text-emerald-400 font-mono mt-1">{{ stats.approved }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <CheckCircle2 class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Rejected Requests</span>
                    <h3 class="text-2xl font-bold text-rose-400 font-mono mt-1">{{ stats.rejected }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <XCircle class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-4 justify-between">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by PIN, Employee Name, or Notes..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
            </div>

            <div class="flex gap-2">
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500"
                >
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <!-- Corrections Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Employee</th>
                        <th class="px-5 py-3.5">Type &amp; Proposed Time</th>
                        <th class="px-5 py-3.5">Reason / Description</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="c in corrections.data" :key="c.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-4 font-medium text-white">
                            <div>{{ c.employee_name }}</div>
                            <div class="text-xs text-slate-500 font-mono">PIN: {{ c.employee_id }} ({{ c.department }})</div>
                        </td>
                        <td class="px-5 py-4 font-mono text-xs">
                            <div class="flex items-center gap-2 mb-1">
                                <span :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                    c.proposed_punch_type === 'In' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-indigo-500/10 text-indigo-400'
                                ]">
                                    {{ c.proposed_punch_type || 'Punch' }}
                                </span>
                                <span class="text-slate-200">{{ c.proposed_timestamp }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-400 max-w-[200px] truncate" :title="c.description">
                            {{ c.description || c.correction_type || '-' }}
                        </td>
                        <td class="px-5 py-4">
                            <span v-if="c.status === 'approved'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Approved
                            </span>
                            <span v-else-if="c.status === 'pending'" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Pending
                            </span>
                            <span v-else class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                Rejected
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div v-if="c.status === 'pending'" class="flex items-center justify-end gap-2">
                                <button
                                    @click="approveCorrection(c.id)"
                                    class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition"
                                    title="Approve &amp; Add Punch"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                </button>
                                <button
                                    @click="rejectCorrection(c.id)"
                                    class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 transition"
                                    title="Reject Request"
                                >
                                    <XCircle class="w-4 h-4" />
                                </button>
                            </div>
                            <span v-else class="text-xs text-slate-500 font-mono">
                                {{ c.reviewed_by || 'Processed' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="corrections.data.length === 0">
                        <td colspan="5" class="px-5 py-8 text-center text-slate-500 text-xs">
                            No attendance correction requests found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="corrections.links"
                :from="corrections.from"
                :to="corrections.to"
                :total="corrections.total"
            />
        </div>

        <!-- Manual Punch Modal -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isAddModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-4">Record Manual Attendance Punch</h3>

                <form @submit.prevent="saveManualPunch" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Employee PIN</label>
                        <input
                            v-model="form.employee_id"
                            type="text"
                            required
                            placeholder="e.g. 000011748"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500 font-mono"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Punch Type</label>
                            <select
                                v-model="form.punch_type"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="In">Clock In (Check-In)</option>
                                <option value="Out">Clock Out (Check-Out)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Date &amp; Time</label>
                            <input
                                v-model="form.timestamp"
                                type="datetime-local"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Reason / Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            placeholder="e.g. Biometric reader offline, verified manually..."
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isAddModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-500 shadow-lg shadow-blue-600/20 transition"
                        >
                            Submit Punch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
