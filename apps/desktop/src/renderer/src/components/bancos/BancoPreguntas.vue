<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  NOMBRE_LENGUAJE,
  NOMBRE_NIVEL,
  NOMBRE_TIPO_ITEM,
  puntosPorOmision,
  respuestaDe,
  resumenEnunciado,
  sirveParaPrueba,
  type ItemBanco,
  type TemaBanco
} from '@shared/banco'
import type { Curso, Prueba, TipoItem } from '@shared/tipos'
import { api } from '../../lib/api'
import VistaMarkdown from '../VistaMarkdown.vue'

const props = defineProps<{ curso: Curso }>()

const temas = ref<TemaBanco[]>([])
const items = ref<ItemBanco[]>([])
const error = ref('')
const aviso = ref('')
const ocupado = ref(false)
const abierto = ref<number | null>(null)

const filtro = reactive({ tema: '', tipo: '' as '' | TipoItem, nivel: '', texto: '' })
const elegidos = reactive<Record<number, number>>({}) // id del ítem => puntos

const prueba = reactive({
  nombre: 'Prueba diagnóstica',
  momento: 'pre' as 'pre' | 'post',
  tipo: 'teorica' as 'teorica' | 'practica',
  forma: 'A' as 'A' | 'B',
  tiempo_limite_min: 40
})
const temasPrueba = ref<string[]>([])
const porTema = ref(3)
const faltan = ref<Record<string, number>>({})

const tituloDe = computed(() => new Map(temas.value.map((t) => [t.clave, t.titulo])))

const visibles = computed(() => {
  const texto = filtro.texto.trim().toLowerCase()
  return items.value.filter(
    (i) =>
      (!filtro.tema || i.tema === filtro.tema) &&
      (!filtro.tipo || i.tipo === filtro.tipo) &&
      (!filtro.nivel || i.nivel === filtro.nivel) &&
      (!texto || i.enunciado.md.toLowerCase().includes(texto))
  )
})

const seleccion = computed(() => items.value.filter((i) => elegidos[i.id] !== undefined))
// Una selección mezclada no se puede crear: el servidor la rechazaría, así que se avisa antes
const noSirven = computed(
  () => seleccion.value.filter((i) => !sirveParaPrueba(i.nivel, prueba.tipo)).length
)
const totalPuntos = computed(() =>
  seleccion.value.reduce((suma, i) => suma + Number(elegidos[i.id]), 0)
)

async function cargar(): Promise<void> {
  const l = props.curso.lenguaje
  temas.value = await api<TemaBanco[]>('GET', `/banco/temas?lenguaje=${l}`)
  items.value = await api<ItemBanco[]>('GET', `/banco/items?lenguaje=${l}&curso=${props.curso.id}`)
}

function alternar(item: ItemBanco): void {
  if (elegidos[item.id] !== undefined) delete elegidos[item.id]
  else elegidos[item.id] = puntosPorOmision(item.nivel)
}

function limpiarSeleccion(): void {
  Object.keys(elegidos).forEach((k) => delete elegidos[Number(k)])
  faltan.value = {}
}

function todosLosConceptos(): void {
  temasPrueba.value =
    temasPrueba.value.length === temas.value.length ? [] : temas.value.map((t) => t.clave)
}

async function ejecutar(accion: () => Promise<void>): Promise<void> {
  error.value = ''
  aviso.value = ''
  ocupado.value = true
  try {
    await accion()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    ocupado.value = false
  }
}

const proponer = (): Promise<void> =>
  ejecutar(async () => {
    const r = await api<{ ids: number[]; faltan: Record<string, number> }>(
      'POST',
      `/courses/${props.curso.id}/banco/seleccion`,
      { tipo: prueba.tipo, temas: temasPrueba.value, por_tema: porTema.value }
    )
    limpiarSeleccion()
    const porId = new Map(items.value.map((i) => [i.id, i]))
    r.ids.forEach((id) => {
      const item = porId.get(id)
      if (item) elegidos[id] = puntosPorOmision(item.nivel)
    })
    // PHP entrega un arreglo vacío cuando no falta nada
    faltan.value = Array.isArray(r.faltan) ? {} : r.faltan
    aviso.value = `Selección propuesta: ${r.ids.length} ítems. Revísala y ajústala antes de crear la prueba.`
  })

const agregarAlCurso = (): Promise<void> =>
  ejecutar(async () => {
    const r = await api<{ nuevos: number; ya_estaban: number }>(
      'POST',
      `/courses/${props.curso.id}/banco/items`,
      { ids: seleccion.value.map((i) => i.id) }
    )
    aviso.value = `${r.nuevos} ítems copiados al Banco de ítems del curso, como borrador${r.ya_estaban ? `; ${r.ya_estaban} ya estaban` : ''}.`
    limpiarSeleccion()
    await cargar()
  })

