<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    modelValue:  { default: null },
    options:     { type: Array,   default: () => [] }, // [{ value, label }]
    placeholder: { type: String,  default: 'Select…' },
    disabled:    { type: Boolean, default: false },
    error:       { type: String,  default: '' },
    clearable:   { type: Boolean, default: false },
    allowCreate: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'create'])

const inputRef   = ref(null)
const query      = ref('')
const open       = ref(false)
const highlighted = ref(-1)

const selectedLabel = computed(() =>
    props.options.find(o => o.value === props.modelValue)?.label ?? ''
)

const filtered = computed(() => {
    if (!query.value) return props.options
    const q = query.value.toLowerCase()
    return props.options.filter(o => o.label.toLowerCase().includes(q))
})

const showCreate = computed(() =>
    props.allowCreate &&
    query.value.trim().length > 0 &&
    !filtered.value.some(o => o.label.toLowerCase() === query.value.trim().toLowerCase())
)

const totalItems = computed(() => filtered.value.length + (showCreate.value ? 1 : 0))

function openDropdown() {
    if (props.disabled) return
    query.value = ''
    open.value  = true
    highlighted.value = -1
}

function closeDropdown() {
    open.value  = false
    query.value = ''
    highlighted.value = -1
}

function select(value) {
    emit('update:modelValue', value)
    closeDropdown()
}

function clear(e) {
    e.stopPropagation()
    emit('update:modelValue', null)
}

function handleCreate() {
    emit('create', query.value.trim())
    closeDropdown()
}

function onKeydown(e) {
    if (!open.value) {
        if (['Enter', ' ', 'ArrowDown'].includes(e.key)) {
            e.preventDefault()
            openDropdown()
        }
        return
    }

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault()
            highlighted.value = Math.min(highlighted.value + 1, totalItems.value - 1)
            break
        case 'ArrowUp':
            e.preventDefault()
            highlighted.value = Math.max(highlighted.value - 1, -1)
            break
        case 'Enter':
            e.preventDefault()
            e.stopPropagation() // prevent form submission while dropdown is open
            if (highlighted.value >= 0 && highlighted.value < filtered.value.length) {
                select(filtered.value[highlighted.value].value)
            } else if (showCreate.value && highlighted.value === filtered.value.length) {
                handleCreate()
            }
            break
        case 'Escape':
            e.preventDefault()
            closeDropdown()
            inputRef.value?.blur()
            break
    }
}

function onBlur() {
    // Delay so mousedown on an option fires before blur closes the list
    setTimeout(closeDropdown, 150)
}

watch(filtered, () => { highlighted.value = -1 })
</script>

<template>
    <div class="relative w-full">
        <div
            class="flex items-center w-full border rounded-md overflow-hidden transition-shadow"
            :class="[
                error   ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-300',
                !disabled ? 'focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500' : '',
            ]"
        >
            <input
                ref="inputRef"
                :value="open ? query : selectedLabel"
                @input="query = $event.target.value"
                @focus="openDropdown"
                @blur="onBlur"
                @keydown="onKeydown"
                :placeholder="placeholder"
                :disabled="disabled"
                class="flex-1 px-3 py-2 text-sm bg-transparent outline-none"
                :class="disabled ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'cursor-pointer'"
            />
            <button
                v-if="clearable && modelValue !== null"
                type="button"
                @mousedown.prevent="clear"
                tabindex="-1"
                class="px-2 text-gray-400 hover:text-gray-600 text-lg leading-none"
            >
                &times;
            </button>
        </div>

        <ul
            v-if="open"
            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-auto text-sm"
        >
            <li
                v-if="filtered.length === 0 && !showCreate"
                class="px-3 py-2 text-gray-400 italic"
            >
                No options found.
            </li>

            <li
                v-for="(opt, i) in filtered"
                :key="opt.value"
                @mousedown.prevent="select(opt.value)"
                class="px-3 py-2 cursor-pointer select-none"
                :class="[
                    i === highlighted ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50',
                    opt.value === modelValue ? 'font-medium' : '',
                ]"
            >
                {{ opt.label }}
            </li>

            <li
                v-if="showCreate"
                @mousedown.prevent="handleCreate"
                class="px-3 py-2 cursor-pointer select-none border-t border-gray-100 text-blue-600"
                :class="highlighted === filtered.length ? 'bg-blue-50' : 'hover:bg-gray-50'"
            >
                + Create "{{ query }}"
            </li>
        </ul>
    </div>
</template>
