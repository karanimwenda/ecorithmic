<script setup lang="ts">
defineProps<{
    tier: 'high' | 'medium' | 'low';
}>();

const tierConfig = {
    high: {
        label: 'High',
        classes:
            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    },
    medium: {
        label: 'Medium',
        classes:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    },
    low: {
        label: 'Low',
        classes:
            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    },
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="tierConfig[tier].classes"
    >
        {{ tierConfig[tier].label }}
    </span>
</template>
