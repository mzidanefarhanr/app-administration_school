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

const canViewStudents = computed(() => store.can('students.view'));

const buildChartConfig = () => {
    if (!rawData.value?.labels || !rawData.value.labels.length) {
        chartData.value = null;
        return;
    }

    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color') || '#495057';
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary') || '#6c757d';
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border') || '#dfe7ef';

    const maleColor = documentStyle.getPropertyValue('--sk-gender-l') || '#0ea5e9';
    const femaleColor = documentStyle.getPropertyValue('--sk-gender-p') || '#ec4899';

    const datasets = (rawData.value.datasets || [])
        .filter((ds) => ds.label !== 'Total')
        .map((ds) => {
            const isMale = ds.label.toLowerCase().includes('laki') || ds.label.toLowerCase().includes('male');
            const color = isMale ? maleColor : femaleColor;
            return {
                label: isMale ? t('dashboard.studentDistribution.male') : t('dashboard.studentDistribution.female'),
                data: ds.data,
                backgroundColor: color,
                borderColor: color,
                borderRadius: 6,
                barThickness: 24
            };
        });

    chartData.value = {
        labels: rawData.value.labels,
        datasets
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
                stacked: true,
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
                stacked: true,
                ticks: {
                    color: textColorSecondary,
                    stepSize: 5,
                    font: {
                        family: 'inherit'
                    }
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            }
        }
    };
};

const load = async () => {
    if (!canViewStudents.value) return;

    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/charts');
        rawData.value = response.data?.data?.students_by_level ?? null;
        buildChartConfig();
    } catch {
        error.value = t('dashboard.studentDistribution.error');
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
    <div class="card mb-0 h-full flex flex-col justify-between">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="font-semibold text-xl">{{ t('dashboard.studentDistribution.title') }}</div>
                <div class="text-xs text-muted-color">{{ t('dashboard.studentDistribution.subtitle') }}</div>
            </div>
            <Button v-if="canViewStudents" icon="pi pi-refresh" size="small" variant="text" severity="secondary" :loading="loading" :aria-label="t('dashboard.studentDistribution.refreshAria')" @click="load" />
        </div>

        <div v-if="!canViewStudents" class="flex flex-col items-center justify-center py-12 text-surface-400 text-center">
            <i class="pi pi-lock text-3xl mb-2" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.studentDistribution.permissionRequired') }}</span>
        </div>

        <div v-else-if="loading" class="flex items-center justify-center py-16 text-surface-500 gap-3">
            <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.studentDistribution.loading') }}</span>
        </div>

        <div v-else-if="error" class="flex flex-col items-center justify-center py-12 text-center text-red-500">
            <i class="pi pi-exclamation-triangle text-2xl mb-2" aria-hidden="true"></i>
            <span class="text-sm">{{ error }}</span>
            <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" class="mt-3" @click="load" />
        </div>

        <div v-else-if="!chartData || !chartData.labels || !chartData.labels.length" class="flex flex-col items-center justify-center py-20 text-center text-surface-400 text-sm">
            <i class="pi pi-chart-bar text-3xl mb-2 text-surface-300 dark:text-surface-600" aria-hidden="true"></i>
            <span>{{ t('dashboard.studentDistribution.empty') }}</span>
        </div>

        <div v-else class="h-80">
            <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
        </div>
    </div>
</template>
