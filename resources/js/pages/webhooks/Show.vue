<script setup lang="ts">
import { Head, router, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { Clock, Copy, Check, Globe, Inbox, Trash2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { index, show, destroyLog } from '@/actions/App/Http/Controllers/WebhookController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Webhook {
    id: number;
    name: string;
    slug: string;
    created_at: string;
}

interface WebhookLog {
    id: number;
    method: string;
    ip_address: string | null;
    user_agent: string | null;
    headers: Record<string, string>;
    query_params: Record<string, string> | null;
    payload: string | null;
    created_at: string;
}

interface Paginator {
    data: WebhookLog[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

const { webhook, logs } = defineProps<{
    webhook: Webhook;
    logs: Paginator;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Webhooks', href: index() },
        { title: webhook.name, href: show(webhook).url },
    ],
});

const selectedLogId = ref<number | null>(logs.data[0]?.id ?? null);

const selectedLog = computed(
    () => logs.data.find((l) => l.id === selectedLogId.value) ?? null,
);

// When poll brings new logs, auto-select the latest if user has no selection
watch(
    () => logs.data,
    (data) => {
        if (!selectedLogId.value || !data.find((l) => l.id === selectedLogId.value)) {
            selectedLogId.value = data[0]?.id ?? null;
        }
    },
);

usePoll(3000, { only: ['logs'] });

const copiedUrl = ref(false);
const copiedPayload = ref(false);

const methodColors: Record<string, string> = {
    GET: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
    POST: 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20',
    PUT: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border-yellow-500/20',
    PATCH: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
    DELETE: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
    HEAD: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
    OPTIONS: 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20',
};

function methodColor(method: string) {
    return methodColors[method.toUpperCase()] ?? 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20';
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

function tryPrettyJson(value: string | null) {
    if (!value) return null;
    try {
        return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
        return value;
    }
}

function isJson(value: string | null) {
    if (!value) return false;
    try {
        JSON.parse(value);
        return true;
    } catch {
        return false;
    }
}

function copyUrl() {
    navigator.clipboard.writeText(webhookUrl);
    copiedUrl.value = true;
    setTimeout(() => (copiedUrl.value = false), 2000);
}

function deleteLog(log: WebhookLog) {
    const isSelected = selectedLogId.value === log.id;
    const currentIndex = logs.data.findIndex((l) => l.id === log.id);
    const next = logs.data[currentIndex + 1] ?? logs.data[currentIndex - 1] ?? null;

    router.delete(destroyLog({ webhook: webhook.id, log: log.id }).url, {
        preserveScroll: true,
        only: ['logs'],
        onSuccess: () => {
            if (isSelected) {
                selectedLogId.value = next?.id ?? null;
            }
        },
    });
}

function copyPayload() {
    if (!selectedLog.value?.payload) return;
    const text = isJson(selectedLog.value.payload)
        ? tryPrettyJson(selectedLog.value.payload) ?? selectedLog.value.payload
        : selectedLog.value.payload;
    navigator.clipboard.writeText(text);
    copiedPayload.value = true;
    setTimeout(() => (copiedPayload.value = false), 2000);
}

const webhookUrl = `${window.location.origin}/webhook/${webhook.slug}`;

const hasQueryParams = computed(
    () => selectedLog.value?.query_params && Object.keys(selectedLog.value.query_params).length > 0,
);
</script>

<template>
    <Head :title="webhook.name" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
        <div class="flex shrink-0 items-center gap-3 border-b border-border px-4 py-3">
            <Globe class="size-4 shrink-0 text-muted-foreground" />
            <code class="flex-1 truncate font-mono text-sm">{{ webhookUrl }}</code>
            <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="copyUrl">
                <Check v-if="copiedUrl" class="size-4 text-green-500" />
                <Copy v-else class="size-4" />
            </Button>
        </div>

        <!-- Empty state -->
        <div v-if="logs.data.length === 0" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
            <Inbox class="size-12 text-muted-foreground/40" />
            <div>
                <p class="font-medium">Aguardando requisições</p>
                <p class="mt-1 text-sm text-muted-foreground">Envie uma requisição para a URL acima.</p>
            </div>
        </div>

        <!-- Split panel -->
        <div v-else class="flex min-h-0 flex-1">
            <!-- Left: request list -->
            <div class="flex w-72 shrink-0 flex-col overflow-y-auto border-r border-border">
                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="group/log cursor-pointer border-b border-border px-3 py-3 transition-colors hover:bg-accent/40"
                    :class="selectedLogId === log.id ? 'bg-accent' : ''"
                    @click="selectedLogId = log.id"
                >
                    <div class="flex items-center gap-2">
                        <span
                            :class="methodColor(log.method)"
                            class="inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 font-mono text-[11px] font-bold uppercase"
                        >
                            {{ log.method }}
                        </span>
                        <span class="truncate font-mono text-xs text-muted-foreground">
                            /{{ webhook.slug }}
                        </span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                        <Clock class="size-3 shrink-0" />
                        <span>{{ formatDateShort(log.created_at) }}</span>
                        <span v-if="log.ip_address" class="truncate">· {{ log.ip_address }}</span>
                        <button
                            type="button"
                            class="ml-auto shrink-0 rounded p-0.5 text-muted-foreground/50 opacity-0 transition-opacity hover:text-destructive group-hover/log:opacity-100"
                            title="Deletar request"
                            @click.stop="deleteLog(log)"
                        >
                            <Trash2 class="size-3" />
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-border p-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="!logs.prev_page_url"
                        @click="logs.prev_page_url && router.visit(logs.prev_page_url)"
                    >
                        ← Anterior
                    </Button>
                    <span class="text-xs text-muted-foreground">{{ logs.current_page }}/{{ logs.last_page }}</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="!logs.next_page_url"
                        @click="logs.next_page_url && router.visit(logs.next_page_url)"
                    >
                        Próxima →
                    </Button>
                </div>
            </div>

            <!-- Right: request detail -->
            <div v-if="selectedLog" class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <!-- Detail header -->
                <div class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3">
                    <span
                        :class="methodColor(selectedLog.method)"
                        class="inline-flex items-center rounded border px-2 py-0.5 font-mono text-xs font-bold uppercase"
                    >
                        {{ selectedLog.method }}
                    </span>
                    <span class="font-mono text-sm text-muted-foreground">/webhook/{{ webhook.slug }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">{{ formatDate(selectedLog.created_at) }}</span>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                        title="Deletar request"
                        @click="deleteLog(selectedLog)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <!-- Sections -->
                <div class="divide-y divide-border">

                    <!-- Meta -->
                    <div class="px-5 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Info</p>
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs">
                            <span class="text-muted-foreground">IP</span>
                            <span>{{ selectedLog.ip_address ?? '—' }}</span>
                            <span class="text-muted-foreground">User-Agent</span>
                            <span class="break-all">{{ selectedLog.user_agent ?? '—' }}</span>
                        </div>
                    </div>

                    <!-- Query params -->
                    <div v-if="hasQueryParams" class="px-5 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Query Params</p>
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs">
                            <template v-for="(value, key) in selectedLog.query_params" :key="key">
                                <span class="text-primary">{{ key }}</span>
                                <span class="text-foreground/80">{{ value }}</span>
                            </template>
                        </div>
                    </div>

                    <!-- Headers -->
                    <div class="px-5 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Headers</p>
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 font-mono text-xs">
                            <template v-for="(value, key) in selectedLog.headers" :key="key">
                                <span class="shrink-0 text-primary">{{ key }}</span>
                                <span class="break-all text-foreground/80">{{ value }}</span>
                            </template>
                        </div>
                    </div>

                    <!-- Payload -->
                    <div class="px-5 py-4">
                        <div class="mb-3 flex items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Payload</p>
                            <Badge v-if="isJson(selectedLog.payload)" variant="outline" class="text-[10px]">JSON</Badge>
                            <Button
                                v-if="selectedLog.payload"
                                variant="ghost"
                                size="icon"
                                class="ml-auto size-6"
                                :title="copiedPayload ? 'Copiado!' : 'Copiar payload'"
                                @click="copyPayload"
                            >
                                <Check v-if="copiedPayload" class="size-3 text-green-500" />
                                <Copy v-else class="size-3" />
                            </Button>
                        </div>
                        <pre v-if="selectedLog.payload" class="overflow-auto rounded-lg bg-muted/60 p-3 font-mono text-xs leading-relaxed">{{ tryPrettyJson(selectedLog.payload) }}</pre>
                        <p v-else class="text-xs italic text-muted-foreground">Sem payload</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
