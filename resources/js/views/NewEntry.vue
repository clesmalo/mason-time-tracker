<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useAppStore } from '../stores/app'
import { useCatalogStore } from '../stores/catalog'
import SearchableSelect from '../components/SearchableSelect.vue'

const appStore = useAppStore()
const catalog  = useCatalogStore()

const form = ref({
    company_id:   null,
    employee_id:  null,
    project_id:   null,
    task_id:      null,
    date:         '',
    started_time: '',
    ended_time:   '',
})

const errors     = ref({})
const submitting = ref(false)
const success    = ref(false)

// Apply all global pre-fills in cascade order.
// Two nextTick hops let the form's own cascade watches (company → reset employee/project/task,
// project → reset task) fire and settle before we write the downstream values.
async function applyGlobalPreFill () {
    form.value.company_id = appStore.selectedCompanyId
    await nextTick()                                      // company cascade settles
    form.value.employee_id = appStore.selectedEmployeeId
    form.value.project_id  = appStore.selectedProjectId
    await nextTick()                                      // project cascade settles
    form.value.task_id = appStore.selectedTaskId
    form.value.date    = appStore.selectedDateFrom || ''
}

onMounted(() => {
    catalog.fetchCompanies()
    applyGlobalPreFill()
})

// Re-apply whenever the global company changes (App.vue already resets the other global
// fields, so they'll be null by the time the second nextTick resolves)
watch(() => appStore.selectedCompanyId, applyGlobalPreFill)

// For individual field changes (user updates global bar while on this tab)
watch(() => appStore.selectedEmployeeId, (id) => { form.value.employee_id = id })
watch(() => appStore.selectedProjectId,  async (id) => {
    form.value.project_id = id
    await nextTick()
    form.value.task_id = appStore.selectedTaskId
})
watch(() => appStore.selectedTaskId, (id) => { form.value.task_id = id })
watch(() => appStore.selectedDateFrom, (d) => { form.value.date = d || '' })

// Form-level cascade: company change resets downstream + fetches catalog
watch(() => form.value.company_id, (id) => {
    form.value.employee_id = null
    form.value.project_id  = null
    form.value.task_id     = null
    errors.value = {}
    if (id) {
        catalog.fetchEmployees(id)
        catalog.fetchProjects(id)
        catalog.fetchTasks(id)
    }
})

// Form-level cascade: project change resets task
watch(() => form.value.project_id, () => {
    form.value.task_id = null
})

const companyOptions = computed(() =>
    catalog.companies.map(c => ({ value: c.id, label: c.name }))
)
const employeeOptions = computed(() =>
    (catalog.employees[form.value.company_id] ?? []).map(e => ({ value: e.id, label: e.name }))
)
const projectOptions = computed(() =>
    (catalog.projects[form.value.company_id] ?? []).map(p => ({ value: p.id, label: p.name }))
)
const taskOptions = computed(() => {
    if (!form.value.company_id) return []
    return catalog
        .getTasksForProject(form.value.company_id, form.value.project_id)
        .map(t => ({ value: t.id, label: t.name }))
})

async function handleCreateProject(name) {
    const res = await fetch('/api/projects', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body:    JSON.stringify({ company_id: form.value.company_id, name }),
    })
    if (res.ok) {
        const project = await res.json()
        catalog.addProject(form.value.company_id, project)
        form.value.project_id = project.id
    }
}

async function handleCreateTask(name) {
    const res = await fetch('/api/tasks', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body:    JSON.stringify({
            company_id: form.value.company_id,
            project_id: form.value.project_id,
            name,
        }),
    })
    if (res.ok) {
        const task = await res.json()
        catalog.addTask(form.value.company_id, task)
        form.value.task_id = task.id
    }
}

function validate() {
    const e = {}
    if (!form.value.company_id)   e.company_id  = ['Company is required.']
    if (!form.value.employee_id)  e.employee_id = ['Employee is required.']
    if (!form.value.task_id)      e.task_id     = ['Task is required.']
    if (!form.value.date)         e.date        = ['Date is required.']
    if (!form.value.started_time) e.started_at  = ['Start time is required.']
    if (!form.value.ended_time)   e.ended_at    = ['End time is required.']
    return e
}

