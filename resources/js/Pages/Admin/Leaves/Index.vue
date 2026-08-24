<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Calendar,
    Plus,
    CheckCircle2,
    XCircle,
    Clock,
    Search,
    UserCheck,
    X,
    Filter,
    FileText,
    ExternalLink,
    AlertCircle,
    Paperclip,
    Eye,
    Briefcase,
    Building2,
    CalendarCheck,
    MessageSquare
} from 'lucide-vue-next';

const props = defineProps({
    leaves: Object,
    stats: Object,
    employees: Array,
    filters: Object,
});

const status = ref(props.filters.status || 'all');
const category = ref(props.filters.category || 'all');
const search = ref(props.filters.search || '');

// Action Modals
const isAddModalOpen = ref(false);
const isProcessModalOpen = ref(false);
const isAttachmentModalOpen = ref(false);

const selectedRequest = ref(null);
const processAction = ref('approve'); // 'approve' or 'reject'
const adminNotes = ref('');
const attachmentPreviewUrl = ref('');

const form = ref({
    employee_id: '',
    category: 'leave', // 'leave' or 'permit'
    leave_type: 'annual',
    permit_type: 'late_arrival',
    start_date: '',
    end_date: '',
    expected_time: '09:30',
    reason: '',
    status: 'approved',
    admin_notes: '',
});

const applyFilters = () => {
    router.get('/admin/leaves', {
        status: status.value,
        category: category.value,
        search: search.value,
    }, { preserveState: true });
};

const setCategoryTab = (cat) => {
    category.value = cat;
    applyFilters();
};

const openApproveModal = (req) => {
    selectedRequest.value = req;
    processAction.value = 'approve';
    adminNotes.value = '';
    isProcessModalOpen.value = true;
};

const openRejectModal = (req) => {
    selectedRequest.value = req;
    processAction.value = 'reject';
    adminNotes.value = '';
    isProcessModalOpen.value = true;
};

const submitProcess = () => {
    if (!selectedRequest.value) return;
    const url = processAction.value === 'approve'
        ? `/admin/leaves/${selectedRequest.value.id}/approve`
        : `/admin/leaves/${selectedRequest.value.id}/reject`;

    router.post(url, {
        admin_notes: adminNotes.value,
    }, {
        onSuccess: () => {
            isProcessModalOpen.value = false;
            selectedRequest.value = null;
        }
    });
};

const openAttachment = (url) => {
    attachmentPreviewUrl.value = url;
    isAttachmentModalOpen.value = true;
};

const openAddModal = () => {
    form.value = {
        employee_id: props.employees?.[0]?.employee_id || '',
        category: 'leave',
        leave_type: 'annual',
        permit_type: 'late_arrival',
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        expected_time: '09:30',
        reason: '',
        status: 'approved',
        admin_notes: '',
    };
    isAddModalOpen.value = true;
};

const saveLeave = () => {
    router.post('/admin/leaves', form.value, {
        onSuccess: () => isAddModalOpen.value = false,
    });
};

