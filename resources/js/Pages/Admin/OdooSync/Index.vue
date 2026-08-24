<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    settings: Object,
    logs: Object,
});

const form = ref({
    enabled: props.settings.enabled,
    url: props.settings.url || 'https://odoo.hartonomotor-group.com',
    db: props.settings.db || 'odoo_production',
    username: props.settings.username || 'admin',
    password: props.settings.password || '',
    sync_interval: props.settings.sync_interval || 15,
});

const saveLoading = ref(false);
const testLoading = ref(false);
const testResult = ref(null);

function saveSettings() {
    saveLoading.value = true;
    router.post(route('admin.odoo-sync.settings'), form.value, {
        onSuccess: () => { saveLoading.value = false; },
        onError: () => { saveLoading.value = false; }
    });
}

async function runTestConnection() {
    testLoading.value = true;
    testResult.value = null;
    try {
        const res = await fetch(route('admin.odoo-sync.test'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            }
        });
        const data = await res.json();
        testResult.value = data;
    } catch (e) {
        testResult.value = { success: false, message: 'Test connection failed: ' + e.message };
    } finally {
        testLoading.value = false;
    }
}

const syncActionLoading = ref({});

function triggerManualSync(type) {
    syncActionLoading.value[type] = true;
    router.post(route('admin.odoo-sync.trigger'), { type }, {
        onSuccess: () => { syncActionLoading.value[type] = false; },
        onError: () => { syncActionLoading.value[type] = false; }
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Odoo CRM / ERP Sync" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-amber-500/10 text-amber-500 dark:bg-amber-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </span>
                        Odoo CRM / ERP Synchronization
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Bi-directional XML-RPC / JSON-RPC integration linking Attendance field visits, mechanics, and sales canvassing with Odoo.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Configuration Form -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">Connection Settings</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure Odoo external API credentials.</p>
                        </div>

                        <!-- Master Toggle -->
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input v-model="form.enabled" type="checkbox" class="sr-only peer" />
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ form.enabled ? 'Sync Active' : 'Disabled' }}
                            </span>
                        </label>
                    </div>

                    <form @submit.prevent="saveSettings" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Odoo Server URL *</label>
                            <input v-model="form.url" required type="url" placeholder="https://odoo.yourcompany.com" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Database Name *</label>
                                <input v-model="form.db" required type="text" placeholder="odoo_production" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Sync Interval (Minutes)</label>
                                <select v-model="form.sync_interval" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500">
                                    <option :value="5">Every 5 Minutes</option>
                                    <option :value="15">Every 15 Minutes (Default)</option>
                                    <option :value="30">Every 30 Minutes</option>
                                    <option :value="60">Hourly</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">API Username / Login *</label>
                                <input v-model="form.username" required type="text" placeholder="admin@domain.com" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">API Password / Key</label>
                                <input v-model="form.password" type="password" placeholder="Leave blank to keep existing password" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                        </div>

                        <!-- Test result banner -->
                        <div v-if="testResult" :class="['p-3 rounded-xl text-xs font-medium border flex items-start gap-2', testResult.success ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20']">
                            <span>{{ testResult.success ? '✓' : '⚠️' }}</span>
                            <div>
                                <div>{{ testResult.message }}</div>
                                <div v-if="testResult.server_version" class="text-[11px] text-slate-400 mt-0.5">
                                    Odoo Server Version: <strong>{{ testResult.server_version }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                            <button type="button" @click="runTestConnection" :disabled="testLoading" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center gap-2 transition">
                                <span v-if="testLoading" class="w-3.5 h-3.5 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>
                                Test Connection
                            </button>

                            <button type="submit" :disabled="saveLoading" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium text-xs shadow-sm flex items-center gap-2 transition">
                                <span v-if="saveLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right: Manual Triggers Panel -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Manual Sync Triggers</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Trigger individual or full synchronization on demand without waiting for the scheduled cron job.
                    </p>

                    <div class="space-y-2.5 pt-2">
                        <button @click="triggerManualSync('full')" :disabled="syncActionLoading['full']" class="w-full p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 text-amber-700 dark:text-amber-300 font-semibold text-xs text-left flex items-center justify-between hover:bg-amber-100 transition">
                            <div class="flex items-center gap-2">
                                <span>⚡</span> Run Full Sync (All Models)
                            </div>
                            <span v-if="syncActionLoading['full']" class="w-3.5 h-3.5 border-2 border-amber-600 border-t-transparent rounded-full animate-spin"></span>
                        </button>

                        <button @click="triggerManualSync('customers_pull')" :disabled="syncActionLoading['customers_pull']" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-700 dark:text-slate-300 text-xs text-left flex items-center justify-between transition">
                            <span>📥 Pull Customers from Odoo (res.partner)</span>
                            <span v-if="syncActionLoading['customers_pull']" class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>
                        </button>

                        <button @click="triggerManualSync('customers_push')" :disabled="syncActionLoading['customers_push']" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-700 dark:text-slate-300 text-xs text-left flex items-center justify-between transition">
                            <span>📤 Push New Customers to Odoo</span>
                            <span v-if="syncActionLoading['customers_push']" class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>
                        </button>

                        <button @click="triggerManualSync('visits_push')" :disabled="syncActionLoading['visits_push']" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-700 dark:text-slate-300 text-xs text-left flex items-center justify-between transition">
                            <span>📤 Push Field Visits to CRM (crm.lead)</span>
                            <span v-if="syncActionLoading['visits_push']" class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>
                        </button>

                        <button @click="triggerManualSync('employees_pull')" :disabled="syncActionLoading['employees_pull']" class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-700 dark:text-slate-300 text-xs text-left flex items-center justify-between transition">
                            <span>📥 Pull Employees from Odoo (hr.employee)</span>
                            <span v-if="syncActionLoading['employees_pull']" class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sync Logs Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Sync Logs</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Timestamp</th>
                                <th class="px-4 py-3">Operation Type</th>
                                <th class="px-4 py-3">Direction</th>
                                <th class="px-4 py-3 text-center">Processed</th>
                                <th class="px-4 py-3 text-center">Created</th>
                                <th class="px-4 py-3 text-center">Updated</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Error Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-normal text-xs">
                            <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono text-slate-500">
                                    {{ new Date(l.started_at).toLocaleString('id-ID') }}
                                </td>

                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                    {{ l.sync_type.replace('_', ' ').toUpperCase() }}
                                </td>

                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded uppercase text-[10px] font-bold', l.direction === 'pull' ? 'bg-blue-500/10 text-blue-400' : 'bg-purple-500/10 text-purple-400']">
                                        {{ l.direction }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center font-semibold">{{ l.records_processed }}</td>
                                <td class="px-4 py-3 text-center text-emerald-500 font-semibold">{{ l.records_created }}</td>
                                <td class="px-4 py-3 text-center text-blue-500 font-semibold">{{ l.records_updated }}</td>

                                <td class="px-4 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold uppercase', l.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400' : (l.status === 'running' ? 'bg-blue-500/10 text-blue-400' : 'bg-red-500/10 text-red-400')]">
                                        {{ l.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-slate-400 truncate max-w-xs" :title="l.error_message || ''">
                                    {{ l.error_message || '-' }}
                                </td>
                            </tr>

                            <tr v-if="!logs.data || logs.data.length === 0">
                                <td colspan="8" class="text-center py-10 text-slate-400">
                                    No sync logs recorded yet. Use manual triggers or let the scheduled cron run.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                    <Pagination :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
