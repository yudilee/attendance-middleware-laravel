<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Smartphone,
    QrCode,
    CheckCircle2,
    XCircle,
    ShieldAlert,
    Trash2,
    Search,
    X,
    User,
    UserCheck,
    Check,
    Loader2,
    Fingerprint,
    Building2,
    Edit2,
    Plus,
    Activity,
    Radio
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import axios from 'axios';

const props = defineProps({
    devices: Object,
    terminals: {
        type: Array,
        default: () => [],
    },
    branches: Array,
    filters: Object,
});

// Navigation Tab: 'mobile' | 'terminals'
const activeTab = ref('terminals'); // Default to terminals as requested or mobile

const search = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);
const terminalSearch = ref('');

// QR Modal & Autocomplete State
const isQrModalOpen = ref(false);
const selectedEmployee = ref(null);
const employeeSearchQuery = ref('');
const employeeSearchResults = ref([]);
const isSearchingEmployees = ref(false);
const qrLabel = ref('');
const qrDataUrl = ref('');
const generatingQr = ref(false);

// Terminal Modal State
const isTerminalModalOpen = ref(false);
const isEditingTerminal = ref(false);
const terminalForm = ref({
    id: null,
    device_sn: '',
    device_name: '',
    ip_address: '',
    branch_id: null,
    is_active: true,
});

let debounceTimer = null;

const filteredTerminals = computed(() => {
    if (!terminalSearch.value) return props.terminals;
    const q = terminalSearch.value.toLowerCase();
    return props.terminals.filter(t =>
        (t.device_sn && t.device_sn.toLowerCase().includes(q)) ||
        (t.device_name && t.device_name.toLowerCase().includes(q)) ||
        (t.ip_address && t.ip_address.toLowerCase().includes(q)) ||
        (t.branch_name && t.branch_name.toLowerCase().includes(q))
    );
});

const applyFilters = () => {
    router.get('/admin/devices', {
        search: search.value,
        status: selectedStatus.value,
    }, { preserveState: true });
};

const approveDevice = (id) => {
    router.post(`/admin/devices/${id}/approve`);
};

const suspendDevice = (id) => {
    router.post(`/admin/devices/${id}/suspend`);
};

const deleteDevice = (id) => {
    if (confirm('Are you sure you want to delete this mobile device binding?')) {
        router.delete(`/admin/devices/${id}`);
    }
};

// Terminal Quick Branch Change
const updateTerminalBranch = (terminal, branchId) => {
    router.put(`/admin/devices/terminals/${terminal.id}`, {
        device_name: terminal.device_name,
        ip_address: terminal.ip_address,
        branch_id: branchId,
        is_active: terminal.is_active,
    }, { preserveScroll: true });
};

// Open Terminal Create Modal
const openCreateTerminalModal = () => {
    isEditingTerminal.value = false;
    terminalForm.value = {
        id: null,
        device_sn: '',
        device_name: '',
        ip_address: '',
        branch_id: props.branches.length > 0 ? props.branches[0].id : null,
        is_active: true,
    };
    isTerminalModalOpen.value = true;
};

// Open Terminal Edit Modal
const openEditTerminalModal = (terminal) => {
    isEditingTerminal.value = true;
    terminalForm.value = {
        id: terminal.id,
        device_sn: terminal.device_sn,
        device_name: terminal.device_name,
        ip_address: terminal.ip_address,
        branch_id: terminal.branch_id,
        is_active: terminal.is_active,
    };
    isTerminalModalOpen.value = true;
};

// Save Terminal Form
const saveTerminal = () => {
    if (isEditingTerminal.value) {
        router.put(`/admin/devices/terminals/${terminalForm.value.id}`, terminalForm.value, {
            onSuccess: () => {
                isTerminalModalOpen.value = false;
            }
        });
    } else {
        router.post('/admin/devices/terminals', terminalForm.value, {
            onSuccess: () => {
                isTerminalModalOpen.value = false;
            }
        });
    }
};

// Delete Terminal
const deleteTerminal = (id) => {
    if (confirm('Delete this biometric terminal record?')) {
        router.delete(`/admin/devices/terminals/${id}`);
    }
};

// QR Functions
const openQrModal = (employee = null) => {
    selectedEmployee.value = employee;
    employeeSearchQuery.value = '';
    employeeSearchResults.value = [];
    qrDataUrl.value = '';

    if (employee) {
        qrLabel.value = `${employee.full_name}'s Device`;
        generateQrForEmployee(employee.employee_id, qrLabel.value);
    } else {
        qrLabel.value = '';
        fetchInitialEmployees();
    }

    isQrModalOpen.value = true;
};