async function submit() {
    errors.value = {}
    success.value = false

    const clientErrors = validate()
    if (Object.keys(clientErrors).length) {
        errors.value = clientErrors
        return
    }

    submitting.value = true
    try {
        const res = await fetch('/api/time-entries', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify({
                company_id:  form.value.company_id,
                employee_id: form.value.employee_id,
                project_id:  form.value.project_id,
                task_id:     form.value.task_id,
                started_at:  `${form.value.date} ${form.value.started_time}:00`,
                ended_at:    `${form.value.date} ${form.value.ended_time}:00`,
            }),
        })

        const data = await res.json()

        if (!res.ok) {
            errors.value = data.errors ?? {}
            return
        }

        success.value = true
        // Keep company + employee always; restore project/task/date from global
        // filters if they were set, otherwise clear them
        form.value.project_id   = appStore.selectedProjectId  ?? null
        form.value.task_id      = appStore.selectedTaskId     ?? null
        form.value.date         = appStore.selectedDateFrom   || ''
        form.value.started_time = ''
        form.value.ended_time   = ''

        setTimeout(() => { success.value = false }, 4000)
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="max-w-xl">
        <h1 class="text-xl font-semibold text-gray-900 mb-6">New Time Entry</h1>

        <form
            @submit.prevent="submit"
            class="bg-white border border-gray-200 rounded-xl p-6 space-y-5 shadow-sm"
        >
            <!-- Company -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.company_id"
                    :options="companyOptions"
                    placeholder="Select a company…"
                    :error="errors.company_id?.[0]"
                />
                <p v-if="errors.company_id" class="mt-1 text-xs text-red-500">
                    {{ errors.company_id[0] }}
                </p>
            </div>

            <!-- Employee -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Employee <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.employee_id"
                    :options="employeeOptions"
                    placeholder="Select an employee…"
                    :disabled="!form.company_id"
                    :error="errors.employee_id?.[0]"
                />
                <p v-if="errors.employee_id" class="mt-1 text-xs text-red-500">
                    {{ errors.employee_id[0] }}
                </p>
            </div>

            <!-- Project (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                <SearchableSelect
                    v-model="form.project_id"
                    :options="projectOptions"
                    placeholder="No project"
                    :disabled="!form.company_id"
                    :error="errors.project_id?.[0]"
                    :clearable="true"
                    :allow-create="!!form.company_id"
                    @create="handleCreateProject"
                />
                <p v-if="errors.project_id" class="mt-1 text-xs text-red-500">
                    {{ errors.project_id[0] }}
                </p>
            </div>

            <!-- Task -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Task <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.task_id"
                    :options="taskOptions"
                    placeholder="Select a task…"
                    :disabled="!form.company_id"
                    :error="errors.task_id?.[0]"
                    :allow-create="!!form.company_id"
                    @create="handleCreateTask"
                />
                <p v-if="errors.task_id" class="mt-1 text-xs text-red-500">
                    {{ errors.task_id[0] }}
                </p>
            </div>

            <!-- Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Date <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    v-model="form.date"
                    class="w-full px-3 py-2 text-sm border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow"
                    :class="errors.date ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                />
                <p v-if="errors.date" class="mt-1 text-xs text-red-500">
                    {{ errors.date[0] }}
                </p>
            </div>

            <!-- Start / End time -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Start time <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="time"
                        v-model="form.started_time"
                        class="w-full px-3 py-2 text-sm border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow"
                        :class="errors.started_at ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                    />
                    <p v-if="errors.started_at" class="mt-1 text-xs text-red-500">
                        {{ errors.started_at[0] }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        End time <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="time"
                        v-model="form.ended_time"
                        class="w-full px-3 py-2 text-sm border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow"
                        :class="errors.ended_at ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                    />
                    <p v-if="errors.ended_at" class="mt-1 text-xs text-red-500">
                        {{ errors.ended_at[0] }}
                    </p>
                </div>
            </div>

            <!-- Success feedback -->
            <p v-if="success" class="text-sm font-medium text-green-600">
                ✓ Time entry logged successfully.
            </p>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="submitting"
                class="w-full py-2 px-4 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
            >
                {{ submitting ? 'Saving…' : 'Log Entry' }}
            </button>
        </form>
    </div>
</template>
