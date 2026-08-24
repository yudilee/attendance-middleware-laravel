<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ShieldCheck, Search, ShieldAlert, History } from 'lucide-vue-next';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

const applyFilters = () => {
    router.get('/admin/audit-logs', {
        search: search.value,
    }, { preserveState: true });
};
</script>

<template>
    <AdminLayout title="System Audit Logs">
        <Head title="Audit Trail" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <History class="w-5 h-5 text-indigo-400" />
                    Security &amp; Administrator Audit Trail
                </h2>
                <p class="text-xs text-slate-400">Immutable ledger tracking administrative actions, approvals, and system mutations</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex gap-4">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by Admin User, Action, Target, or Details..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Timestamp</th>
                        <th class="px-5 py-3.5">Admin User</th>
                        <th class="px-5 py-3.5">Action</th>
                        <th class="px-5 py-3.5">Target</th>
                        <th class="px-5 py-3.5">Details</th>
                        <th class="px-5 py-3.5">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-4 font-mono text-xs text-slate-300">
                            {{ log.created_at }}
                        </td>
                        <td class="px-5 py-4 font-bold text-white text-xs">
                            {{ log.admin_username }}
                        </td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-xs font-mono text-slate-300">
                            {{ log.target_type }} #{{ log.target_id || '-' }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-400 max-w-[250px] truncate" :title="log.details">
                            {{ log.details || '-' }}
                        </td>
                        <td class="px-5 py-4 font-mono text-xs text-slate-500">
                            {{ log.ip_address || '127.0.0.1' }}
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-5 py-8 text-center text-slate-500 text-xs">
                            No audit log records found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                :links="logs.links"
                :from="logs.from"
                :to="logs.to"
                :total="logs.total"
            />
        </div>
    </AdminLayout>
</template>
