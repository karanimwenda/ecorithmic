<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/ProductEnrichment/ImportController';

const form = useForm({
    spreadsheet: null as File | null,
    archive: null as File | null,
    cost_cap_usd: '',
});

function submit() {
    form.post(store.url(), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Import Products" />

    <div class="mx-auto max-w-2xl p-6">
        <h1 class="mb-6 text-2xl font-semibold text-gray-900 dark:text-white">
            Import Products
        </h1>

        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    for="spreadsheet"
                >
                    Product Spreadsheet
                    <span class="text-gray-400">(XLSX or CSV)</span>
                </label>
                <input
                    id="spreadsheet"
                    accept=".xlsx,.csv"
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    type="file"
                    @change="
                        (e) =>
                            (form.spreadsheet =
                                (e.target as HTMLInputElement).files?.[0] ??
                                null)
                    "
                />
                <p
                    v-if="form.errors.spreadsheet"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.spreadsheet }}
                </p>
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    for="archive"
                >
                    Photo Archive
                    <span class="text-gray-400">(ZIP, max 1GB)</span>
                </label>
                <input
                    id="archive"
                    accept=".zip"
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    type="file"
                    @change="
                        (e) =>
                            (form.archive =
                                (e.target as HTMLInputElement).files?.[0] ??
                                null)
                    "
                />
                <p v-if="form.errors.archive" class="mt-1 text-sm text-red-600">
                    {{ form.errors.archive }}
                </p>
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    for="cost_cap_usd"
                >
                    Processing Cost Cap (USD)
                    <span class="text-gray-400">— optional</span>
                </label>
                <input
                    id="cost_cap_usd"
                    v-model="form.cost_cap_usd"
                    class="block w-48 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    min="0"
                    placeholder="e.g. 5.00"
                    step="0.01"
                    type="number"
                />
                <p class="mt-1 text-xs text-gray-500">
                    Leave blank for uncapped processing.
                </p>
            </div>

            <div>
                <button
                    :disabled="form.processing"
                    class="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
                    type="submit"
                >
                    {{ form.processing ? 'Uploading…' : 'Upload & Validate' }}
                </button>
            </div>
        </form>
    </div>
</template>
