<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Search,
    UserPlus,
    Shield,
    Key,
    Trash2,
    Edit3,
    Laptop,
    Smartphone,
    Globe,
    LogOut,
    CheckCircle2,
    XCircle,
    X,
    ShieldAlert,
    Clock
} from 'lucide-vue-next';

const props = defineProps({
    users: Object,
    filters: Object,
    sessions: Array,
});

const page = usePage();
const search = ref(props.filters.search);
const roleFilter = ref(props.filters.role);

// Modals state
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingUser = ref(null);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'admin',
    is_active: true,
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'admin',
    is_active: true,
});

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value,
        role: roleFilter.value,
    }, { preserveState: true });
};

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    isCreateModalOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/admin/users', {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (user) => {
    editingUser.value = user;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role || 'admin';
    editForm.is_active = Boolean(user.is_active);
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    if (!editingUser.value) return;
    editForm.put(`/admin/users/${editingUser.value.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
            editingUser.value = null;
        },
    });
};

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete user "${user.name}" (${user.email})?`)) {
        router.delete(`/admin/users/${user.id}`);
    }
};

const revokeSession = (sessionId) => {
    if (confirm('Are you sure you want to revoke this session?')) {
        router.post(`/admin/sessions/${sessionId}/revoke`);
    }
};

