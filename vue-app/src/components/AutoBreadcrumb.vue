<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import Breadcrumb from 'primevue/breadcrumb';

const route = useRoute();

// Dynamically generate breadcrumb paths based on router metadata
const breadcrumbItems = computed(() => {
    // route.matched contains the complete tree chain of the active view page
    return route.matched
        .filter(match => match.meta && match.meta.breadcrumb) // Only pick routes with custom labels
        .map(match => {
            return {
                label: match.meta.breadcrumb, // Delivers your custom menu title text!
                to: match.path // Smooth SPA destination redirect links
            };
        });
});

// The standard static starting root home node configuration
const homeItem = ref({
    icon: 'pi pi-home',
    to: '/'
});
</script>

<template>
    <div class="mb-4">
        <Breadcrumb v-if="breadcrumbItems.length > 0" :home="homeItem" :model="breadcrumbItems">

            <template #item="{ item, props }">

                <router-link
                    v-if="item.to && breadcrumbItems.indexOf(item) < breadcrumbItems.length - 1"
                    :to="item.to"
                    custom
                    v-slot="{ navigate, href }"
                >
                    <a :href="href" @click="navigate" v-bind="props.action" class="text-primary font-medium hover:underline">
                        <span v-if="item.icon" :class="item.icon" class="mr-2"></span>
                        <span>{{ item.label }}</span>
                    </a>
                </router-link>

                <span
                    v-else
                    v-bind="props.action"
                    class="text-surface-500 dark:text-surface-400 font-semibold cursor-default"
                >
                    <span v-if="item.icon" :class="item.icon" class="mr-2"></span>
                    <span>{{ item.label }}</span>
                </span>

            </template>
        </Breadcrumb>
    </div>
</template>
