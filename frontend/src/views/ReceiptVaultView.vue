<template>
    <div class="workspace-page receipts-workspace">
        <div class="workspace-title receipt-page-heading">
            <div>
                <p class="eyebrow">DOSSIER FISCAL</p>
                <h2>Factures et rapprochements</h2>
                <p>Retrouvez chaque passage et reliez-le à la bonne dépense.</p>
            </div>
            <div class="receipt-actions">
                <label class="receipt-year-filter">
                    <span>Année</span>
                    <select v-model="year" class="quiet-button">
                        <option v-for="value in years" :key="value" :value="value">{{ value }}</option>
                    </select>
                </label>
                <label class="primary-action receipt-upload-button" :class="{ disabled: uploading }">
                    <span>{{ uploading ? "Analyse en cours…" : "Importer une facture" }}</span>
                    <input class="sr-only" type="file" accept="application/pdf" :disabled="uploading" @change="upload" />
                </label>
            </div>
        </div>

        <div v-if="!personStore.activePerson" class="empty-workspace">
            <AppIcon name="users" />
            <h3>Sélectionnez une personne</h3>
            <p>Les justificatifs sont organisés séparément pour chaque déclarant.</p>
        </div>

        <template v-else>
            <p v-if="error" class="receipt-error" role="alert">{{ error }}</p>

            <div class="receipt-kpis">
                <div><span class="overview-icon blue"><AppIcon name="receipt" /></span><p><strong>{{ total }}</strong><span>factures</span></p></div>
                <div><span class="overview-icon mint"><AppIcon name="check" /></span><p><strong>{{ completed }}</strong><span>analysées</span></p></div>
                <div><span class="overview-icon peach"><AppIcon name="alert" /></span><p><strong>{{ failed }}</strong><span>à vérifier</span></p></div>
            </div>

            <div v-if="documents.length" class="receipt-browser">
                <aside class="receipt-document-list" aria-label="Factures importées">
                    <header><div><p class="eyebrow">FACTURES</p><h3>{{ total }} document{{ total > 1 ? "s" : "" }}</h3></div></header>
                    <InfiniteScrollList :has-more="hasMore" :loading="loading" @load-more="loadMore">
                        <TransitionGroup name="receipt-list" tag="div" class="receipt-document-stack">
                            <button v-for="document in documents" :key="document.id" class="receipt-document-item" :class="{ active: selected?.id === document.id }" @click="selectDocument(document.id)">
                                <span class="document-file-icon"><AppIcon name="receipt" /></span>
                                <span class="document-copy">
                                    <strong>{{ document.supplier || document.filename }}</strong>
                                    <small>{{ periodLabel(document) }}</small>
                                    <span class="document-status" :class="document.status">{{ statusLabel(document.status) }}</span>
                                </span>
                                <b>{{ document.totalAmount == null ? "—" : formatAmount(document.totalAmount) }}</b>
                            </button>
                        </TransitionGroup>
                        <template #loading><span class="receipt-loading-indicator"><i></i> Chargement…</span></template>
                    </InfiniteScrollList>
                </aside>

                <section class="receipt-detail-panel">
                    <template v-if="selected">
                        <header class="receipt-detail-header">
                            <div>
                                <p class="eyebrow">{{ selected.supplier || "FACTURE" }} · {{ selected.invoiceNumber || "SANS NUMÉRO" }}</p>
                                <h3>{{ selected.filename }}</h3>
                                <p>{{ selected.lines?.length || 0 }} passage{{ (selected.lines?.length || 0) > 1 ? "s" : "" }} détecté{{ (selected.lines?.length || 0) > 1 ? "s" : "" }}</p>
                            </div>
                            <div class="receipt-detail-actions">
                                <button class="quiet-button" @click="openDocument(selected.id)">Voir le PDF</button>
                                <button class="icon-danger-button" title="Supprimer la facture" aria-label="Supprimer la facture" @click="removeDocument(selected.id)">×</button>
                            </div>
                        </header>

                        <p v-if="selected.errorMessage" class="receipt-error">{{ selected.errorMessage }}</p>

                        <div class="receipt-lines-heading">
                            <span>Passage</span><span>Montant</span><span>Rattachement</span>
                        </div>
                        <div class="receipt-lines-list">
                            <article v-for="line in selected.lines" :key="line.id" class="receipt-line-card">
                                <div class="receipt-line-route">
                                    <time :datetime="line.date">{{ formatDate(line.date) }}</time>
                                    <strong>{{ line.departure || "Départ non détecté" }} <span>→</span> {{ line.arrival || "Arrivée non détectée" }}</strong>
                                    <small v-if="line.distanceKm">{{ line.distanceKm }} km</small>
                                </div>
                                <strong class="receipt-line-amount">{{ formatAmount(line.amountTtc) }}</strong>
                                <div class="receipt-line-match">
                                    <div v-if="activeMatch(line)" class="match-summary" :class="activeMatch(line)?.status">
                                        <span class="match-status-dot"></span>
                                        <span>
                                            <strong>{{ matchLabel(activeMatch(line)!.status) }}</strong>
                                            <small v-if="activeMatch(line)?.expense">{{ formatDate(activeMatch(line)!.expense!.date) }} · {{ formatAmount(activeMatch(line)!.expense!.amount) }}</small>
                                            <small v-else>{{ activeMatch(line)!.confidence }} % de confiance</small>
                                        </span>
                                    </div>
                                    <div v-else class="match-summary unmatched"><span class="match-status-dot"></span><span><strong>Non rattaché</strong><small>Choisissez une dépense</small></span></div>
                                    <div class="line-link-actions">
                                        <template v-if="activeMatch(line)?.status === 'suggested'">
                                            <button class="compact-action success" @click="review(activeMatch(line)!.id, 'confirm')">Confirmer</button>
                                            <button class="compact-action" @click="review(activeMatch(line)!.id, 'reject')">Ignorer</button>
                                        </template>
                                        <button class="compact-action primary" @click="openAttachModal(line)">{{ activeMatch(line) ? "Modifier" : "Rattacher" }}</button>
                                        <button v-if="activeMatch(line)?.status === 'confirmed'" class="compact-action" @click="review(activeMatch(line)!.id, 'reject')">Détacher</button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </template>
                    <div v-else class="receipt-detail-empty"><span class="overview-icon blue"><AppIcon name="receipt" /></span><h3>Sélectionnez une facture</h3><p>Ses passages et leurs rapprochements apparaîtront ici.</p></div>
                </section>
            </div>

            <div v-if="!loading && documents.length === 0" class="empty-workspace receipt-empty-state">
                <span class="overview-icon blue"><AppIcon name="receipt" /></span>
                <h3>Votre coffre est prêt</h3>
                <p>Importez une facture Fulli au format PDF pour détecter automatiquement ses passages.</p>
                <label class="primary-action cursor-pointer"><span>Importer ma première facture</span><input class="sr-only" type="file" accept="application/pdf" @change="upload" /></label>
            </div>
        </template>

        <Transition name="modal-fade">
            <div v-if="attachLine" class="receipt-modal-backdrop" @click.self="closeAttachModal">
                <section class="receipt-attach-modal" role="dialog" aria-modal="true" aria-labelledby="attach-title">
                    <header>
                        <div><p class="eyebrow">RATTACHER LE PASSAGE</p><h3 id="attach-title">Choisir une dépense</h3><p>Comparez la ligne détectée avec vos frais de péage.</p></div>
                        <button class="modal-close-button" aria-label="Fermer" @click="closeAttachModal">×</button>
                    </header>

                    <div class="source-line-card">
                        <span><small>LIGNE DE FACTURE</small><strong>{{ formatDate(attachLine.date) }}</strong><p>{{ attachLine.departure || "Départ inconnu" }} → {{ attachLine.arrival || "Arrivée inconnue" }}</p></span>
                        <b>{{ formatAmount(attachLine.amountTtc) }}</b>
                    </div>

                    <label class="candidate-search"><AppIcon name="search" /><input v-model="candidateSearch" autofocus placeholder="Rechercher une date, un trajet ou un montant" /></label>
                    <p class="candidate-help">Dépenses de péage trouvées dans une fenêtre de 31 jours.</p>

                    <div v-if="candidateLoading" class="candidate-loading"><span class="receipt-loading-indicator"><i></i> Recherche des dépenses…</span></div>
                    <div v-else class="candidate-list">
                        <button v-for="(candidate, index) in filteredCandidates" :key="candidate.id" class="candidate-card" :disabled="linkingExpenseId !== null" @click="attach(candidate.id)">
                            <span class="candidate-rank">{{ index + 1 }}</span>
                            <span class="candidate-copy"><strong>{{ candidate.description || "Frais de péage" }}</strong><small>{{ formatDate(candidate.date) }} · {{ candidate.departure || "Départ inconnu" }} → {{ candidate.arrival || "Arrivée inconnue" }}</small><small v-if="candidate.reasons.length" class="candidate-reasons">{{ candidate.reasons.join(" · ") }}</small></span>
                            <b>{{ formatAmount(candidate.amount) }}</b>
                            <span class="candidate-score" :class="{ strong: candidate.score >= 80 }"><strong>{{ candidate.score }} %</strong><small>{{ candidate.score >= 80 ? "Très proche" : "À vérifier" }}</small></span>
                            <span class="candidate-select">{{ linkingExpenseId === candidate.id ? "Rattachement…" : "Choisir" }}</span>
                        </button>
                        <div v-if="filteredCandidates.length === 0" class="empty-workspace compact"><p>Aucune dépense ne correspond à cette recherche.</p></div>
                    </div>
                    <footer class="create-expense-footer">
                        <span><strong>La dépense n’existe pas encore ?</strong><small>Créez un frais de péage prérempli avec cette ligne et rattachez-le automatiquement.</small></span>
                        <button class="quiet-button create-expense-button" :disabled="creatingExpense || linkingExpenseId !== null" @click="createExpenseFromLine">{{ creatingExpense ? "Création…" : "Créer la dépense" }}</button>
                    </footer>
                </section>
            </div>
        </Transition>
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePersonStore } from "@/stores/personStore";
import { expenseApi } from "@/api/expenseApi";
import { extractApiError } from "@/utils/apiError";
import AppIcon from "@/components/ui/AppIcon.vue";
import InfiniteScrollList from "@/components/ui/InfiniteScrollList.vue";
import type {
    ReceiptDocument,
    ReceiptAnalysisStatus,
    ReceiptMatchStatus,
    ReceiptLine,
    ReceiptMatch,
    ReceiptExpenseCandidate,
} from "@/types";

