<script setup lang="ts">
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import { store as confirmStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ImportConfirmationController';

interface ValidationReport {
    matched: number;
    unmatched_rows: string[];
    unmatched_photos: string[];
    duplicate_skus: string[];
    unreadable_rows: number[];
}

interface ImportProps {
    id: number;
    status: string;
    validation_report: ValidationReport | null;
    estimated_cost_usd: string;
    processing_cost_usd: string;
    cost_cap_usd: string | null;
    row_count: number;
    matched_row_count: number;
    unmatched_row_count: number;
    unmatched_photo_count: number;
    duplicate_sku_count: number;
    unreadable_file_count: number;
    confirmed_at: string | null;
    created_at: string;
}

const props = defineProps<{ importData: ImportProps }>();

const confirmForm = useForm({});

// Poll for progress updates while processing (FR-017: visible within a few seconds)
if (['confirmed', 'processing'].includes(props.importData.status)) {
    usePoll(3000, { only: ['importData'] });
}

function confirm() {
    confirmForm.post(confirmStore({ import: props.importData.id }).url);
}

const statusLabels: Record<string, string> = {
    pending_validation: 'Pending Validation',
    rejected: 'Rejected',
    confirmed: 'Confirmed',
    processing: 'Processing…',
    completed: 'Completed',
    halted_cost_cap: 'Halted (cost cap reached)',
};
</script>

<template>
    <Head title="Validation Report" />

    <div class="mx-auto max-w-3xl p-6">
        <h1 class="mb-2 text-2xl font-semibold text-gray-900 dark:text-white">
            Validation Report
        </h1>
        <p class="mb-6 text-sm text-gray-500">
            Status:
            <span class="font-medium">{{
                statusLabels[importData.status] ?? importData.status
            }}</span>
        </p>

        <!-- Counts -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ importData.row_count }}
                </div>
                <div class="text-sm text-gray-500">Total rows</div>
            </div>
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="text-2xl font-bold text-green-600">
                    {{ importData.matched_row_count }}
                </div>
                <div class="text-sm text-gray-500">Matched to photo</div>
            </div>
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div
                    class="text-2xl font-bold"
                    :class="
                        importData.unmatched_row_count > 0
                            ? 'text-amber-600'
                            : 'text-gray-400'
                    "
                >
                    {{ importData.unmatched_row_count }}
                </div>
                <div class="text-sm text-gray-500">Rows without photo</div>
            </div>
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div
                    class="text-2xl font-bold"
                    :class="
                        importData.unmatched_photo_count > 0
                            ? 'text-amber-600'
                            : 'text-gray-400'
                    "
                >
                    {{ importData.unmatched_photo_count }}
                </div>
                <div class="text-sm text-gray-500">Photos without row</div>
            </div>
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div
                    class="text-2xl font-bold"
                    :class="
                        importData.duplicate_sku_count > 0
                            ? 'text-red-600'
                            : 'text-gray-400'
                    "
                >
                    {{ importData.duplicate_sku_count }}
                </div>
                <div class="text-sm text-gray-500">Duplicate SKUs</div>
            </div>
            <div
                class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Estimated cost
                </div>
                <div class="text-lg font-bold text-gray-900 dark:text-white">
                    ${{ Number(importData.estimated_cost_usd).toFixed(4) }}
                </div>
                <div
                    v-if="importData.cost_cap_usd"
                    class="text-xs text-gray-400"
                >
                    Cap: ${{ Number(importData.cost_cap_usd).toFixed(2) }}
                </div>
            </div>
        </div>

        <!-- Rejected import -->
        <div
            v-if="importData.status === 'rejected'"
            class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20"
        >
            <p class="font-medium text-red-700 dark:text-red-400">
                Import rejected: more than 50% of rows have no matching photo.
            </p>
            <p class="mt-1 text-sm text-red-600">
                Please upload a corrected file as a new import.
            </p>
        </div>

        <!-- Confirm button -->
        <div
            v-if="importData.status === 'pending_validation'"
            class="mb-6 flex gap-3"
        >
            <button
                :disabled="confirmForm.processing"
                class="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
                @click="confirm"
            >
                {{ confirmForm.processing ? 'Confirming…' : 'Confirm Import' }}
            </button>
        </div>

        <!-- Processing progress -->
        <div
            v-if="['confirmed', 'processing'].includes(importData.status)"
            class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Processing products in the background… This page refreshes
                automatically.
            </p>
            <div
                v-if="importData.processing_cost_usd"
                class="mt-1 text-xs text-blue-500"
            >
                Cost so far: ${{
                    Number(importData.processing_cost_usd).toFixed(4)
                }}
            </div>
        </div>

        <!-- Halted -->
        <div
            v-if="importData.status === 'halted_cost_cap'"
            class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20"
        >
            <p class="font-medium text-amber-700 dark:text-amber-400">
                Processing halted: cost cap of ${{ importData.cost_cap_usd }}
                reached.
            </p>
            <p class="mt-1 text-sm text-amber-600">
                Products completed so far are available for review.
            </p>
        </div>

        <!-- Validation issues -->
        <div
            v-if="importData.validation_report?.duplicate_skus?.length"
            class="mb-4"
        >
            <h3
                class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Duplicate SKUs in this upload
            </h3>
            <ul class="list-inside list-disc text-sm text-red-600">
                <li
                    v-for="sku in importData.validation_report.duplicate_skus"
                    :key="sku"
                >
                    {{ sku }}
                </li>
            </ul>
        </div>
    </div>
</template>
