<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAppStore } from './stores/app'
import { useCatalogStore } from './stores/catalog'
import SearchableSelect from './components/SearchableSelect.vue'

const appStore = useAppStore()
const catalog  = useCatalogStore()

const filtersOpen = ref(false)

const companyOptions = computed(() =>
    catalog.companies.map(c => ({ value: c.id, label: c.name }))
)
const employeeOptions = computed(() =>
    (catalog.employees[appStore.selectedCompanyId] ?? []).map(e => ({ value: e.id, label: e.name }))
)
const projectOptions = computed(() =>
    (catalog.projects[appStore.selectedCompanyId] ?? []).map(p => ({ value: p.id, label: p.name }))
)
const taskOptions = computed(() => {
    const companyId = appStore.selectedCompanyId
    if (!companyId) return []
    const all = catalog.tasks[companyId] ?? []
    // When a project is selected show only its tasks; otherwise show all company tasks
    if (appStore.selectedProjectId) {
        return all
            .filter(t => t.project_id === appStore.selectedProjectId)
            .map(t => ({ value: t.id, label: t.name }))
    }
    return all.map(t => ({ value: t.id, label: t.name }))
})

onMounted(() => catalog.fetchCompanies())

// Company change → fetch catalog + reset downstream global filters
watch(() => appStore.selectedCompanyId, (id) => {
    appStore.selectedEmployeeId = null
    appStore.selectedProjectId  = null
    appStore.selectedTaskId     = null
    if (id) {
        catalog.fetchEmployees(id)
        catalog.fetchProjects(id)
        catalog.fetchTasks(id)
    }
})

// Project change → reset task
watch(() => appStore.selectedProjectId, () => {
    appStore.selectedTaskId = null
})

function toggleFilters () {
    if (filtersOpen.value) {
        appStore.selectedEmployeeId = null
        appStore.selectedProjectId  = null
        appStore.selectedTaskId     = null
        appStore.selectedDateFrom   = ''
        appStore.selectedDateTo     = ''
    }
    filtersOpen.value = !filtersOpen.value
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">

        <!-- Nav bar -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex items-center justify-between h-14">
                    <span class="font-semibold text-gray-800 tracking-tight">Time Tracker</span>
                    <nav class="flex gap-1">
                        <RouterLink
                            :to="{ name: 'new-entry' }"
                            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
                            :class="$route.name === 'new-entry'
                                ? 'bg-gray-100 text-gray-900'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            New Entry
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'history' }"
                            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
                            :class="$route.name === 'history'
                                ? 'bg-gray-100 text-gray-900'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            History
                        </RouterLink>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Global filter bar -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-3 space-y-3">

                <!-- Row 1: company + toggle -->
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-600 whitespace-nowrap">Company</span>
                    <div class="w-64">
                        <SearchableSelect
                            v-model="appStore.selectedCompanyId"
                            :options="companyOptions"
                            placeholder="All companies"
                            :clearable="true"
                        />
                    </div>
                    <button
                        @click="toggleFilters"
                        class="ml-auto flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <span>{{ filtersOpen ? 'Fewer filters' : 'More filters' }}</span>
                        <svg
                            class="w-4 h-4 transition-transform"
                            :class="filtersOpen ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Row 2: collapsible extra filters -->
                <div v-if="filtersOpen" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Employee</label>
                        <SearchableSelect
                            v-model="appStore.selectedEmployeeId"
                            :options="employeeOptions"
                            placeholder="All employees"
                            :clearable="true"
                            :disabled="!appStore.selectedCompanyId"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                        <SearchableSelect
                            v-model="appStore.selectedProjectId"
                            :options="projectOptions"
                            placeholder="All projects"
                            :clearable="true"
                            :disabled="!appStore.selectedCompanyId"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Task</label>
                        <SearchableSelect
                            v-model="appStore.selectedTaskId"
                            :options="taskOptions"
                            placeholder="All tasks"
                            :clearable="true"
                            :disabled="!appStore.selectedCompanyId"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date from</label>
                        <input
                            type="date"
                            v-model="appStore.selectedDateFrom"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date to</label>
                        <input
                            type="date"
                            v-model="appStore.selectedDateTo"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

            </div>
        </div>

        <main class="max-w-4xl mx-auto px-4 py-8">
            <RouterView />
        </main>

    </div>
</template>
