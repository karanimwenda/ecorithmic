<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store as exportStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ExportController';
import { Button } from '@/components/ui/button';

const form = useForm({});

function createExport() {
    form.post(exportStore().url);
}
</script>

<template>
    <Head title="Export Catalog" />

    <div class="mx-auto max-w-xl p-6">
        <h1 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">
            Export Catalog
        </h1>
        <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
            Export all currently-approved products and fields as a spreadsheet,
            image package, and provenance manifest.
        </p>

        <Button
            :disabled="form.processing"
            variant="default"
            @click="createExport"
        >
            {{ form.processing ? 'Starting export…' : 'Generate Export' }}
        </Button>
    </div>
</template>
