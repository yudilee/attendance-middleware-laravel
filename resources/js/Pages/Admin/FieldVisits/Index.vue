<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Chart, registerables } from 'chart.js';
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

Chart.register(...registerables);

const props = defineProps({
    visits: Object,
    activeVisits: Array,
    customerPins: Array,
    employees: Array,
    kpis: Object,
    chartData: Object,
    filters: Object,
});

const currentTab = ref('map'); // 'map', 'history', 'analytics'

// Filter states
const employeeFilter = ref(props.filters.employee_id || '');
const typeFilter = ref(props.filters.visit_type || '');
const statusFilter = ref(props.filters.status || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

function applyFilters() {
    router.get(route('admin.field-visits'), {
        employee_id: employeeFilter.value || undefined,
        visit_type: typeFilter.value || undefined,
        status: statusFilter.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function setDatePreset(preset) {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        startDate.value = formatDate(today);
        endDate.value = formatDate(today);
    } else if (preset === 'yesterday') {
        const y = new Date();
        y.setDate(y.getDate() - 1);
        startDate.value = formatDate(y);
        endDate.value = formatDate(y);
    } else if (preset === '7days') {
        const d = new Date();
        d.setDate(d.getDate() - 6);
        startDate.value = formatDate(d);
        endDate.value = formatDate(today);
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startDate.value = formatDate(firstDay);
        endDate.value = formatDate(today);
    }
    applyFilters();
}

// Expand visit row for photos / details
const expandedVisitId = ref(null);
function toggleExpand(id) {
    expandedVisitId.value = expandedVisitId.value === id ? null : id;
}

// ── GPS Route Trajectory Modal & Visualizer ──────────────────────────────────
const routeModalOpen = ref(false);
const selectedRouteVisit = ref(null);
const routeLoading = ref(false);
const routeStats = ref({
    distanceKm: 0,
    durationMinutes: 0,
    avgSpeed: 0,
    waypointsCount: 0,
    isMock: false,
});

let routeMapInstance = null;
let routePolylineLayer = null;

async function openRouteModal(visit) {
    selectedRouteVisit.value = visit;
    routeModalOpen.value = true;
    routeLoading.value = true;

    try {
        const res = await fetch(`/admin/field-visits/${visit.id}/breadcrumbs`);
        const data = await res.json();
        const bcs = data.breadcrumbs || [];

        // Compute speed stats
        let totalSpeed = 0;
        let speedCount = 0;
        bcs.forEach(b => {
            if (b.speed && b.speed > 0) {
                totalSpeed += b.speed;
                speedCount++;
            }
        });
        const avgSpeedKmh = speedCount > 0 ? (totalSpeed / speedCount) : 0;

        routeStats.value = {
            distanceKm: data.total_distance_km || visit.total_distance_km || 0,
            durationMinutes: visit.duration_minutes || 0,
            avgSpeed: Math.round(avgSpeedKmh * 10) / 10,
            waypointsCount: bcs.length,
            isMock: visit.is_mock_location || false,
        };

        renderRouteMap(visit, bcs);
    } catch (e) {
        console.error('Failed to load route breadcrumbs', e);
    } finally {
        routeLoading.value = false;
    }
}

function closeRouteModal() {
    routeModalOpen.value = false;
    selectedRouteVisit.value = null;
    if (routeMapInstance) {
        routeMapInstance.remove();
        routeMapInstance = null;
    }
}

function renderRouteMap(visit, breadcrumbs) {
    nextTick(() => {
        const container = document.getElementById('route-trajectory-map');
        if (!container) return;

        if (routeMapInstance) {
            routeMapInstance.remove();
            routeMapInstance = null;
        }

        if (typeof L === 'undefined') return;

        const defaultLat = visit.check_in_lat || -6.2088;
        const defaultLng = visit.check_in_lng || 106.8456;

        routeMapInstance = L.map('route-trajectory-map').setView([defaultLat, defaultLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(routeMapInstance);

        const latlngs = [];

        // 1. Start Check-In Marker
        if (visit.check_in_lat && visit.check_in_lng) {
            latlngs.push([visit.check_in_lat, visit.check_in_lng]);
            const startIcon = L.divIcon({
                html: `<div class="w-8 h-8 rounded-full bg-emerald-600 border-2 border-white shadow-xl flex items-center justify-center text-white text-xs font-bold ring-4 ring-emerald-500/30">🟢</div>`,
                className: 'custom-start-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            });
            const checkInTime = new Date(visit.check_in_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            L.marker([visit.check_in_lat, visit.check_in_lng], { icon: startIcon })
                .bindPopup(`
                    <div class="p-1 text-xs">
                        <strong class="text-emerald-700 font-bold">🟢 Start: Check-In</strong>
                        <div class="text-slate-600 mt-0.5">${checkInTime}</div>
                        <div class="font-mono text-[10px] text-slate-400 mt-1">${visit.check_in_lat}, ${visit.check_in_lng}</div>
                    </div>
                `)
                .addTo(routeMapInstance);
        }

        // 2. Breadcrumbs path
        breadcrumbs.forEach((b, idx) => {
            latlngs.push([b.latitude, b.longitude]);

            // Add circle waypoint markers
            const waypointTime = new Date(b.recorded_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const speedText = b.speed ? `${Math.round(b.speed)} km/h` : '-';

            const waypointMarker = L.circleMarker([b.latitude, b.longitude], {
                radius: 4,
                color: '#2563eb',
                fillColor: '#60a5fa',
                fillOpacity: 0.9,
                weight: 2,
            });

            waypointMarker.bindPopup(`
                <div class="p-1 text-xs">
                    <div class="font-bold text-slate-900">Waypoint #${idx + 1}</div>
                    <div class="text-slate-600 mt-0.5">Time: <strong>${waypointTime}</strong></div>
                    <div class="text-slate-600">Speed: <strong>${speedText}</strong></div>
                    <div class="font-mono text-[10px] text-slate-400 mt-1">${b.latitude.toFixed(5)}, ${b.longitude.toFixed(5)}</div>
                </div>
            `);
            waypointMarker.addTo(routeMapInstance);
        });

        // 3. End Check-Out Marker
        if (visit.check_out_lat && visit.check_out_lng) {
            latlngs.push([visit.check_out_lat, visit.check_out_lng]);
            const endIcon = L.divIcon({
                html: `<div class="w-8 h-8 rounded-full bg-red-600 border-2 border-white shadow-xl flex items-center justify-center text-white text-xs font-bold ring-4 ring-red-500/30">🏁</div>`,
                className: 'custom-end-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            });
            const checkOutTime = new Date(visit.check_out_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            L.marker([visit.check_out_lat, visit.check_out_lng], { icon: endIcon })
                .bindPopup(`
                    <div class="p-1 text-xs">
                        <strong class="text-red-700 font-bold">🏁 Finish: Check-Out</strong>
                        <div class="text-slate-600 mt-0.5">${checkOutTime}</div>
                        <div class="text-slate-600">Duration: <strong>${visit.duration_minutes || '-'} mins</strong></div>
                        <div class="font-mono text-[10px] text-slate-400 mt-1">${visit.check_out_lat}, ${visit.check_out_lng}</div>
                    </div>
                `)
                .addTo(routeMapInstance);
        }

        // Draw Polyline
        if (latlngs.length >= 2) {
            L.polyline(latlngs, {
                color: '#0284c7', // Sky-600 vibrant blue
                weight: 5,
                opacity: 0.85,
                smoothFactor: 1,
            }).addTo(routeMapInstance);

            routeMapInstance.fitBounds(latlngs, { padding: [40, 40], maxZoom: 15 });
        }

        setTimeout(() => {
            if (routeMapInstance) routeMapInstance.invalidateSize();
        }, 300);
    });
}

// Leaflet Map logic
let mapInstance = null;
let markersLayer = null;
let autoRefreshTimer = null;

function initLiveMap() {
    nextTick(() => {
        const container = document.getElementById('field-live-map');
        if (!container) return;

        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }

        if (typeof L === 'undefined') return;

        // Default to Jakarta
        mapInstance = L.map('field-live-map').setView([-6.2088, 106.8456], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(mapInstance);

        markersLayer = L.layerGroup().addTo(mapInstance);
        renderMapMarkers();

        // Auto refresh map every 60s
        autoRefreshTimer = setInterval(() => {
            fetchLiveVisits();
        }, 60000);

        setTimeout(() => {
            mapInstance.invalidateSize();
        }, 200);
    });
}

function renderMapMarkers() {
    if (!markersLayer || typeof L === 'undefined') return;
    markersLayer.clearLayers();

    const bounds = [];

    // 1. Customer locations (Gray / Blue dots)
    props.customerPins.forEach(c => {
        if (!c.latitude || !c.longitude) return;
        const iconHtml = `<div class="w-6 h-6 rounded-full bg-slate-700 border-2 border-white shadow-md flex items-center justify-center text-white text-[10px]">🏢</div>`;
        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-customer-icon',
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        });

        const m = L.marker([c.latitude, c.longitude], { icon: customIcon })
            .bindPopup(`
                <div class="p-1 text-xs">
                    <strong class="font-bold text-slate-900">${c.name}</strong>
                    <div class="text-slate-500 capitalize">${c.customer_type} • ${c.city || ''}</div>
                    <div class="text-blue-600 mt-1">${c.phone || ''}</div>
                </div>
            `);
        markersLayer.addLayer(m);
        bounds.push([c.latitude, c.longitude]);
    });

    // 2. Active field visits (Live workers)
    props.activeVisits.forEach(v => {
        if (!v.check_in_lat || !v.check_in_lng) return;
        const isMechanic = v.visit_type === 'storing' || (v.employee && v.employee.employee_type === 'mechanic');
        const iconBg = isMechanic ? 'bg-blue-600 animate-pulse' : 'bg-emerald-600 animate-pulse';
        const iconEmoji = isMechanic ? '🔧' : '💼';

        const iconHtml = `<div class="w-8 h-8 rounded-full ${iconBg} border-2 border-white shadow-lg flex items-center justify-center text-white text-sm font-bold ring-4 ring-blue-500/20">${iconEmoji}</div>`;
        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-worker-icon',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        });

        const empName = v.employee ? v.employee.full_name : v.employee_id;
        const customerName = v.customer ? v.customer.name : 'Unknown Location';
        const checkInTime = new Date(v.check_in_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

        const m = L.marker([v.check_in_lat, v.check_in_lng], { icon: customIcon })
            .bindPopup(`
                <div class="p-1 text-xs">
                    <div class="font-bold text-slate-900 flex items-center gap-1">
                        <span>${empName}</span>
                        <span class="text-[10px] px-1 py-0.2 rounded bg-blue-100 text-blue-700 uppercase font-semibold">${v.visit_type}</span>
                    </div>
                    <div class="text-slate-600 mt-1">📍 ${customerName}</div>
                    <div class="text-slate-500 text-[11px] mt-0.5">Checked in: <strong>${checkInTime}</strong></div>
                    <div class="text-slate-500 italic mt-1">${v.purpose || 'Field Visit'}</div>
                </div>
            `);
        markersLayer.addLayer(m);
        bounds.push([v.check_in_lat, v.check_in_lng]);
    });

    if (bounds.length > 0 && mapInstance) {
        mapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }
}

async function fetchLiveVisits() {
    try {
        const res = await fetch('/admin/field-visits/live');
        const data = await res.json();
        // Update markers Layer
    } catch (e) {
        console.error('Failed to refresh live visits', e);
    }
}

// Chart.js Analytics
let chartInstance = null;
function initAnalyticsChart() {
    nextTick(() => {
        const ctx = document.getElementById('fieldVisitsChart');
        if (!ctx) return;

        if (chartInstance) {
            chartInstance.destroy();
        }

        const isDark = document.documentElement.classList.contains('dark');

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: props.chartData.labels,
                datasets: [
                    {
                        label: 'Mechanic Storing',
                        data: props.chartData.storing,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderRadius: 6,
                    },
                    {
                        label: 'Sales Canvassing',
                        data: props.chartData.canvassing,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: isDark ? '#94a3b8' : '#64748b', font: { family: 'Inter', size: 12 } }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: isDark ? '#94a3b8' : '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)' },
                        ticks: { precision: 0, color: isDark ? '#94a3b8' : '#64748b' }
                    }
                }
            }
        });
    });
}

function handleTabChange(tab) {
    currentTab.value = tab;
    if (tab === 'map') {
        initLiveMap();
    } else if (tab === 'analytics') {
        initAnalyticsChart();
    }
}

onMounted(() => {
    initLiveMap();
});

onUnmounted(() => {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    if (mapInstance) mapInstance.remove();
    if (chartInstance) chartInstance.destroy();
});

function exportCsvUrl() {
    const params = new URLSearchParams({
        employee_id: employeeFilter.value || '',
        visit_type: typeFilter.value || '',
        status: statusFilter.value || '',
        start_date: startDate.value || '',
        end_date: endDate.value || '',
    });
    return route('admin.field-visits.export') + '?' + params.toString();
}
</script>

<template>
    <AdminLayout>
        <Head title="Field Visits Live Tracker" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 dark:bg-emerald-500/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                        </span>
                        Field Visits & Canvassing Tracker
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Real-time GPS tracking of external mechanic storing dispatches and sales customer canvassing visits.
                    </p>
                </div>

                <!-- Tab switcher -->
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                    <button @click="handleTabChange('map')" :class="['px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5', currentTab === 'map' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
                        <span>🗺️</span> Live Map
                    </button>
                    <button @click="handleTabChange('history')" :class="['px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5', currentTab === 'history' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
                        <span>📋</span> Visit History
                    </button>
                    <button @click="handleTabChange('analytics')" :class="['px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5', currentTab === 'analytics' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
                        <span>📊</span> Analytics
                    </button>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Visits Today</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ kpis.total_today }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                    <div class="text-xs font-medium text-emerald-500">Completed Visits</div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ kpis.completed_today }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                    <div class="text-xs font-medium text-blue-500 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span>
                        Active in Field Now
                    </div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ kpis.active_now }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Avg Duration Today</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ kpis.avg_duration }} <span class="text-sm font-normal text-slate-400">mins</span></div>
                </div>
            </div>

            <!-- TAB 1: LIVE MAP -->
            <div v-show="currentTab === 'map'" class="space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-4 text-xs font-medium text-slate-600 dark:text-slate-400">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-600"></span> 🔧 Mechanic Storing</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-600"></span> 💼 Sales Canvassing</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-slate-700"></span> 🏢 Customer / Dealer</span>
                        </div>
                        <div class="text-xs text-slate-400">Auto-refresh every 60s</div>
                    </div>
                    <div id="field-live-map" class="w-full h-[540px] rounded-xl border border-slate-200 dark:border-slate-800 z-10"></div>
                </div>
            </div>

            <!-- TAB 2: VISIT HISTORY -->
            <div v-show="currentTab === 'history'" class="space-y-4">
                <!-- Filters & Date Presets -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-2">Presets:</span>
                            <button @click="setDatePreset('today')" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Today</button>
                            <button @click="setDatePreset('yesterday')" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Yesterday</button>
                            <button @click="setDatePreset('7days')" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Last 7 Days</button>
                            <button @click="setDatePreset('month')" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">This Month</button>
                        </div>
                        <a :href="exportCsvUrl()" target="_blank" class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-xs flex items-center gap-1.5 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export CSV
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Staff Member</label>
                            <select v-model="employeeFilter" @change="applyFilters" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none">
                                <option value="">All Staff</option>
                                <option v-for="emp in employees" :key="emp.employee_id" :value="emp.employee_id">
                                    {{ emp.full_name }} ({{ emp.employee_type || 'regular' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Visit Type</label>
                            <select v-model="typeFilter" @change="applyFilters" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none">
                                <option value="">All Types</option>
                                <option value="storing">Mechanic Storing</option>
                                <option value="canvassing">Sales Canvassing</option>
                                <option value="delivery">Delivery</option>
                                <option value="service">Service / Repair</option>
                                <option value="survey">Survey / Inspection</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Status</label>
                            <select v-model="statusFilter" @change="applyFilters" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none">
                                <option value="">All Statuses</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Start Date</label>
                            <input v-model="startDate" type="date" @change="applyFilters" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">End Date</label>
                            <input v-model="endDate" type="date" @change="applyFilters" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white outline-none" />
                        </div>
                    </div>
                </div>

                <!-- Visits Table -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="px-4 py-3.5">Employee</th>
                                    <th class="px-4 py-3.5">Customer / Destination</th>
                                    <th class="px-4 py-3.5">Type</th>
                                    <th class="px-4 py-3.5">Check-In</th>
                                    <th class="px-4 py-3.5">Check-Out</th>
                                    <th class="px-4 py-3.5 text-center">Duration</th>
                                    <th class="px-4 py-3.5 text-center">Distance</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                    <th class="px-4 py-3.5 text-center">Photos</th>
                                    <th class="px-4 py-3.5 text-right">Route & Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-normal">
                                <template v-for="v in visits.data" :key="v.id">
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                        <td class="px-4 py-3.5">
                                             <div class="font-semibold text-slate-900 dark:text-white">
                                                {{ v.employee ? v.employee.full_name : v.employee_id }}
                                            </div>
                                            <div class="text-xs text-slate-400">PIN: {{ v.employee_id }}</div>
                                        </td>

                                        <td class="px-4 py-3.5">
                                            <div class="font-medium text-slate-800 dark:text-slate-200">
                                                {{ v.customer ? v.customer.name : 'External Location' }}
                                            </div>
                                            <div class="text-xs text-slate-400 truncate max-w-xs">{{ v.purpose || 'No purpose stated' }}</div>
                                        </td>

                                        <td class="px-4 py-3.5">
                                            <span :class="['px-2 py-0.5 rounded-lg text-xs font-semibold border capitalize', v.visit_type === 'storing' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20']">
                                                {{ v.visit_type }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3.5">
                                            <div class="font-medium text-slate-800 dark:text-slate-200">
                                                {{ new Date(v.check_in_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                            <div class="text-[11px] text-slate-400">{{ new Date(v.check_in_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) }}</div>
                                        </td>

                                        <td class="px-4 py-3.5">
                                            <div v-if="v.check_out_at" class="font-medium text-slate-800 dark:text-slate-200">
                                                {{ new Date(v.check_out_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                            <span v-else class="text-xs text-amber-500 font-medium italic">Ongoing</span>
                                        </td>

                                        <td class="px-4 py-3.5 text-center">
                                            <span v-if="v.duration_minutes !== null" class="font-semibold text-slate-900 dark:text-white">
                                                {{ v.duration_minutes }}m
                                            </span>
                                            <span v-else class="text-slate-400">-</span>
                                        </td>

                                        <td class="px-4 py-3.5 text-center">
                                            <span v-if="v.total_distance_km !== null" class="font-semibold text-blue-600 dark:text-blue-400">
                                                {{ v.total_distance_km }} <span class="text-[11px] font-normal text-slate-400">km</span>
                                            </span>
                                            <span v-else class="text-slate-400">-</span>
                                        </td>

                                        <td class="px-4 py-3.5 text-center">
                                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', v.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400' : (v.status === 'in_progress' ? 'bg-blue-500/10 text-blue-400' : 'bg-slate-500/10 text-slate-400')]">
                                                {{ v.status.replace('_', ' ') }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3.5 text-center">
                                            <span class="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                                                📷 {{ v.photos ? v.photos.length : 0 }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3.5 text-right space-x-2">
                                            <button @click="openRouteModal(v)" class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-xs font-semibold inline-flex items-center gap-1 transition">
                                                <span>🗺️</span> Route
                                            </button>
                                            <button @click="toggleExpand(v.id)" class="text-xs text-slate-500 dark:text-slate-400 hover:underline font-medium">
                                                {{ expandedVisitId === v.id ? 'Hide' : 'Info' }}
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Expandable details row -->
                                    <tr v-if="expandedVisitId === v.id" class="bg-slate-50/60 dark:bg-slate-800/30">
                                        <td colspan="10" class="p-4">
                                            <div class="space-y-3">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <h4 class="text-xs font-semibold uppercase text-slate-500 mb-1">Check-in / Check-out Notes</h4>
                                                        <p class="text-sm text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                                                            {{ v.result || v.notes || 'No notes provided by staff.' }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-xs font-semibold uppercase text-slate-500 mb-1">GPS Coordinates & Travel Distance</h4>
                                                        <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                                                            <div>📍 Check-In: <span class="font-mono">{{ v.check_in_lat }}, {{ v.check_in_lng }}</span></div>
                                                            <div v-if="v.check_out_lat">🏁 Check-Out: <span class="font-mono">{{ v.check_out_lat }}, {{ v.check_out_lng }}</span></div>
                                                            <div v-if="v.total_distance_km" class="text-blue-600 dark:text-blue-400 font-semibold">
                                                                🛣️ Total Distance: {{ v.total_distance_km }} km
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Photos Gallery -->
                                                <div v-if="v.photos && v.photos.length > 0">
                                                    <h4 class="text-xs font-semibold uppercase text-slate-500 mb-2">Visit Photos ({{ v.photos.length }})</h4>
                                                    <div class="flex flex-wrap gap-3">
                                                        <div v-for="p in v.photos" :key="p.id" class="w-28 h-28 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 relative group">
                                                            <img :src="'/storage/' + p.filename" class="w-full h-full object-cover" />
                                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-[10px] p-1 text-center font-medium">
                                                                {{ p.caption || p.photo_type }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="!visits.data || visits.data.length === 0">
                                    <td colspan="10" class="text-center py-12 text-slate-400">
                                        No field visits recorded for the selected period.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                        <Pagination :links="visits.links" :from="visits.from" :to="visits.to" :total="visits.total" />
                    </div>
                </div>
            </div>

            <!-- TAB 3: ANALYTICS -->
            <div v-show="currentTab === 'analytics'" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Velocity Chart -->
                    <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">7-Day Field Visit Velocity</h3>
                        <div class="h-64">
                            <canvas id="fieldVisitsChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Field Performers -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Top Performers (This Week)</h3>
                        <div class="space-y-3">
                            <div v-for="(p, idx) in chartData.topEmployees" :key="idx" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-6 h-6 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center text-xs font-bold">
                                        {{ idx + 1 }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white">{{ p.name }}</div>
                                        <div class="text-[10px] text-slate-400 capitalize">{{ p.type }}</div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-400 text-xs font-bold">
                                    {{ p.count }} visits
                                </span>
                            </div>

                            <div v-if="!chartData.topEmployees || chartData.topEmployees.length === 0" class="text-xs text-slate-400 text-center py-6">
                                No visits recorded this week yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- GPS ROUTE TRAJECTORY MODAL                                            -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="routeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-4xl max-h-[90vh] shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🗺️</span> GPS Route Trajectory & Breadcrumbs
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Staff: <strong class="text-slate-800 dark:text-slate-200">{{ selectedRouteVisit?.employee?.full_name || selectedRouteVisit?.employee_id }}</strong> • Destination: <strong class="text-slate-800 dark:text-slate-200">{{ selectedRouteVisit?.customer?.name || 'External' }}</strong>
                        </p>
                    </div>
                    <button @click="closeRouteModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Stats Summary Cards -->
                <div class="px-6 py-3.5 bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase text-slate-400">Total Distance</div>
                        <div class="text-base font-bold text-blue-600 dark:text-blue-400 mt-0.5">{{ routeStats.distanceKm }} km</div>
                    </div>
                    <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase text-slate-400">Duration</div>
                        <div class="text-base font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ routeStats.durationMinutes }} mins</div>
                    </div>
                    <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase text-slate-400">Avg Speed</div>
                        <div class="text-base font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ routeStats.avgSpeed }} km/h</div>
                    </div>
                    <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase text-slate-400">GPS Waypoints</div>
                        <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ routeStats.waypointsCount }} pings</div>
                    </div>
                </div>

                <!-- Modal Body: Map Container -->
                <div class="p-6 flex-1 min-h-[420px] relative">
                    <div v-if="routeLoading" class="absolute inset-0 z-20 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm flex items-center justify-center">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
                    </div>
                    <div id="route-trajectory-map" class="w-full h-[400px] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-inner z-10"></div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20 flex items-center justify-between text-xs text-slate-500">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Check-In Start</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Check-Out Finish</span>
                        <span class="flex items-center gap-1.5"><span class="w-4 h-1 bg-blue-500 rounded"></span> Traveled Path</span>
                    </div>
                    <button @click="closeRouteModal" class="px-4 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-semibold transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
