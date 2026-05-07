<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/WebhookController';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import app from '@/routes/app';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    Clock,
    Inbox,
    MailOpen,
    Radio,
    Webhook,
} from 'lucide-vue-next';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: app.dashboard() }],
    },
});

interface MostActiveWebhook {
    name: string;
    slug: string;
    logs_count: number;
}

interface RecentRequest {
    sqid: string;
    method: string;
    ip_address: string | null;
    created_at: string;
    read_at: string | null;
    webhook: { name: string; slug: string };
}

interface Stats {
    totalWebhooks: number;
    totalRequests: number;
    unreadRequests: number;
    requestsToday: number;
    requestsThisWeek: number;
    topMethod: string | null;
    mostActiveWebhook: MostActiveWebhook | null;
}

const { stats, recentRequests } = defineProps<{
    stats: Stats;
    recentRequests: RecentRequest[];
}>();

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
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 overflow-x-auto p-4">
        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Webhooks</CardTitle
                    >
                    <Webhook class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.totalWebhooks }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        endpoints ativos
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Total de requisições</CardTitle
                    >
                    <Activity class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.totalRequests }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ stats.requestsThisWeek }} nesta semana
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Não lidas</CardTitle
                    >
                    <Inbox class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-bold"
                        :class="stats.unreadRequests > 0 ? 'text-primary' : ''"
                    >
                        {{ stats.unreadRequests }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        requisições pendentes
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Hoje</CardTitle
                    >
                    <Clock class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.requestsToday }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        requisições recebidas
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Bottom row -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Recent requests -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center gap-2 pb-3">
                    <Radio class="size-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-medium"
                        >Requisições recentes</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="recentRequests.length === 0"
                        class="flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground"
                    >
                        <Inbox class="size-8 opacity-40" />
                        <p class="text-sm">Nenhuma requisição ainda</p>
                    </div>
                    <div v-else class="divide-y divide-border">
                        <Link
                            v-for="req in recentRequests"
                            :key="req.sqid"
                            :href="
                                app.webhooks.show({
                                    slug: req.webhook.slug,
                                    log: req.sqid,
                                }).url
                            "
                            class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-accent/40"
                        >
                            <span
                                v-if="!req.read_at"
                                class="size-1.5 shrink-0 rounded-full bg-primary"
                            />
                            <span
                                :class="methodColor(req.method)"
                                class="inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 font-mono text-[11px] font-bold uppercase"
                            >
                                {{ req.method }}
                            </span>
                            <span
                                class="min-w-0 flex-1 truncate text-xs text-muted-foreground"
                            >
                                {{ req.webhook.name }}
                            </span>
                            <span
                                class="shrink-0 text-[11px] text-muted-foreground"
                            >
                                {{ req.ip_address ?? '—' }}
                            </span>
                            <span
                                class="shrink-0 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ formatDate(req.created_at) }}
                            </span>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Highlights -->
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader class="flex flex-row items-center gap-2 pb-2">
                        <Radio class="size-4 text-muted-foreground" />
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Método mais usado</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <span
                            v-if="stats.topMethod"
                            :class="methodColor(stats.topMethod)"
                            class="inline-flex items-center rounded border px-2 py-1 font-mono text-sm font-bold uppercase"
                        >
                            {{ stats.topMethod }}
                        </span>
                        <span v-else class="text-sm text-muted-foreground"
                            >—</span
                        >
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center gap-2 pb-2">
                        <MailOpen class="size-4 text-muted-foreground" />
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Webhook mais ativo</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.mostActiveWebhook">
                            <Link
                                :href="
                                    show({ slug: stats.mostActiveWebhook.slug })
                                        .url
                                "
                                class="font-medium hover:underline"
                            >
                                {{ stats.mostActiveWebhook.name }}
                            </Link>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ stats.mostActiveWebhook.logs_count }}
                                requisições
                            </p>
                        </div>
                        <span v-else class="text-sm text-muted-foreground"
                            >—</span
                        >
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center gap-2 pb-2">
                        <Activity class="size-4 text-muted-foreground" />
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Esta semana</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ stats.requestsThisWeek }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ stats.requestsToday }} hoje
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
