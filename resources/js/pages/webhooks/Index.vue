<script setup lang="ts">
import {
    destroy,
    index,
    store,
} from '@/actions/App/Http/Controllers/WebhookController';
import Heading from '@/components/Heading.vue';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import app from '@/routes/app';
import { receive } from '@/routes/webhook';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Globe, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Webhook {
    id: number;
    name: string;
    slug: string;
    token: string;
    logs_count: number;
    created_at: string;
}

const { webhooks } = defineProps<{
    webhooks: Webhook[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Webhooks', href: index() }],
    },
});

const form = useForm({ name: '' });
const open = ref(false);
const copiedId = ref<number | null>(null);
const webhookToDelete = ref<Webhook | null>(null);

function submit() {
    form.post(store().url, {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

function deleteWebhook(webhook: Webhook) {
    webhookToDelete.value = webhook;
}

function confirmDelete() {
    if (!webhookToDelete.value) {
        return;
    }

    router.delete(destroy(webhookToDelete.value).url);

    webhookToDelete.value = null;
}

function copyUrl(webhook: Webhook) {
    const url = window.location.origin + receive(webhook).url;
    navigator.clipboard.writeText(url);
    copiedId.value = webhook.id;
    setTimeout(() => (copiedId.value = null), 2000);
}

function formatDate(date: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}
</script>

<template>
    <Head title="Webhooks" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <Heading
                title="Webhooks"
                description="Receba e inspecione requisições HTTP de qualquer origem."
            />

            <Dialog v-model:open="open">
                <DialogTrigger as-child>
                    <Button>
                        <Plus class="size-4" />
                        Novo Webhook
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Criar Webhook</DialogTitle>
                        <DialogDescription>
                            Dê um nome ao seu webhook. A URL será gerada
                            automaticamente.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nome</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Meu Webhook"
                                autofocus
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="form.processing">
                                Criar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <div
            v-if="webhooks.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border p-16 text-center"
        >
            <Globe class="size-10 text-muted-foreground" />
            <div>
                <p class="font-medium">Nenhum webhook ainda</p>
                <p class="text-sm text-muted-foreground">
                    Crie um webhook para começar a receber requisições.
                </p>
            </div>
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="webhook in webhooks"
                :key="webhook.id"
                class="group flex items-center gap-4 rounded-xl border border-border bg-card p-4 transition-colors hover:bg-accent/30"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                >
                    <Globe class="size-5 text-primary" />
                </div>

                <div
                    class="min-w-0 flex-1 cursor-pointer"
                    @click="router.visit(app.webhooks.show(webhook).url)"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ webhook.name }}</span>
                        <Badge variant="secondary"
                            >{{ webhook.logs_count }} requests</Badge
                        >
                    </div>
                    <p
                        class="mt-0.5 truncate font-mono text-sm text-muted-foreground"
                    >
                        /w/{{ webhook.slug }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <span class="mr-2 text-xs text-muted-foreground">{{
                        formatDate(webhook.created_at)
                    }}</span>

                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    @click.stop="copyUrl(webhook)"
                                >
                                    <Check
                                        v-if="copiedId === webhook.id"
                                        class="size-4 text-green-500"
                                    />
                                    <Copy v-else class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{ copiedId === webhook.id ? 'Copiado!' : 'Copiar URL' }}</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive hover:text-destructive"
                                    @click.stop="deleteWebhook(webhook)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Deletar webhook</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>
    </div>

    <Dialog
        :open="!!webhookToDelete"
        @update:open="(v) => !v && (webhookToDelete = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Deletar webhook</DialogTitle>
                <DialogDescription>
                    Tem certeza que deseja deletar o webhook
                    <strong>{{ webhookToDelete?.name }}</strong
                    >? Todas as requisições registradas serão apagadas
                    permanentemente.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="webhookToDelete = null"
                    >Cancelar</Button
                >
                <Button variant="destructive" @click="confirmDelete"
                    >Deletar</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
