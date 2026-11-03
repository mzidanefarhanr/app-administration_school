<script setup>
import { ref } from 'vue';
import MultiYearTrendsTab from '@/components/reports/MultiYearTrendsTab.vue';
import DemographicsTab from '@/components/reports/DemographicsTab.vue';
import DapodikReconciliationTab from '@/components/reports/DapodikReconciliationTab.vue';

const activeTab = ref('trends');
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="card p-6 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-0">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 tracking-tight">Laporan Rekapitulasi Sekolah & Rekonsiliasi Dapodik</h2>
                    <p class="text-surface-500 text-sm mt-1">Analisis perkembangan multi-tahun, profil demografi kesiswaan, audit kelengkapan profil, dan rekonsiliasi data Dapodik (DEC-43, DEC-12).</p>
                </div>
            </div>

            <Tabs v-model:value="activeTab">
                <TabList>
                    <Tab value="trends" class="flex items-center gap-2">
                        <i class="pi pi-chart-line"></i>
                        <span>Tren Multi-Tahun</span>
                    </Tab>
                    <Tab value="demographics" class="flex items-center gap-2">
                        <i class="pi pi-users"></i>
                        <span>Demografi & Kesiapan Profil</span>
                    </Tab>
                    <Tab value="dapodik" class="flex items-center gap-2">
                        <i class="pi pi-sync"></i>
                        <span>Rekonsiliasi Data Dapodik</span>
                    </Tab>
                </TabList>

                <TabPanels class="pt-6">
                    <TabPanel value="trends">
                        <MultiYearTrendsTab />
                    </TabPanel>
                    <TabPanel value="demographics">
                        <DemographicsTab />
                    </TabPanel>
                    <TabPanel value="dapodik">
                        <DapodikReconciliationTab />
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </div>
</template>
