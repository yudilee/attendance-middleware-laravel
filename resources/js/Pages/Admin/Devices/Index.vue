<script setup>
import { ref, onMounted } from 'vue';
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
    Loader2
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import axios from 'axios';

const props = defineProps({
    devices: Object,
    branches: Array,
    filters: Object,
});

const search = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);

// QR Modal & Autocomplete State
const isQrModalOpen = ref(false);
const selectedEmployee = ref(null);
const employeeSearchQuery = ref('');
const employeeSearchResults = ref([]);
const isSearchingEmployees = ref(false);
const qrLabel = ref('');
const qrDataUrl = ref('');
const generatingQr = ref(false);

let debounceTimer = null;

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
    if (confirm('Are you sure you want to delete this device binding?')) {
        router.delete(`/admin/devices/${id}`);
    }
};

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
        <Head title="Devices & QR" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white">Registered Mobile Devices</h2>
                <p class="text-xs text-slate-400">Approve new mobile devices and generate onboarding QR codes</p>
            </div>

            <button
                @click="openQrModal()"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition"
            >
                <QrCode class="w-4 h-4" />
                Generate Onboarding QR
            </button>
        </div>

        <!-- Filters Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-4 justify-between">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by PIN, Employee Name, or Device Label..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
            </div>

            <div class="flex gap-2">
                <select
                    v-model="selectedStatus"
                    @change="applyFilters"
                    class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500"
                >
                    <option value="all">All Statuses</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>

        <!-- Devices Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Employee</th>
                        <th class="px-5 py-3.5">Device Info</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Assigned Branches</th>
                        <th class="px-5 py-3.5">Registered At</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="device in devices.data" :key="device.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-4 font-medium text-white">
                            <div>{{ device.employee_name }}</div>
                            <div class="text-xs text-slate-500 font-mono">PIN: {{ device.employee_id }} ({{ device.department }})</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-200">{{ device.device_label }}</div>
                            <div class="text-[11px] text-slate-500 font-mono truncate max-w-[200px]" :title="device.device_uuid">
                                {{ device.device_uuid }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span v-if="device.registration_status === 'approved'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Approved
                            </span>
                            <span v-else-if="device.registration_status === 'pending_approval'" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Pending Approval
                            </span>
                            <span v-else class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                Suspended
                            </span>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-400">
                            {{ device.branches.join(', ') || 'All Active Branches' }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-400 font-mono">
                            {{ device.created_at }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    v-if="device.registration_status !== 'approved' || !device.is_active"
                                    @click="approveDevice(device.id)"
                                    class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition"
                                    title="Approve / Activate Device"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="device.registration_status === 'approved' && device.is_active"
                                    @click="suspendDevice(device.id)"
                                    class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 transition"
                                    title="Suspend Device"
                                >
                                    <XCircle class="w-4 h-4" />
                                </button>
                                <button
                                    @click="openQrModal({ employee_id: device.employee_id, full_name: device.employee_name, department: device.department })"
                                    class="p-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition"
                                    title="Regenerate QR Code"
                                >
                                    <QrCode class="w-4 h-4" />
                                </button>
                                <button
                                    @click="deleteDevice(device.id)"
                                    class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 transition"
                                    title="Delete Binding"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="devices.data.length === 0">
                        <td colspan="6" class="px-5 py-8 text-center text-slate-500">
                            No registered devices match the filter.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="devices.links"
                :from="devices.from"
                :to="devices.to"
                :total="devices.total"
            />
        </div>

        <!-- Onboarding QR Generator Modal (With Smart Searchable Autocomplete) -->
        <div v-if="isQrModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
                <button @click="isQrModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <QrCode class="w-5 h-5 text-blue-400" />
                    Onboarding QR Code Generator
                </h3>
                <p class="text-xs text-slate-400 mb-5">Search an employee to automatically generate their secure onboarding QR code.</p>

                <div class="space-y-4">
                    <!-- 1. Employee Search & Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Select Employee</label>

                        <!-- If employee is already selected -->
                        <div v-if="selectedEmployee" class="p-3 bg-slate-950 border border-blue-500/40 rounded-xl flex items-center justify-between shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 font-bold text-xs">
                                    {{ selectedEmployee.full_name?.substring(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-white">{{ selectedEmployee.full_name }}</div>
                                    <div class="text-xs text-slate-400 font-mono">
                                        PIN: <span class="text-blue-400 font-bold">{{ selectedEmployee.employee_id }}</span> • {{ selectedEmployee.department || 'No Dept' }}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="clearSelectedEmployee"
                                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs"
                                title="Change Employee"
                            >
                                Change
                            </button>
                        </div>

                        <!-- Searchable Autocomplete Combobox -->
                        <div v-else class="relative">
                            <div class="relative">
                                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                                <input
                                    v-model="employeeSearchQuery"
                                    @input="onEmployeeSearchInput"
                                    type="text"
                                    placeholder="Type to search name, PIN, or department (e.g. Yudi, 000011748)..."
                                    class="w-full pl-9 pr-8 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                                    autofocus
                                />
                                <Loader2 v-if="isSearchingEmployees" class="w-4 h-4 text-blue-400 animate-spin absolute right-3 top-3" />
                            </div>

                            <!-- Autocomplete Dropdown List -->
                            <div class="mt-1 max-h-52 overflow-y-auto bg-slate-950 border border-slate-800 rounded-xl divide-y divide-slate-900 shadow-xl">
                                <div
                                    v-for="emp in employeeSearchResults"
                                    :key="emp.id"
                                    @click="selectEmployee(emp)"
                                    class="p-2.5 hover:bg-slate-900 cursor-pointer transition flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-300">
                                            {{ emp.full_name?.substring(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-white">{{ emp.full_name }}</div>
                                            <div class="text-[11px] text-slate-400 font-mono">
                                                PIN: <span class="text-blue-400 font-semibold">{{ emp.employee_id }}</span> • {{ emp.department }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-[11px] text-blue-400 font-semibold">Select →</span>
                                </div>
                                <div v-if="employeeSearchResults.length === 0 && !isSearchingEmployees" class="p-4 text-center text-xs text-slate-500">
                                    No employees found. Try typing a name or PIN.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Device Label (Optional) -->
                    <div v-if="selectedEmployee">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Device Label</label>
                        <input
                            v-model="qrLabel"
                            type="text"
                            placeholder="e.g. Yudi's Phone"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <!-- 3. Rendered QR Code -->
                    <div v-if="qrDataUrl" class="mt-4 p-5 rounded-2xl bg-white flex flex-col items-center justify-center shadow-inner">
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
                        <Loader2 class="w-4 h-4 animate-spin text-blue-400" />
                        Generating security keys and QR code...
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