const personStore = usePersonStore();
const attachLine = ref<ReceiptLine | null>(null);
const candidates = ref<ReceiptExpenseCandidate[]>([]);
const candidateSearch = ref("");
const candidateLoading = ref(false);
const linkingExpenseId = ref<string | null>(null);
const creatingExpense = ref(false);
const year = ref(new Date().getFullYear());
const years = Array.from({ length: 6 }, (_, index) => new Date().getFullYear() - index);
const documents = ref<ReceiptDocument[]>([]);
const selected = ref<ReceiptDocument | null>(null);
const loading = ref(false);
const uploading = ref(false);
const error = ref<string | null>(null);
const page = ref(1);
const hasMore = ref(false);
const total = ref(0);

const completed = computed(() => documents.value.filter((document) => document.status === "completed").length);
const failed = computed(() => documents.value.filter((document) => document.status === "failed").length);
const filteredCandidates = computed(() => {
    const search = candidateSearch.value.trim().toLocaleLowerCase("fr");
    if (!search) return candidates.value;
    return candidates.value.filter((candidate) =>
        [candidate.date, candidate.description, candidate.departure, candidate.arrival, String(candidate.amount)]
            .some((value) => value?.toLocaleLowerCase("fr").includes(search)),
    );
});

async function load(reset = false) {
    if (!personStore.activePerson) {
        documents.value = [];
        selected.value = null;
        return;
    }
    if (reset) {
        page.value = 1;
        documents.value = [];
        selected.value = null;
    }
    loading.value = true;
    error.value = null;
    try {
        const result = await expenseApi.getReceiptDocuments(personStore.activePerson.id, year.value, page.value);
        documents.value = reset ? result.items : [...documents.value, ...result.items];
        total.value = result.total;
        hasMore.value = result.hasMore;
        if (!selected.value && documents.value[0]) await selectDocument(documents.value[0].id);
    } catch (caught) {
        error.value = extractApiError(caught);
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    if (hasMore.value && !loading.value) {
        page.value++;
        void load();
    }
}

async function upload(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || !personStore.activePerson) return;
    uploading.value = true;
    error.value = null;
    try {
        const document = await expenseApi.uploadReceiptDocument(personStore.activePerson.id, file);
        await load(true);
        await selectDocument(document.id);
    } catch (caught) {
        error.value = extractApiError(caught);
    } finally {
        uploading.value = false;
        input.value = "";
    }
}

