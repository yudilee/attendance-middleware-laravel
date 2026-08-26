<script setup>
import { ref, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Smartphone,
    Users,
    Building2,
    Clock,
    FileSpreadsheet,
    Calendar as CalendarIcon,
    AlertCircle,
    History,
    RefreshCw,
    LogOut,
    Menu,
    X,
    ShieldCheck,
    Sun,
    Moon,
    ChevronDown,
    User,
    MapPin,
    Briefcase,
    CheckSquare,
    Layers,
    Sliders
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const page = usePage();
const isSidebarOpen = ref(true);
const syncing = ref(false);
const isDarkMode = ref(true);

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDarkMode.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
    }
});

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const logout = () => {
    if (confirm('Are you sure you want to sign out?')) {
        router.post('/logout');
    }
};

const navigationGroups = [
    {
        title: 'Core Operations',
        items: [
            { name: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard, current: page.url.startsWith('/admin/dashboard') },
            { name: 'Attendance Reports', href: '/admin/reports', icon: FileSpreadsheet, current: page.url.startsWith('/admin/reports') },
        ]
    },
    {
        title: 'Field Operations',
        items: [
            { name: 'Customer Registry', href: '/admin/customers', icon: Building2, current: page.url.startsWith('/admin/customers') },
            { name: 'Field Visits Tracker', href: '/admin/field-visits', icon: MapPin, current: page.url.startsWith('/admin/field-visits') },
            { name: 'Tasks & Canvass Plans', href: '/admin/field-tasks', icon: CheckSquare, current: page.url.startsWith('/admin/field-tasks') },
        ]
    },
    {
        title: 'Organization',
        items: [
            { name: 'Employees', href: '/admin/employees', icon: Users, current: page.url.startsWith('/admin/employees') },
            { name: 'Branches & Geofence', href: '/admin/branches', icon: Building2, current: page.url.startsWith('/admin/branches') },
            { name: 'Shifts & Overtime', href: '/admin/shifts', icon: Clock, current: page.url.startsWith('/admin/shifts') },
            { name: 'Company Holidays', href: '/admin/holidays', icon: CalendarIcon, current: page.url.startsWith('/admin/holidays') },
        ]
    },
    {
        title: 'Approvals & Integrations',
        items: [
            { name: 'System Settings', href: '/admin/settings', icon: Sliders, current: page.url.startsWith('/admin/settings') },
            { name: 'User Management', href: '/admin/users', icon: ShieldCheck, current: page.url.startsWith('/admin/users') },
            { name: 'Devices & QR', href: '/admin/devices', icon: Smartphone, current: page.url.startsWith('/admin/devices') },
            { name: 'Leave Requests', href: '/admin/leaves', icon: CalendarIcon, current: page.url.startsWith('/admin/leaves') },
            { name: 'Punch Corrections', href: '/admin/corrections', icon: AlertCircle, current: page.url.startsWith('/admin/corrections') },
            { name: 'Odoo CRM Sync', href: '/admin/odoo-sync', icon: Layers, current: page.url.startsWith('/admin/odoo-sync') },
            { name: 'Audit Trail', href: '/admin/audit-logs', icon: History, current: page.url.startsWith('/admin/audit-logs') },
        ]
    }
];

const syncAdms = () => {
    syncing.value = true;
    router.post('/admin/sync-adms', {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
        onError: () => {
            syncing.value = false;
        },
    });
};
</script>

