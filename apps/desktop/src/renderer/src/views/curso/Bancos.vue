<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import type { Curso } from '@shared/tipos'
import { api } from '../../lib/api'
import PestanasCurso from '../../components/PestanasCurso.vue'
import BancoPreguntas from '../../components/bancos/BancoPreguntas.vue'
import BancoObjetivos from '../../components/bancos/BancoObjetivos.vue'
import BancoRecursos from '../../components/bancos/BancoRecursos.vue'

const props = defineProps<{ id: string }>()
const route = useRoute()

const SECCIONES = [
  { clave: 'preguntas', texto: 'Preguntas y problemas' },
  { clave: 'objetivos', texto: 'Objetivos y ruta' },
  { clave: 'recursos', texto: 'Recursos' }
] as const

const curso = ref<Curso | null>(null)
const error = ref('')
const seccion = ref(
  SECCIONES.find((s) => s.clave === route.query.seccion)?.clave ?? SECCIONES[0].clave
)

onMounted(async () => {
  try {
    curso.value = await api<Curso>('GET', `/courses/${props.id}`)
  } catch (e) {
    error.value = (e as Error).message
  }
})
</script>

<template>
  <h1 class="mb-2 text-2xl font-semibold">Bancos</h1>
  <PestanasCurso :id="id" />

  <div class="mb-4 flex gap-2 text-sm">
    <button
      v-for="s in SECCIONES"
      :key="s.clave"
      :data-seccion="s.clave"
      class="rounded-md px-3 py-1.5"
      :class="seccion === s.clave ? 'bg-indigo-600 text-white' : 'bg-slate-100 hover:bg-slate-200'"
      @click="seccion = s.clave"
    >
      {{ s.texto }}
    </button>
  </div>

  <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
  <template v-if="curso">
    <!-- v-show: cambiar de sección no debe perder lo que el instructor ya seleccionó. Va en un div porque
         v-show no funciona sobre un componente con varios elementos raíz -->
    <div v-show="seccion === 'preguntas'"><BancoPreguntas :curso="curso" /></div>
    <div v-show="seccion === 'objetivos'"><BancoObjetivos :curso="curso" /></div>
    <div v-show="seccion === 'recursos'"><BancoRecursos :curso="curso" /></div>
  </template>
</template>