async function selectDocument(id: string) {
    if (!personStore.activePerson) return;
    try {
        selected.value = await expenseApi.getReceiptDocument(id, personStore.activePerson.id);
    } catch (caught) {
        error.value = extractApiError(caught);
    }
}

async function review(matchId: string, decision: "confirm" | "reject") {
    if (!selected.value || !personStore.activePerson) return;
    try {
        await expenseApi.reviewReceiptMatch(selected.value.id, matchId, personStore.activePerson.id, decision);
        await selectDocument(selected.value.id);
    } catch (caught) {
        error.value = extractApiError(caught);
    }
}

const activeMatch = (line: ReceiptLine): ReceiptMatch | undefined => line.matches.find((match) => match.status !== "rejected");

async function openAttachModal(line: ReceiptLine) {
    if (!selected.value || !personStore.activePerson) return;
    attachLine.value = line;
    candidateSearch.value = "";
    candidateLoading.value = true;
    try {
        candidates.value = await expenseApi.getReceiptLineCandidates(selected.value.id, line.id, personStore.activePerson.id);
    } catch (caught) {
        error.value = extractApiError(caught);
        attachLine.value = null;
    } finally {
        candidateLoading.value = false;
    }
}

function closeAttachModal() {
    if (linkingExpenseId.value || creatingExpense.value) return;
    attachLine.value = null;
    candidates.value = [];
}

