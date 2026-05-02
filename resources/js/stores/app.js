import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
    const selectedCompanyId  = ref(null)
    const selectedEmployeeId = ref(null)
    const selectedProjectId  = ref(null)
    const selectedTaskId     = ref(null)
    const selectedDateFrom   = ref('')
    const selectedDateTo     = ref('')

    return {
        selectedCompanyId,
        selectedEmployeeId,
        selectedProjectId,
        selectedTaskId,
        selectedDateFrom,
        selectedDateTo,
    }
})
