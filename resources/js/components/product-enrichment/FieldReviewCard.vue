<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ConfidenceBadge from './ConfidenceBadge.vue';
import DiffView from './DiffView.vue';
import { store as approveStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductAttributeValueApprovalController';
import { store as rejectStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductAttributeValueRejectionController';
import { update as editStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductAttributeValueController';
import { store as regenerateStore } from '@/actions/App/Http/Controllers/ProductEnrichment/ProductAttributeValueRegenerationController';
import { ref } from 'vue';

interface SourceInfo {
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
    source: SourceInfo | null;
}

const props = defineProps<{
    originalValue: unknown;
    value: AttributeValue;
}>();

const approveForm = useForm({});
const rejectForm = useForm({});
const editForm = useForm({ value: String(props.value.display_value ?? '') });
const regenerateForm = useForm({ feedback: '' });

const isEditing = ref(false);
const isRegenerating = ref(false);

function approve() {
    approveForm.post(
        approveStore({ productAttributeValue: props.value.id }).url,
    );
}

function reject() {
    rejectForm.post(rejectStore({ productAttributeValue: props.value.id }).url);
}

function submitEdit() {
    editForm.patch(editStore({ productAttributeValue: props.value.id }).url, {
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}

function submitRegenerate() {
    regenerateForm.post(
        regenerateStore({ productAttributeValue: props.value.id }).url,
        {
            onSuccess: () => {
                isRegenerating.value = false;
                regenerateForm.reset();
            },
        },
    );
}

const statusColors: Record<string, string> = {
    pending: 'border-gray-200 dark:border-gray-700',
    approved: 'border-green-400 dark:border-green-700',
    rejected: 'border-red-300 dark:border-red-700',
    conflicted: 'border-amber-400 dark:border-amber-600',
};
</script>

<template>
    <div
        class="space-y-3 rounded-lg border p-4"
        :class="statusColors[value.review_status] ?? 'border-gray-200'"
    >
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-medium text-gray-900 dark:text-white"
                    >{{ value.attribute_name }}</span
                >
                <ConfidenceBadge :tier="value.confidence_tier" />
                <span
                    v-if="value.review_status !== 'pending'"
                    class="text-xs text-gray-500 capitalize"
                    >{{ value.review_status }}</span
                >
            </div>
            <span class="text-xs text-gray-400">{{
                value.origin.replace('_', ' ')
            }}</span>
        </div>

        <!-- Conflicted warning -->
        <div
            v-if="value.review_status === 'conflicted'"
            class="text-xs font-medium text-amber-600 dark:text-amber-400"
        >
            ⚠ Conflicting sources — select a value to resolve
        </div>

        <!-- Diff view -->
        <DiffView
            :original="originalValue"
            :proposed="value.display_value"
            :type="value.attribute_type"
        />

        <!-- Evidence -->
        <div
            v-if="value.evidence_quote"
            class="border-l-2 border-gray-200 pl-3 text-xs text-gray-500 italic dark:border-gray-700"
        >
            "{{ value.evidence_quote }}"
            <a
                v-if="value.source"
                :href="value.source.url"
                class="mt-0.5 block text-blue-500 not-italic hover:underline"
                rel="noopener noreferrer"
                target="_blank"
            >
                {{ value.source.domain }}
            </a>
        </div>

        <!-- Edit form -->
        <div v-if="isEditing" class="space-y-2">
            <textarea
                v-model="editForm.value"
                class="w-full rounded-md border border-gray-300 p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                rows="3"
            />
            <div class="flex gap-2">
                <button
                    :disabled="editForm.processing"
                    class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60"
                    @click="submitEdit"
                >
                    Save & Approve
                </button>
                <button
                    class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400"
                    @click="isEditing = false"
                >
                    Cancel
                </button>
            </div>
        </div>

        <!-- Regeneration form -->
        <div v-if="isRegenerating" class="space-y-2">
            <textarea
                v-model="regenerateForm.feedback"
                class="w-full rounded-md border border-gray-300 p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                placeholder="e.g. make it shorter, fix the color to navy…"
                rows="2"
            />
            <div class="flex gap-2">
                <button
                    :disabled="
                        regenerateForm.processing || !regenerateForm.feedback
                    "
                    class="rounded bg-purple-600 px-3 py-1 text-xs font-medium text-white hover:bg-purple-700 disabled:opacity-60"
                    @click="submitRegenerate"
                >
                    Regenerate
                </button>
                <button
                    class="rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400"
                    @click="isRegenerating = false"
                >
                    Cancel
                </button>
            </div>
        </div>

        <!-- Actions -->
        <div
            v-if="
                value.review_status === 'pending' ||
                value.review_status === 'conflicted'
            "
            class="flex flex-wrap gap-2"
        >
            <button
                :disabled="approveForm.processing"
                class="rounded bg-green-100 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-200 disabled:opacity-60 dark:bg-green-900/30 dark:text-green-300"
                @click="approve"
            >
                ✓ Approve
            </button>
            <button
                :disabled="rejectForm.processing"
                class="rounded bg-red-100 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-200 disabled:opacity-60 dark:bg-red-900/30 dark:text-red-300"
                @click="reject"
            >
                ✗ Reject
            </button>
            <button
                class="rounded bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                @click="isEditing = !isEditing"
            >
                ✎ Edit
            </button>
            <button
                v-if="value.is_regeneratable"
                class="rounded bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-300"
                @click="isRegenerating = !isRegenerating"
            >
                ↻ Regenerate
            </button>
        </div>
    </div>
</template>
