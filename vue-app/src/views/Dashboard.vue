<script setup>
import ArchiveExpiryWidget from '@/components/dashboard/ArchiveExpiryWidget.vue';
import DataQualityOverviewWidget from '@/components/dashboard/DataQualityOverviewWidget.vue';
import ListUsersOnlineWidget from '@/components/dashboard/ListUsersOnlineWidget.vue';
import OperationalStatsWidget from '@/components/dashboard/OperationalStatsWidget.vue';
import PendingTasksWidget from '@/components/dashboard/PendingTasksWidget.vue';
import StudentDistributionChartWidget from '@/components/dashboard/StudentDistributionChartWidget.vue';
import SystemOverviewWidget from '@/components/dashboard/SystemOverviewWidget.vue';
import TeachingComplianceChartWidget from '@/components/dashboard/TeachingComplianceChartWidget.vue';
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <ArchiveExpiryWidget />

        <OperationalStatsWidget />

        <div class="col-span-12 xl:col-span-6">
            <StudentDistributionChartWidget />
        </div>
        <div class="col-span-12 xl:col-span-6">
            <TeachingComplianceChartWidget />
        </div>

        <div class="col-span-12 xl:col-span-6">
            <PendingTasksWidget />
            <DataQualityOverviewWidget />
        </div>
        <div class="col-span-12 xl:col-span-6">
            <ListUsersOnlineWidget />
            <SystemOverviewWidget />
        </div>
    </div>
</template>
