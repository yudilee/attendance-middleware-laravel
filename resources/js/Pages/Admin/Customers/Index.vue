<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

// Fix Leaflet Default Icon in bundlers
if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
}

const props = defineProps({
    customers: Object,
    employees: Array,
    companies: Array,
    filters: Object,
});

// Filter states
const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const assignedFilter = ref(props.filters.assigned_id || '');
const statusFilter = ref(props.filters.status ?? '');

let searchDebounce = null;
function applyFilters() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        router.get(route('admin.customers'), {
            search: search.value || undefined,
            type: typeFilter.value || undefined,
            assigned_id: assignedFilter.value || undefined,
            status: statusFilter.value !== '' ? statusFilter.value : undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    }, 300);
}

watch([search, typeFilter, assignedFilter, statusFilter], () => {
    applyFilters();
});

// Add / Edit Modal
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const form = ref({
    name: '',
    customer_type: 'dealer',
    city: '',
    address: '',
    phone: '',
    email: '',
    latitude: -6.2088,
    longitude: 106.8456,
    assigned_employee_id: '',
    company_id: '',
    is_active: true,
    notes: '',
});
const formLoading = ref(false);

// Leaflet Map Picker
let mapInstance = null;
let markerInstance = null;

function initMap(lat, lng) {
    nextTick(() => {
        const mapContainer = document.getElementById('customer-map-picker');
        if (!mapContainer) return;

        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }

        if (typeof L === 'undefined') return;

        const defaultLat = lat || -6.2088;
        const defaultLng = lng || 106.8456;

        mapInstance = L.map('customer-map-picker').setView([defaultLat, defaultLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(mapInstance);

        markerInstance = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(mapInstance);

        markerInstance.on('dragend', (e) => {
            const pos = e.target.getLatLng();
            form.value.latitude = parseFloat(pos.lat.toFixed(6));
            form.value.longitude = parseFloat(pos.lng.toFixed(6));
        });

        mapInstance.on('click', (e) => {
            markerInstance.setLatLng(e.latlng);
            form.value.latitude = parseFloat(e.latlng.lat.toFixed(6));
            form.value.longitude = parseFloat(e.latlng.lng.toFixed(6));
        });

        setTimeout(() => {
            mapInstance.invalidateSize();
        }, 200);
    });
}

function openCreateModal() {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        name: '',
        customer_type: 'dealer',
        city: '',
        address: '',
        phone: '',
        email: '',
        latitude: -6.2088,
        longitude: 106.8456,
        assigned_employee_id: '',
        company_id: '',
        is_active: true,
        notes: '',
    };
    showModal.value = true;
    initMap(-6.2088, 106.8456);
}

function openEditModal(c) {
    isEditing.value = true;
    editingId.value = c.id;
    form.value = {
        name: c.name,
        customer_type: c.customer_type,
        city: c.city || '',
        address: c.address || '',
        phone: c.phone || '',
        email: c.email || '',
        latitude: c.latitude || -6.2088,
        longitude: c.longitude || 106.8456,
        assigned_employee_id: c.assigned_employee_id || '',
        company_id: c.company_id || '',
        is_active: !!c.is_active,
        notes: c.notes || '',
    };
    showModal.value = true;
    initMap(c.latitude, c.longitude);
}

function saveCustomer() {
    if (!form.value.name) return;
    formLoading.value = true;

    if (isEditing.value) {
        router.put(route('admin.customers.update', editingId.value), form.value, {
            onSuccess: () => {
                showModal.value = false;
                formLoading.value = false;
            },
            onError: () => { formLoading.value = false; }
        });
    } else {
        router.post(route('admin.customers.store'), form.value, {
            onSuccess: () => {
                showModal.value = false;
                formLoading.value = false;
            },
            onError: () => { formLoading.value = false; }
        });
    }
}

// Delete modal
const showDeleteModal = ref(false);
const deleteTarget = ref(null);
const deleteLoading = ref(false);

function confirmDelete(c) {
    deleteTarget.value = c;
    showDeleteModal.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    router.delete(route('admin.customers.destroy', deleteTarget.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteLoading.value = false;
        },
        onError: () => { deleteLoading.value = false; }
    });
}

// Import CSV Modal
const showImportModal = ref(false);
const csvFile = ref(null);
const importLoading = ref(false);

function handleFileUpload(e) {
    csvFile.value = e.target.files[0];
}

function submitImport() {
    if (!csvFile.value) return;
    importLoading.value = true;
    const formData = new FormData();
    formData.append('file', csvFile.value);

    router.post(route('admin.customers.import-csv'), formData, {
        onSuccess: () => {
            showImportModal.value = false;
            importLoading.value = false;
            csvFile.value = null;
        },
        onError: () => { importLoading.value = false; }
    });
}