const fetchInitialEmployees = async () => {
    isSearchingEmployees.value = true;
    try {
        const res = await axios.get('/admin/employees/search');
        employeeSearchResults.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        isSearchingEmployees.value = false;
    }
};

const onEmployeeSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
        isSearchingEmployees.value = true;
        try {
            const res = await axios.get(`/admin/employees/search?query=${encodeURIComponent(employeeSearchQuery.value)}`);
            employeeSearchResults.value = res.data;
        } catch (e) {
            console.error(e);
        } finally {
            isSearchingEmployees.value = false;
        }
    }, 250);
};

const selectEmployee = (emp) => {
    selectedEmployee.value = emp;
    employeeSearchQuery.value = '';
    employeeSearchResults.value = [];
    qrLabel.value = `${emp.full_name}'s Device`;
    generateQrForEmployee(emp.employee_id, qrLabel.value);
};

const clearSelectedEmployee = () => {
    selectedEmployee.value = null;
    qrDataUrl.value = '';
    fetchInitialEmployees();
};

const generateQrForEmployee = async (employeeId, label) => {
    generatingQr.value = true;
    try {
        const res = await axios.post('/admin/devices/generate-qr', {
            employee_id: employeeId,
            label: label,
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
    <AdminLayout title="Device Management">
        <Head title="Devices & Terminals" />

        <!-- Top Header & Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Device &amp; Terminal Management</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Map physical fingerprint machines to branches and manage registered mobile devices</p>
            </div>

            <!-- Tab Buttons -->
            <div class="flex items-center gap-2 p-1 bg-slate-200/80 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl">
                <button
                    @click="activeTab = 'terminals'"
                    :class="[
                        'flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all',
                        activeTab === 'terminals'
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <Fingerprint class="w-4 h-4" />
                    <span>Fingerprint Machines</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-white/20 dark:bg-slate-800 text-white">
                        {{ terminals.length }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'mobile'"
                    :class="[
                        'flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all',
                        activeTab === 'mobile'
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    <Smartphone class="w-4 h-4" />
                    <span>Mobile App Devices</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-white/20 dark:bg-slate-800 text-white">
                        {{ devices.total ?? devices.data?.length ?? 0 }}
                    </span>
                </button>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 1: BIOMETRIC & FINGERPRINT TERMINALS                       -->
        <!-- ============================================================== -->
        <div v-if="activeTab === 'terminals'" class="space-y-4">
            <!-- Action Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row gap-3 justify-between items-center shadow-xs">
                <div class="relative flex-1 w-full">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    <input
                        v-model="terminalSearch"
                        type="text"
                        placeholder="Search by Machine SN, Name, IP, or Branch..."
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button
                        @click="openCreateTerminalModal"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition whitespace-nowrap"
                    >
                        <Plus class="w-4 h-4" />
                        Add Machine SN
                    </button>
                </div>
            </div>

            <!-- Terminals Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Building2 class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            Fingerprint Machine ↔ Branch Linking
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Select which physical branch each fingerprint machine is located at. Punches from that machine will automatically register to that branch.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">Fingerprint Terminal</th>
                                <th class="px-5 py-3.5">Device Serial Number (SN)</th>
                                <th class="px-5 py-3.5">IP Address</th>
                                <th class="px-5 py-3.5">Assigned Branch</th>
                                <th class="px-5 py-3.5">Last Sync Activity</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="t in filteredTerminals" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700/40 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                            <Fingerprint class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white text-xs">{{ t.device_name || 'Biometric Terminal' }}</div>
                                            <span v-if="t.is_active" class="inline-flex items-center gap-1 text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 text-[10px] text-rose-500 font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Disabled
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60">
                                        {{ t.device_sn }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                                    {{ t.ip_address || '-' }}
                                </td>
                                <td class="px-5 py-4">
                                    <!-- 1-Click Branch Selector Dropdown -->
                                    <div class="flex items-center gap-2">
                                        <select
                                            :value="t.branch_id"
                                            @change="updateTerminalBranch(t, $event.target.value ? parseInt($event.target.value) : null)"
                                            class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer max-w-[200px]"
                                        >
                                            <option :value="null">⚠️ Unassigned (Employee Fallback)</option>
                                            <option v-for="b in branches" :key="b.id" :value="b.id">
                                                🏢 {{ b.name }}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
                                    <span v-if="t.last_activity_at" class="flex items-center gap-1.5">
                                        <Activity class="w-3.5 h-3.5 text-emerald-500" />
                                        {{ t.last_activity_at }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openEditTerminalModal(t)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:bg-blue-50 dark:hover:bg-blue-600/20 hover:text-blue-600 dark:hover:text-blue-300 transition"
                                            title="Edit Machine Name & IP"
                                        >
                                            <Edit2 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="deleteTerminal(t.id)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:bg-rose-50 dark:hover:bg-rose-500/20 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                            title="Delete Machine Record"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredTerminals.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No fingerprint terminals found. Machine serial numbers are auto-discovered during ADMS sync or can be manually added with "+ Add Machine SN".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 2: REGISTERED MOBILE DEVICES & ONBOARDING QR               -->
        <!-- ============================================================== -->
        <div v-else-if="activeTab === 'mobile'" class="space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Registered Mobile Employee Devices</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Approve new mobile devices and generate onboarding QR codes for employees</p>
                </div>
                <button
                    @click="openQrModal()"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition"
                >
                    <QrCode class="w-4 h-4" />
                    Generate Onboarding QR
                </button>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row gap-4 justify-between shadow-xs">
                <div class="relative flex-1">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search by PIN, Employee Name, or Device Label..."
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div class="flex gap-2">
                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-300 focus:outline-none focus:border-blue-500"
                    >
                        <option value="all">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <!-- Devices Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">Employee</th>
                                <th class="px-5 py-3.5">Device Info</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5">Assigned Branches</th>
                                <th class="px-5 py-3.5">Registered At</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="device in devices.data" :key="device.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">
                                    <div>{{ device.employee_name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">PIN: {{ device.employee_id }} ({{ device.department }})</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ device.device_label }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono truncate max-w-[200px]" :title="device.device_uuid">
                                        {{ device.device_uuid }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span v-if="device.registration_status === 'approved'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Approved
                                    </span>
                                    <span v-else-if="device.registration_status === 'pending_approval'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Pending Approval
                                    </span>
                                    <span v-else class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        Suspended
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500 dark:text-slate-400">
                                    {{ device.branches.join(', ') || 'All Active Branches' }}
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                    {{ device.created_at }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="device.registration_status !== 'approved' || !device.is_active"
                                            @click="approveDevice(device.id)"
                                            class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 transition"
                                            title="Approve / Activate Device"
                                        >
                                            <CheckCircle2 class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-if="device.registration_status === 'approved' && device.is_active"
                                            @click="suspendDevice(device.id)"
                                            class="p-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 transition"
                                            title="Suspend Device"
                                        >
                                            <XCircle class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="openQrModal({ employee_id: device.employee_id, full_name: device.employee_name, department: device.department })"
                                            class="p-1.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition"
                                            title="Regenerate QR Code"
                                        >
                                            <QrCode class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteDevice(device.id)"
                                            class="p-1.5 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20 transition"
                                            title="Delete Binding"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="devices.data.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                                    No registered mobile devices match the filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="devices.links"
                    :from="devices.from"
                    :to="devices.to"
                    :total="devices.total"
                />
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL: ADD / EDIT BIOMETRIC TERMINAL                           -->
        <!-- ============================================================== -->
        <div v-if="isTerminalModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isTerminalModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                    <Fingerprint class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    {{ isEditingTerminal ? 'Edit Biometric Terminal' : 'Register Fingerprint Machine' }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">
                    Link physical biometric machine serial number to a designated branch.
                </p>

                <form @submit.prevent="saveTerminal" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Device Serial Number (SN)</label>
                        <input
                            v-model="terminalForm.device_sn"
                            :disabled="isEditingTerminal"
                            type="text"
                            required
                            placeholder="e.g. BWXP191562649 or NJF7261700626"
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500 disabled:opacity-60"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Friendly Terminal Name</label>
                        <input
                            v-model="terminalForm.device_name"
                            type="text"
                            placeholder="e.g. Surabaya HQ - Lobby Entrance"
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">IP Address (Optional)</label>
                        <input
                            v-model="terminalForm.ip_address"
                            type="text"
                            placeholder="e.g. 192.168.10.123"
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Branch</label>
                        <select
                            v-model="terminalForm.branch_id"
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        >
                            <option :value="null">⚠️ Unassigned (Employee Default Branch)</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">
                                🏢 {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="terminal_is_active"
                            v-model="terminalForm.is_active"
                            class="rounded border-slate-300 dark:border-slate-800 text-blue-600 focus:ring-blue-500"
                        />
                        <label for="terminal_is_active" class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                            Enable this terminal for attendance tracking
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <button
                            type="button"
                            @click="isTerminalModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition"
                        >
                            {{ isEditingTerminal ? 'Save Changes' : 'Register Machine' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL: ONBOARDING QR GENERATOR                                 -->
        <!-- ============================================================== -->
        <div v-if="isQrModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
                <button @click="isQrModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                    <QrCode class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    Onboarding QR Code Generator
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">Search an employee to automatically generate their secure onboarding QR code.</p>

                <div class="space-y-4">
                    <!-- 1. Employee Search & Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wider">Select Employee</label>

                        <!-- If employee is already selected -->
                        <div v-if="selectedEmployee" class="p-3 bg-slate-50 dark:bg-slate-950 border border-blue-500/40 rounded-xl flex items-center justify-between shadow-xs">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-xs">
                                    {{ selectedEmployee.full_name?.substring(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-slate-900 dark:text-white">{{ selectedEmployee.full_name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                                        PIN: <span class="text-blue-600 dark:text-blue-400 font-bold">{{ selectedEmployee.employee_id }}</span> • {{ selectedEmployee.department || 'No Dept' }}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="clearSelectedEmployee"
                                class="p-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-400 text-xs"
                                title="Change Employee"
                            >
                                Change
                            </button>
                        </div>

                        <!-- Searchable Autocomplete Combobox -->
                        <div v-else class="relative">
                            <div class="relative">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                                <input
                                    v-model="employeeSearchQuery"
                                    @input="onEmployeeSearchInput"
                                    type="text"
                                    placeholder="Type to search name, PIN, or department (e.g. Bagus, 000020431)..."
                                    class="w-full pl-9 pr-8 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                                    autofocus
                                />
                                <Loader2 v-if="isSearchingEmployees" class="w-4 h-4 text-blue-500 animate-spin absolute right-3 top-3" />
                            </div>

                            <!-- Autocomplete Dropdown List -->
                            <div class="mt-1 max-h-52 overflow-y-auto bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl divide-y divide-slate-200 dark:divide-slate-900 shadow-xl">
                                <div
                                    v-for="emp in employeeSearchResults"
                                    :key="emp.id"
                                    @click="selectEmployee(emp)"
                                    class="p-2.5 hover:bg-slate-100 dark:hover:bg-slate-900 cursor-pointer transition flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-700 dark:text-slate-300">
                                            {{ emp.full_name?.substring(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-slate-900 dark:text-white">{{ emp.full_name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                                PIN: <span class="text-blue-600 dark:text-blue-400 font-semibold">{{ emp.employee_id }}</span> • {{ emp.department }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold">Select →</span>
                                </div>
                                <div v-if="employeeSearchResults.length === 0 && !isSearchingEmployees" class="p-4 text-center text-xs text-slate-500">
                                    No employees found. Try typing a name or PIN.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Device Label (Optional) -->
                    <div v-if="selectedEmployee">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Device Label</label>
                        <input
                            v-model="qrLabel"
                            type="text"
                            placeholder="e.g. Bagus's Phone"
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <!-- 3. Rendered QR Code -->
                    <div v-if="qrDataUrl" class="mt-4 p-5 rounded-2xl bg-white border border-slate-200 dark:border-transparent flex flex-col items-center justify-center shadow-inner">
                        <img :src="qrDataUrl" alt="Onboarding QR Code" class="w-56 h-56" />
                        <div class="text-center mt-2">
                            <span class="text-xs font-bold text-slate-900 block">{{ selectedEmployee?.full_name }}</span>
                            <span class="text-[11px] text-slate-500 font-mono block">PIN: {{ selectedEmployee?.employee_id }}</span>
                            <p class="text-[10px] text-blue-600 font-semibold mt-1">
                                Scan in Flutter App Settings &gt; Scan Onboarding QR
                            </p>
                        </div>
                    </div>

                    <div v-if="generatingQr" class="py-10 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                        <Loader2 class="w-4 h-4 animate-spin text-blue-500" />
                        Generating security keys and QR code...
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

