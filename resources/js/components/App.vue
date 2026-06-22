<template>
    <div id="app">
        <component :is="layout" v-if="layout" />
    </div>
</template>

<script setup>
import { ref, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import './styles.css'


// Load layout components dynamically.
const layouts = import.meta.glob('../layouts/*.vue', { eager: true })
const layoutComponents = Object.keys(layouts).reduce((components, path) => {
    const name = path.match(/\.\/layouts\/(.*)(\.vue)$/)[1]
    components[name] = layouts[path].default
    return components
}, {})


const router = useRouter()

const layout = ref(null)
const defaultLayout = 'dashboard'

const setLayout = (newLayout) => {
    if (!newLayout || !layoutComponents[newLayout]) {
        newLayout = defaultLayout
    }
    layout.value = markRaw(layoutComponents[newLayout])
}

// Update layout whenever route changes
router.afterEach(to => {
    setLayout(to.meta.layout)
})
</script>

