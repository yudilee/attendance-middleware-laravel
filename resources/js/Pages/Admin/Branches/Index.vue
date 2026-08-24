<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Building2,
    Plus,
    MapPin,
    Trash2,
    Edit2,
    ArrowLeft,
    Compass,
    Layers,
    PenTool,
    CheckCircle2,
    RotateCcw,
    Sliders,
    Sparkles
} from 'lucide-vue-next';

import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';

// Fix Leaflet Default Icon in bundlers
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

const props = defineProps({
    branches: Array,
});

// View Mode: 'visualizer' | 'editor'
const currentView = ref('visualizer');

// Visualizer Map State
let visualizerMap = null;
let visualizerLayers = new Map();
const selectedBranch = ref(null);

// Editor Map State
let editorMap = null;
let editorMarker = null;
let editorCircle = null;
let editorPolygon = null;
const isEditing = ref(false);
const isDrawingPolygon = ref(false);

// Active Branch Form
const form = ref({
    id: null,
    name: '',
    latitude: -7.2575,
    longitude: 112.7521,
    radius_meters: 50,
    geofence_type: 'circle',
    polygon_coordinates: '',
    is_active: true,
    timezone_name: 'Asia/Jakarta',
    timezone_offset: 7,
});

// Checkpoint Form
const checkpointForm = ref({
    name: '',
    latitude: -7.2575,
    longitude: 112.7521,
    radius_meters: 30,
    geofence_type: 'circle',
    polygon_coordinates: '',
    is_active: true,
});

// -------------------------------------------------------------
// 1. VISUALIZER MAP (READ-ONLY)
// -------------------------------------------------------------
const initVisualizerMap = () => {
    if (visualizerMap) return;

    const defaultCenter = props.branches.length > 0
        ? [props.branches[0].latitude, props.branches[0].longitude]
        : [-7.2575, 112.7521];

    visualizerMap = L.map('visualizerMap', {
        zoomControl: false,
        attributionControl: false,
    }).setView(defaultCenter, 14);

    L.control.zoom({ position: 'topright' }).addTo(visualizerMap);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(visualizerMap);

    renderVisualizerBranches();
};

const renderVisualizerBranches = () => {
    if (!visualizerMap) return;

    visualizerLayers.forEach(layer => visualizerMap.removeLayer(layer));
    visualizerLayers.clear();

    props.branches.forEach(branch => {
        const group = L.featureGroup();

        // Marker
        const marker = L.marker([branch.latitude, branch.longitude]).bindPopup(`
            <div style="font-family: sans-serif; font-size: 12px; color: #0f172a; padding: 4px;">
                <b style="font-size: 13px; color: #1e40af;">📍 ${branch.name}</b><br/>
                <span style="color: #64748b;">Type:</span> <b>${branch.geofence_type}</b><br/>
                <span style="color: #64748b;">Radius:</span> <b>${branch.radius_meters}m</b><br/>
                <span style="color: #64748b;">GPS:</span> ${branch.latitude.toFixed(5)}, ${branch.longitude.toFixed(5)}
            </div>
        `);
        group.addLayer(marker);

        // Circular Radius
        if (branch.geofence_type === 'circle' || !branch.polygon_coordinates) {
            const circle = L.circle([branch.latitude, branch.longitude], {
                radius: branch.radius_meters,
                color: '#3b82f6',
                fillColor: '#60a5fa',
                fillOpacity: 0.2,
                weight: 2,
            });
            group.addLayer(circle);
        }

        // Polygon
        if (branch.geofence_type === 'polygon' && Array.isArray(branch.polygon_coordinates) && branch.polygon_coordinates.length > 2) {
            const polygon = L.polygon(branch.polygon_coordinates, {
                color: '#10b981',
                fillColor: '#34d399',
                fillOpacity: 0.25,
                weight: 2.5,
            });
            group.addLayer(polygon);
        }

        // Checkpoints
        if (branch.checkpoints) {
            branch.checkpoints.forEach(cp => {
                const cpMarker = L.circleMarker([cp.latitude, cp.longitude], {
                    radius: 5,
                    color: '#f59e0b',
                    fillColor: '#fbbf24',
                    fillOpacity: 0.9,
                }).bindTooltip(`🟡 Point: ${cp.name}`);
                group.addLayer(cpMarker);

                const cpCircle = L.circle([cp.latitude, cp.longitude], {
                    radius: cp.radius_meters,
                    color: '#f59e0b',
                    fillColor: '#fbbf24',
                    fillOpacity: 0.15,
                    weight: 1.5,
                    dashArray: '3, 3',
                });
                group.addLayer(cpCircle);
            });
        }

        group.addTo(visualizerMap);
        visualizerLayers.set(branch.id, group);
    });
};