<template>
    <div :class="['min-h-screen font-sans flex transition-colors duration-200', isDarkMode ? 'bg-slate-950 text-slate-100 dark' : 'bg-slate-50 text-slate-900']">
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="isSidebarOpen"
            class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40 md:hidden transition-opacity duration-300"
            @click="isSidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 border-r backdrop-blur-xl transition-all duration-300 md:static md:translate-x-0 flex flex-col',
                isDarkMode ? 'bg-slate-900/95 border-slate-800' : 'bg-white border-slate-200/90 shadow-sm',
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Brand Header -->
            <div :class="['h-16 flex items-center justify-between px-5 border-b shrink-0', isDarkMode ? 'border-slate-800/80 bg-slate-900/40' : 'border-slate-200/80 bg-white']">
                <div class="flex items-center gap-3">
                    <div :class="[
                        'w-10 h-10 rounded-xl p-1.5 flex items-center justify-center border transition-all duration-200 shadow-sm relative overflow-hidden',
                        isDarkMode ? 'bg-slate-900 border-slate-700/80 shadow-teal-500/10' : 'bg-white border-slate-200 shadow-slate-200/50'
                    ]">
                        <img src="/images/favicon-64.png" alt="Company Logo" class="w-full h-full object-contain" />
                        <div class="absolute inset-0 bg-gradient-to-tr from-teal-500/10 to-transparent pointer-events-none"></div>
                    </div>
                    <div>
                        <span :class="['font-extrabold text-[15px] tracking-tight leading-none block', isDarkMode ? 'text-white' : 'text-slate-900']">
                            Attendance HRM
                        </span>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                            <span class="text-[9px] text-teal-600 dark:text-teal-400 font-extrabold tracking-widest uppercase">Enterprise Pro</span>
                        </div>
                    </div>
                </div>
                <button
                    @click="isSidebarOpen = false"
                    class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    title="Close Sidebar"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-3 space-y-4 flex-1 overflow-y-auto sidebar-scroll">
                <div v-for="group in navigationGroups" :key="group.title" class="space-y-1">
                    <div class="px-3 pt-1 pb-1 flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ group.title }}
                        </span>
                    </div>

                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-200 group relative',
                                item.current
                                    ? (isDarkMode
                                        ? 'bg-gradient-to-r from-blue-600/20 via-indigo-600/10 to-transparent text-blue-400 border border-blue-500/30 shadow-xs before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:bg-blue-500 before:rounded-r-full'
                                        : 'bg-gradient-to-r from-blue-50 to-indigo-50/50 text-blue-600 border border-blue-200/80 shadow-xs before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:bg-blue-600 before:rounded-r-full')
                                    : (isDarkMode
                                        ? 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100 hover:translate-x-0.5'
                                        : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900 hover:translate-x-0.5')
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'w-4 h-4 shrink-0 transition-colors duration-200',
                                    item.current
                                        ? (isDarkMode ? 'text-blue-400' : 'text-blue-600')
                                        : (isDarkMode ? 'text-slate-500 group-hover:text-slate-300' : 'text-slate-400 group-hover:text-slate-700')
                                ]"
                            />
                            <span class="truncate">{{ item.name }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Bottom Actions / ADMS Engine Card -->
            <div :class="['p-3 border-t shrink-0 space-y-2', isDarkMode ? 'border-slate-800/80 bg-slate-900/70' : 'border-slate-200/90 bg-slate-50/70']">
                <!-- Sync Trigger Button -->
                <button
                    @click="syncAdms"
                    :disabled="syncing"
                    :class="[
                        'w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold border transition duration-200 disabled:opacity-50 group',
                        isDarkMode
                            ? 'bg-slate-800/80 hover:bg-slate-800 text-slate-200 border-slate-700/60 hover:border-slate-600'
                            : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-xs'
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <RefreshCw :class="['w-3.5 h-3.5 text-blue-500', syncing ? 'animate-spin' : 'group-hover:rotate-45 transition-transform duration-300']" />
                        <span>{{ syncing ? 'Syncing ADMS...' : 'Sync ADMS Feed' }}</span>
                    </div>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-xs shadow-emerald-500/50"></span>
                </button>

                <!-- Sign Out -->
                <button
                    @click="logout"
                    :class="[
                        'w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition duration-200',
                        isDarkMode ? '' : 'bg-transparent'
                    ]"
                    title="Sign Out from Admin Panel"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    Sign Out
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Header -->
            <header :class="['h-16 border-b backdrop-blur px-6 flex items-center justify-between sticky top-0 z-40', isDarkMode ? 'bg-slate-900/70 border-slate-800/80' : 'bg-white/80 border-slate-200 shadow-xs']">
                <div class="flex items-center gap-4">
                    <button
                        @click="isSidebarOpen = !isSidebarOpen"
                        class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        <Menu class="w-5 h-5" />
                    </button>
                    <h1 :class="['text-base font-bold tracking-tight', isDarkMode ? 'text-white' : 'text-slate-900']">{{ title || 'Admin Portal' }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Dark / Light Mode Toggle Button -->
                    <button
                        @click="toggleTheme"
                        :class="[
                            'p-2 rounded-xl border transition flex items-center gap-1.5 text-xs font-medium',
                            isDarkMode
                                ? 'bg-slate-800/80 hover:bg-slate-700 text-amber-300 border-slate-700'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300 shadow-xs'
                        ]"
                        :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <Sun v-if="isDarkMode" class="w-4 h-4 text-amber-400" />
                        <Moon v-else class="w-4 h-4 text-slate-700" />
                        <span class="hidden sm:inline text-[11px] font-semibold">{{ isDarkMode ? 'Dark' : 'Light' }}</span>
                    </button>

                    <!-- Status indicator -->
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline">ADMS Online</span>
                    </div>

                    <!-- User Profile & Sign Out Button -->
                    <div :class="['flex items-center gap-2.5 pl-3 border-l', isDarkMode ? 'border-slate-800' : 'border-slate-200']">
                        <Link
                            href="/admin/users"
                            class="flex items-center gap-2 hover:opacity-80 transition group"
                            title="Manage Profile & Users"
                        >
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                                {{ ($page.props.auth.user?.name || 'A').charAt(0).toUpperCase() }}
                            </div>
                            <div class="hidden md:block text-left">
                                <span :class="['block text-xs font-semibold leading-tight group-hover:text-blue-500 transition', isDarkMode ? 'text-slate-200' : 'text-slate-800']">
                                    {{ $page.props.auth.user?.name || 'Administrator' }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-mono capitalize leading-tight">
                                    {{ $page.props.auth.user?.role || 'Admin' }}
                                </span>
                            </div>
                        </Link>
                        <button
                            @click="logout"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 transition ml-1"
                            title="Sign Out"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Body -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Flash Messages -->
                <div v-if="$page.props.flash?.success" class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                    <span>{{ $page.props.flash.success }}</span>
                </div>
                <div v-if="$page.props.flash?.warning" class="mb-4 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                    <span>{{ $page.props.flash.warning }}</span>
                </div>
                <div v-if="$page.props.flash?.info" class="mb-4 p-4 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-600 dark:text-blue-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                    <span>{{ $page.props.flash.info }}</span>
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-semibold flex items-center justify-between shadow-xs">
                    <span>{{ $page.props.flash.error }}</span>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
