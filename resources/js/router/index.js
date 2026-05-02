import { createRouter, createWebHistory } from 'vue-router'
import NewEntry from '../views/NewEntry.vue'
import History from '../views/History.vue'

export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/',        name: 'new-entry', component: NewEntry },
        { path: '/history', name: 'history',   component: History  },
    ],
})
