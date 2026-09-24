<template>
    <div>
        <slot />
        <div ref="sentinel" class="infinite-scroll-sentinel" aria-live="polite">
            <slot v-if="loading" name="loading">
                <span>Chargement…</span>
            </slot>
            <button
                v-else-if="hasMore"
                type="button"
                class="quiet-button"
                @click="requestMore"
            >
                {{ loadMoreLabel }}
            </button>
            <slot v-else name="complete" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = withDefaults(
    defineProps<{
        hasMore: boolean;
        loading: boolean;
        rootMargin?: string;
        loadMoreLabel?: string;
    }>(),
    {
        rootMargin: "600px 0px",
        loadMoreLabel: "Afficher la suite",
    },
);
const emit = defineEmits<{ loadMore: [] }>();
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

function requestMore() {
    if (props.hasMore && !props.loading) emit("loadMore");
}

function observe() {
    observer?.disconnect();
    if (!sentinel.value || typeof IntersectionObserver === "undefined") return;
    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry?.isIntersecting) requestMore();
        },
        { rootMargin: props.rootMargin },
    );
    observer.observe(sentinel.value);
}

watch(() => props.rootMargin, observe);
onMounted(observe);
onBeforeUnmount(() => observer?.disconnect());
</script>
