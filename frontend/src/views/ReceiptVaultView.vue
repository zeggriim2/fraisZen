<template>
    <div class="workspace-page receipts-workspace">
        <div class="workspace-title">
            <div>
                <p class="eyebrow">DOSSIER FISCAL</p>
                <h2>Coffre-fort de justificatifs</h2>
                <p>
                    Centralisez vos pièces, repérez les éléments manquants et
                    contrôlez les données extraites.
                </p>
            </div>
            <select v-model="year" class="quiet-button" @change="resetAndLoad">
                <option v-for="value in years" :key="value" :value="value">
                    {{ value }}
                </option>
            </select>
        </div>

        <div v-if="!personStore.activePerson" class="empty-workspace">
            <AppIcon name="users" />
            <h3>Sélectionnez une personne</h3>
            <p>Le coffre-fort reste séparé pour chaque déclarant.</p>
        </div>
        <template v-else>
            <div class="month-overview">
                <div>
                    <span class="overview-icon blue"
                        ><AppIcon name="receipt"
                    /></span>
                    <div>
                        <strong>{{ vault?.withReceipt ?? 0 }}</strong
                        ><span>pièces archivées</span>
                    </div>
                </div>
                <div>
                    <span class="overview-icon peach"
                        ><AppIcon name="alert"
                    /></span>
                    <div>
                        <strong>{{ vault?.missing ?? 0 }}</strong
                        ><span>justificatifs manquants</span>
                    </div>
                </div>
                <div>
                    <span class="overview-icon mint"
                        ><AppIcon name="check"
                    /></span>
                    <div>
                        <strong>{{ completion }}%</strong
                        ><span>dossier documenté</span>
                    </div>
                </div>
            </div>

            <div class="directory-toolbar">
                <div class="directory-search">
                    <AppIcon name="search" /><input
                        v-model="search"
                        placeholder="Rechercher une dépense ou un fichier…"
                    />
                </div>
                <div class="segmented-control">
                    <button
                        :class="{ active: filter === 'all' }"
                        @click="filter = 'all'"
                    >
                        Tout</button
                    ><button
                        :class="{ active: filter === 'missing' }"
                        @click="filter = 'missing'"
                    >
                        À compléter</button
                    ><button
                        :class="{ active: filter === 'archived' }"
                        @click="filter = 'archived'"
                    >
                        Archivés
                    </button>
                </div>
            </div>

            <p
                v-if="error"
                class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"
            >
                {{ error }}
            </p>
            <div v-if="loading && items.length === 0" class="empty-workspace">
                <p>Chargement du coffre-fort…</p>
            </div>
            <InfiniteScrollList
                v-else
                :has-more="hasMore"
                :loading="loading"
                @load-more="loadMore"
            >
                <TransitionGroup name="receipt-list" tag="div" class="receipt-grid">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="receipt-card"
                >
                    <div class="receipt-card-head">
                        <span
                            class="overview-icon"
                            :class="item.receiptFilename ? 'mint' : 'peach'"
                            ><AppIcon
                                :name="
                                    item.receiptFilename ? 'receipt' : 'alert'
                                "
                        /></span>
                        <div>
                            <p class="eyebrow">
                                {{ item.typeLabel }} ·
                                {{ formatDate(item.date) }}
                            </p>
                            <h3>
                                {{
                                    item.description ||
                                    item.receiptOcrData?.merchant ||
                                    "Dépense sans libellé"
                                }}
                            </h3>
                        </div>
                        <strong>{{ formatAmount(item.amount) }}</strong>
                    </div>
                    <div v-if="item.receiptFilename" class="receipt-metadata">
                        <p>
                            <span>Fichier</span
                            ><strong>{{ item.receiptFilename }}</strong>
                        </p>
                        <p>
                            <span>OCR</span
                            ><strong>{{
                                ocrLabel(item.receiptOcrData?.status)
                            }}</strong>
                        </p>
                        <p v-if="item.receiptOcrData?.amount">
                            <span>Montant détecté</span
                            ><strong>{{
                                formatAmount(item.receiptOcrData.amount)
                            }}</strong>
                        </p>
                    </div>
                    <p v-else class="receipt-missing">
                        Aucune pièce n’est encore associée à cette dépense.
                    </p>
                    <footer>
                        <button
                            v-if="item.receiptFilename"
                            class="quiet-button"
                            @click="openReceipt(item.id)"
                        >
                            Consulter</button
                        ><label class="primary-action cursor-pointer"
                            ><span>{{
                                uploading === item.id
                                    ? "Analyse…"
                                    : item.receiptFilename
                                      ? "Remplacer"
                                      : "Ajouter une pièce"
                            }}</span
                            ><input
                                class="sr-only"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png,image/webp"
                                :disabled="uploading === item.id"
                                @change="(event) => upload(item.id, event)"
                        /></label>
                    </footer>
                </article>
                </TransitionGroup>
                <template #loading>
                    <span class="receipt-loading-indicator">
                        <i></i> Chargement de {{ pageSize }} justificatifs…
                    </span>
                </template>
            </InfiniteScrollList>
            <div
                v-if="!loading && items.length === 0"
                class="empty-workspace"
            >
                <AppIcon name="receipt" />
                <h3>Aucun résultat</h3>
                <p>Modifiez la recherche ou le filtre sélectionné.</p>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";
