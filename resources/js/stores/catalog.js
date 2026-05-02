import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useCatalogStore = defineStore('catalog', () => {
    const companies  = ref([])
    const employees  = ref({})  // { [companyId]: Employee[] }
    const projects   = ref({})  // { [companyId]: Project[] }
    const tasks      = ref({})  // { [companyId]: Task[] }

    async function fetchCompanies() {
        if (companies.value.length) return
        const res = await fetch('/api/companies')
        companies.value = await res.json()
    }

    async function fetchEmployees(companyId) {
        if (employees.value[companyId]) return
        const res = await fetch(`/api/employees?company_id=${companyId}`)
        employees.value[companyId] = await res.json()
    }

    async function fetchProjects(companyId) {
        if (projects.value[companyId]) return
        const res = await fetch(`/api/projects?company_id=${companyId}`)
        projects.value[companyId] = await res.json()
    }

    async function fetchTasks(companyId) {
        if (tasks.value[companyId]) return
        const res = await fetch(`/api/tasks?company_id=${companyId}`)
        tasks.value[companyId] = await res.json()
    }

    // Filter already-cached tasks by project (null = company-level tasks)
    function getTasksForProject(companyId, projectId) {
        return (tasks.value[companyId] ?? []).filter(
            t => t.project_id === (projectId ?? null)
        )
    }

    // Append newly created records into the cache so the UI updates immediately
    function addProject(companyId, project) {
        if (projects.value[companyId]) {
            projects.value[companyId].push(project)
        }
    }

    function addTask(companyId, task) {
        if (tasks.value[companyId]) {
            tasks.value[companyId].push(task)
        }
    }

    return {
        companies, employees, projects, tasks,
        fetchCompanies, fetchEmployees, fetchProjects, fetchTasks,
        getTasksForProject,
        addProject, addTask,
    }
})