const revokeOtherSessions = () => {
    if (confirm('Log out from all other browser devices? You will remain logged in on this browser.')) {
        router.post('/admin/sessions/revoke-others');
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin':
            return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'manager':
            return 'bg-purple-500/10 text-purple-400 border-purple-500/20';
        case 'hr':
            return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        default:
            return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
};
</script>

<template>
    <AdminLayout title="User & Session Management">
        <Head title="User Management" />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <Shield class="w-5 h-5 text-blue-400" />
                    Admin Users & Access Control
                </h2>
                <p class="text-xs text-slate-400">Manage administrator accounts, assign permissions, and supervise active browser sessions.</p>
            </div>
            <button
                @click="openCreateModal"
                class="flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition self-start sm:self-auto"
            >
                <UserPlus class="w-4 h-4" />
                Add New User
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Users List -->
            <div class="lg:col-span-8 space-y-4">
                <!-- Filters Bar -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row gap-3 justify-between shadow-sm">
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Search by name or email..."
                            class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="flex gap-2">
                        <select
                            v-model="roleFilter"
                            @change="applyFilters"
                            class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500 capitalize"
                        >
                            <option value="all">All Roles</option>
                            <option value="admin">Administrator</option>
                            <option value="manager">Manager</option>
                            <option value="hr">HR Personnel</option>
                            <option value="viewer">Read-Only Viewer</option>
                        </select>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/60 uppercase text-[10px] text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3.5">User</th>
                                <th class="px-4 py-3.5">Role</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Created</th>
                                <th class="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-white">
                                            {{ (user.name || 'U').charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-white flex items-center gap-1.5">
                                                {{ user.name }}
                                                <span v-if="user.id === $page.props.auth.user?.id" class="text-[9px] px-1.5 py-0.2 rounded bg-blue-500/20 text-blue-400 font-mono">You</span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-mono">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-semibold border capitalize', getRoleBadgeClass(user.role)]">
                                        {{ user.role || 'admin' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="user.is_active" class="flex items-center gap-1 text-[11px] text-emerald-400 font-semibold">
                                        <CheckCircle2 class="w-3.5 h-3.5" /> Active
                                    </span>
                                    <span v-else class="flex items-center gap-1 text-[11px] text-rose-400 font-semibold">
                                        <XCircle class="w-3.5 h-3.5" /> Disabled
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openEditModal(user)"
                                            class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition"
                                            title="Edit user & password"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            v-if="user.id !== $page.props.auth.user?.id"
                                            @click="deleteUser(user)"
                                            class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition"
                                            title="Delete user"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-500">No user accounts found matching the criteria.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="users.links"
                    :links="users.links"
                    :from="users.from"
                    :to="users.to"
                    :total="users.total"
                />
            </div>

            <!-- Right: Active Browser Sessions -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <Laptop class="w-4 h-4 text-emerald-400" />
                            <h3 class="text-sm font-bold text-white">Active Browser Sessions</h3>
                        </div>
                        <button
                            v-if="sessions && sessions.length > 1"
                            @click="revokeOtherSessions"
                            class="text-[11px] font-semibold text-rose-400 hover:text-rose-300 transition"
                        >
                            Log Out Others
                        </button>
                    </div>

                    <p class="text-xs text-slate-400">
                        Manage devices currently signed into your account. If you notice an unfamiliar device, log out immediately.
                    </p>

                    <div class="space-y-3">
                        <div
                            v-for="session in sessions"
                            :key="session.id"
                            class="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-start justify-between gap-3"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-slate-900 text-slate-400 mt-0.5">
                                    <Globe class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-white font-mono">{{ session.ip_address || '127.0.0.1' }}</span>
                                        <span
                                            v-if="session.is_current_device"
                                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                                        >
                                            This Device
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[180px]" :title="session.user_agent">
                                        {{ session.user_agent }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                        <Clock class="w-3 h-3" />
                                        Last active: {{ session.last_active }}
                                    </div>
                                </div>
                            </div>

                            <button
                                v-if="!session.is_current_device"
                                @click="revokeSession(session.id)"
                                class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                title="Revoke Session"
                            >
                                <LogOut class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div v-if="!sessions || sessions.length === 0" class="py-6 text-center text-xs text-slate-500">
                            No active database sessions found.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-5 relative">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <UserPlus class="w-4 h-4 text-blue-400" />
                        Create New User
                    </h3>
                    <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-white">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Full Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="e.g. Jane Doe"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="createForm.errors.name" class="text-rose-400 text-[10px] mt-1">{{ createForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Email Address</label>
                        <input
                            v-model="createForm.email"
                            type="email"
                            required
                            placeholder="jane@hartonomotor.com"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="createForm.errors.email" class="text-rose-400 text-[10px] mt-1">{{ createForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Password</label>
                        <input
                            v-model="createForm.password"
                            type="password"
                            required
                            placeholder="Minimum 8 characters"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="createForm.errors.password" class="text-rose-400 text-[10px] mt-1">{{ createForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Role / Permissions</label>
                        <select
                            v-model="createForm.role"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500 capitalize"
                        >
                            <option value="admin">Administrator (Full Access)</option>
                            <option value="manager">Manager (Operations & Approvals)</option>
                            <option value="hr">HR Personnel (Leaves & Reports)</option>
                            <option value="viewer">Viewer (Read-Only Logs)</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="create_is_active"
                            v-model="createForm.is_active"
                            class="rounded border-slate-800 bg-slate-950 text-blue-600 focus:ring-0"
                        />
                        <label for="create_is_active" class="text-xs text-slate-300 cursor-pointer">Account is Active</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                        <button
                            type="button"
                            @click="isCreateModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition disabled:opacity-50"
                        >
                            {{ createForm.processing ? 'Creating...' : 'Create Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-5 relative">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <Edit3 class="w-4 h-4 text-blue-400" />
                        Edit User Account
                    </h3>
                    <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-white">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Full Name</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="editForm.errors.name" class="text-rose-400 text-[10px] mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Email Address</label>
                        <input
                            v-model="editForm.email"
                            type="email"
                            required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="editForm.errors.email" class="text-rose-400 text-[10px] mt-1">{{ editForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">
                            Reset Password <span class="text-slate-500 font-normal lowercase">(leave blank to keep current)</span>
                        </label>
                        <input
                            v-model="editForm.password"
                            type="password"
                            placeholder="New password..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                        <p v-if="editForm.errors.password" class="text-rose-400 text-[10px] mt-1">{{ editForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-slate-400 mb-1">Role / Permissions</label>
                        <select
                            v-model="editForm.role"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500 capitalize"
                        >
                            <option value="admin">Administrator (Full Access)</option>
                            <option value="manager">Manager (Operations & Approvals)</option>
                            <option value="hr">HR Personnel (Leaves & Reports)</option>
                            <option value="viewer">Viewer (Read-Only Logs)</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="edit_is_active"
                            v-model="editForm.is_active"
                            class="rounded border-slate-800 bg-slate-950 text-blue-600 focus:ring-0"
                        />
                        <label for="edit_is_active" class="text-xs text-slate-300 cursor-pointer">Account is Active</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                        <button
                            type="button"
                            @click="isEditModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition disabled:opacity-50"
                        >
                            {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