const flyToBranch = (branch) => {
    selectedBranch.value = branch;
    if (visualizerMap) {
        visualizerMap.flyTo([branch.latitude, branch.longitude], 16, { duration: 1.2 });
    }
};

// -------------------------------------------------------------
// 2. DEDICATED EDITOR MAP (FULL DRAWING & EDITING MODE)
// -------------------------------------------------------------
const openCreateView = () => {
    isEditing.value = false;
    selectedBranch.value = null;

    const center = visualizerMap ? visualizerMap.getCenter() : { lat: -7.2575, lng: 112.7521 };
    form.value = {
        id: null,
        name: '',
        latitude: parseFloat(center.lat.toFixed(6)),
        longitude: parseFloat(center.lng.toFixed(6)),
        radius_meters: 50,
        geofence_type: 'circle',
        polygon_coordinates: '',
        is_active: true,
        timezone_name: 'Asia/Jakarta',
        timezone_offset: 7,
    };

    currentView.value = 'editor';
    nextTick(() => {
        initEditorMap();
        updateEditorOverlays();
    });
};

const openEditView = (branch) => {
    isEditing.value = true;
    selectedBranch.value = branch;

    form.value = {
        id: branch.id,
        name: branch.name,
        latitude: branch.latitude,
        longitude: branch.longitude,
        radius_meters: branch.radius_meters,
        geofence_type: branch.geofence_type || 'circle',
        polygon_coordinates: branch.polygon_coordinates ? JSON.stringify(branch.polygon_coordinates) : '',
        is_active: branch.is_active,
        timezone_name: branch.timezone_name || 'Asia/Jakarta',
        timezone_offset: branch.timezone_offset || 7,
    };

    currentView.value = 'editor';
    nextTick(() => {
        initEditorMap();
        updateEditorOverlays();
        if (editorMap) {
            editorMap.flyTo([branch.latitude, branch.longitude], 16);
        }
    });
};

const initEditorMap = () => {
    if (editorMap) {
        editorMap.remove();
        editorMap = null;
    }

    editorMap = L.map('editorMap', {
        zoomControl: false,
        attributionControl: false,
    }).setView([form.value.latitude, form.value.longitude], 16);

    L.control.zoom({ position: 'topright' }).addTo(editorMap);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(editorMap);

    // Add Geoman Toolbar Controls to Editor Map
    editorMap.pm.addControls({
        position: 'topleft',
        drawPolygon: true,
        drawMarker: false,
        drawCircleMarker: false,
        drawPolyline: false,
        drawRectangle: true,
        drawCircle: false,
        editMode: true,
        dragMode: true,
        cutPolygon: false,
        removalMode: true,
    });

    // Handle map click to relocate center pin in circle mode
    editorMap.on('click', (e) => {
        if (form.value.geofence_type === 'circle') {
            form.value.latitude = parseFloat(e.latlng.lat.toFixed(6));
            form.value.longitude = parseFloat(e.latlng.lng.toFixed(6));
            updateEditorOverlays();
        }
    });

    // Handle Geoman Polygon Creation
    editorMap.on('pm:create', (e) => {
        if (e.shape === 'Polygon' || e.shape === 'Rectangle') {
            const latlngs = e.layer.getLatLngs()[0];
            const coords = latlngs.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);

            form.value.polygon_coordinates = JSON.stringify(coords);
            form.value.geofence_type = 'polygon';

            // Auto-compute center coordinate
            const center = e.layer.getBounds().getCenter();
            form.value.latitude = parseFloat(center.lat.toFixed(6));
            form.value.longitude = parseFloat(center.lng.toFixed(6));

            if (editorPolygon && editorMap.hasLayer(editorPolygon)) {
                editorMap.removeLayer(editorPolygon);
            }
            editorPolygon = e.layer;
            isDrawingPolygon.value = false;

            // Listen to edit updates on this polygon layer
            e.layer.on('pm:edit', (ev) => {
                const updatedPts = ev.target.getLatLngs()[0];
                const updatedCoords = updatedPts.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
                form.value.polygon_coordinates = JSON.stringify(updatedCoords);
                const newCenter = ev.target.getBounds().getCenter();
                form.value.latitude = parseFloat(newCenter.lat.toFixed(6));
                form.value.longitude = parseFloat(newCenter.lng.toFixed(6));
            });
        }
    });

    editorMap.on('pm:remove', (e) => {
        if (e.layer === editorPolygon) {
            form.value.polygon_coordinates = '';
            editorPolygon = null;
        }
    });
};