function getTypeBadge(type) {
    switch (type) {
        case 'dealer': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'warehouse': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        case 'workshop': return 'bg-purple-500/10 text-purple-400 border-purple-500/20';
        case 'prospect': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'end_customer': return 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
        default: return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
}
</script>

<template>
    <AdminLayout>
        <Head title="Customer & Location Registry" />

        <div class="space-y-6">
            <!-- Header banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-blue-500/10 text-blue-500 dark:bg-blue-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </span>
                        Customer & Location Registry
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Master list of dealers, workshops, warehouses, and customer visit destinations for mechanics and sales canvassing.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="showImportModal = true" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-sm flex items-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import CSV
                    </button>
                    <button @click="openCreateModal" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm shadow-sm flex items-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Customer / Location
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Search</label>
                        <div class="relative">
                            <input v-model="search" type="text" placeholder="Name, city, address, phone..." class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 outline-none" />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Type</label>
                        <select v-model="typeFilter" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option value="dealer">Dealer</option>
                            <option value="workshop">Workshop</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="prospect">Sales Prospect</option>
                            <option value="end_customer">End Customer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Assigned Staff</label>
                        <select v-model="assignedFilter" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Staff</option>
                            <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                {{ emp.full_name }} ({{ emp.employee_id }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                        <select v-model="statusFilter" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3.5">Customer / Location</th>
                                <th class="px-4 py-3.5">Type</th>
                                <th class="px-4 py-3.5">City & Address</th>
                                <th class="px-4 py-3.5">Contact</th>
                                <th class="px-4 py-3.5">Assigned Staff</th>
                                <th class="px-4 py-3.5 text-center">Visits</th>
                                <th class="px-4 py-3.5 text-center">Odoo CRM</th>
                                <th class="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-normal">
                            <tr v-for="c in customers.data" :key="c.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>{{ c.name }}</span>
                                        <span v-if="!c.is_active" class="text-[10px] px-1.5 py-0.5 rounded bg-red-500/10 text-red-400">Inactive</span>
                                    </div>
                                    <div v-if="c.latitude && c.longitude" class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        {{ c.latitude.toFixed(4) }}, {{ c.longitude.toFixed(4) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold border capitalize', getTypeBadge(c.customer_type)]">
                                        {{ c.customer_type.replace('_', ' ') }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ c.city || '-' }}</div>
                                    <div class="text-xs text-slate-400 truncate max-w-xs">{{ c.address || 'No address' }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="text-slate-800 dark:text-slate-200">{{ c.phone || '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ c.email || '' }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div v-if="c.assigned_employee" class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ c.assigned_employee.full_name }}
                                    </div>
                                    <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ c.field_visits_count || 0 }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span v-if="c.odoo_partner_id" class="inline-flex items-center gap-1 text-xs text-emerald-400 font-medium" title="Synced with Odoo res.partner">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        #{{ c.odoo_partner_id }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400 italic">Local</span>
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button @click="openEditModal(c)" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-blue-500 transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="confirmDelete(c)" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500 hover:text-red-500 transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!customers.data || customers.data.length === 0">
                                <td colspan="8" class="text-center py-12 text-slate-400">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    No customers or visit locations found. Click "+ Add Customer / Location" to get started.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                    <Pagination :links="customers.links" :from="customers.from" :to="customers.to" :total="customers.total" />
                </div>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ isEditing ? 'Edit Customer / Location' : 'Add New Customer / Location' }}
                    </h2>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveCustomer" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Customer / Location Name *</label>
                            <input v-model="form.name" required type="text" placeholder="e.g. PT Maju Motor Dealer" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Type *</label>
                            <select v-model="form.customer_type" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="dealer">Dealer</option>
                                <option value="workshop">Workshop</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="prospect">Sales Prospect</option>
                                <option value="end_customer">End Customer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">City</label>
                            <input v-model="form.city" type="text" placeholder="e.g. Jakarta Pusat" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Phone</label>
                            <input v-model="form.phone" type="text" placeholder="e.g. 08123456789" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Full Address</label>
                        <textarea v-model="form.address" rows="2" placeholder="Street name, building, RT/RW..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Map Location Picker -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">GPS Location (Click or Drag Marker)</label>
                            <span class="text-xs text-slate-400 font-mono">{{ form.latitude }}, {{ form.longitude }}</span>
                        </div>
                        <div id="customer-map-picker" class="w-full h-48 rounded-xl border border-slate-200 dark:border-slate-700 z-10"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Assigned Staff (Mechanic / Sales)</label>
                            <select v-model="form.assigned_employee_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- No Assigned Staff --</option>
                                <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                    {{ emp.full_name }} ({{ emp.employee_type || 'regular' }})
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700" />
                                <span class="text-sm text-slate-700 dark:text-slate-300 font-medium">Active Customer</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Notes / Instructions</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Special storing instructions, contact person, opening hours..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="formLoading" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm transition flex items-center gap-2">
                            <span v-if="formLoading" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            {{ isEditing ? 'Update Customer' : 'Save Customer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import CSV Modal -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Import Customers CSV</h2>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Upload a CSV file with columns: <br/>
                        <code class="text-blue-500 font-mono text-[11px]">Name, Address, City, Phone, Email, Latitude, Longitude, Type</code>
                    </p>

                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 text-center">
                        <input type="file" accept=".csv,.txt" @change="handleFileUpload" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showImportModal = false" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="button" @click="submitImport" :disabled="!csvFile || importLoading" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm transition flex items-center gap-2 disabled:opacity-50">
                            <span v-if="importLoading" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            Upload & Import
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Delete Customer / Location?"
            :message="`Are you sure you want to delete customer '${deleteTarget?.name}'? This cannot be undone.`"
            confirmText="Delete Customer"
            :loading="deleteLoading"
            @confirm="executeDelete"
            @cancel="showDeleteModal = false"
        />
    </AdminLayout>
</template>