const formatCategory = (cat, lType, pType, expectedTime) => {
    if (cat === 'permit') {
        if (pType === 'late_arrival') return { text: `⏰ Late Arrival ${expectedTime ? '@ ' + expectedTime : ''}`, color: 'bg-purple-500/10 text-purple-400 border-purple-500/20' };
        if (pType === 'early_departure') return { text: `🚪 Early Departure ${expectedTime ? '@ ' + expectedTime : ''}`, color: 'bg-sky-500/10 text-sky-400 border-sky-500/20' };
        if (pType === 'official_duty') return { text: '🏢 Official External Duty', color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' };
        return { text: '📝 General Permit', color: 'bg-slate-500/10 text-slate-400 border-slate-500/20' };
    }
    // Leave
    if (lType === 'sick' || lType === 'Sick Leave') return { text: '🏥 Sick Leave', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' };
    if (lType === 'unpaid' || lType === 'Unpaid Leave') return { text: '🛑 Unpaid Leave', color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
    if (lType === 'maternity' || lType === 'Maternity / Paternity') return { text: '👶 Maternity/Paternity', color: 'bg-pink-500/10 text-pink-400 border-pink-500/20' };
    return { text: '🌴 Annual Leave', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' };
};
</script>

<template>
    <AdminLayout title="Leave & Permission Management">
        <Head title="Leave & Permission Requests" />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                    <CalendarCheck class="w-6 h-6 text-emerald-400" />
                    Leave &amp; Permission Hub
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Review and approve employee leave requests, late arrivals, and official duty permits
                </p>
            </div>

            <button
                @click="openAddModal"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                Record Leave / Permit
            </button>
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Pending Approval</span>
                    <h3 class="text-2xl font-bold text-amber-400 font-mono mt-1">{{ stats.pending }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <Clock class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Approved</span>
                    <h3 class="text-2xl font-bold text-emerald-400 font-mono mt-1">{{ stats.approved }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <CheckCircle2 class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Late / Permits</span>
                    <h3 class="text-2xl font-bold text-purple-400 font-mono mt-1">{{ stats.total_permits }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                    <AlertCircle class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Rejected</span>
                    <h3 class="text-2xl font-bold text-rose-400 font-mono mt-1">{{ stats.rejected }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <XCircle class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex items-center gap-2 mb-4 border-b border-slate-800 pb-3">
            <button
                @click="setCategoryTab('all')"
                :class="category === 'all' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
            >
                <Calendar class="w-4 h-4" />
                All Applications ({{ stats.total }})
            </button>
            <button
                @click="setCategoryTab('leave')"
                :class="category === 'leave' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
            >
                🌴 Leave Requests ({{ stats.total_leaves }})
            </button>
            <button
                @click="setCategoryTab('permit')"
                :class="category === 'permit' ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
            >
                ⏰ Late Arrival &amp; Permits ({{ stats.total_permits }})
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-4 justify-between">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3.5" />
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by PIN, Employee Name, Reason, or Type..."
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
                    <option value="pending">⏳ Pending</option>
                    <option value="approved">✅ Approved</option>
                    <option value="rejected">❌ Rejected</option>
                </select>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Employee</th>
                        <th class="px-5 py-3.5">Type &amp; Category</th>
                        <th class="px-5 py-3.5">Date &amp; Schedule</th>
                        <th class="px-5 py-3.5">Reason &amp; Remarks</th>
                        <th class="px-5 py-3.5 text-center">Attachment</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="l in leaves.data" :key="l.id" class="hover:bg-slate-800/40 transition">
                        <!-- Employee Info -->
                        <td class="px-5 py-4 font-medium text-white">
                            <div class="font-bold">{{ l.employee_name }}</div>
                            <div class="text-xs text-slate-400 font-mono flex items-center gap-1.5 mt-0.5">
                                <span>PIN: {{ l.employee_id }}</span>
                                <span v-if="l.department" class="text-slate-600">• {{ l.department }}</span>
                            </div>
                        </td>

                        <!-- Category / Type Badge -->
                        <td class="px-5 py-4">
                            <div class="flex flex-col items-start gap-1">
                                <span :class="formatCategory(l.category, l.leave_type, l.permit_type, l.expected_time).color" class="px-2.5 py-1 rounded-lg text-xs font-bold border">
                                    {{ formatCategory(l.category, l.leave_type, l.permit_type, l.expected_time).text }}
                                </span>
                                <span v-if="l.category === 'permit'" class="text-[11px] text-purple-300 font-medium ml-1">
                                    Permit Form
                                </span>
                            </div>
                        </td>

                        <!-- Dates & Expected Times -->
                        <td class="px-5 py-4 font-mono text-xs">
                            <div class="text-slate-200 font-bold">
                                {{ l.start_date }}
                                <span v-if="l.end_date && l.end_date !== l.start_date">→ {{ l.end_date }}</span>
                            </div>
                            <div v-if="l.category === 'leave'" class="text-emerald-400 font-semibold font-sans mt-0.5">
                                {{ l.days_count }} day(s) duration
                            </div>
                            <div v-else-if="l.expected_time" class="text-purple-400 font-semibold font-sans mt-0.5 flex items-center gap-1">
                                <Clock class="w-3.5 h-3.5" />
                                Est. Arrival: {{ l.expected_time }}
                            </div>
                        </td>

                        <!-- Reason & Admin Remarks -->
                        <td class="px-5 py-4 text-xs max-w-[240px]">
                            <div class="text-slate-300 italic" :title="l.reason">
                                "{{ l.reason || 'No reason provided' }}"
                            </div>
                            <div v-if="l.admin_notes" class="mt-1.5 p-1.5 rounded-lg bg-slate-950/80 border border-slate-800 text-[11px] text-blue-300 flex items-start gap-1.5">
                                <MessageSquare class="w-3.5 h-3.5 text-blue-400 shrink-0 mt-0.5" />
                                <span><strong>HR Remark:</strong> {{ l.admin_notes }}</span>
                            </div>
                        </td>

                        <!-- Attachment -->
                        <td class="px-5 py-4 text-center">
                            <button
                                v-if="l.attachment_url"
                                @click="openAttachment(l.attachment_url)"
                                class="p-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 border border-blue-500/20 transition cursor-pointer inline-flex items-center gap-1 text-xs font-semibold"
                                title="View Document / Certificate"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                View
                            </button>
                            <span v-else class="text-slate-600 text-xs">-</span>
                        </td>

                        <!-- Status Badge -->
                        <td class="px-5 py-4">
                            <span v-if="l.status === 'approved'" class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1 w-fit">
                                <CheckCircle2 class="w-3.5 h-3.5" />
                                Approved
                            </span>
                            <span v-else-if="l.status === 'pending'" class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1 w-fit">
                                <Clock class="w-3.5 h-3.5 animate-pulse" />
                                Pending
                            </span>
                            <span v-else class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1 w-fit">
                                <XCircle class="w-3.5 h-3.5" />
                                Rejected
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="px-5 py-4 text-right">
                            <div v-if="l.status === 'pending'" class="flex items-center justify-end gap-2">
                                <button
                                    @click="openApproveModal(l)"
                                    class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition flex items-center gap-1 cursor-pointer"
                                    title="Approve Request"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" />
                                    Approve
                                </button>
                                <button
                                    @click="openRejectModal(l)"
                                    class="px-2.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-sm transition flex items-center gap-1 cursor-pointer"
                                    title="Reject Request"
                                >
                                    <XCircle class="w-3.5 h-3.5" />
                                    Reject
                                </button>
                            </div>
                            <div v-else class="text-xs text-slate-500 font-mono text-right">
                                <div>{{ l.approved_by || 'HR Admin' }}</div>
                                <div class="text-[10px] text-slate-600">{{ l.processed_at || l.created_at }}</div>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="leaves.data.length === 0">
                        <td colspan="7" class="px-5 py-12 text-center text-slate-500 text-xs">
                            <Calendar class="w-8 h-8 mx-auto text-slate-600 mb-2" />
                            No leave applications or permission requests found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="leaves.links"
                :from="leaves.from"
                :to="leaves.to"
                :total="leaves.total"
            />
        </div>

        <!-- Approve / Reject Modal with Admin Remarks -->
        <div v-if="isProcessModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isProcessModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white cursor-pointer">
                    <X class="w-5 h-5" />
                </button>

                <div class="flex items-center gap-3 mb-4">
                    <div :class="processAction === 'approve' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'" class="w-10 h-10 rounded-xl border flex items-center justify-center">
                        <CheckCircle2 v-if="processAction === 'approve'" class="w-6 h-6" />
                        <XCircle v-else class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">
                            {{ processAction === 'approve' ? 'Approve Application' : 'Reject Application' }}
                        </h3>
                        <p class="text-xs text-slate-400">
                            {{ selectedRequest?.employee_name }} ({{ selectedRequest?.employee_id }})
                        </p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 mb-4 text-xs space-y-1.5">
                    <div class="flex justify-between text-slate-400">
                        <span>Application Type:</span>
                        <strong class="text-white capitalize">{{ selectedRequest?.category }} ({{ selectedRequest?.leave_type || selectedRequest?.permit_type }})</strong>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Date / Schedule:</span>
                        <strong class="text-white">{{ selectedRequest?.start_date }} {{ selectedRequest?.expected_time ? '@ ' + selectedRequest?.expected_time : '' }}</strong>
                    </div>
                    <div class="text-slate-400 pt-1 border-t border-slate-800/80">
                        <span>Reason:</span>
                        <p class="text-slate-200 italic mt-0.5">"{{ selectedRequest?.reason }}"</p>
                    </div>
                </div>

                <form @submit.prevent="submitProcess" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            HR Admin Remarks / Feedback (Optional)
                        </label>
                        <textarea
                            v-model="adminNotes"
                            rows="3"
                            :placeholder="processAction === 'approve' ? 'e.g. Medical certificate verified and approved.' : 'e.g. Quota exceeded or notice period insufficient.'"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isProcessModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :class="processAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/20' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/20'"
                            class="px-5 py-2 rounded-xl text-white text-xs font-bold shadow-lg transition cursor-pointer"
                        >
                            {{ processAction === 'approve' ? 'Confirm Approval' : 'Confirm Rejection' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attachment Preview Modal -->
        <div v-if="isAttachmentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl p-6 shadow-2xl relative">
                <button @click="isAttachmentModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white cursor-pointer">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                    <Paperclip class="w-4 h-4 text-blue-400" />
                    Medical Certificate / Evidence Document
                </h3>

                <div class="rounded-xl overflow-hidden bg-slate-950 border border-slate-800 flex items-center justify-center min-h-[300px] max-h-[500px]">
                    <img :src="attachmentPreviewUrl" alt="Attachment" class="max-h-[480px] w-auto object-contain" />
                </div>

                <div class="flex justify-between items-center mt-4">
                    <a :href="attachmentPreviewUrl" target="_blank" class="text-xs text-blue-400 hover:underline flex items-center gap-1">
                        <ExternalLink class="w-3.5 h-3.5" />
                        Open original file in new tab
                    </a>
                    <button
                        @click="isAttachmentModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Record Leave / Permit Modal -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isAddModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white cursor-pointer">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <Plus class="w-5 h-5 text-emerald-400" />
                    Record Absence or Permit
                </h3>

                <form @submit.prevent="saveLeave" class="space-y-3.5">
                    <!-- Category Toggle -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Application Category</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="form.category = 'leave'"
                                :class="form.category === 'leave' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                class="py-2 rounded-xl text-xs transition cursor-pointer"
                            >
                                🌴 Leave (Cuti)
                            </button>
                            <button
                                type="button"
                                @click="form.category = 'permit'"
                                :class="form.category === 'permit' ? 'bg-purple-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                class="py-2 rounded-xl text-xs transition cursor-pointer"
                            >
                                ⏰ Permit / Late Arrival
                            </button>
                        </div>
                    </div>

                    <!-- Employee Selector -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Select Employee</label>
                        <select
                            v-model="form.employee_id"
                            required
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        >
                            <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                {{ emp.full_name }} (PIN: {{ emp.employee_id }}) - {{ emp.department || 'Staff' }}
                            </option>
                        </select>
                    </div>

                    <!-- Leave Type Dropdown (If Leave) -->
                    <div v-if="form.category === 'leave'">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Leave Type</label>
                        <select
                            v-model="form.leave_type"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        >
                            <option value="annual">🌴 Annual Leave (Cuti Tahunan)</option>
                            <option value="sick">🏥 Sick Leave (Sakit)</option>
                            <option value="maternity">👶 Maternity / Paternity Leave</option>
                            <option value="unpaid">🛑 Unpaid Leave (Cuti Diluar Tanggungan)</option>
                            <option value="special">⭐ Special Event Leave</option>
                        </select>
                    </div>

                    <!-- Permit Type Dropdown (If Permit) -->
                    <div v-if="form.category === 'permit'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Permit Type</label>
                            <select
                                v-model="form.permit_type"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            >
                                <option value="late_arrival">⏰ Late Arrival</option>
                                <option value="early_departure">🚪 Early Departure</option>
                                <option value="official_duty">🏢 Official External Duty</option>
                                <option value="other">📝 Other Reason</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Expected Time</label>
                            <input
                                v-model="form.expected_time"
                                type="time"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Start Date</label>
                            <input
                                v-model="form.start_date"
                                type="date"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">End Date</label>
                            <input
                                v-model="form.end_date"
                                type="date"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Reason / Description</label>
                        <textarea
                            v-model="form.reason"
                            rows="2"
                            placeholder="Reason for leave or late arrival..."
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isAddModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-500 shadow-lg shadow-blue-600/20 transition cursor-pointer"
                        >
                            Save Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
