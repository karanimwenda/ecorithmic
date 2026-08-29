<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { show as artifactShow } from '@/actions/App/Http/Controllers/ProductEnrichment/ExportArtifactController';

interface ExportSummary {
    included_products: number;
    excluded_products: number;
    included_fields: number;
    excluded_fields: number;
    excluded_reasons: Record<string, number>;
    message: string | null;
}

interface ExportProps {
    id: number;
    status: string;
    summary: ExportSummary | null;
    spreadsheet_url: string | null;
    images_url: string | null;
    manifest_url: string | null;
    created_at: string;
}

const props = defineProps<{ exportData: ExportProps }>();

if (['pending', 'processing'].includes(props.exportData.status)) {
    usePoll(3000, { only: ['exportData'] });
}
</script>

<template>
    <Head title="Export" />

    <div class="mx-auto max-w-2xl p-6">
        <h1 class="mb-2 text-2xl font-semibold text-gray-900 dark:text-white">
            Catalog Export
        </h1>
        <p class="mb-6 text-sm text-gray-500">
            Status:
            <span class="font-medium capitalize">{{ exportData.status }}</span>
        </p>

        <!-- Processing -->
        <div
            v-if="['pending', 'processing'].includes(exportData.status)"
            class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20"
        >
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Export in progress… Page refreshes automatically.
            </p>
        </div>

        <!-- Summary -->
        <div v-if="exportData.summary" class="mb-6">
            <div
                v-if="exportData.summary.message"
                class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
            >
                {{ exportData.summary.message }}
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div
                    class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ exportData.summary.included_products }}
                    </div>
                    <div class="text-sm text-gray-500">Products included</div>
                </div>
                <div
                    class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="text-2xl font-bold text-gray-400">
                        {{ exportData.summary.excluded_products }}
                    </div>
                    <div class="text-sm text-gray-500">Products excluded</div>
                </div>
                <div
                    class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="text-2xl font-bold text-green-600">
                        {{ exportData.summary.included_fields }}
                    </div>
                    <div class="text-sm text-gray-500">Fields included</div>
                </div>
                <div
                    class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="text-2xl font-bold text-gray-400">
                        {{ exportData.summary.excluded_fields }}
                    </div>
                    <div class="text-sm text-gray-500">Fields excluded</div>
                </div>
            </div>

            <!-- Exclusion reasons -->
            <div
                v-if="Object.keys(exportData.summary.excluded_reasons).length"
                class="mt-3"
            >
                <h3
                    class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Exclusion reasons:
                </h3>
                <ul class="list-inside list-disc text-sm text-gray-500">
                    <li
                        v-for="(count, reason) in exportData.summary
                            .excluded_reasons"
                        :key="reason"
                    >
                        {{ reason.replace(/_/g, ' ') }}: {{ count }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Downloads -->
        <div
            v-if="exportData.status === 'completed'"
            class="flex flex-wrap gap-3"
        >
            <a
                v-if="exportData.spreadsheet_url"
                :href="exportData.spreadsheet_url"
                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                download
                >⬇ Spreadsheet</a
            >
            <a
                v-if="exportData.images_url"
                :href="exportData.images_url"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                download
                >⬇ Images</a
            >
            <a
                v-if="exportData.manifest_url"
                :href="exportData.manifest_url"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                download
                >⬇ Manifest</a
            >
        </div>
    </div>
</template>
