<script setup>
import { useLayout } from '@/layout/composables/layout';
import { onMounted, ref, watch } from 'vue';
import { useToast } from "primevue/usetoast";

const { getPrimary, getSurface, isDarkTheme } = useLayout();
const lineData = ref(null);
const barData = ref(null);
const lineOptions = ref(null);
const barOptions = ref(null);
const toast = useToast();
const selectedUser = ref();
const menu = ref();

onMounted(() => {
    setColorOptions();
});

function setColorOptions() {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');

    barData.value = {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
        datasets: [
            {
                label: 'My First dataset',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                borderColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: [65, 59, 80, 81, 56, 55, 40]
            },
            {
                label: 'My Second dataset',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-200'),
                borderColor: documentStyle.getPropertyValue('--p-primary-200'),
                data: [28, 48, 40, 19, 86, 27, 90]
            }
        ]
    };
    barOptions.value = {
        plugins: {
            legend: {
                labels: {
                    fontColor: textColor
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary,
                    font: {
                        weight: 500
                    }
                },
                grid: {
                    display: false,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            }
        }
    };

    lineData.value = {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
        datasets: [
            {
                label: 'First Dataset',
                data: [65, 59, 80, 81, 56, 55, 40],
                fill: false,
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                borderColor: documentStyle.getPropertyValue('--p-primary-500'),
                tension: 0.4
            },
            {
                label: 'Second Dataset',
                data: [28, 48, 40, 19, 86, 27, 90],
                fill: false,
                backgroundColor: documentStyle.getPropertyValue('--p-primary-200'),
                borderColor: documentStyle.getPropertyValue('--p-primary-200'),
                tension: 0.4
            }
        ]
    };

    lineOptions.value = {
        plugins: {
            legend: {
                labels: {
                    fontColor: textColor
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            }
        }
    };
}

watch(
    [getPrimary, getSurface, isDarkTheme],
    () => {
        setColorOptions();
    },
    { immediate: true }
);

const users = ref([
    { id: 0, name: 'Amy Elsner', image: 'amyelsner.png', role: 'Admin' },
    { id: 1, name: 'Anna Fali', image: 'annafali.png', role: 'Member' },
    { id: 2, name: 'Asiya Javayant', image: 'asiyajavayant.png', role: 'Member' },
    { id: 3, name: 'Bernardo Dominic', image: 'bernardodominic.png', role: 'Guest' },
    { id: 4, name: 'Elwin Sharvill', image: 'elwinsharvill.png', role: 'Member' }
]);
const items = ref([
    {
        label: 'Roles',
        icon: 'pi pi-users',
        items: [
            {
                label: 'Admin',
                command: () => {
                    selectedUser.value.role = 'Admin';
                }
            },
            {
                label: 'Member',
                command: () => {
                    selectedUser.value.role = 'Member';
                }
            },
            {
                label: 'Guest',
                command: () => {
                    selectedUser.value.role = 'Guest';
                }
            }
        ]
    },
    {
        label: 'Invite',
        icon: 'pi pi-user-plus',
        command: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invitation sent!', life: 3000 });
        }
    }
]);

const getBadge = (user) => {
    if (user.role === 'Member') return 'info';
    else if (user.role === 'Guest') return 'warn';
    else return null;
}
</script>

<template>
    <div class="card">
        <div class="font-semibold text-xl mb-4">Linear</div>
        <Chart type="line" :data="lineData" :options="lineOptions"></Chart>
    </div>
</template>
