<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    User,
    ArrowLeft,
    Smartphone,
    Clock,
    Calendar,
    Building2,
    ShieldCheck,
    CheckCircle2,
    XCircle,
    QrCode,
    Activity,
    Layers,
    Trash2,
    AlertTriangle,
    Loader2
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import axios from 'axios';

const props = defineProps({
    employee: Object,
    devices: Array,
    recent_punches: Array,
    leave_balance: Object,
    shifts: Array,
    branches: Array,
});

const isQrModalOpen = ref(false);
const qrDataUrl = ref('');
const generatingQr = ref(false);

const isDeleteModalOpen = ref(false);
const deletingFromAdms = ref(false);

const confirmDeleteFromAdms = () => {
    isDeleteModalOpen.value = true;
};

const executeDeleteFromAdms = async () => {
    deletingFromAdms.value = true;
    try {
        await axios.post(`/admin/employees/${props.employee.id}/delete-from-adms`);
        router.reload();
    } catch (e) {
        alert('Failed to delete employee from ADMS: ' + (e.response?.data?.message || e.message));
    } finally {
        deletingFromAdms.value = false;
        isDeleteModalOpen.value = false;
    }
};

const changeShift = (shiftId) => {
    router.post(`/admin/employees/${props.employee.id}/shift`, {
        shift_schedule_id: shiftId,
    });
};

const changeRole = (role) => {
    router.post(`/admin/employees/${props.employee.id}/role`, {
        employee_type: role,
    });
};

const openQrModal = async () => {
    isQrModalOpen.value = true;
    generatingQr.value = true;
    try {
        const res = await axios.post('/admin/devices/generate-qr', {
            employee_id: props.employee.employee_id,
            label: `${props.employee.full_name}'s Device`,
        });

        if (res.data.status === 'ok') {
            qrDataUrl.value = await QRCode.toDataURL(res.data.qr_data, {
                width: 260,
                margin: 2,
                color: { dark: '#0f172a', light: '#ffffff' }
            });
        }
    } catch (e) {
        alert('Failed to generate QR: ' + (e.response?.data?.message || e.message));
    } finally {
        generatingQr.value = false;
    }
};
</script>

