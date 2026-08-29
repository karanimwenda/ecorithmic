<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FieldReviewCard from '@/components/product-enrichment/FieldReviewCard.vue';
import { store as approveProductStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductApprovalController';
import { update as updateAssetStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductAssetController';

interface Source {
    url: string;
    domain: string;
    trust_classification: string;
}

interface AttributeValue {
    id: number;
    attribute_code: string;
    attribute_name: string;
    attribute_type: string;
    display_value: unknown;
    origin: string;
    confidence_tier: 'high' | 'medium' | 'low';
    review_status: string;
    conflict_group_id: string | null;
    evidence_quote: string | null;
    is_regeneratable: boolean;
    source: Source | null;
}

interface Asset {
    id: number;
    collection_name: string;
    file_name: string;
    url: string;
    review_status: string;
    quality_flags: Record<string, boolean>;
    is_primary: boolean;
}

interface Product {
    id: number;
    sku: string;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    product: Product;
    attributeValues: Record<string, AttributeValue[]>;
    assets: Asset[];
}>();

const approveAllForm = useForm({});
const setPrimaryForms: Record<number, ReturnType<typeof useForm>> = {};

// Create a form for each asset
props.assets.forEach((asset) => {
    setPrimaryForms[asset.id] = useForm({ is_primary: true });
});

function approveAll() {
    approveAllForm.post(approveProductStore({ product: props.product.id }).url);
}

function setPrimary(assetId: number) {
    const form = setPrimaryForms[assetId];
    if (form) {
        form.patch(updateAssetStore({ productAsset: assetId }).url);
    }
}

const originalValues: Record<string, unknown> = {};
// First manager value per attribute = the original uploaded value
Object.entries(props.attributeValues).forEach(([code, values]) => {
    const managerValue = values.find((v) => v.origin === 'manager');
    originalValues[code] = managerValue?.display_value ?? null;
});
</script>

<template>
    <Head :title="`Review: ${product.sku}`" />

    <div class="mx-auto max-w-4xl p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Review: {{ product.sku }}
                </h1>
                <p class="text-sm text-gray-500">
                    Updated {{ product.updated_at }}
                </p>
            </div>
            <button
                :disabled="approveAllForm.processing"
                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
                @click="approveAll"
            >
                Approve All Pending
            </button>
        </div>

        <!-- Attribute values -->
        <div class="mb-8 space-y-4">
            <template v-for="(values, code) in attributeValues" :key="code">
                <FieldReviewCard
                    v-for="value in values"
                    :key="value.id"
                    :original-value="originalValues[code]"
                    :value="value"
                />
            </template>
        </div>

        <!-- Assets -->
        <div v-if="assets.length" class="mt-8">
            <h2 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                Product Images
            </h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div
                    v-for="asset in assets"
                    :key="asset.id"
                    class="relative overflow-hidden rounded-lg border"
                    :class="
                        asset.is_primary
                            ? 'border-blue-500'
                            : 'border-gray-200 dark:border-gray-700'
                    "
                >
                    <img
                        :alt="asset.file_name"
                        :src="asset.url"
                        class="h-40 w-full bg-gray-50 object-contain dark:bg-gray-800"
                    />
                    <div class="p-2 text-xs">
                        <div class="truncate text-gray-500">
                            {{ asset.collection_name }}
                        </div>
                        <div
                            v-if="asset.is_primary"
                            class="font-medium text-blue-500"
                        >
                            Primary
                        </div>
                        <div
                            v-if="asset.quality_flags?.busy_background"
                            class="text-amber-500"
                        >
                            ⚠ Busy background
                        </div>
                        <div
                            v-if="asset.quality_flags?.low_resolution"
                            class="text-amber-500"
                        >
                            ⚠ Low resolution
                        </div>
                        <button
                            v-if="
                                asset.collection_name === 'original' &&
                                !asset.is_primary
                            "
                            class="mt-1 text-blue-500 hover:underline"
                            @click="setPrimary(asset.id)"
                        >
                            Set as primary
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
