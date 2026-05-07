<script setup lang="ts">
import {
    destroyLog,
    destroyLogs,
    index,
    markRead,
    resetToken,
} from '@/actions/App/Http/Controllers/WebhookController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import app from '@/routes/app';
import { receive as webhookReceiveUrl } from '@/routes/webhook';
import {
    Head,
    InfiniteScroll,
    router,
    setLayoutProps,
    usePoll,
} from '@inertiajs/vue3';
import {
    Check,
    Clock,
    Copy,
    Eye,
    EyeOff,
    Globe,
    Inbox,
    Loader2,
    RefreshCw,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';

const listContainerEl = ref<HTMLDivElement | null>(null);
const selectedItemEl = ref<HTMLDivElement | null>(null);

interface Webhook {
    id: number;
    name: string;
    slug: string;
    token: string;
    created_at: string;
}

interface WebhookLogSummary {
    sqid: string;
    method: string;
    ip_address: string | null;
    created_at: string;
    read_at: string | null;
}

interface WebhookLogDetail {
    sqid: string;
    method: string;
    ip_address: string | null;
    user_agent: string | null;
    headers: Record<string, string>;
    query_params: Record<string, string> | null;
    payload: string | null;
    created_at: string;
    read_at: string | null;
}

interface Paginator {
    data: WebhookLogSummary[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

const { webhook, logs, logSelected } = defineProps<{
    webhook: Webhook;
    logs: Paginator;
    logSelected?: WebhookLogDetail;
}>();

const latestLogSqid = ref<string | null>(null);

setLayoutProps({
    breadcrumbs: [
        { title: 'Webhooks', href: index() },
        { title: webhook.name, href: app.webhooks.show(webhook).url },
    ],
});

function markAsRead(sqid: string | null) {
    if (!sqid) {
        return;
    }

    const log = logs.data.find((l) => l.sqid === sqid);

    if (!log || log.read_at) {
        return;
    }

    router
        .optimistic<{ logs: Paginator }>((props) => ({
            logs: {
                ...props.logs,
                data: props.logs.data.map((l: WebhookLogSummary) =>
                    l.sqid === sqid
                        ? { ...l, read_at: new Date().toISOString() }
                        : l,
                ),
            },
        }))
        .patch(
            markRead({ slug: webhook.slug, log: sqid }).url,
            {},
            {
                preserveScroll: true,
                only: ['logSelected'],
            },
        );
}

function openLog(id: string) {
    router.visit(
        app.webhooks.show({
            slug: webhook.slug,
            log: id,
        }).url,
        {
            preserveState: true,
            preserveScroll: true,
            only: ['logSelected'],
            onSuccess: () => {
                markAsRead(id);
            },
        },
    );
}

onMounted(() => {
    if (logSelected?.sqid) {
        markAsRead(logSelected.sqid);
        nextTick(() => {
            if (selectedItemEl.value && listContainerEl.value) {
                const container = listContainerEl.value;
                const item = selectedItemEl.value;
                container.scrollTop =
                    item.offsetTop -
                    container.clientHeight / 2 +
                    item.clientHeight / 2;
            }
        });
    }
});

usePoll(3000, {
    onSuccess: (response) => {
        const props = response.props as { logs?: { data: { sqid: string }[] } };
        const firstSqid = props.logs?.data?.[0]?.sqid ?? null;

        if (firstSqid !== latestLogSqid.value) {
            latestLogSqid.value = firstSqid;
        }
    },
});

const showNewBadge = ref(false);

watch(latestLogSqid, (newVal, oldVal) => {
    if (
        (newVal &&
            newVal !== oldVal &&
            oldVal !== null &&
            listContainerEl.value?.scrollTop) ||
        0 > 200
    ) {
        showNewBadge.value = true;
    }
});

function dismissNewBadge() {
    showNewBadge.value = false;
    listContainerEl.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

const STORAGE_KEY = 'webhook-url-visible';
const urlVisible = ref(localStorage.getItem(STORAGE_KEY) === 'true');

function toggleUrlVisible() {
    urlVisible.value = !urlVisible.value;
    localStorage.setItem(STORAGE_KEY, String(urlVisible.value));
}

const copiedUrl = ref(false);
const copiedPayload = ref(false);

const methodColors: Record<string, string> = {
    GET: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
    POST: 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20',
    PUT: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border-yellow-500/20',
    PATCH: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
    DELETE: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
    HEAD: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
    OPTIONS:
        'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20',
};

function methodColor(method: string) {
    return (
        methodColors[method.toUpperCase()] ??
        'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20'
    );
}

function formatDate(date: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(date));
}

function formatDateShort(date: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        day: '2-digit',
        month: '2-digit',
    }).format(new Date(date));
}

function tryParseJson(value: string | null): unknown | null {
    if (!value) {
        return null;
    }

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function copyUrl() {
    navigator.clipboard.writeText(webhookUrl);
    copiedUrl.value = true;
    setTimeout(() => (copiedUrl.value = false), 2000);
}

function deleteLog(log: WebhookLogSummary) {
    const isSelected = logSelected?.sqid === log.sqid;
    const currentIndex = logs.data.findIndex((l) => l.sqid === log.sqid);
    const next =
        logs.data[currentIndex + 1] ?? logs.data[currentIndex - 1] ?? null;

    if (isSelected) {
        openLog(next?.sqid ?? null);
    }

    router
        .optimistic<{ logs: Paginator }>((props) => ({
            logs: {
                ...props.logs,
                data: props.logs.data.filter(
                    (l: WebhookLogSummary) => l.sqid !== log.sqid,
                ),
            },
        }))
        .delete(destroyLog({ slug: webhook.slug, log: log.sqid }).url, {
            preserveScroll: true,
            only: ['logSelected'],
        });
}

const confirmDeleteAll = ref(false);
const confirmResetToken = ref(false);

function doResetToken() {
    confirmResetToken.value = false;
    router.patch(
        resetToken({ slug: webhook.slug }).url,
        {},
        {
            preserveState: false,
        },
    );
}

function deleteAllLogs() {
    confirmDeleteAll.value = true;
}

function doDeleteAllLogs() {
    confirmDeleteAll.value = false;
    router
        .optimistic<{ logs: Paginator }>((props) => ({
            logs: { ...props.logs, data: [] },
        }))
        .delete(destroyLogs({ slug: webhook.slug }).url, {
            preserveScroll: true,
            only: ['logs'],
        });
}

function copyPayload() {
    if (!logSelected?.payload) {
        return;
    }

    navigator.clipboard.writeText(logSelected.payload);

    copiedPayload.value = true;
    setTimeout(() => (copiedPayload.value = false), 2000);
}

const webhookUrl = `${window.location.origin}${webhookReceiveUrl({ slug: webhook.slug, token: webhook.token }).url}`;

const hasQueryParams = computed(
    () =>
        logSelected?.query_params &&
        Object.keys(logSelected.query_params).length > 0,
);

const parsedPayload = computed(() =>
    tryParseJson(logSelected?.payload ?? null),
);

const unreadCount = computed(
    () => logs.data.filter((l) => !l.read_at).length,
);

const pageTitle = computed(() =>
    unreadCount.value > 0
        ? `(${unreadCount.value}) ${webhook.name}`
        : webhook.name,
);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
        <div
            class="flex shrink-0 items-center gap-3 border-b border-border px-4 py-3"
        >
            <Globe class="size-4 shrink-0 text-muted-foreground" />
            <code
                class="flex-1 truncate font-mono text-sm transition-all"
                :class="!urlVisible && 'blur-xs select-none'"
                >{{ webhookUrl }}</code
            >
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0 text-muted-foreground"
                            @click="toggleUrlVisible"
                        >
                            <EyeOff v-if="urlVisible" class="size-4" />
                            <Eye v-else class="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ urlVisible ? 'Ocultar URL' : 'Mostrar URL' }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0"
                            @click="copyUrl"
                        >
                            <Check v-if="copiedUrl" class="size-4 text-green-500" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ copiedUrl ? 'Copiado!' : 'Copiar URL' }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0 text-muted-foreground"
                            @click="confirmResetToken = true"
                        >
                            <RefreshCw class="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Gerar novo token</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>

        <!-- Empty state -->
        <div
            v-if="logs.data.length === 0"
            class="flex flex-1 flex-col items-center justify-center gap-4 text-center"
        >
            <Inbox class="size-12 text-muted-foreground/40" />
            <div>
                <p class="font-medium">Aguardando requisições</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Envie uma requisição para a URL acima.
                </p>
            </div>
        </div>

        <!-- Split panel -->
        <div v-else class="flex min-h-0 flex-1 overflow-hidden">
            <!-- Left: request list -->
            <div
                class="flex w-72 shrink-0 flex-col overflow-hidden border-r border-border"
            >
                <!-- List header with delete all -->
                <div
                    class="flex shrink-0 items-center justify-between border-b border-border px-3 py-2"
                >
                    <span class="text-xs font-medium text-muted-foreground"
                        >Requisições</span
                    >
                    <button
                        type="button"
                        class="rounded p-0.5 text-xs text-muted-foreground/50 transition-colors hover:text-destructive"
                        title="Apagar todas"
                        @click="deleteAllLogs"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>

                <div
                    ref="listContainerEl"
                    class="relative flex-1 overflow-y-auto"
                >
                    <!-- Floating new-request badge -->
                    <div
                        v-if="showNewBadge"
                        class="pointer-events-none sticky top-2 z-10 flex justify-center"
                    >
                        <button
                            type="button"
                            class="pointer-events-auto flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500 px-3 py-1 text-[11px] font-semibold text-white shadow-lg transition-colors hover:bg-green-600"
                            @click="dismissNewBadge"
                        >
                            <span class="size-1.5 rounded-full bg-white/70" />
                            ↑ Nova requisição
                        </button>
                    </div>

                    <InfiniteScroll data="logs">
                        <div
                            v-for="log in logs.data"
                            :key="log.sqid"
                            :ref="
                                (el) => {
                                    if (log.sqid === logSelected?.sqid)
                                        selectedItemEl = el as HTMLDivElement;
                                }
                            "
                            class="group/log cursor-pointer border-b border-border px-3 py-3 transition-colors hover:bg-accent/40"
                            :class="[
                                logSelected?.sqid === log.sqid
                                    ? 'bg-accent'
                                    : '',
                                !log.read_at && logSelected?.sqid !== log.sqid
                                    ? 'bg-green-50 dark:bg-green-900/30'
                                    : '',
                            ]"
                            @click="openLog(log.sqid)"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="!log.read_at"
                                    class="size-1.5 shrink-0 rounded-full bg-green-500 dark:bg-green-400"
                                />
                                <span
                                    :class="methodColor(log.method)"
                                    class="inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 font-mono text-[11px] font-bold uppercase"
                                >
                                    {{ log.method }}
                                </span>
                                <span
                                    class="truncate font-mono text-xs text-muted-foreground"
                                >
                                    /{{ webhook.slug }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 flex items-center gap-2 text-[11px] text-muted-foreground"
                            >
                                <Clock class="size-3 shrink-0" />
                                <span>{{
                                    formatDateShort(log.created_at)
                                }}</span>
                                <span v-if="log.ip_address" class="truncate"
                                    >· {{ log.ip_address }}</span
                                >
                                <button
                                    type="button"
                                    class="ml-auto shrink-0 rounded p-0.5 text-muted-foreground/50 opacity-0 transition-opacity group-hover/log:opacity-100 hover:text-destructive"
                                    title="Deletar request"
                                    @click.stop="deleteLog(log)"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </div>
                        </div>

                        <template #loading> Carregando... </template>
                    </InfiniteScroll>
                </div>

                <!-- Pagination -->
                <div
                    v-if="logs.last_page > 1"
                    class="flex shrink-0 items-center justify-between border-t border-border p-2"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="!logs.prev_page_url"
                        @click="
                            logs.prev_page_url &&
                            router.visit(logs.prev_page_url)
                        "
                    >
                        ← Anterior
                    </Button>
                    <span class="text-xs text-muted-foreground"
                        >{{ logs.current_page }}/{{ logs.last_page }}</span
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="!logs.next_page_url"
                        @click="
                            logs.next_page_url &&
                            router.visit(logs.next_page_url)
                        "
                    >
                        Próxima →
                    </Button>
                </div>
            </div>

            <!-- Right: request detail -->
            <div
                v-if="logSelected"
                class="flex min-w-0 flex-1 flex-col overflow-hidden"
            >
                <!-- Detail header — fixed -->
                <div
                    class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3"
                >
                    <span
                        :class="methodColor(logSelected.method)"
                        class="inline-flex items-center rounded border px-2 py-0.5 font-mono text-xs font-bold uppercase"
                    >
                        {{ logSelected.method }}
                    </span>
                    <span class="font-mono text-sm text-muted-foreground"
                        >/webhook/{{ webhook.slug }}</span
                    >
                    <span class="ml-auto text-xs text-muted-foreground">{{
                        formatDate(logSelected.created_at)
                    }}</span>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                        title="Deletar request"
                        @click="deleteLog(logSelected)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <!-- Scrollable sections -->
                <div class="flex-1 divide-y divide-border overflow-y-auto">
                    <!-- Loading state -->
                    <div
                        v-if="!logSelected"
                        class="flex flex-1 items-center justify-center py-16"
                    >
                        <Loader2
                            class="size-5 animate-spin text-muted-foreground"
                        />
                    </div>

                    <template v-else>
                        <!-- Meta -->
                        <div class="px-5 py-4">
                            <p
                                class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Info
                            </p>
                            <div
                                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs"
                            >
                                <span class="text-muted-foreground">IP</span>
                                <span>{{ logSelected.ip_address ?? '—' }}</span>
                                <span class="text-muted-foreground"
                                    >User-Agent</span
                                >
                                <span class="break-all">{{
                                    logSelected.user_agent ?? '—'
                                }}</span>
                            </div>
                        </div>

                        <!-- Query params -->
                        <div v-if="hasQueryParams" class="px-5 py-4">
                            <p
                                class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Query Params
                            </p>
                            <div
                                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs"
                            >
                                <template
                                    v-for="(
                                        value, key
                                    ) in logSelected.query_params"
                                    :key="key"
                                >
                                    <span class="text-primary">{{ key }}</span>
                                    <span class="text-foreground/80">{{
                                        value
                                    }}</span>
                                </template>
                            </div>
                        </div>

                        <!-- Headers -->
                        <div class="px-5 py-4">
                            <p
                                class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Headers
                            </p>
                            <div
                                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs"
                            >
                                <template
                                    v-for="(value, key) in logSelected.headers"
                                    :key="key"
                                >
                                    <span class="shrink-0 text-primary">{{
                                        key
                                    }}</span>
                                    <span
                                        class="break-all text-foreground/80"
                                        >{{ value }}</span
                                    >
                                </template>
                            </div>
                        </div>

                        <!-- Payload -->
                        <div class="px-5 py-4">
                            <div class="mb-3 flex items-center gap-2">
                                <p
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Payload
                                </p>
                                <Badge
                                    v-if="parsedPayload !== null"
                                    variant="outline"
                                    class="text-[10px]"
                                    >JSON</Badge
                                >
                                <Button
                                    v-if="logSelected.payload"
                                    variant="ghost"
                                    size="icon"
                                    class="ml-auto size-6"
                                    :title="
                                        copiedPayload
                                            ? 'Copiado!'
                                            : 'Copiar payload'
                                    "
                                    @click="copyPayload"
                                >
                                    <Check
                                        v-if="copiedPayload"
                                        class="size-3 text-green-500"
                                    />
                                    <Copy v-else class="size-3" />
                                </Button>
                            </div>

                            <div
                                v-if="parsedPayload !== null"
                                class="rounded-lg bg-muted/60 p-3"
                            >
                                <VueJsonPretty
                                    :data="
                                        parsedPayload as Record<string, unknown>
                                    "
                                    :deep="3"
                                    :show-line="false"
                                    :show-double-quotes="true"
                                    class="text-xs"
                                />
                            </div>
                            <pre
                                v-else-if="logSelected.payload"
                                class="overflow-auto rounded-lg bg-muted/60 p-3 font-mono text-xs leading-relaxed"
                                >{{ logSelected.payload }}</pre
                            >
                            <p
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Sem payload
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <Dialog
        :open="confirmDeleteAll"
        @update:open="(v) => (confirmDeleteAll = v)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Apagar todas as requisições</DialogTitle>
                <DialogDescription>
                    Tem certeza? Todas as requisições do webhook
                    <strong>{{ webhook.name }}</strong> serão apagadas
                    permanentemente e não poderão ser recuperadas.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirmDeleteAll = false"
                    >Cancelar</Button
                >
                <Button variant="destructive" @click="doDeleteAllLogs"
                    >Apagar tudo</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="confirmResetToken" @update:open="confirmResetToken = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Resetar token do webhook</DialogTitle>
                <DialogDescription>
                    O token atual será
                    <strong>invalidado imediatamente</strong>. Qualquer
                    integração que use a URL atual deixará de funcionar. Você
                    precisará atualizar a URL em todos os serviços que a
                    utilizam.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirmResetToken = false"
                    >Cancelar</Button
                >
                <Button variant="default" @click="doResetToken"
                    >Sim, resetar token</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