<template>
    <AdminLayout :title="`Employee Profile: ${employee.full_name}`">
        <Head :title="employee.full_name" />

        <!-- Top Navigation / Breadcrumb -->
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <Link
                    href="/admin/employees"
                    class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition"
                >
                    <ArrowLeft class="w-4 h-4" />
                </Link>
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>{{ employee.full_name }}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
                            PIN: {{ employee.employee_id }}
                        </span>
                        <span :class="['text-xs px-2.5 py-0.5 rounded-full border capitalize font-semibold', employee.employee_type === 'mechanic' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : (employee.employee_type === 'sales' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20')]">
                            Role: {{ employee.employee_type || 'regular' }}
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400">{{ employee.department }} • {{ employee.company_name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="confirmDeleteFromAdms"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-600/20 transition"
                >
                    <Trash2 class="w-4 h-4" />
                    Delete from ADMS
                </button>
                <button
                    @click="openQrModal"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition"
                >
                    <QrCode class="w-4 h-4" />
                    Generate Onboarding QR
                </button>
            </div>
        </div>

        <!-- 3-Column Profile Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
            <!-- 1. Assigned Shift & Role -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Work Schedule & Role</span>
                    <Clock class="w-4 h-4 text-blue-400" />
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 uppercase font-semibold mb-1">Shift Schedule</label>
                    <select
                        :value="employee.shift_schedule_id"
                        @change="changeShift($event.target.value)"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                    >
                        <option :value="null">Company Default Shift</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 uppercase font-semibold mb-1">Workforce Role</label>
                    <select
                        :value="employee.employee_type || 'regular'"
                        @change="changeRole($event.target.value)"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500 capitalize"
                    >
                        <option value="regular">Regular Staff (In-Office Attendance)</option>
                        <option value="mechanic">Mechanic (External Storing & Delivery)</option>
                        <option value="sales">Sales (Customer Canvassing & Visits)</option>
                    </select>
                </div>
            </div>

            <!-- 2. Annual Leave Balance -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Annual Leave</span>
                    <Calendar class="w-4 h-4 text-emerald-400" />
                </div>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-bold font-mono text-emerald-400">{{ leave_balance.annual_remaining }}</span>
                    <span class="text-xs text-slate-400">days available</span>
                </div>
                <div class="text-xs text-slate-500 font-mono">
                    Used: {{ leave_balance.annual_used }} / Quota: {{ leave_balance.annual_total }} days
                </div>
            </div>

            <!-- 3. Sick Leave Balance -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Sick Leave</span>
                    <ShieldCheck class="w-4 h-4 text-indigo-400" />
                </div>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-bold font-mono text-indigo-400">{{ leave_balance.sick_remaining }}</span>
                    <span class="text-xs text-slate-400">days available</span>
                </div>
                <div class="text-xs text-slate-500 font-mono">
                    Used: {{ leave_balance.sick_used }} / Quota: {{ leave_balance.sick_total }} days
                </div>
            </div>
        </div>

        <!-- 2-Column Details Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Registered Mobile Devices (5 Cols) -->
            <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <Smartphone class="w-4 h-4 text-blue-400" />
                        <h3 class="text-sm font-bold text-white">Registered Mobile Devices</h3>
                    </div>
                    <span class="text-xs text-slate-400 font-mono">{{ devices.length }} registered</span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="d in devices"
                        :key="d.id"
                        class="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 space-y-2"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-white">{{ d.device_label }}</span>
                            <span v-if="d.registration_status === 'approved'" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Approved
                            </span>
                            <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                {{ d.registration_status }}
                            </span>
                        </div>
                        <div class="text-[11px] font-mono text-slate-500 truncate" :title="d.device_uuid">
                            UUID: {{ d.device_uuid }}
                        </div>
                        <div class="text-[11px] text-slate-400 flex justify-between">
                            <span>Branches: {{ d.branches.join(', ') || 'All Branches' }}</span>
                            <span>{{ d.created_at }}</span>
                        </div>
                    </div>

                    <div v-if="devices.length === 0" class="py-8 text-center text-xs text-slate-500">
                        No mobile device bound yet. Click 'Generate Onboarding QR' to register one.
                    </div>
                </div>
            </div>

            <!-- Right: Recent 30 Clock-in / Out History (7 Cols) -->
            <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                    <div class="flex items-center gap-2">
                        <Activity class="w-4 h-4 text-emerald-400" />
                        <h3 class="text-sm font-bold text-white">Punch Activity History</h3>
                    </div>
                    <span class="text-xs text-slate-400">Last 30 Punches</span>
                </div>

                <div class="overflow-x-auto max-h-[460px] overflow-y-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/60 uppercase text-[10px] text-slate-400 border-b border-slate-800 sticky top-0">
                            <tr>
                                <th class="px-3 py-2.5">Type</th>
                                <th class="px-3 py-2.5">Timestamp</th>
                                <th class="px-3 py-2.5">Biometric</th>
                                <th class="px-3 py-2.5">GPS Coordinates</th>
                                <th class="px-3 py-2.5">ADMS Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="p in recent_punches" :key="p.id" class="hover:bg-slate-800/40">
                                <td class="px-3 py-2.5">
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full font-semibold',
                                        p.punch_type === 'In' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-indigo-500/10 text-indigo-400'
                                    ]">
                                        {{ p.punch_type }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-slate-200">{{ p.timestamp }}</td>
                                <td class="px-3 py-2.5">
                                    <span v-if="p.biometric_verified" class="text-emerald-400">Verified</span>
                                    <span v-else class="text-slate-500">Standard</span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-slate-400">
                                    {{ p.latitude ? `${p.latitude.toFixed(4)}, ${p.longitude.toFixed(4)}` : '-' }}
                                </td>
                                <td class="px-3 py-2.5 font-mono text-slate-400">{{ p.adms_status }}</td>
                            </tr>
                            <tr v-if="recent_punches.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-500">No punch records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Onboarding QR Modal -->
        <div v-if="isQrModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative text-center">
                <button @click="isQrModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    ✕
                </button>

                <h3 class="text-base font-bold text-white mb-1">Onboarding QR Code</h3>
                <p class="text-xs text-slate-400 mb-4">Scan with Mobile App to onboard {{ employee.full_name }}</p>

                <div v-if="generatingQr" class="py-12 text-xs text-slate-400">
                    Generating security key &amp; QR payload...
                </div>

                <div v-else-if="qrDataUrl" class="space-y-4">
                    <div class="p-5 rounded-2xl bg-white flex flex-col items-center justify-center shadow-inner">
                        <img :src="qrDataUrl" alt="Onboarding QR Code" class="w-56 h-56" />
                        <div class="mt-2 text-center">
                            <span class="text-xs font-bold text-slate-900 block">{{ employee.full_name }}</span>
                            <span class="text-[11px] text-slate-500 font-mono block">PIN: {{ employee.employee_id }}</span>
                        </div>
                    </div>

                    <button
                        @click="isQrModalOpen = false"
                        class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete from ADMS Confirmation Modal -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isDeleteModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    ✕
                </button>

                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2.5 rounded-xl bg-rose-500/10">
                        <AlertTriangle class="w-5 h-5 text-rose-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Delete from ADMS</h3>
                        <p class="text-xs text-slate-400">This action cannot be undone.</p>
                    </div>
                </div>

                <p class="text-sm text-slate-300 mb-6">
                    Are you sure you want to delete <strong class="text-white">{{ employee.full_name }}</strong>
                    (PIN: {{ employee.employee_id }}) from the ADMS server? This will remove the user from the
                    fingerprint/face recognition device registry.
                </p>

                <div class="flex items-center gap-3 justify-end">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                        :disabled="deletingFromAdms"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeDeleteFromAdms"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition disabled:opacity-50"
                        :disabled="deletingFromAdms"
                    >
                        <Loader2 v-if="deletingFromAdms" class="w-4 h-4 animate-spin" />
                        <Trash2 v-else class="w-4 h-4" />
                        {{ deletingFromAdms ? 'Deleting...' : 'Yes, Delete from ADMS' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
