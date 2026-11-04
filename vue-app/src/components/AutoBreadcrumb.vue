<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Breadcrumb from 'primevue/breadcrumb';

const route = useRoute();
const { t, te } = useI18n();

// Helper untuk menerjemahkan label breadcrumb (kunci i18n, fallback string asli)
function resolveBreadcrumbLabel(match) {
    const key = match.meta?.breadcrumbKey;
    if (key && te(key)) {
        return t(key);
    }
    const legacyKey = match.meta?.breadcrumb;
    if (legacyKey && te(legacyKey)) {
        return t(legacyKey);
    }
    if (match.name && te(`menu.item.${match.name}`)) {
        return t(`menu.item.${match.name}`);
    }
    return match.meta?.breadcrumb || '';
}

// Dynamically generate breadcrumb paths based on router metadata
const breadcrumbItems = computed(() => {
    // route.matched contains the complete tree chain of the active view page
    return route.matched
        .filter((match) => match.meta && (match.meta.breadcrumb || match.meta.breadcrumbKey))
        .map((match) => {
            return {
                label: resolveBreadcrumbLabel(match),
                to: match.path
            };
        });
});

// The standard static starting root home node configuration
const homeItem = computed(() => ({
    icon: 'pi pi-home',
    to: '/',
    label: t('menu.group.home')
}));
</script>

<template>
    <div class="mb-4">
        <Breadcrumb v-if="breadcrumbItems.length > 0" :home="homeItem" :model="breadcrumbItems">
            <template #item="{ item, props }">
                <router-link v-if="item.to && breadcrumbItems.indexOf(item) < breadcrumbItems.length - 1" :to="item.to" custom v-slot="{ navigate, href }">
                    <a :href="href" @click="navigate" v-bind="props.action" class="text-primary font-medium hover:underline">
                        <span v-if="item.icon" :class="item.icon" class="mr-2"></span>
                        <span>{{ item.label }}</span>
                    </a>
                </router-link>

                <span v-else v-bind="props.action" class="text-surface-500 dark:text-surface-400 font-semibold cursor-default">
                    <span v-if="item.icon" :class="item.icon" class="mr-2"></span>
                    <span>{{ item.label }}</span>
                </span>
            </template>
        </Breadcrumb>
    </div>
</template>
