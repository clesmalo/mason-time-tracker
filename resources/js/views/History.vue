<script setup>
import { ref, watch } from 'vue'
import DataTable from 'datatables.net-vue3'
import DataTablesCore from 'datatables.net-dt'
import { useAppStore } from '../stores/app'

DataTable.use(DataTablesCore)

const appStore = useAppStore()
const table    = ref(null)
const summary  = ref(null)

function filterParams () {
    return {
        company_id:  appStore.selectedCompanyId,
        employee_id: appStore.selectedEmployeeId,
        project_id:  appStore.selectedProjectId,
        task_id:     appStore.selectedTaskId,
        date_from:   appStore.selectedDateFrom,
        date_to:     appStore.selectedDateTo,
    }
}

async function fetchSummary () {
    const params = new URLSearchParams()
    const f = filterParams()
    Object.entries(f).forEach(([k, v]) => { if (v) params.set(k, v) })
    const res = await fetch(`/api/time-entries/summary?${params}`, {
        headers: { Accept: 'application/json' },
    })
    summary.value = await res.json()
}

watch(
    [
        () => appStore.selectedCompanyId,
        () => appStore.selectedEmployeeId,
        () => appStore.selectedProjectId,
        () => appStore.selectedTaskId,
        () => appStore.selectedDateFrom,
        () => appStore.selectedDateTo,
    ],
    () => {
        table.value?.dt.ajax.reload()
        fetchSummary()
    },
    { immediate: true }
)

const dtAjax = {
    url: '/api/time-entries',
    data (d) {
        const f = filterParams()
        Object.assign(d, f)
    },
}

const columns = [
    { data: 'company',  title: 'Company'  },
    { data: 'date',     title: 'Date'     },
    { data: 'employee', title: 'Employee' },
    { data: 'project',  title: 'Project'  },
    { data: 'task',     title: 'Task'     },
    {
        data: 'duration',
        title: 'Hours',
        orderable: false,
        render (data, type, row) {
            if (type !== 'display') return data
            return `<span class="dt-dur cursor-pointer font-mono underline decoration-dotted text-gray-700">${data}</span>`
                 + `<span class="dt-time text-xs text-gray-400 mt-0.5 block" style="display:none">${row.started_at} – ${row.ended_at}</span>`
        },
    },
]

const dtOptions = {
    serverSide: true,
    processing: true,
    paging:     true,
    pageLength: 15,
    lengthMenu: [10, 15, 25, 50],
    searching:  false,
    order:      [[1, 'desc']],
    layout: {
        topStart:    null,
        topEnd:      null,
        bottomStart: 'info',
        bottomEnd:   ['pageLength', 'paging'],
    },
    language: {
        processing:  'Loading…',
        emptyTable:  'No time entries found.',
        zeroRecords: 'No entries match the current filters.',
    },
}

function onTableClick (e) {
    const span = e.target.closest('.dt-dur')
    if (!span) return
    const detail = span.nextElementSibling
    if (detail) detail.style.display = detail.style.display === 'none' ? 'block' : 'none'
}
</script>

<template>
    <div>
        <h1 class="text-xl font-semibold text-gray-900 mb-6">History</h1>

        <!-- Summary cards -->
        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Hours</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_hours }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Records</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_records }}</p>
            </div>
            <div v-if="summary.total_projects !== null" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Projects</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_projects }}</p>
            </div>
            <div v-if="summary.total_employees !== null" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Employees</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_employees }}</p>
            </div>
        </div>

        <!-- Table -->
        <div
            class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden"
            @click="onTableClick"
        >
            <DataTable
                ref="table"
                :ajax="dtAjax"
                :columns="columns"
                :options="dtOptions"
                class="display w-full"
            />
        </div>
    </div>
</template>

<style>
@import 'datatables.net-dt/css/dataTables.dataTables.min.css';
</style>
