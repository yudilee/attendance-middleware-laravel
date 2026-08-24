<script setup>
import { AlertTriangle, X } from 'lucide-vue-next';

defineProps({
    show: Boolean,
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, default: 'Are you sure you want to proceed with this action?' },
    confirmText: { type: String, default: 'Confirm' },
    confirmColor: { type: String, default: 'bg-rose-600 hover:bg-rose-500 text-white' },
    loading: Boolean,
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl relative text-center">
            <button @click="$emit('cancel')" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                <X class="w-5 h-5" />
            </button>

            <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-3">
                <AlertTriangle class="w-6 h-6" />
            </div>

            <h3 class="text-base font-bold text-white mb-1.5">{{ title }}</h3>
            <p class="text-xs text-slate-400 mb-5 leading-relaxed">{{ message }}</p>

            <div class="flex gap-3">
                <button
                    @click="$emit('cancel')"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                >
                    Cancel
                </button>
                <button
                    @click="$emit('confirm')"
                    :disabled="loading"
                    type="button"
                    :class="['flex-1 py-2.5 rounded-xl text-xs font-bold transition shadow-lg disabled:opacity-50', confirmColor]"
                >
                    {{ loading ? 'Processing...' : confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>