async function attach(expenseId: string) {
    if (!selected.value || !attachLine.value || !personStore.activePerson) return;
    linkingExpenseId.value = expenseId;
    try {
        await expenseApi.attachReceiptLine(selected.value.id, attachLine.value.id, expenseId, personStore.activePerson.id);
        const documentId = selected.value.id;
        closeAttachModal();
        attachLine.value = null;
        candidates.value = [];
        await selectDocument(documentId);
    } catch (caught) {
        error.value = extractApiError(caught);
    } finally {
        linkingExpenseId.value = null;
    }
}

async function createExpenseFromLine() {
    if (!selected.value || !attachLine.value || !personStore.activePerson) return;
    creatingExpense.value = true;
    try {
        const documentId = selected.value.id;
        await expenseApi.createTollExpenseFromReceiptLine(documentId, attachLine.value.id, personStore.activePerson.id);
        attachLine.value = null;
        candidates.value = [];
        await selectDocument(documentId);
    } catch (caught) {
        error.value = extractApiError(caught);
    } finally {
        creatingExpense.value = false;
    }
}

async function openDocument(id: string) {
    if (!personStore.activePerson) return;
    try {
        const blob = await expenseApi.downloadReceiptDocument(id, personStore.activePerson.id);
        window.open(URL.createObjectURL(blob), "_blank", "noopener");
    } catch (caught) {
        error.value = extractApiError(caught);
    }
}

async function removeDocument(id: string) {
    if (!personStore.activePerson) return;
    try {
        await expenseApi.deleteReceiptDocument(id, personStore.activePerson.id);
        await load(true);
    } catch (caught) {
        error.value = extractApiError(caught);
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === "Escape" && attachLine.value) closeAttachModal();
}

const formatAmount = (value: number) => new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(value);
const formatDate = (value: string) => new Intl.DateTimeFormat("fr-FR").format(new Date(`${value}T12:00:00`));
const periodLabel = (document: ReceiptDocument) => document.periodStart && document.periodEnd ? `${formatDate(document.periodStart)} – ${formatDate(document.periodEnd)}` : formatDate(document.createdAt.slice(0, 10));
const statusLabel = (status: ReceiptAnalysisStatus) => ({ pending: "En attente", processing: "Analyse", completed: "Analysée", failed: "À vérifier" })[status];
const matchLabel = (status: ReceiptMatchStatus) => ({ suggested: "Suggestion", confirmed: "Rattaché", rejected: "Rejeté" })[status];

watch(() => personStore.activePerson?.id, () => void load(true), { immediate: true });
watch(year, () => void load(true));
onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));
</script>