const updateEditorOverlays = () => {
    if (!editorMap) return;

    // Remove previous temporary overlays
    if (editorMarker && editorMap.hasLayer(editorMarker)) editorMap.removeLayer(editorMarker);
    if (editorCircle && editorMap.hasLayer(editorCircle)) editorMap.removeLayer(editorCircle);
    if (editorPolygon && editorMap.hasLayer(editorPolygon)) editorMap.removeLayer(editorPolygon);

    const lat = form.value.latitude;
    const lng = form.value.longitude;
    const rad = form.value.radius_meters;

    // Draggable Center Pin
    editorMarker = L.marker([lat, lng], {
        draggable: true,
        title: 'Center GPS Location',
    }).addTo(editorMap);

    editorMarker.on('dragend', (e) => {
        const pt = e.target.getLatLng();
        form.value.latitude = parseFloat(pt.lat.toFixed(6));
        form.value.longitude = parseFloat(pt.lng.toFixed(6));
        updateEditorOverlays();
    });

    // Circular Radius Overlay
    if (form.value.geofence_type === 'circle') {
        editorCircle = L.circle([lat, lng], {
            radius: rad,
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.3,
            weight: 3,
        }).addTo(editorMap);
    }

    // Polygon Overlay
    if (form.value.geofence_type === 'polygon' && form.value.polygon_coordinates) {
        try {
            const coords = typeof form.value.polygon_coordinates === 'string'
                ? JSON.parse(form.value.polygon_coordinates)
                : form.value.polygon_coordinates;

            if (Array.isArray(coords) && coords.length > 2) {
                editorPolygon = L.polygon(coords, {
                    color: '#10b981',
                    fillColor: '#34d399',
                    fillOpacity: 0.35,
                    weight: 3,
                }).addTo(editorMap);

                // Enable Geoman editing on this polygon
                editorPolygon.pm.enable();

                editorPolygon.on('pm:edit', (ev) => {
                    const updatedPts = ev.target.getLatLngs()[0];
                    const updatedCoords = updatedPts.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
                    form.value.polygon_coordinates = JSON.stringify(updatedCoords);
                });
            }
        } catch (e) {}
    }
};

const switchGeofenceType = (type) => {
    form.value.geofence_type = type;
    if (type === 'polygon' && !form.value.polygon_coordinates) {
        startDrawPolygonTool();
    } else {
        updateEditorOverlays();
    }
};

const startDrawPolygonTool = () => {
    if (!editorMap) return;
    isDrawingPolygon.value = true;
    if (editorPolygon && editorMap.hasLayer(editorPolygon)) {
        editorMap.removeLayer(editorPolygon);
        editorPolygon = null;
    }
    editorMap.pm.enableDraw('Polygon', {
        snappable: true,
        allowSelfIntersection: false,
    });
};

const clearPolygonTool = () => {
    form.value.polygon_coordinates = '';
    if (editorPolygon && editorMap.hasLayer(editorPolygon)) {
        editorMap.removeLayer(editorPolygon);
        editorPolygon = null;
    }
};

