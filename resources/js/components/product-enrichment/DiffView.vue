<script setup lang="ts">
defineProps<{
    original: unknown;
    proposed: unknown;
    type?: string;
}>();

function format(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (Array.isArray(value)) return value.join('\n• ');
    return String(value);
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <div
            class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50"
        >
            <div
                class="mb-1 text-xs font-medium tracking-wide text-gray-400 uppercase"
            >
                Original
            </div>
            <div
                class="text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-300"
            >
                {{ format(original) }}
            </div>
        </div>
        <div
            class="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-900/20"
        >
            <div
                class="mb-1 text-xs font-medium tracking-wide text-blue-400 uppercase"
            >
                Proposed
            </div>
            <div
                class="text-sm whitespace-pre-wrap text-gray-900 dark:text-gray-100"
            >
                {{ format(proposed) }}
            </div>
        </div>
    </div>
</template>
