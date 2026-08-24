<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Clock, Plus, Trash2, Edit2, X } from 'lucide-vue-next';

import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    shifts: Array,
});

const isModalOpen = ref(false);
const editingShift = ref(null);
const deleteModal = ref({
    show: false,
    id: null,
    name: '',
    loading: false,
});

const form = ref({
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    grace_minutes: 15,
    min_work_hours: 8.0,
    overtime_after_hours: 9.0,
    working_days: '1,2,3,4,5',
    is_default: false,
});

const openAddModal = () => {
    editingShift.value = null;
    form.value = {
        name: '',
        start_time: '08:00',
        end_time: '17:00',
        grace_minutes: 15,
        min_work_hours: 8.0,
        overtime_after_hours: 9.0,
        working_days: '1,2,3,4,5',
        is_default: false,
    };
    isModalOpen.value = true;
};

const openEditModal = (shift) => {
    editingShift.value = shift;
    form.value = { ...shift };
    isModalOpen.value = true;
};

const saveShift = () => {
    if (editingShift.value) {
        router.put(`/admin/shifts/${editingShift.value.id}`, form.value, {
            onSuccess: () => isModalOpen.value = false,
        });
    } else {
        router.post('/admin/shifts', form.value, {
            onSuccess: () => isModalOpen.value = false,
        });
    }
};

const confirmDeleteShift = (shift) => {
    deleteModal.value = {
        show: true,
        id: shift.id,
        name: shift.name,
        loading: false,
    };
};

const executeDeleteShift = () => {
    if (!deleteModal.value.id) return;
    deleteModal.value.loading = true;
    router.delete(`/admin/shifts/${deleteModal.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteModal.value.show = false;
            deleteModal.value.loading = false;
        },
    });
};
</script>

<template>
    <AdminLayout title="Shift & Overtime Policies">
        <Head title="Shifts & Overtime" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white">Shift Schedules</h2>
                <p class="text-xs text-slate-400">Manage work hours, grace periods, and overtime policy calculations</p>
            </div>

            <button
                @click="openAddModal"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition"
            >
                <Plus class="w-4 h-4" />
                Add Shift Schedule
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="shift in shifts"
                :key="shift.id"
                class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm relative"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                            <Clock class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white">{{ shift.name }}</h3>
                            <span v-if="shift.is_default" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 font-bold uppercase">Default</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button @click="openEditModal(shift)" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white">
                            <Edit2 class="w-4 h-4" />
                        </button>
                        <button @click="confirmDeleteShift(shift)" class="p-1.5 rounded-lg text-slate-400 hover:bg-rose-500/10 hover:text-rose-400">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs text-slate-300 py-3 border-y border-slate-800/80">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Working Hours:</span>
                        <span class="font-bold text-white font-mono">{{ shift.start_time }} — {{ shift.end_time }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Grace Period:</span>
                        <span class="font-semibold text-emerald-400">{{ shift.grace_minutes }} minutes</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Min Work Hours:</span>
                        <span>{{ shift.min_work_hours }} hrs</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Overtime Threshold:</span>
                        <span>After {{ shift.overtime_after_hours }} hrs</span>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-400">
                    <span>Working Days:</span>
                    <span class="font-mono bg-slate-950 px-2 py-0.5 rounded border border-slate-800 text-slate-300">{{ shift.working_days }}</span>
                </div>
            </div>
        </div>

        <!-- Shift Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-4">
                    {{ editingShift ? 'Edit Shift Schedule' : 'Create Shift Schedule' }}
                </h3>

                <form @submit.prevent="saveShift" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Shift Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Regular Office (08:00 - 17:00)"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Start Time</label>
                            <input
                                v-model="form.start_time"
                                type="time"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">End Time</label>
                            <input
                                v-model="form.end_time"
                                type="time"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Grace Period (Mins)</label>
                            <input
                                v-model.number="form.grace_minutes"
                                type="number"
                                required
                                min="0"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Min Work Hours</label>
                            <input
                                v-model.number="form.min_work_hours"
                                type="number"
                                step="0.5"
                                required
                                min="0"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Overtime Threshold (Hours)</label>
                        <input
                            v-model.number="form.overtime_after_hours"
                            type="number"
                            step="0.5"
                            required
                            min="0"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Working Days (1=Mon, 7=Sun)</label>
                        <input
                            v-model="form.working_days"
                            type="text"
                            placeholder="1,2,3,4,5"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            v-model="form.is_default"
                            id="defaultCheckbox"
                            type="checkbox"
                            class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-0 cursor-pointer"
                        />
                        <label for="defaultCheckbox" class="text-xs text-slate-300 cursor-pointer">
                            Set as Default Shift for new employees
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-500 shadow-lg shadow-blue-600/20 transition"
                        >
                            Save Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Custom Delete Modal -->
        <ConfirmModal
            :show="deleteModal.show"
            :loading="deleteModal.loading"
            title="Delete Shift Schedule"
            :message="`Are you sure you want to delete shift schedule '${deleteModal.name}'?`"
            confirmText="Delete Shift"
            @confirm="executeDeleteShift"
            @cancel="deleteModal.show = false"
        />
    </AdminLayout>
</template>