const saveBranch = () => {
    if (isEditing.value) {
        router.put(`/admin/branches/${form.value.id}`, form.value, {
            onSuccess: () => {
                backToVisualizer();
            },
        });
    } else {
        router.post('/admin/branches', form.value, {
            onSuccess: () => {
                backToVisualizer();
            },
        });
    }
};

const backToVisualizer = () => {
    currentView.value = 'visualizer';
    nextTick(() => {
        initVisualizerMap();
        renderVisualizerBranches();
        visualizerMap.invalidateSize();
    });
};

const deleteBranch = (id) => {
    if (confirm('Delete this branch location and all its checkpoints?')) {
        router.delete(`/admin/branches/${id}`);
    }
};

const addCheckpoint = () => {
    if (!selectedBranch.value) return;
    router.post(`/admin/branches/${selectedBranch.value.id}/checkpoints`, checkpointForm.value, {
        onSuccess: () => {
            checkpointForm.value.name = '';
        }
    });
};

const deleteCheckpoint = (cpId) => {
    if (confirm('Delete this checkpoint?')) {
        router.delete(`/admin/checkpoints/${cpId}`);
    }
};

onMounted(() => {
    initVisualizerMap();
});

watch(() => props.branches, () => {
    if (currentView.value === 'visualizer') {
        renderVisualizerBranches();
    }
}, { deep: true });
</script>

