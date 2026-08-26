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
    Sparkles,
    Search,
    Loader2,
    X,
    Crosshair,
    Navigation,
    Clock,
    ClipboardPaste,
    CircleDot,
    Check,
    Radio
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
    shifts: {
        type: Array,
        default: () => [],
    },
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
let editorAuxLayers = [];
const isEditing = ref(false);
const isDrawingPolygon = ref(false);

// Dual-Target Editor State: 'branch' | 'checkpoint'
const editingTarget = ref('branch');
const editingCheckpoint = ref(null);
const isSavingCheckpoint = ref(false);

// Map Search & Coordinate Parsing State
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);
let searchDebounce = null;
const pasteCoordinateInput = ref('');

// Parse coordinates from text (supports Decimal Degrees and DMS Degrees Minutes Seconds)
const parseCoordinates = (input) => {
    if (!input || typeof input !== 'string') return null;
    const str = input.trim();

    // 1. Decimal format: e.g. "-2.634581, 121.107485" or "-2.634581 121.107485"
    const decimalMatch = str.match(/^(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)$/);
    if (decimalMatch) {
        const lat = parseFloat(decimalMatch[1]);
        const lng = parseFloat(decimalMatch[2]);
        if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            return {
                lat: parseFloat(lat.toFixed(6)),
                lng: parseFloat(lng.toFixed(6)),
                title: 'Parsed GPS Coordinates',
                address: `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`,
                isCoordinate: true
            };
        }
    }

    // 2. DMS format: e.g. 2°38'04.5"S 121°06'27.0"E or 2°38'4.5" S, 121°6'27" E
    const dmsRegex = /(\d+)[°\s]+(\d+)['\s]+(\d+(?:\.\d+)?)["]?\s*([NSEWnsew])[,\s]+(\d+)[°\s]+(\d+)['\s]+(\d+(?:\.\d+)?)["]?\s*([NSEWnsew])/i;
    const dmsMatch = str.match(dmsRegex);
    if (dmsMatch) {
        const latDeg = parseFloat(dmsMatch[1]);
        const latMin = parseFloat(dmsMatch[2]);
        const latSec = parseFloat(dmsMatch[3]);
        const latDir = dmsMatch[4].toUpperCase();

        const lngDeg = parseFloat(dmsMatch[5]);
        const lngMin = parseFloat(dmsMatch[6]);
        const lngSec = parseFloat(dmsMatch[7]);
        const lngDir = dmsMatch[8].toUpperCase();

        let lat = latDeg + (latMin / 60) + (latSec / 3600);
        if (latDir === 'S') lat = -lat;

        let lng = lngDeg + (lngMin / 60) + (lngSec / 3600);
        if (lngDir === 'W') lng = -lng;

        if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            return {
                lat: parseFloat(lat.toFixed(6)),
                lng: parseFloat(lng.toFixed(6)),
                title: 'DMS Converted Coordinates',
                address: `Lat: ${lat.toFixed(6)} (${dmsMatch[1]}°${dmsMatch[2]}'${dmsMatch[3]}"${latDir}), Lng: ${lng.toFixed(6)} (${dmsMatch[5]}°${dmsMatch[6]}'${dmsMatch[7]}"${lngDir})`,
                isCoordinate: true
            };
        }
    }

    return null;
};