const crearPrueba = (): Promise<void> =>
  ejecutar(async () => {
    const creada = await api<Prueba>('POST', `/courses/${props.curso.id}/banco/prueba`, {
      ...prueba,
      items: seleccion.value.map((i) => ({ id: i.id, puntos: elegidos[i.id] }))
    })
    aviso.value = `Prueba «${creada.nombre}» creada con ${creada.items_count} ítems. Ya aparece en la pestaña Pruebas.`
    limpiarSeleccion()
    await cargar()
  })

onMounted(() => void ejecutar(cargar))
</script>

<template>
  <section class="tarjeta mb-4">
    <div class="mb-3 flex items-start justify-between gap-4">
      <div>
        <h2 class="font-semibold">Armar una prueba diagnóstica</h2>
        <p class="text-sm text-slate-600">
          Elige los conceptos y la plataforma propone una selección equilibrada, sin repetir lo que
          ya usaste en otra prueba de este curso. Después puedes ajustarla a mano.
        </p>
      </div>
      <RouterLink
        class="btn-sec shrink-0"
        :to="{
          name: 'curso-estudio',
          params: { id: curso.id },
          query: { accion: 'Ítems de evaluación' }
        }"
      >
        Generar con el agente
      </RouterLink>
    </div>

    <div class="grid grid-cols-6 gap-3">
      <div class="col-span-2">
        <label class="etiqueta" for="prueba-nombre">Nombre</label>
        <input id="prueba-nombre" v-model="prueba.nombre" class="campo" maxlength="160" />
      </div>
      <div>
        <label class="etiqueta" for="prueba-momento">Momento</label>
        <select id="prueba-momento" v-model="prueba.momento" class="campo">
          <option value="pre">Pre (diagnóstico)</option>
          <option value="post">Post</option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="prueba-tipo">Tipo</label>
        <select id="prueba-tipo" v-model="prueba.tipo" class="campo">
          <option value="teorica">Teórica</option>
          <option value="practica">Práctica</option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="prueba-forma">Forma</label>
        <select id="prueba-forma" v-model="prueba.forma" class="campo">
          <option>A</option>
          <option>B</option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="prueba-minutos">Minutos</label>
        <input
          id="prueba-minutos"
          v-model.number="prueba.tiempo_limite_min"
          class="campo"
          type="number"
          min="5"
          max="240"
        />
      </div>
    </div>

    <fieldset class="mt-3">
      <legend class="etiqueta flex items-center gap-3">
        Conceptos que evalúa
        <button id="todos-conceptos" type="button" class="underline" @click="todosLosConceptos">
          {{ temasPrueba.length === temas.length ? 'Quitar todos' : 'Todos los conceptos básicos' }}
        </button>
      </legend>
      <div class="grid grid-cols-3 gap-x-4 gap-y-0.5 text-sm">
        <label v-for="t in temas" :key="t.clave" class="flex items-center gap-1.5">
          <input v-model="temasPrueba" type="checkbox" :value="t.clave" />
          <span class="truncate" :title="t.descripcion">{{ t.titulo }}</span>
        </label>
      </div>
    </fieldset>

    <div class="mt-3 flex flex-wrap items-end gap-3">
      <div>
        <label class="etiqueta" for="por-tema">Ítems por concepto</label>
        <input
          id="por-tema"
          v-model.number="porTema"
          class="campo w-24"
          type="number"
          min="1"
          max="20"
        />
      </div>
      <button
        id="proponer-seleccion"
        class="btn-sec"
        :disabled="ocupado || !temasPrueba.length"
        @click="proponer"
      >
        Proponer selección
      </button>
      <button
        id="crear-prueba"
        class="btn"
        :disabled="ocupado || !seleccion.length || noSirven > 0 || !prueba.nombre.trim()"
        @click="crearPrueba"
      >
        Crear prueba con {{ seleccion.length }} ítems ({{ totalPuntos }} puntos)
      </button>
      <button
        id="agregar-curso"
        class="btn-sec"
        :disabled="ocupado || !seleccion.length"
        @click="agregarAlCurso"
      >
        Solo agregar al Banco de ítems del curso
      </button>
      <button v-if="seleccion.length" class="text-sm underline" @click="limpiarSeleccion">
        Limpiar
      </button>
    </div>

    <p v-if="noSirven" class="mt-2 text-sm text-amber-700">
      {{ noSirven }} ítems elegidos no sirven para una prueba
      {{ prueba.tipo === 'teorica' ? 'teórica' : 'práctica' }}: una prueba teórica lleva preguntas y
      una práctica, problemas de programación.
    </p>
    <p v-for="(n, tema) in faltan" :key="tema" class="mt-1 text-sm text-amber-700">
      «{{ tituloDe.get(String(tema)) ?? tema }}»: faltaron {{ n }} ítems sin usar para este tipo de
      prueba.
    </p>
    <p v-if="aviso" id="aviso-preguntas" class="mt-2 text-sm text-green-700">{{ aviso }}</p>
    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
  </section>

  <section class="tarjeta">
    <div class="mb-3 grid grid-cols-4 gap-3">
      <div>
        <label class="etiqueta" for="filtro-tema">Concepto</label>
        <select id="filtro-tema" v-model="filtro.tema" class="campo">
          <option value="">Todos</option>
          <option v-for="t in temas" :key="t.clave" :value="t.clave">{{ t.titulo }}</option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="filtro-tipo">Tipo</label>
        <select id="filtro-tipo" v-model="filtro.tipo" class="campo">
          <option value="">Todos</option>
          <option v-for="(nombre, clave) in NOMBRE_TIPO_ITEM" :key="clave" :value="clave">
            {{ nombre }}
          </option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="filtro-nivel">Nivel</label>
        <select id="filtro-nivel" v-model="filtro.nivel" class="campo">
          <option value="">Todos</option>
          <option v-for="(nombre, clave) in NOMBRE_NIVEL" :key="clave" :value="clave">
            {{ nombre }}
          </option>
        </select>
      </div>
      <div>
        <label class="etiqueta" for="filtro-texto">Buscar</label>
        <input id="filtro-texto" v-model="filtro.texto" class="campo" />
      </div>
    </div>

    <p id="total-items" class="mb-2 text-sm text-slate-500">
      {{ visibles.length }} de {{ items.length }} ítems para {{ NOMBRE_LENGUAJE[curso.lenguaje] }}
    </p>
    <table class="w-full text-sm">
      <thead class="text-left text-slate-500">
        <tr>
          <th class="w-8" />
          <th class="py-1 pr-4">Enunciado</th>
          <th class="pr-4">Concepto</th>
          <th class="pr-4">Tipo</th>
          <th class="pr-4">Nivel</th>
          <th class="w-20">Puntos</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="i in visibles" :key="i.id">
          <tr class="border-t border-slate-100 align-top">
            <td class="py-2">
              <input
                type="checkbox"
                :checked="elegidos[i.id] !== undefined"
                :aria-label="`Elegir ${i.codigo}`"
                @change="alternar(i)"
              />
            </td>
            <td class="max-w-md py-2 pr-4">
              <button
                class="block w-full truncate text-left hover:text-indigo-700"
                :title="resumenEnunciado(i.enunciado.md)"
                @click="abierto = abierto === i.id ? null : i.id"
              >
                {{ resumenEnunciado(i.enunciado.md) }}
              </button>
              <span v-if="i.en_curso" class="text-xs text-green-700">Ya está en el curso</span>
            </td>
            <td class="py-2 pr-4 text-slate-600">{{ tituloDe.get(i.tema) ?? i.tema }}</td>
            <td class="py-2 pr-4">{{ NOMBRE_TIPO_ITEM[i.tipo] }}</td>
            <td class="py-2 pr-4">{{ NOMBRE_NIVEL[i.nivel] }}</td>
            <td class="py-1">
              <input
                v-if="elegidos[i.id] !== undefined"
                v-model.number="elegidos[i.id]"
                class="campo w-16"
                type="number"
                min="0.5"
                step="0.5"
              />
            </td>
          </tr>
          <tr v-if="abierto === i.id">
            <td />
            <td colspan="5" class="space-y-2 rounded bg-slate-50 p-3">
              <VistaMarkdown :md="i.enunciado.md" />
              <ul v-if="i.enunciado.opciones" class="list-inside list-disc">
                <li v-for="o in i.enunciado.opciones" :key="o.id">{{ o.texto }}</li>
              </ul>
              <div v-if="respuestaDe(i)">
                <p class="etiqueta">
                  {{ i.tipo === 'parsons' ? 'Orden correcto' : 'Respuesta correcta' }}
                </p>
                <pre class="whitespace-pre-wrap rounded bg-white p-2 font-mono text-xs">{{
                  respuestaDe(i)
                }}</pre>
              </div>
              <div v-if="i.solucion">
                <p class="etiqueta">Solución de referencia</p>
                <pre class="overflow-x-auto rounded bg-white p-2 font-mono text-xs">{{
                  i.solucion
                }}</pre>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </section>
</template>