<template>
    <AdminLayout title="Branch & Geofence Visualizer">
        <Head title="Branches & Geofence" />

        <!-- ========================================================= -->
        <!-- VIEW 1: READ-ONLY SPATIAL MAP VISUALIZER                  -->
        <!-- ========================================================= -->
        <div v-if="currentView === 'visualizer'" class="space-y-4">
            <!-- Header Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <Compass class="w-5 h-5 text-blue-400" />
                        Branch Spatial Map & Geofences
                    </h2>
                    <p class="text-xs text-slate-400">Read-only overview of all branch geofences. Click 'Edit' or '+ Add New Branch' to modify boundaries on the map.</p>
                </div>

                <button
                    @click="openCreateView"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition"
                >
                    <Plus class="w-4 h-4" />
                    Add New Branch
                </button>
            </div>

            <!-- Visualizer Split Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 h-[calc(100vh-175px)] min-h-[600px]">
                <!-- Left: Branch Roster Cards (4 Cols) -->
                <div class="lg:col-span-4 flex flex-col gap-3 overflow-y-auto pr-1">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        @click="flyToBranch(branch)"
                        :class="[
                            'p-4 rounded-2xl border transition-all cursor-pointer relative group',
                            selectedBranch?.id === branch.id
                                ? 'bg-slate-900 border-blue-500/60 shadow-lg shadow-blue-500/10 ring-1 ring-blue-500/40'
                                : 'bg-slate-900/90 border-slate-800 hover:border-slate-700 hover:bg-slate-800/60'
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2.5">
                                <div :class="[
                                    'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold',
                                    branch.geofence_type === 'polygon'
                                        ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400'
                                        : 'bg-blue-500/10 border border-blue-500/20 text-blue-400'
                                ]">
                                    <MapPin class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white group-hover:text-blue-300 transition">{{ branch.name }}</h4>
                                    <span class="text-[10px] text-slate-500 font-mono">ID: #{{ branch.id }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    @click.stop="openEditView(branch)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:bg-blue-600/20 hover:text-blue-300 transition"
                                    title="Edit Branch & Draw Geofence"
                                >
                                    <Edit2 class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    @click.stop="deleteBranch(branch.id)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:bg-rose-500/20 hover:text-rose-400 transition"
                                    title="Delete Branch"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1 text-xs text-slate-300 pt-2 border-t border-slate-800/80">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Center:</span>
                                <span class="font-mono text-[11px]">{{ branch.latitude.toFixed(4) }}, {{ branch.longitude.toFixed(4) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Boundary:</span>
                                <span :class="[
                                    'text-[11px] font-semibold uppercase',
                                    branch.geofence_type === 'polygon' ? 'text-emerald-400' : 'text-blue-400'
                                ]">
                                    {{ branch.geofence_type }} ({{ branch.radius_meters }}m)
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Checkpoints:</span>
                                <span class="text-[11px] text-amber-400 font-medium">{{ branch.checkpoints_count }} assigned</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="branches.length === 0" class="p-8 text-center bg-slate-900 border border-slate-800 rounded-2xl text-slate-500 text-xs">
                        No branches configured. Click "+ Add New Branch" to create one.
                    </div>
                </div>

                <!-- Right: Read-Only Leaflet Map (8 Cols) -->
                <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl relative flex flex-col">
                    <div class="absolute top-4 left-4 z-20 flex items-center gap-2 bg-slate-900/90 border border-slate-800/90 backdrop-blur-md px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-200 shadow-xl">
                        <Layers class="w-3.5 h-3.5 text-blue-400" />
                        <span>Live Branch Geofences</span>
                        <span class="text-slate-500">|</span>
                        <span class="text-blue-400 flex items-center gap-1">⭕ Circle</span>
                        <span class="text-emerald-400 flex items-center gap-1">⬡ Polygon</span>
                        <span class="text-amber-400 flex items-center gap-1">🟡 Checkpoint</span>
                    </div>

                    <div id="visualizerMap" class="w-full h-full min-h-[500px] z-10"></div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- VIEW 2: DEDICATED FULL EDIT & DRAW VIEW                   -->
        <!-- ========================================================= -->
        <div v-else-if="currentView === 'editor'" class="space-y-4">
            <!-- Header Bar -->
            <div class="flex items-center justify-between bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <button
                        @click="backToVisualizer"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition flex items-center gap-1.5 text-xs font-semibold"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        Back to Visualizer
                    </button>
                    <div>
                        <h3 class="text-sm font-bold text-white">
                            {{ isEditing ? `Editing: ${form.name}` : 'Create New Branch' }}
                        </h3>
                        <p class="text-[11px] text-slate-400">Use the interactive map on the right to freely draw polygon shapes or drag the center pin.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="backToVisualizer"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveBranch"
                        class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition flex items-center gap-1.5"
                    >
                        <CheckCircle2 class="w-4 h-4" />
                        {{ isEditing ? 'Save Changes' : 'Create Branch' }}
                    </button>
                </div>
            </div>

            <!-- 2-Column Editor Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 h-[calc(100vh-210px)] min-h-[580px]">
                <!-- Left: Form Controls (5 Cols) -->
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-y-auto space-y-4 shadow-xl">
                    <!-- Branch Name -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Branch Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. HRM Surabaya HQ"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-200 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <!-- Geofence Boundary Selector -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Geofence Boundary Type</label>
                        <div class="grid grid-cols-2 gap-2 p-1 bg-slate-950 border border-slate-800 rounded-xl">
                            <button
                                type="button"
                                @click="switchGeofenceType('circle')"
                                :class="[
                                    'py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5',
                                    form.geofence_type === 'circle'
                                        ? 'bg-blue-600 text-white shadow-md'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                            >
                                ⭕ Circular Radius
                            </button>
                            <button
                                type="button"
                                @click="switchGeofenceType('polygon')"
                                :class="[
                                    'py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5',
                                    form.geofence_type === 'polygon'
                                        ? 'bg-emerald-600 text-white shadow-md'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                            >
                                ⬡ Polygon (Free Draw)
                            </button>
                        </div>
                    </div>

                    <!-- Coordinates & Radius -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Latitude</label>
                            <input
                                v-model.number="form.latitude"
                                @input="updateEditorOverlays"
                                type="number"
                                step="any"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Longitude</label>
                            <input
                                v-model.number="form.longitude"
                                @input="updateEditorOverlays"
                                type="number"
                                step="any"
                                required
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div class="col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-semibold text-slate-300">Allowed Radius (Meters)</label>
                                <span class="text-xs font-mono font-bold text-blue-400">{{ form.radius_meters }} m</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <input
                                    v-model.number="form.radius_meters"
                                    @input="updateEditorOverlays"
                                    type="range"
                                    min="10"
                                    max="500"
                                    step="5"
                                    class="w-full h-2 bg-slate-950 rounded-lg appearance-none cursor-pointer accent-blue-500"
                                />
                                <input
                                    v-model.number="form.radius_meters"
                                    @input="updateEditorOverlays"
                                    type="number"
                                    min="5"
                                    class="w-20 px-2 py-1 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200 font-mono text-right"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Polygon Tool Actions -->
                    <div v-if="form.geofence_type === 'polygon'" class="p-4 rounded-xl bg-slate-950 border border-emerald-500/30 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                                <PenTool class="w-4 h-4" />
                                Polygon Free-Draw Tools
                            </span>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="startDrawPolygonTool"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500 transition shadow-sm"
                                >
                                    🖌️ Draw on Map
                                </button>
                                <button
                                    type="button"
                                    @click="clearPolygonTool"
                                    class="px-2.5 py-1 rounded-lg bg-rose-600/20 text-rose-400 border border-rose-500/30 text-xs font-semibold hover:bg-rose-600/30"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Click <b>'Draw on Map'</b>, click multi-points on the map to construct the geofenced perimeter, and double-click to finish closing the shape. You can also drag the vertex points to adjust borders!
                        </p>

                        <textarea
                            v-model="form.polygon_coordinates"
                            rows="2"
                            placeholder="[[lat, lng], [lat, lng], [lat, lng]]"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-[11px] font-mono text-slate-300 focus:outline-none focus:border-emerald-500"
                        ></textarea>
                    </div>

                    <!-- Checkpoints Manager (When Editing) -->
                    <div v-if="isEditing && selectedBranch" class="pt-3 border-t border-slate-800">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-2 flex items-center gap-1.5">
                            <MapPin class="w-4 h-4" />
                            Multipoint Checkpoints ({{ selectedBranch.checkpoints?.length || 0 }})
                        </h4>

                        <div class="space-y-2 mb-3">
                            <div
                                v-for="cp in selectedBranch.checkpoints"
                                :key="cp.id"
                                class="flex items-center justify-between p-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs"
                            >
                                <div>
                                    <span class="font-bold text-white">{{ cp.name }}</span>
                                    <span class="text-slate-500 ml-2 font-mono text-[11px]">({{ cp.latitude.toFixed(4) }}, {{ cp.longitude.toFixed(4) }} • {{ cp.radius_meters }}m)</span>
                                </div>
                                <button
                                    type="button"
                                    @click="deleteCheckpoint(cp.id)"
                                    class="p-1 rounded-lg text-slate-500 hover:text-rose-400"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div v-if="selectedBranch.checkpoints?.length === 0" class="text-xs text-slate-500 text-center py-2">
                                No secondary checkpoints added yet.
                            </div>
                        </div>

                        <!-- Add Checkpoint Inline Form -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                            <div class="sm:col-span-2">
                                <input
                                    v-model="checkpointForm.name"
                                    type="text"
                                    placeholder="Point Name (e.g. Gate 2)"
                                    class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200"
                                />
                            </div>
                            <div>
                                <input
                                    v-model.number="checkpointForm.radius_meters"
                                    type="number"
                                    placeholder="Radius (m)"
                                    class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200"
                                />
                            </div>
                            <button
                                type="button"
                                @click="addCheckpoint"
                                :disabled="!checkpointForm.name"
                                class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-50"
                            >
                                + Add Point
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Dedicated Interactive Drawing Map (7 Cols) -->
                <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl relative flex flex-col">
                    <div class="absolute top-4 right-4 z-20 flex items-center gap-2 bg-slate-900/95 border border-slate-800 backdrop-blur-md px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-200 shadow-xl">
                        <span v-if="form.geofence_type === 'polygon'" class="text-emerald-400 flex items-center gap-1.5">
                            <PenTool class="w-3.5 h-3.5" />
                            Polygon Free-Draw Active
                        </span>
                        <span v-else class="text-blue-400 flex items-center gap-1.5">
                            <Compass class="w-3.5 h-3.5" />
                            Click Map or Drag Pin to Set Center
                        </span>
                    </div>

                    <div id="editorMap" class="w-full h-full min-h-[500px] z-10"></div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