import { usePersonStore } from "@/stores/personStore";
import { expenseApi } from "@/api/expenseApi";
import { extractApiError } from "@/utils/apiError";
import AppIcon from "@/components/ui/AppIcon.vue";
import InfiniteScrollList from "@/components/ui/InfiniteScrollList.vue";
import type { ReceiptOcrData, ReceiptVault, ReceiptVaultItem } from "@/types";

const personStore = usePersonStore();
const year = ref(new Date().getFullYear());
const years = Array.from(
    { length: 6 },
    (_, index) => new Date().getFullYear() - index,
);
const vault = ref<ReceiptVault | null>(null);
const items = ref<ReceiptVaultItem[]>([]);
const loading = ref(false);
const uploading = ref<string | null>(null);
const error = ref<string | null>(null);
const search = ref("");
const filter = ref<"all" | "missing" | "archived">("all");
const page = ref(1);
const pageSize = 6;
const hasMore = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const completion = computed(() =>
    vault.value?.total
        ? Math.round((vault.value.withReceipt / vault.value.total) * 100)
        : 100,
);
async function load(reset = false) {
    if (!personStore.activePerson) {
        vault.value = null;
        items.value = [];
        hasMore.value = false;
        return;
    }
    if (loading.value) return;
    if (reset) {
        page.value = 1;
        items.value = [];
        hasMore.value = false;
    }
    const activeRequest = ++requestId;
    loading.value = true;
    error.value = null;
    try {
        const result = await expenseApi.getReceiptVault(
            personStore.activePerson.id,
            year.value,
            page.value,
            pageSize,
            filter.value,
            search.value.trim(),
        );
        if (activeRequest !== requestId) return;
        vault.value = result;
        items.value = reset ? result.items : [...items.value, ...result.items];
        hasMore.value = result.hasMore;
    } catch (caught) {
        if (activeRequest !== requestId) return;
        error.value = extractApiError(caught);
    } finally {
        if (activeRequest === requestId) loading.value = false;
    }
}
function resetAndLoad() {
    requestId++;
    loading.value = false;
    void load(true);
}
function loadMore() {
    if (!hasMore.value || loading.value) return;
    page.value++;
    void load();
}
async function upload(id: string, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploading.value = id;
    error.value = null;
    try {
        await expenseApi.uploadReceipt(id, file);
        resetAndLoad();
    } catch (caught) {
        error.value = extractApiError(caught);
    } finally {
        uploading.value = null;
        input.value = "";
    }
}
async function openReceipt(id: string) {
    try {
        const blob = await expenseApi.downloadReceipt(id);
        window.open(URL.createObjectURL(blob), "_blank", "noopener");
    } catch (caught) {
        error.value = extractApiError(caught);
    }
}
function formatAmount(value: number) {
    return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
    }).format(value);
}
function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR").format(
        new Date(`${value}T12:00:00`),
    );
}
function ocrLabel(status?: ReceiptOcrData["status"]) {
    return status === "completed"
        ? "Analysé"
        : status === "empty"
          ? "À vérifier"
          : "OCR indisponible";
}

watch(() => personStore.activePerson?.id, resetAndLoad);
watch(filter, resetAndLoad);
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(resetAndLoad, 300);
});
onMounted(resetAndLoad);
onBeforeUnmount(() => {
    requestId++;
    if (searchTimer) clearTimeout(searchTimer);
});
</script>