const onSearchInput = () => {
    clearTimeout(searchDebounce);
    const query = searchQuery.value.trim();

    if (!query) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }

    // Direct coordinates check (DMS or Decimal)
    const directCoords = parseCoordinates(query);
    if (directCoords) {
        searchResults.value = [directCoords];
        showSearchResults.value = true;
        return;
    }

    searchDebounce = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=6&addressdetails=1&countrycodes=id`,
                { headers: { 'Accept-Language': 'id,en' } }
            );
            const data = await res.json();
            searchResults.value = data.map(item => ({
                lat: parseFloat(parseFloat(item.lat).toFixed(6)),
                lng: parseFloat(parseFloat(item.lon).toFixed(6)),
                title: item.name || item.display_name.split(',')[0],
                address: item.display_name,
                isCoordinate: false
            }));
            showSearchResults.value = searchResults.value.length > 0;
        } catch (e) {
            console.error('Geocoding search error:', e);
        } finally {
            isSearching.value = false;
        }
    }, 300);
};

const selectSearchResult = (item) => {
    searchQuery.value = item.title;
    showSearchResults.value = false;

    if (currentView.value === 'editor') {
        if (editingTarget.value === 'branch') {
            form.value.latitude = item.lat;
            form.value.longitude = item.lng;
        } else {
            checkpointForm.value.latitude = item.lat;
            checkpointForm.value.longitude = item.lng;
        }
        updateEditorOverlays();
        if (editorMap) {
            editorMap.flyTo([item.lat, item.lng], 17, { duration: 1.2 });
        }
    } else if (currentView.value === 'visualizer') {
        if (visualizerMap) {
            visualizerMap.flyTo([item.lat, item.lng], 16, { duration: 1.2 });
        }
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
    showSearchResults.value = false;
};

const applyPastedCoordinates = () => {
    const parsed = parseCoordinates(pasteCoordinateInput.value);
    if (parsed) {
        if (editingTarget.value === 'branch') {
            form.value.latitude = parsed.lat;
            form.value.longitude = parsed.lng;
        } else {
            checkpointForm.value.latitude = parsed.lat;
            checkpointForm.value.longitude = parsed.lng;
        }
        updateEditorOverlays();
        if (editorMap) {
            editorMap.flyTo([parsed.lat, parsed.lng], 17, { duration: 1.2 });
        }
        pasteCoordinateInput.value = '';
    } else {
        alert('Could not parse coordinates. Please enter format like: "-2.634581, 121.107485" or DMS "2°38\'04.5\\"S 121°06\'27.0\\"E"');
    }
};

const onCoordinateChange = () => {
    if (editingTarget.value === 'branch') {
        if (form.value.latitude && form.value.longitude) {
            updateEditorOverlays();
            if (editorMap) {
                editorMap.panTo([form.value.latitude, form.value.longitude]);
            }
        }
    } else {
        if (checkpointForm.value.latitude && checkpointForm.value.longitude) {
            updateEditorOverlays();
            if (editorMap) {
                editorMap.panTo([checkpointForm.value.latitude, checkpointForm.value.longitude]);
            }
        }
    }
};

// Active Branch Form
const form = ref({
    id: null,
    name: '',
    latitude: -7.2575,
    longitude: 112.7521,
    radius_meters: 50,
    geofence_type: 'circle',
    polygon_coordinates: '',
    shift_schedule_id: null,
    is_active: true,
    timezone_name: 'Asia/Jakarta',
    timezone_offset: 7,
});

// Checkpoint Form
const checkpointForm = ref({
    id: null,
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

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        subdomains: 'abc',
    }).addTo(visualizerMap);

    renderVisualizerBranches();
};

const renderVisualizerBranches = () => {
    if (!visualizerMap) return;

    visualizerLayers.forEach(layer => visualizerMap.removeLayer(layer));
    visualizerLayers.clear();

    props.branches.forEach(branch => {
        const group = L.featureGroup();

        // Main Branch Marker
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

        // Checkpoints (Both Circular & Polygon)
        if (branch.checkpoints) {
            branch.checkpoints.forEach(cp => {
                const cpMarker = L.circleMarker([cp.latitude, cp.longitude], {
                    radius: 6,
                    color: '#b45309',
                    fillColor: '#f59e0b',
                    fillOpacity: 0.95,
                    weight: 2,
                }).bindTooltip(`🟡 ${cp.name} (${cp.geofence_type === 'polygon' ? 'Polygon' : cp.radius_meters + 'm'})`);
                group.addLayer(cpMarker);

                if (cp.geofence_type === 'polygon' && Array.isArray(cp.polygon_coordinates) && cp.polygon_coordinates.length > 2) {
                    const cpPolygon = L.polygon(cp.polygon_coordinates, {
                        color: '#d97706',
                        fillColor: '#fbbf24',
                        fillOpacity: 0.25,
                        weight: 2,
                        dashArray: '4, 4',
                    }).bindPopup(`
                        <div style="font-family: sans-serif; font-size: 12px; color: #0f172a; padding: 4px;">
                            <b style="font-size: 13px; color: #b45309;">🟡 Checkpoint: ${cp.name}</b><br/>
                            <span style="color: #64748b;">Branch:</span> <b>${branch.name}</b><br/>
                            <span style="color: #64748b;">Boundary:</span> <b>Polygon</b> (${cp.polygon_coordinates.length} pts)<br/>
                            <span style="color: #64748b;">Center GPS:</span> ${cp.latitude.toFixed(5)}, ${cp.longitude.toFixed(5)}
                        </div>
                    `);
                    group.addLayer(cpPolygon);
                } else {
                    const cpCircle = L.circle([cp.latitude, cp.longitude], {
                        radius: cp.radius_meters || 30,
                        color: '#d97706',
                        fillColor: '#fbbf24',
                        fillOpacity: 0.18,
                        weight: 1.5,
                        dashArray: '3, 3',
                    }).bindPopup(`
                        <div style="font-family: sans-serif; font-size: 12px; color: #0f172a; padding: 4px;">
                            <b style="font-size: 13px; color: #b45309;">🟡 Checkpoint: ${cp.name}</b><br/>
                            <span style="color: #64748b;">Branch:</span> <b>${branch.name}</b><br/>
                            <span style="color: #64748b;">Boundary:</span> <b>Circular Radius</b> (${cp.radius_meters}m)<br/>
                            <span style="color: #64748b;">Center GPS:</span> ${cp.latitude.toFixed(5)}, ${cp.longitude.toFixed(5)}
                        </div>
                    `);
                    group.addLayer(cpCircle);
                }
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
// 2. DEDICATED EDITOR MAP (FULL DUAL-MODE DRAWING & EDITING)
// -------------------------------------------------------------
const openCreateView = () => {
    isEditing.value = false;
    selectedBranch.value = null;
    editingTarget.value = 'branch';
    editingCheckpoint.value = null;

    const center = visualizerMap ? visualizerMap.getCenter() : { lat: -7.2575, lng: 112.7521 };
    form.value = {
        id: null,
        name: '',
        latitude: parseFloat(center.lat.toFixed(6)),
        longitude: parseFloat(center.lng.toFixed(6)),
        radius_meters: 50,
        geofence_type: 'circle',
        polygon_coordinates: '',
        shift_schedule_id: null,
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
    editingTarget.value = 'branch';
    editingCheckpoint.value = null;

    form.value = {
        id: branch.id,
        name: branch.name,
        latitude: branch.latitude,
        longitude: branch.longitude,
        radius_meters: branch.radius_meters,
        geofence_type: branch.geofence_type || 'circle',
        polygon_coordinates: branch.polygon_coordinates ? JSON.stringify(branch.polygon_coordinates) : '',
        shift_schedule_id: branch.shift_schedule_id ?? null,
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

    const initLat = editingTarget.value === 'branch' ? form.value.latitude : checkpointForm.value.latitude;
    const initLng = editingTarget.value === 'branch' ? form.value.longitude : checkpointForm.value.longitude;

    editorMap = L.map('editorMap', {
        zoomControl: false,
        attributionControl: false,
    }).setView([initLat, initLng], 16);

    L.control.zoom({ position: 'topright' }).addTo(editorMap);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        subdomains: 'abc',
    }).addTo(editorMap);

    // Geoman Toolbar Controls
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
        if (editingTarget.value === 'branch' && form.value.geofence_type === 'circle') {
            form.value.latitude = parseFloat(e.latlng.lat.toFixed(6));
            form.value.longitude = parseFloat(e.latlng.lng.toFixed(6));
            updateEditorOverlays();
        } else if (editingTarget.value === 'checkpoint' && checkpointForm.value.geofence_type === 'circle') {
            checkpointForm.value.latitude = parseFloat(e.latlng.lat.toFixed(6));
            checkpointForm.value.longitude = parseFloat(e.latlng.lng.toFixed(6));
            updateEditorOverlays();
        }
    });

    // Handle Geoman Polygon Creation
    editorMap.on('pm:create', (e) => {
        if (e.shape === 'Polygon' || e.shape === 'Rectangle') {
            const latlngs = e.layer.getLatLngs()[0];
            const coords = latlngs.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
            const center = e.layer.getBounds().getCenter();

            if (editingTarget.value === 'branch') {
                form.value.polygon_coordinates = JSON.stringify(coords);
                form.value.geofence_type = 'polygon';
                form.value.latitude = parseFloat(center.lat.toFixed(6));
                form.value.longitude = parseFloat(center.lng.toFixed(6));
            } else {
                checkpointForm.value.polygon_coordinates = JSON.stringify(coords);
                checkpointForm.value.geofence_type = 'polygon';
                checkpointForm.value.latitude = parseFloat(center.lat.toFixed(6));
                checkpointForm.value.longitude = parseFloat(center.lng.toFixed(6));
            }

            if (editorPolygon && editorMap.hasLayer(editorPolygon)) {
                editorMap.removeLayer(editorPolygon);
            }
            editorPolygon = e.layer;
            isDrawingPolygon.value = false;

            // Listen to edit updates on this polygon layer
            e.layer.on('pm:edit', (ev) => {
                const updatedPts = ev.target.getLatLngs()[0];
                const updatedCoords = updatedPts.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
                const newCenter = ev.target.getBounds().getCenter();

                if (editingTarget.value === 'branch') {
                    form.value.polygon_coordinates = JSON.stringify(updatedCoords);
                    form.value.latitude = parseFloat(newCenter.lat.toFixed(6));
                    form.value.longitude = parseFloat(newCenter.lng.toFixed(6));
                } else {
                    checkpointForm.value.polygon_coordinates = JSON.stringify(updatedCoords);
                    checkpointForm.value.latitude = parseFloat(newCenter.lat.toFixed(6));
                    checkpointForm.value.longitude = parseFloat(newCenter.lng.toFixed(6));
                }
            });
        }
    });

    editorMap.on('pm:remove', (e) => {
        if (e.layer === editorPolygon) {
            if (editingTarget.value === 'branch') {
                form.value.polygon_coordinates = '';
            } else {
                checkpointForm.value.polygon_coordinates = '';
            }
            editorPolygon = null;
        }
    });
};

const updateEditorOverlays = () => {
    if (!editorMap) return;

    // Clean up primary overlays
    if (editorMarker && editorMap.hasLayer(editorMarker)) editorMap.removeLayer(editorMarker);
    if (editorCircle && editorMap.hasLayer(editorCircle)) editorMap.removeLayer(editorCircle);
    if (editorPolygon && editorMap.hasLayer(editorPolygon)) editorMap.removeLayer(editorPolygon);

    // Clean up auxiliary layers
    editorAuxLayers.forEach(l => {
        if (editorMap.hasLayer(l)) editorMap.removeLayer(l);
    });
    editorAuxLayers = [];

    // =========================================================================
    // CASE A: EDITING MAIN BRANCH PERIMETER
    // =========================================================================
    if (editingTarget.value === 'branch') {
        const lat = form.value.latitude;
        const lng = form.value.longitude;
        const rad = form.value.radius_meters;

        // Draggable Blue Branch Marker
        editorMarker = L.marker([lat, lng], {
            draggable: true,
            title: 'Branch Center Location',
        }).addTo(editorMap);

        editorMarker.on('dragend', (e) => {
            const pt = e.target.getLatLng();
            form.value.latitude = parseFloat(pt.lat.toFixed(6));
            form.value.longitude = parseFloat(pt.lng.toFixed(6));
            updateEditorOverlays();
        });

        // Branch Circular Radius
        if (form.value.geofence_type === 'circle') {
            editorCircle = L.circle([lat, lng], {
                radius: rad,
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.28,
                weight: 3,
            }).addTo(editorMap);
        }

        // Branch Polygon Overlay
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

                    editorPolygon.pm.enable();

                    editorPolygon.on('pm:edit', (ev) => {
                        const updatedPts = ev.target.getLatLngs()[0];
                        const updatedCoords = updatedPts.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
                        form.value.polygon_coordinates = JSON.stringify(updatedCoords);
                        const newCenter = ev.target.getBounds().getCenter();
                        form.value.latitude = parseFloat(newCenter.lat.toFixed(6));
                        form.value.longitude = parseFloat(newCenter.lng.toFixed(6));
                    });
                }
            } catch (e) {}
        }

        // Auxiliary Display: Show all existing checkpoints for visual context
        if (selectedBranch.value?.checkpoints) {
            selectedBranch.value.checkpoints.forEach(cp => {
                const cpM = L.circleMarker([cp.latitude, cp.longitude], {
                    radius: 6,
                    color: '#b45309',
                    fillColor: '#f59e0b',
                    fillOpacity: 0.9,
                    weight: 2,
                }).bindTooltip(`🟡 Checkpoint: ${cp.name} (Click to Edit)`).on('click', () => {
                    startEditCheckpoint(cp);
                });
                cpM.addTo(editorMap);
                editorAuxLayers.push(cpM);

                if (cp.geofence_type === 'polygon' && cp.polygon_coordinates) {
                    try {
                        const cpCoords = typeof cp.polygon_coordinates === 'string' ? JSON.parse(cp.polygon_coordinates) : cp.polygon_coordinates;
                        if (Array.isArray(cpCoords) && cpCoords.length > 2) {
                            const cpPoly = L.polygon(cpCoords, {
                                color: '#d97706',
                                fillColor: '#fbbf24',
                                fillOpacity: 0.2,
                                weight: 2,
                                dashArray: '4, 4',
                            }).on('click', () => {
                                startEditCheckpoint(cp);
                            });
                            cpPoly.addTo(editorMap);
                            editorAuxLayers.push(cpPoly);
                        }
                    } catch (e) {}
                } else {
                    const cpCirc = L.circle([cp.latitude, cp.longitude], {
                        radius: cp.radius_meters || 30,
                        color: '#d97706',
                        fillColor: '#fbbf24',
                        fillOpacity: 0.15,
                        weight: 1.5,
                        dashArray: '3, 3',
                    }).on('click', () => {
                        startEditCheckpoint(cp);
                    });
                    cpCirc.addTo(editorMap);
                    editorAuxLayers.push(cpCirc);
                }
            });
        }
    }

    // =========================================================================
    // CASE B: EDITING A CHECKPOINT (PARITY WITH MAIN GEOFENCE)
    // =========================================================================
    else if (editingTarget.value === 'checkpoint') {
        // 1. Auxiliary Context: Branch Main Geofence (faint reference)
        if (form.value.geofence_type === 'polygon' && form.value.polygon_coordinates) {
            try {
                const bCoords = typeof form.value.polygon_coordinates === 'string' ? JSON.parse(form.value.polygon_coordinates) : form.value.polygon_coordinates;
                if (Array.isArray(bCoords) && bCoords.length > 2) {
                    const bPoly = L.polygon(bCoords, {
                        color: '#10b981',
                        fillColor: '#34d399',
                        fillOpacity: 0.1,
                        weight: 2,
                        dashArray: '5, 5',
                    });
                    bPoly.addTo(editorMap);
                    editorAuxLayers.push(bPoly);
                }
            } catch (e) {}
        } else {
            const bCirc = L.circle([form.value.latitude, form.value.longitude], {
                radius: form.value.radius_meters,
                color: '#3b82f6',
                fillColor: '#60a5fa',
                fillOpacity: 0.1,
                weight: 1.5,
                dashArray: '5, 5',
            });
            bCirc.addTo(editorMap);
            editorAuxLayers.push(bCirc);
        }

        // Branch center pin reference
        const bMarker = L.circleMarker([form.value.latitude, form.value.longitude], {
            radius: 5,
            color: '#2563eb',
            fillColor: '#60a5fa',
            fillOpacity: 0.6,
        }).bindTooltip(`📍 Branch Center: ${form.value.name || 'HQ'}`);
        bMarker.addTo(editorMap);
        editorAuxLayers.push(bMarker);

        // 2. Auxiliary Context: Other Checkpoints (faint)
        if (selectedBranch.value?.checkpoints) {
            selectedBranch.value.checkpoints.forEach(cp => {
                if (editingCheckpoint.value && cp.id === editingCheckpoint.value.id) return; // Skip active one

                const otherM = L.circleMarker([cp.latitude, cp.longitude], {
                    radius: 5,
                    color: '#78350f',
                    fillColor: '#d97706',
                    fillOpacity: 0.4,
                }).bindTooltip(`Point: ${cp.name}`);
                otherM.addTo(editorMap);
                editorAuxLayers.push(otherM);
            });
        }

        // 3. Active Checkpoint Elements (Amber/Gold Editable)
        const cpLat = checkpointForm.value.latitude;
        const cpLng = checkpointForm.value.longitude;
        const cpRad = checkpointForm.value.radius_meters;

        // Draggable Amber Checkpoint Center Pin
        editorMarker = L.marker([cpLat, cpLng], {
            draggable: true,
            title: 'Drag Checkpoint Center',
        }).addTo(editorMap);

        editorMarker.on('dragend', (e) => {
            const pt = e.target.getLatLng();
            checkpointForm.value.latitude = parseFloat(pt.lat.toFixed(6));
            checkpointForm.value.longitude = parseFloat(pt.lng.toFixed(6));
            updateEditorOverlays();
        });

        // Checkpoint Circular Radius Overlay
        if (checkpointForm.value.geofence_type === 'circle') {
            editorCircle = L.circle([cpLat, cpLng], {
                radius: cpRad,
                color: '#d97706',
                fillColor: '#fbbf24',
                fillOpacity: 0.35,
                weight: 3,
            }).addTo(editorMap);
        }

        // Checkpoint Polygon Overlay (Geoman Editable)
        if (checkpointForm.value.geofence_type === 'polygon' && checkpointForm.value.polygon_coordinates) {
            try {
                const coords = typeof checkpointForm.value.polygon_coordinates === 'string'
                    ? JSON.parse(checkpointForm.value.polygon_coordinates)
                    : checkpointForm.value.polygon_coordinates;

                if (Array.isArray(coords) && coords.length > 2) {
                    editorPolygon = L.polygon(coords, {
                        color: '#d97706',
                        fillColor: '#fbbf24',
                        fillOpacity: 0.38,
                        weight: 3,
                    }).addTo(editorMap);

                    editorPolygon.pm.enable();

                    editorPolygon.on('pm:edit', (ev) => {
                        const updatedPts = ev.target.getLatLngs()[0];
                        const updatedCoords = updatedPts.map(pt => [parseFloat(pt.lat.toFixed(6)), parseFloat(pt.lng.toFixed(6))]);
                        checkpointForm.value.polygon_coordinates = JSON.stringify(updatedCoords);
                        const newCenter = ev.target.getBounds().getCenter();
                        checkpointForm.value.latitude = parseFloat(newCenter.lat.toFixed(6));
                        checkpointForm.value.longitude = parseFloat(newCenter.lng.toFixed(6));
                    });
                }
            } catch (e) {}
        }
    }
};

const switchGeofenceType = (type) => {
    if (editingTarget.value === 'branch') {
        form.value.geofence_type = type;
        if (type === 'polygon' && !form.value.polygon_coordinates) {
            startDrawPolygonTool();
        } else {
            updateEditorOverlays();
        }
    } else {
        checkpointForm.value.geofence_type = type;
        if (type === 'polygon' && !checkpointForm.value.polygon_coordinates) {
            startDrawPolygonTool();
        } else {
            updateEditorOverlays();
        }
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
    if (editingTarget.value === 'branch') {
        form.value.polygon_coordinates = '';
    } else {
        checkpointForm.value.polygon_coordinates = '';
    }
    if (editorPolygon && editorMap.hasLayer(editorPolygon)) {
        editorMap.removeLayer(editorPolygon);
        editorPolygon = null;
    }
    updateEditorOverlays();
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
    editingTarget.value = 'branch';
    editingCheckpoint.value = null;
    nextTick(() => {
        initVisualizerMap();
        renderVisualizerBranches();
        if (visualizerMap) visualizerMap.invalidateSize();
    });
};

const quickAssignShift = (branch, shiftId) => {
    router.put(`/admin/branches/${branch.id}`, {
        name: branch.name,
        latitude: branch.latitude,
        longitude: branch.longitude,
        radius_meters: branch.radius_meters,
        geofence_type: branch.geofence_type || 'circle',
        polygon_coordinates: branch.polygon_coordinates ? JSON.stringify(branch.polygon_coordinates) : '',
        shift_schedule_id: shiftId ? parseInt(shiftId) : null,
        is_active: branch.is_active,
        timezone_name: branch.timezone_name || 'Asia/Jakarta',
        timezone_offset: branch.timezone_offset || 7,
    }, {
        preserveScroll: true,
    });
};

const deleteBranch = (id) => {
    if (confirm('Delete this branch location and all its checkpoints?')) {
        router.delete(`/admin/branches/${id}`);
    }
};

// Checkpoint Actions
const startAddCheckpoint = () => {
    editingTarget.value = 'checkpoint';
    editingCheckpoint.value = null;

    const lat = form.value.latitude || (editorMap ? editorMap.getCenter().lat : -7.2575);
    const lng = form.value.longitude || (editorMap ? editorMap.getCenter().lng : 112.7521);

    checkpointForm.value = {
        id: null,
        name: '',
        latitude: parseFloat(Number(lat).toFixed(6)),
        longitude: parseFloat(Number(lng).toFixed(6)),
        radius_meters: 30,
        geofence_type: 'circle',
        polygon_coordinates: '',
        is_active: true,
    };

    nextTick(() => {
        updateEditorOverlays();
        if (editorMap) {
            editorMap.panTo([checkpointForm.value.latitude, checkpointForm.value.longitude]);
        }
    });
};

const startEditCheckpoint = (cp) => {
    editingTarget.value = 'checkpoint';
    editingCheckpoint.value = cp;

    checkpointForm.value = {
        id: cp.id,
        name: cp.name,
        latitude: parseFloat(Number(cp.latitude).toFixed(6)),
        longitude: parseFloat(Number(cp.longitude).toFixed(6)),
        radius_meters: cp.radius_meters || 30,
        geofence_type: cp.geofence_type || 'circle',
        polygon_coordinates: cp.polygon_coordinates ? (typeof cp.polygon_coordinates === 'string' ? cp.polygon_coordinates : JSON.stringify(cp.polygon_coordinates)) : '',
        is_active: cp.is_active !== false,
    };

    nextTick(() => {
        updateEditorOverlays();
        if (editorMap) {
            editorMap.flyTo([cp.latitude, cp.longitude], 17);
        }
    });
};

const exitCheckpointMode = () => {
    editingTarget.value = 'branch';
    editingCheckpoint.value = null;
    nextTick(() => {
        updateEditorOverlays();
        if (editorMap && form.value.latitude && form.value.longitude) {
            editorMap.panTo([form.value.latitude, form.value.longitude]);
        }
    });
};

const saveCheckpoint = () => {
    if (!selectedBranch.value) return;
    if (!checkpointForm.value.name?.trim()) {
        alert('Please provide a name for this checkpoint.');
        return;
    }

    isSavingCheckpoint.value = true;

    if (editingCheckpoint.value && editingCheckpoint.value.id) {
        router.put(`/admin/checkpoints/${editingCheckpoint.value.id}`, checkpointForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                isSavingCheckpoint.value = false;
                exitCheckpointMode();
            },
            onError: () => {
                isSavingCheckpoint.value = false;
            }
        });
    } else {
        router.post(`/admin/branches/${selectedBranch.value.id}/checkpoints`, checkpointForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                isSavingCheckpoint.value = false;
                exitCheckpointMode();
            },
            onError: () => {
                isSavingCheckpoint.value = false;
            }
        });
    }
};

const deleteCheckpoint = (cpId) => {
    if (confirm('Delete this checkpoint?')) {
        router.delete(`/admin/checkpoints/${cpId}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (editingCheckpoint.value && editingCheckpoint.value.id === cpId) {
                    exitCheckpointMode();
                }
            }
        });
    }
};

