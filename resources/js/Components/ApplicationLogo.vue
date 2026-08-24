<script setup>
defineOptions({
    inheritAttrs: false,
});

defineProps({
    variant: {
        type: String,
        default: 'full', // 'full' or 'icon'
    },
    alt: {
        type: String,
        default: 'Hartono Motor Group Logo',
    },
});
</script>

<template>
    <img
        v-if="variant === 'icon'"
        src="/images/favicon-64.png"
        :alt="alt"
        v-bind="$attrs"
        :class="[$attrs.class, 'object-contain select-none']"
        loading="eager"
    />
    <img
        v-else
        src="/images/company-logo.png"
        :alt="alt"
        v-bind="$attrs"
        :class="[$attrs.class, 'object-contain select-none max-w-full']"
        loading="eager"
    />
</template>
