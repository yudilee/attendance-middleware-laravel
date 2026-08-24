<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps({
    links: Array,
    from: Number,
    to: Number,
    total: Number,
});
</script>

<template>
    <div v-if="total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-4 border-t border-slate-800 bg-slate-950/40 text-xs text-slate-400">
        <div class="flex items-center gap-1 font-medium">
            <span>Showing</span>
            <span class="font-bold text-slate-200 font-mono">{{ from || 0 }}</span>
            <span>to</span>
            <span class="font-bold text-slate-200 font-mono">{{ to || 0 }}</span>
            <span>of</span>
            <span class="font-bold text-blue-400 font-mono">{{ total }}</span>
            <span>records</span>
        </div>

        <div v-if="links && links.length > 3" class="flex items-center gap-1">
            <template v-for="(link, key) in links" :key="key">
                <div
                    v-if="link.url === null"
                    class="px-3 py-1.5 rounded-lg border border-slate-800 text-slate-600 cursor-not-allowed select-none"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                        link.active
                            ? 'bg-blue-600 text-white border-blue-500 shadow-sm shadow-blue-500/20'
                            : 'bg-slate-900 border-slate-800 text-slate-300 hover:bg-slate-800 hover:border-slate-700 hover:text-white'
                    ]"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
