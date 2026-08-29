<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    create as previewStore,
    store as bulkApproveStore,
} from '@/actions/App/Http/Controllers/ProductEnrichment/BulkApprovalController';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    rule: Record<string, string>;
    previewCount: number | null;
}>();

const rule = ref({ ...props.rule });

const confirmForm = useForm({
    rule: rule.value,
});

let debounceTimer: ReturnType<typeof setTimeout>;

watch(
    rule,
    (newRule) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get(
                previewStore({ query: { rule: newRule } }).url,
                {},
                { preserveState: true, replace: true },
            );
        }, 400);
    },
    { deep: true },
);

function confirm() {
    confirmForm.rule = rule.value;
    confirmForm.post(bulkApproveStore().url);
}
</script>

<template>
    <Head title="Bulk Approve" />

    <div class="mx-auto max-w-xl p-6">
        <h1 class="mb-6 text-2xl font-semibold text-gray-900 dark:text-white">
            Bulk Approval
        </h1>

        <div class="space-y-4">
            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Confidence Tier</label
                >
                <select
                    v-model="rule.confidence_tier"
                    class="block w-48 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">Any</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Attribute Code</label
                >
                <input
                    v-model="rule.attribute_code"
                    class="block w-48 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="e.g. color"
                    type="text"
                />
            </div>
        </div>

        <!-- Preview count -->
        <div
            class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700"
        >
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Fields that will be approved:
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ previewCount !== null ? previewCount : '—' }}
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button
                :disabled="confirmForm.processing || previewCount === 0"
                class="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
                @click="confirm"
            >
                {{
                    confirmForm.processing
                        ? 'Applying…'
                        : 'Approve All Matching'
                }}
            </button>
        </div>
    </div>
</template>