onMounted(() => {
    initVisualizerMap();
});

watch(() => props.branches, (newBranches) => {
    if (selectedBranch.value) {
        const updated = newBranches.find(b => b.id === selectedBranch.value.id);
        if (updated) {
            selectedBranch.value = updated;
        }
    }
    if (currentView.value === 'visualizer') {
        renderVisualizerBranches();
    } else if (currentView.value === 'editor') {
        updateEditorOverlays();
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
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Compass class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        Branch Spatial Map & Geofences
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Read-only overview of all branch geofences & checkpoints. Click 'Edit' or '+ Add New Branch' to modify boundaries on the map.</p>
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
                <div class="lg:col-span-4 flex flex-col gap-3 overflow-y-auto pr-1 sidebar-scroll">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        @click="flyToBranch(branch)"
                        :class="[
                            'p-4 rounded-2xl border transition-all cursor-pointer relative group',
                            selectedBranch?.id === branch.id
                                ? 'bg-blue-50/80 dark:bg-slate-900 border-blue-500 shadow-md shadow-blue-500/10 ring-1 ring-blue-500/40'
                                : 'bg-white dark:bg-slate-900/90 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 shadow-xs'
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2.5">
                                <div :class="[
                                    'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold',
                                    branch.geofence_type === 'polygon'
                                        ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400'
                                ]">
                                    <MapPin class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-300 transition">{{ branch.name }}</h4>
                                    <span class="text-[10px] text-slate-500 font-mono">ID: #{{ branch.id }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    @click.stop="openEditView(branch)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:bg-blue-50 dark:hover:bg-blue-600/20 hover:text-blue-600 dark:hover:text-blue-300 transition"
                                    title="Edit Branch & Draw Geofence"
                                >
                                    <Edit2 class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    @click.stop="deleteBranch(branch.id)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:bg-rose-50 dark:hover:bg-rose-500/20 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                    title="Delete Branch"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2 text-xs pt-2.5 border-t border-slate-100 dark:border-slate-800/80">
                            <!-- Quick Operating Hours Selector -->
                            <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200/80 dark:border-slate-800 flex flex-col gap-1">
                                <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                    <span class="flex items-center gap-1">
                                        <Clock class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                        Operating Schedule:
                                    </span>
                                </div>
                                <select
                                    :value="branch.shift_schedule_id ?? ''"
                                    @click.stop
                                    @change="quickAssignShift(branch, $event.target.value)"
                                    class="w-full text-xs font-semibold px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shadow-2xs"
                                >
                                    <option value="">Default (08:00 - 17:00, Grace: 15m)</option>
                                    <option v-for="sh in shifts" :key="sh.id" :value="sh.id">
                                        {{ sh.name }} ({{ sh.start_time }} - {{ sh.end_time }})
                                    </option>
                                </select>
                            </div>

                            <!-- Boundary & Checkpoint Pills -->
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 dark:text-slate-400">Boundary:</span>
                                <span :class="[
                                    'px-2 py-0.5 rounded-full font-bold text-[10px]',
                                    branch.geofence_type === 'polygon'
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                        : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                                ]">
                                    {{ branch.geofence_type === 'polygon' ? '⬡ Polygon' : `⭕ ${branch.radius_meters}m Radius` }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 dark:text-slate-400">Checkpoints:</span>
                                <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px]">
                                    🟡 {{ branch.checkpoints_count || 0 }} Checkpoint(s)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Full Interactive Visualizer Map (8 Cols) -->
                <div class="lg:col-span-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl relative flex flex-col">
                    <div class="absolute top-4 left-4 z-20 w-64 sm:w-80">
                        <div class="relative">
                            <div class="relative flex items-center">
                                <Search class="absolute left-3 w-3.5 h-3.5 text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    @focus="if (searchResults.length > 0) showSearchResults = true;"
                                    type="text"
                                    placeholder="Search location or paste GPS..."
                                    class="w-full pl-8 pr-7 py-1.5 bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 backdrop-blur-md rounded-xl text-xs text-slate-900 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="clearSearch"
                                    type="button"
                                    class="absolute right-2 p-0.5 rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-white"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                                <Loader2 v-if="isSearching" class="absolute right-7 w-3 h-3 animate-spin text-blue-500" />
                            </div>

                            <div
                                v-if="showSearchResults && searchResults.length > 0"
                                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl overflow-hidden z-30 divide-y divide-slate-100 dark:divide-slate-800 max-h-56 overflow-y-auto"
                            >
                                <div
                                    v-for="(item, idx) in searchResults"
                                    :key="idx"
                                    @click="selectSearchResult(item)"
                                    class="p-2 hover:bg-blue-50 dark:hover:bg-blue-900/30 cursor-pointer transition flex items-start gap-2"
                                >
                                    <div class="w-5 h-5 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 mt-0.5">
                                        <MapPin class="w-3 h-3" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ item.title }}</p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 line-clamp-1">{{ item.address }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="visualizerMap" class="w-full h-full min-h-[500px] z-10"></div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- VIEW 2: DEDICATED FULL-SCREEN MAP & GEOFENCE EDITOR       -->
        <!-- ========================================================= -->
        <div v-else class="space-y-4">
            <!-- Top Action Header -->
            <div class="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <button
                        @click="backToVisualizer"
                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition flex items-center gap-1.5 text-xs font-semibold"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        Back to Visualizer
                    </button>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ isEditing ? `Editing Branch: ${form.name}` : 'Create New Branch' }}</span>
                            <span v-if="editingTarget === 'checkpoint'" class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 text-[10px] font-bold">
                                🟡 Checkpoint Mode Active
                            </span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ editingTarget === 'checkpoint'
                                ? 'Configuring checkpoint boundary (Circle / Polygon) directly on the interactive map.'
                                : 'Configure the primary branch geofence boundary or select a checkpoint below.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="backToVisualizer"
                        class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="editingTarget === 'branch'"
                        type="button"
                        @click="saveBranch"
                        class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/20 transition flex items-center gap-1.5"
                    >
                        <CheckCircle2 class="w-4 h-4" />
                        {{ isEditing ? 'Save Branch' : 'Create Branch' }}
                    </button>
                </div>
            </div>

            <!-- 2-Column Editor Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 h-[calc(100vh-210px)] min-h-[580px]">
                <!-- Left: Form Controls (5 Cols) -->
                <div class="lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 overflow-y-auto space-y-4 shadow-xl sidebar-scroll">

                    <!-- ========================================================= -->
                    <!-- PANEL A: EDITING MAIN BRANCH PERIMETER                    -->
                    <!-- ========================================================= -->
                    <template v-if="editingTarget === 'branch'">
                        <!-- Branch Name -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. HRM Surabaya HQ"
                                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                            />
                        </div>

                        <!-- Branch Operating Hours (Shift Schedule) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Branch Operating Schedule
                                <span class="text-slate-400 font-normal text-[11px]">(Working hours &amp; Lateness rule)</span>
                            </label>
                            <select
                                v-model="form.shift_schedule_id"
                                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                            >
                                <option :value="null">Default Schedule (08:00 - 17:00, 15m Grace)</option>
                                <option v-for="sh in shifts" :key="sh.id" :value="sh.id">
                                    {{ sh.name }} ({{ sh.start_time }} - {{ sh.end_time }}, Grace: {{ sh.grace_minutes }}m)
                                </option>
                            </select>
                        </div>

                        <!-- Geofence Boundary Selector -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Primary Geofence Boundary Type</label>
                            <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl">
                                <button
                                    type="button"
                                    @click="switchGeofenceType('circle')"
                                    :class="[
                                        'py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5',
                                        form.geofence_type === 'circle'
                                            ? 'bg-blue-600 text-white shadow-md'
                                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
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
                                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    ⬡ Polygon (Free Draw)
                                </button>
                            </div>
                        </div>

                        <!-- Smart GPS / DMS Paste Helper -->
                        <div class="p-3.5 bg-blue-50/70 dark:bg-slate-950/80 border border-blue-200/80 dark:border-blue-900/40 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <label class="font-bold text-blue-900 dark:text-blue-300 flex items-center gap-1.5">
                                    <Crosshair class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                    Smart Coordinate / DMS Paste
                                </label>
                                <span class="text-[10px] text-slate-400">DMS or Decimal</span>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="pasteCoordinateInput"
                                    @keydown.enter.prevent="applyPastedCoordinates"
                                    type="text"
                                    placeholder="Paste e.g. 2°38'04.5&quot;S 121°06'27.0&quot;E or -2.634581, 121.107485"
                                    class="flex-1 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-mono text-slate-900 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-blue-500"
                                />
                                <button
                                    type="button"
                                    @click="applyPastedCoordinates"
                                    :disabled="!pasteCoordinateInput"
                                    class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition disabled:opacity-40 flex items-center gap-1 shadow-xs"
                                >
                                    <ClipboardPaste class="w-3.5 h-3.5" />
                                    Apply
                                </button>
                            </div>
                        </div>

                        <!-- Coordinates & Radius -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Latitude</label>
                                <input
                                    v-model.number="form.latitude"
                                    @input="onCoordinateChange"
                                    @change="onCoordinateChange"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Longitude</label>
                                <input
                                    v-model.number="form.longitude"
                                    @input="onCoordinateChange"
                                    @change="onCoordinateChange"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-blue-500"
                                />
                            </div>

                            <div v-if="form.geofence_type === 'circle'" class="col-span-2">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Allowed Radius (Meters)</label>
                                    <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">{{ form.radius_meters }} m</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="form.radius_meters"
                                        @input="updateEditorOverlays"
                                        type="range"
                                        min="10"
                                        max="500"
                                        step="5"
                                        class="w-full h-2 bg-slate-200 dark:bg-slate-950 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                    />
                                    <input
                                        v-model.number="form.radius_meters"
                                        @input="updateEditorOverlays"
                                        type="number"
                                        min="5"
                                        class="w-20 px-2 py-1 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-slate-200 font-mono text-right"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Polygon Tool Actions -->
                        <div v-if="form.geofence_type === 'polygon'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-emerald-500/30 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
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
                                        class="px-2.5 py-1 rounded-lg bg-rose-600/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 text-xs font-semibold hover:bg-rose-600/20"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                Click <b>'Draw on Map'</b>, click multi-points on the map to construct the geofenced perimeter, and double-click to finish closing the shape. You can drag the vertex points to adjust borders!
                            </p>

                            <textarea
                                v-model="form.polygon_coordinates"
                                rows="2"
                                placeholder="[[lat, lng], [lat, lng], [lat, lng]]"
                                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-[11px] font-mono text-slate-800 dark:text-slate-300 focus:outline-none focus:border-emerald-500"
                            ></textarea>
                        </div>

                        <!-- Checkpoints Manager (When Branch is saved/editing) -->
                        <div v-if="isEditing && selectedBranch" class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <MapPin class="w-4 h-4" />
                                    Multipoint Checkpoints ({{ selectedBranch.checkpoints?.length || 0 }})
                                </h4>

                                <button
                                    type="button"
                                    @click="startAddCheckpoint"
                                    class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold transition flex items-center gap-1 shadow-sm"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    Add Checkpoint
                                </button>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="cp in selectedBranch.checkpoints"
                                    :key="cp.id"
                                    class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between hover:border-amber-500/50 transition group"
                                >
                                    <div class="min-w-0 flex-1 pr-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-slate-900 dark:text-white truncate">{{ cp.name }}</span>
                                            <span :class="[
                                                'px-1.5 py-0.5 rounded text-[10px] font-bold font-mono shrink-0',
                                                cp.geofence_type === 'polygon'
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                                    : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                                            ]">
                                                {{ cp.geofence_type === 'polygon' ? '⬡ Polygon' : `⭕ ${cp.radius_meters}m` }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            GPS: {{ cp.latitude.toFixed(5) }}, {{ cp.longitude.toFixed(5) }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="startEditCheckpoint(cp)"
                                            class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition"
                                            title="Edit Checkpoint on Map"
                                        >
                                            <Edit2 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteCheckpoint(cp.id)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                                            title="Delete Checkpoint"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!selectedBranch.checkpoints || selectedBranch.checkpoints.length === 0" class="text-xs text-slate-500 text-center py-4 bg-slate-50/50 dark:bg-slate-950/50 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                                    No secondary checkpoints added yet. Click <b>'+ Add Checkpoint'</b> to configure multiple check-in gates/areas with circle radius or polygon boundaries.
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ========================================================= -->
                    <!-- PANEL B: DEDICATED CHECKPOINT CONFIGURATION               -->
                    <!-- ========================================================= -->
                    <template v-else-if="editingTarget === 'checkpoint'">
                        <!-- Checkpoint Sub-Header -->
                        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-amber-500 text-slate-950 flex items-center justify-center font-bold text-xs">
                                    🟡
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-amber-900 dark:text-amber-300">
                                        {{ editingCheckpoint ? `Edit Checkpoint: ${checkpointForm.name || 'Unnamed'}` : `New Checkpoint for ${selectedBranch?.name}` }}
                                    </h4>
                                    <p class="text-[10px] text-amber-700/80 dark:text-amber-400/80">Configure boundaries matching main branch options.</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="exitCheckpointMode"
                                class="text-xs font-semibold text-amber-800 dark:text-amber-300 hover:underline flex items-center gap-1"
                            >
                                <ArrowLeft class="w-3 h-3" />
                                Return to Branch
                            </button>
                        </div>

                        <!-- Checkpoint Name & Active Status -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Checkpoint Name / Label</label>
                            <input
                                v-model="checkpointForm.name"
                                type="text"
                                required
                                placeholder="e.g. Gate 2 - Logistics Loading Dock"
                                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-slate-200 focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <!-- Checkpoint Geofence Boundary Selector (Circle vs Polygon) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Checkpoint Boundary Type</label>
                            <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl">
                                <button
                                    type="button"
                                    @click="switchGeofenceType('circle')"
                                    :class="[
                                        'py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5',
                                        checkpointForm.geofence_type === 'circle'
                                            ? 'bg-amber-500 text-slate-950 font-bold shadow-md'
                                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    ⭕ Circular Radius
                                </button>
                                <button
                                    type="button"
                                    @click="switchGeofenceType('polygon')"
                                    :class="[
                                        'py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5',
                                        checkpointForm.geofence_type === 'polygon'
                                            ? 'bg-amber-500 text-slate-950 font-bold shadow-md'
                                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    ⬡ Polygon (Free Draw)
                                </button>
                            </div>
                        </div>

                        <!-- Smart GPS / DMS Paste for Checkpoint -->
                        <div class="p-3.5 bg-amber-500/10 dark:bg-slate-950/80 border border-amber-500/30 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <label class="font-bold text-amber-900 dark:text-amber-300 flex items-center gap-1.5">
                                    <Crosshair class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                    Smart Checkpoint Coordinate Paste
                                </label>
                                <span class="text-[10px] text-slate-400">DMS or Decimal</span>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="pasteCoordinateInput"
                                    @keydown.enter.prevent="applyPastedCoordinates"
                                    type="text"
                                    placeholder="Paste e.g. 2°38'04.5&quot;S 121°06'27.0&quot;E or -2.634581, 121.107485"
                                    class="flex-1 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-mono text-slate-900 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                                />
                                <button
                                    type="button"
                                    @click="applyPastedCoordinates"
                                    :disabled="!pasteCoordinateInput"
                                    class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold transition disabled:opacity-40 flex items-center gap-1 shadow-xs"
                                >
                                    <ClipboardPaste class="w-3.5 h-3.5" />
                                    Apply
                                </button>
                            </div>
                        </div>

                        <!-- Coordinates & Radius Inputs -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Latitude</label>
                                <input
                                    v-model.number="checkpointForm.latitude"
                                    @input="onCoordinateChange"
                                    @change="onCoordinateChange"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Longitude</label>
                                <input
                                    v-model.number="checkpointForm.longitude"
                                    @input="onCoordinateChange"
                                    @change="onCoordinateChange"
                                    type="number"
                                    step="any"
                                    required
                                    class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-slate-200 focus:outline-none focus:border-amber-500"
                                />
                            </div>

                            <div v-if="checkpointForm.geofence_type === 'circle'" class="col-span-2">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Allowed Checkpoint Radius (Meters)</label>
                                    <span class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400">{{ checkpointForm.radius_meters }} m</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="checkpointForm.radius_meters"
                                        @input="updateEditorOverlays"
                                        type="range"
                                        min="5"
                                        max="300"
                                        step="5"
                                        class="w-full h-2 bg-slate-200 dark:bg-slate-950 rounded-lg appearance-none cursor-pointer accent-amber-500"
                                    />
                                    <input
                                        v-model.number="checkpointForm.radius_meters"
                                        @input="updateEditorOverlays"
                                        type="number"
                                        min="5"
                                        class="w-20 px-2 py-1 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-slate-200 font-mono text-right"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Checkpoint Polygon Free-Draw Tools -->
                        <div v-if="checkpointForm.geofence_type === 'polygon'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-amber-500/30 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <PenTool class="w-4 h-4" />
                                    Checkpoint Polygon Free-Draw
                                </span>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="startDrawPolygonTool"
                                        class="px-2.5 py-1 rounded-lg bg-amber-500 text-slate-950 text-xs font-bold hover:bg-amber-600 transition shadow-sm"
                                    >
                                        🖌️ Draw on Map
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearPolygonTool"
                                        class="px-2.5 py-1 rounded-lg bg-rose-600/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 text-xs font-semibold hover:bg-rose-600/20"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                Click <b>'Draw on Map'</b>, click multi-points to create the specific checkpoint boundary, and double-click to close. Drag vertex points anytime to fine-tune!
                            </p>

                            <textarea
                                v-model="checkpointForm.polygon_coordinates"
                                rows="2"
                                placeholder="[[lat, lng], [lat, lng], [lat, lng]]"
                                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-[11px] font-mono text-slate-800 dark:text-slate-300 focus:outline-none focus:border-amber-500"
                            ></textarea>
                        </div>

                        <!-- Checkpoint Actions Footer -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2">
                            <button
                                type="button"
                                @click="exitCheckpointMode"
                                class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="saveCheckpoint"
                                :disabled="isSavingCheckpoint || !checkpointForm.name"
                                class="flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-lg shadow-amber-500/20 disabled:opacity-50"
                            >
                                <Check class="w-4 h-4" />
                                {{ editingCheckpoint ? 'Update Checkpoint' : 'Save Checkpoint' }}
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Right: Dedicated Interactive Drawing Map (7 Cols) -->
                <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl relative flex flex-col">
                    <!-- Status Badge -->
                    <div class="absolute top-4 right-4 z-20 flex items-center gap-2 bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 backdrop-blur-md px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 shadow-lg">
                        <template v-if="editingTarget === 'branch'">
                            <span v-if="form.geofence_type === 'polygon'" class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <PenTool class="w-3.5 h-3.5" />
                                Branch Polygon Active
                            </span>
                            <span v-else class="text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                                <Compass class="w-3.5 h-3.5" />
                                Branch Center Pin
                            </span>
                        </template>
                        <template v-else>
                            <span v-if="checkpointForm.geofence_type === 'polygon'" class="text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                <PenTool class="w-3.5 h-3.5" />
                                Checkpoint Polygon Free-Draw Active
                            </span>
                            <span v-else class="text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                <CircleDot class="w-3.5 h-3.5" />
                                Checkpoint Circle Active
                            </span>
                        </template>
                    </div>

                    <!-- Editor Map Location Search Bar -->
                    <div class="absolute top-4 left-16 z-20 w-64 sm:w-80">
                        <div class="relative">
                            <div class="relative flex items-center">
                                <Search class="absolute left-3 w-3.5 h-3.5 text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    @focus="if (searchResults.length > 0) showSearchResults = true;"
                                    type="text"
                                    :placeholder="editingTarget === 'checkpoint' ? 'Search checkpoint GPS/place...' : 'Search place or paste GPS...'"
                                    class="w-full pl-8 pr-7 py-1.5 bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 backdrop-blur-md rounded-xl text-xs text-slate-900 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="clearSearch"
                                    type="button"
                                    class="absolute right-2 p-0.5 rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-white"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                                <Loader2 v-if="isSearching" class="absolute right-7 w-3 h-3 animate-spin text-blue-500" />
                            </div>

                            <!-- Search Dropdown Results -->
                            <div
                                v-if="showSearchResults && searchResults.length > 0"
                                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl overflow-hidden z-30 divide-y divide-slate-100 dark:divide-slate-800 max-h-56 overflow-y-auto"
                            >
                                <div
                                    v-for="(item, idx) in searchResults"
                                    :key="idx"
                                    @click="selectSearchResult(item)"
                                    class="p-2 hover:bg-blue-50 dark:hover:bg-blue-900/30 cursor-pointer transition flex items-start gap-2"
                                >
                                    <div :class="[
                                        'w-5 h-5 rounded-md flex items-center justify-center shrink-0 mt-0.5',
                                        item.isCoordinate ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                    ]">
                                        <Crosshair v-if="item.isCoordinate" class="w-3 h-3" />
                                        <MapPin v-else class="w-3 h-3" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ item.title }}</p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 line-clamp-1">{{ item.address }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="editorMap" class="w-full h-full min-h-[500px] z-10"></div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
