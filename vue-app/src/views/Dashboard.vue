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
    <div class="grid grid-cols-12 gap-6">
        <!-- 1. Statistik Operasional Utama (4 Kartu Metrik Inti) -->
        <OperationalStatsWidget />

        <!-- 2. Pengingat Masa Berlaku Arsip -->
        <ArchiveExpiryWidget />

        <!-- 3. Grafik Analisis (Distribusi Siswa & Kepatuhan Mengajar) -->
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <StudentDistributionChartWidget />
        </div>
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <TeachingComplianceChartWidget />
        </div>

        <!-- 4. Tugas Operasional & Kualitas Data Master -->
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <PendingTasksWidget />
        </div>
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <DataQualityOverviewWidget />
        </div>

        <!-- 5. Sistem & Pintasan + Daftar Pengguna Online -->
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <SystemOverviewWidget />
        </div>
        <div class="col-span-12 xl:col-span-6 flex flex-col">
            <ListUsersOnlineWidget />
        </div>
    </div>
</template>
