<script setup>
import BestSellingWidget from '@/components/dashboard/BestSellingWidget.vue';
import ChartDataWidget_1 from '@/components/dashboard/ChartDataWidget_1.vue';
import ListUsersOnlineWidget from '@/components/dashboard/ListUsersOnlineWidget.vue';
import NotificationsWidget from '@/components/dashboard/NotificationsWidget.vue';
import RecentSalesWidget from '@/components/dashboard/RecentSalesWidget.vue';
import RevenueStreamWidget from '@/components/dashboard/RevenueStreamWidget.vue';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <StatsWidget />

        <div class="col-span-12 xl:col-span-6">
            <ChartDataWidget_1 />
        </div>
        <div class="col-span-12 xl:col-span-6">
            <ListUsersOnlineWidget />
        </div>
        <div class="col-span-12 xl:col-span-6">
            <RecentSalesWidget />
            <BestSellingWidget />
        </div>
        <div class="col-span-12 xl:col-span-6">
            <RevenueStreamWidget />
            <NotificationsWidget />
        </div>
    </div>
</template>
