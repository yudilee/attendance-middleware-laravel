<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import {
    Calendar as CalendarIcon,
    Plus,
    Trash2,
    Sparkles,
    CheckCircle2,
    RotateCcw,
    X,
    Clock,
    Flag,
    Loader2
} from 'lucide-vue-next';

const props = defineProps({
    holidays: Array,
    year: Number,
    stats: Object,
});

const isAddModalOpen = ref(false);
const importing = ref(false);
const deleteModal = ref({
    show: false,
    id: null,
    name: '',
    loading: false,
});

const form = ref({
    name: '',
    date: '',
    is_recurring: false,
});

const openAddModal = () => {
    form.value = {
        name: '',
        date: '',
        is_recurring: false,
    };
    isAddModalOpen.value = true;
};

const saveHoliday = () => {
    router.post('/admin/holidays', form.value, {
        onSuccess: () => isAddModalOpen.value = false,
    });
};

const confirmDelete = (holiday) => {
    deleteModal.value = {
        show: true,
        id: holiday.id,
        name: holiday.name,
        loading: false,
    };
};

const executeDelete = () => {
    if (!deleteModal.value.id) return;
    deleteModal.value.loading = true;
    router.delete(`/admin/holidays/${deleteModal.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteModal.value.show = false;
            deleteModal.value.loading = false;
        },
    });
};

const importNational = () => {
    importing.value = true;
    router.post('/admin/holidays/import-national', { year: props.year || 2026 }, {
        preserveScroll: true,
        onFinish: () => importing.value = false,
    });
};
</script>

<template>
    <AdminLayout title="Company Holidays Calendar">
        <Head title="Holidays Calendar" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <CalendarIcon class="w-5 h-5 text-indigo-400" />
                    Company &amp; National Holidays
                </h2>
                <p class="text-xs text-slate-400">Configure holidays to auto-exclude dates from absence and late calculation</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="importNational"
                    :disabled="importing"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 border border-indigo-500/30 text-indigo-300 text-xs font-semibold transition disabled:opacity-50"
                >
                    <Loader2 v-if="importing" class="w-4 h-4 text-indigo-400 animate-spin" />
                    <Flag v-else class="w-4 h-4 text-indigo-400" />
                    <span>{{ importing ? 'Importing Holidays...' : 'Import National Holidays' }}</span>
                </button>

                <button
                    @click="openAddModal"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition"
                >
                    <Plus class="w-4 h-4" />
                    Add Holiday
                </button>
            </div>
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Total Configured</span>
                    <h3 class="text-2xl font-bold text-white font-mono mt-1">{{ stats.total_holidays }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <CalendarIcon class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Upcoming This Year</span>
                    <h3 class="text-2xl font-bold text-emerald-400 font-mono mt-1">{{ stats.upcoming_holidays }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <Clock class="w-5 h-5" />
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase font-semibold">Active Calendar Year</span>
                    <h3 class="text-2xl font-bold text-blue-400 font-mono mt-1">{{ year }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                    <Sparkles class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Holidays Roster -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Date</th>
                        <th class="px-5 py-3.5">Holiday Name</th>
                        <th class="px-5 py-3.5">Recurrence</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="h in holidays" :key="h.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-mono text-xs font-semibold text-blue-400">
                            {{ h.formatted_date }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-white">
                            {{ h.name }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span v-if="h.is_recurring" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                🔄 Repeats Yearly
                            </span>
                            <span v-else class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                One-Time
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                @click="confirmDelete(h)"
                                class="p-1.5 rounded-lg text-slate-400 hover:bg-rose-500/20 hover:text-rose-400 transition"
                                title="Delete Holiday"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="holidays.length === 0">
                        <td colspan="4" class="px-5 py-8 text-center text-slate-500 text-xs">
                            No holidays configured for {{ year }}. Click 'Import National Holidays' to populate automatically.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add Holiday Modal -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                <button @click="isAddModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <X class="w-5 h-5" />
                </button>

                <h3 class="text-base font-bold text-white mb-4">Add Holiday</h3>

                <form @submit.prevent="saveHoliday" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Holiday Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Cuti Bersama"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Holiday Date</label>
                        <input
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            v-model="form.is_recurring"
                            id="recurringCheckbox"
                            type="checkbox"
                            class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-0 cursor-pointer"
                        />
                        <label for="recurringCheckbox" class="text-xs text-slate-300 cursor-pointer">
                            Repeats every year on this day
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3">
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
                            Save Holiday
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Custom Delete Confirm Modal -->
        <ConfirmModal
            :show="deleteModal.show"
            :loading="deleteModal.loading"
            title="Delete Holiday"
            :message="`Are you sure you want to remove '${deleteModal.name}' from the holiday calendar?`"
            confirmText="Delete Holiday"
            @confirm="executeDelete"
            @cancel="deleteModal.show = false"
        />
    </AdminLayout>
</template>
