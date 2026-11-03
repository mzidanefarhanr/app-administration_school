<script setup>
import { useLayout } from '@/layout/composables/layout';

const { isDarkTheme } = useLayout();
</script>

<template>
    <img 
        :src="isDarkTheme ? '/logo/sekolario-horizontal-on-dark.svg' : '/logo/sekolario-horizontal.svg'" 
        alt="Sekolario"
        class="h-8 w-auto"
    />
</template>
