<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Search, UserCheck, Clock, QrCode, X, Loader2 } from 'lucide-vue-next';
import QRCode from 'qrcode';
import axios from 'axios';

const props = defineProps({
    employees: Object,
    shifts: Array,
    departments: Array,
    filters: Object,
});

const search = ref(props.filters.search);
const department = ref(props.filters.department);

// QR Modal State
const isQrModalOpen = ref(false);
const activeQrEmployee = ref(null);
const qrDataUrl = ref('');
const generatingQr = ref(false);

const applyFilters = () => {
    router.get('/admin/employees', {
        search: search.value,
        department: department.value,
    }, { preserveState: true });
};

const changeShift = (employeeId, shiftId) => {
    router.post(`/admin/employees/${employeeId}/shift`, {
        shift_schedule_id: shiftId,
    });
};

const openQrForEmployee = async (emp) => {
    activeQrEmployee.value = emp;
    qrDataUrl.value = '';
    isQrModalOpen.value = true;
    generatingQr.value = true;

    try {
        const res = await axios.post('/admin/devices/generate-qr', {
            employee_id: emp.employee_id,
            label: `${emp.full_name}'s Device`,
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
    <AdminLayout title="Employee Management">
        <Head title="Employees" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white">Employee Roster</h2>
                <p class="text-xs text-slate-400">View employees synchronized from ADMS, assign shifts, and generate onboarding QR codes</p>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-4 justify-between">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by PIN or Full Name..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
            </div>

            <div class="flex gap-2">
                <select
                    v-model="department"
                    @change="applyFilters"
                    class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500"
                >
                    <option value="all">All Departments</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">PIN</th>
                        <th class="px-5 py-3.5">Full Name</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">Assigned Shift</th>
                        <th class="px-5 py-3.5">Last Synced</th>
                        <th class="px-5 py-3.5 text-right">Quick QR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="emp in employees.data" :key="emp.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-mono font-bold text-blue-400">
                            <Link :href="`/admin/employees/${emp.id}`" class="hover:underline">
                                {{ emp.employee_id }}
                            </Link>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-white">
                            <Link :href="`/admin/employees/${emp.id}`" class="hover:text-blue-400 transition font-bold">
                                {{ emp.full_name }}
                            </Link>
                        </td>
                        <td class="px-5 py-3.5 text-slate-400 text-xs">
                            {{ emp.department }}
                        </td>
                        <td class="px-5 py-3.5">
                            <select
                                :value="emp.shift_schedule_id"
                                @change="changeShift(emp.id, $event.target.value)"
                                class="bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                            >
                                <option :value="null">Company Default</option>
                                <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500 font-mono">
                            {{ emp.last_synced }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                @click="openQrForEmployee(emp)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-600/15 hover:bg-blue-600/25 border border-blue-500/30 text-blue-400 text-xs font-semibold transition"
                                title="Generate Onboarding QR"
                            >
                                <QrCode class="w-3.5 h-3.5" />
                                <span>QR</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="employees.links"
                :from="employees.from"
                :to="employees.to"
                :total="employees.total"
            />
        </div>

        <!-- 1-Click Employee Onboarding QR Modal -->
        <div v-if="isQrModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative text-center">
                <button @click="isQrModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-1">Onboarding QR Code</h3>
                <p class="text-xs text-slate-400 mb-4">Scan in the Flutter mobile app settings to auto-configure this device.</p>

                <div v-if="generatingQr" class="py-12 flex flex-col items-center justify-center gap-2 text-xs text-slate-400">
                    <Loader2 class="w-5 h-5 animate-spin text-blue-400" />
                    Generating security key &amp; QR payload...
                </div>

                <div v-else-if="qrDataUrl" class="space-y-4">
                    <div class="p-5 rounded-2xl bg-white flex flex-col items-center justify-center shadow-inner">
                        <img :src="qrDataUrl" alt="Onboarding QR Code" class="w-56 h-56" />
                        <div class="mt-2">
                            <span class="text-xs font-bold text-slate-900 block">{{ activeQrEmployee?.full_name }}</span>
                            <span class="text-[11px] text-slate-500 font-mono block">PIN: {{ activeQrEmployee?.employee_id }}</span>
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
    </AdminLayout>
</template>
