<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { useLayout } from '@/layout/composables/layout';
import { authStore } from '@/stores/authStore';

const { t, locale } = useI18n();
const { isDarkTheme } = useLayout();
const store = authStore();

const rawData = ref(null);
const chartData = ref(null);
const chartOptions = ref(null);
const loading = ref(false);
const error = ref('');

const canViewTeaching = computed(() => store.can('teaching.view'));

const buildChartConfig = () => {
    if (!rawData.value?.labels || !rawData.value.labels.length) {
        chartData.value = null;
        return;
    }

    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color') || '#495057';
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary') || '#6c757d';
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border') || '#dfe7ef';

    const complianceColor = documentStyle.getPropertyValue('--p-emerald-500') || '#10B981';
    const recordedColor = documentStyle.getPropertyValue('--p-indigo-400') || '#818CF8';

    chartData.value = {
        labels: rawData.value.labels,
        datasets: [
            {
                type: 'line',
                label: t('dashboard.teachingCompliance.complianceLabel'),
                borderColor: complianceColor,
                backgroundColor: complianceColor,
                borderWidth: 2,
                fill: false,
                tension: 0.3,
                yAxisID: 'y',
                data: rawData.value.datasets?.[0]?.data ?? [],
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                type: 'bar',
                label: t('dashboard.teachingCompliance.recordedLabel'),
                backgroundColor: recordedColor,
                borderRadius: 6,
                barThickness: 28,
                yAxisID: 'y1',
                data: rawData.value.datasets?.[1]?.data ?? []
            }
        ]
    };

    chartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        plugins: {
            legend: {
                labels: {
                    color: textColor,
                    font: {
                        family: 'inherit',
                        weight: 500
                    }
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary,
                    font: {
                        family: 'inherit'
                    }
                },
                grid: {
                    display: false,
                    drawBorder: false
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                min: 0,
                max: 100,
                ticks: {
                    color: textColorSecondary,
                    callback: (value) => `${value}%`,
                    font: {
                        family: 'inherit'
                    }
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                min: 0,
                grid: {
                    drawOnChartArea: false,
                    drawBorder: false
                },
                ticks: {
                    color: textColorSecondary,
                    stepSize: 1,
                    font: {
                        family: 'inherit'
                    }
                }
            }
        }
    };
};

const load = async () => {
    if (!canViewTeaching.value) return;

    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/charts');
        rawData.value = response.data?.data?.teaching_compliance_trend ?? null;
        buildChartConfig();
    } catch {
        error.value = t('dashboard.teachingCompliance.error');
    } finally {
        loading.value = false;
    }
};

watch([isDarkTheme, locale], () => {
    buildChartConfig();
});

onMounted(async () => {
    await load();
});

defineExpose({ load });
</script>

<template>
    <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="font-semibold text-xl">{{ t('dashboard.teachingCompliance.title') }}</div>
                <div class="text-xs text-muted-color">{{ t('dashboard.teachingCompliance.subtitle') }}</div>
            </div>
            <Button v-if="canViewTeaching" icon="pi pi-refresh" size="small" variant="text" severity="secondary" :loading="loading" :aria-label="t('dashboard.teachingCompliance.refreshAria')" @click="load" />
        </div>

        <div v-if="!canViewTeaching" class="flex flex-col items-center justify-center py-12 text-surface-400 text-center">
            <i class="pi pi-lock text-3xl mb-2" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.teachingCompliance.permissionRequired') }}</span>
        </div>

        <div v-else-if="loading" class="flex items-center justify-center py-16 text-surface-500 gap-3">
            <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.teachingCompliance.loading') }}</span>
        </div>

        <div v-else-if="error" class="flex flex-col items-center justify-center py-12 text-center text-red-500">
            <i class="pi pi-exclamation-triangle text-2xl mb-2" aria-hidden="true"></i>
            <span class="text-sm">{{ error }}</span>
            <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" class="mt-3" @click="load" />
        </div>

        <div v-else-if="!chartData || !chartData.labels || !chartData.labels.length" class="py-12 text-center text-surface-400 text-sm">{{ t('dashboard.teachingCompliance.empty') }}</div>

        <div v-else class="h-80">
            <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
        </div>
    </div>
</template>
