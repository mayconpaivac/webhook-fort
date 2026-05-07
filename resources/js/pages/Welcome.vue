<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Globe, Zap } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login, register } from '@/routes';
import app from '@/routes/app';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="WebhookFort" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- Nav -->
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-5 text-black dark:text-white" />
                <span>WebhookFort</span>
            </div>
            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="app.dashboard()"
                    class="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Entrar
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Criar conta
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Hero -->
        <main
            class="flex flex-1 flex-col items-center justify-center gap-8 px-6 text-center"
        >
            <div
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10"
            >
                <AppLogoIcon class="size-8 text-black dark:text-white" />
            </div>

            <div class="max-w-xl">
                <h1 class="text-4xl font-bold tracking-tight">
                    Inspecione webhooks em tempo real
                </h1>
                <p class="mt-4 text-lg text-muted-foreground">
                    Crie uma URL única, envie qualquer requisição HTTP para ela
                    e visualize todos os dados — método, headers, payload —
                    instantaneamente no painel.
                </p>
            </div>

            <div class="flex gap-3">
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                >
                    Começar grátis
                </Link>
                <Link
                    :href="login()"
                    class="rounded-md border border-border px-6 py-2.5 text-sm font-semibold hover:bg-accent"
                >
                    Entrar
                </Link>
            </div>

            <!-- Features -->
            <div class="mt-8 grid max-w-2xl gap-4 sm:grid-cols-3">
                <div
                    class="rounded-xl border border-border bg-card p-5 text-left"
                >
                    <Globe class="mb-3 size-5 text-primary" />
                    <p class="font-semibold">URL dedicada</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cada webhook tem uma URL única pronta para receber
                        qualquer verbo HTTP.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-card p-5 text-left"
                >
                    <Zap class="mb-3 size-5 text-primary" />
                    <p class="font-semibold">Tempo real</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Requests aparecem automaticamente no painel sem precisar
                        recarregar.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-card p-5 text-left"
                >
                    <Eye class="mb-3 size-5 text-primary" />
                    <p class="font-semibold">Inspeção completa</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Veja método, IP, headers e payload formatado de cada
                        requisição recebida.
                    </p>
                </div>
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-muted-foreground">
            WebhookFort &copy; {{ new Date().getFullYear() }}
        </footer>
    </div>
</template>
