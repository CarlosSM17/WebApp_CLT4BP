<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { textoAviso, type AvisoRuta, type PasoRuta, type TemaBanco } from '@shared/banco'
import type { Curso } from '@shared/tipos'
import { api } from '../../lib/api'

const props = defineProps<{ curso: Curso }>()

const METODOS = [
  { valor: 'recall', texto: 'Recuerdo' },
  { valor: 'comprension', texto: 'Comprensión' },
  { valor: 'practica', texto: 'Práctica de programación' }
]

const temas = ref<TemaBanco[]>([])
const elegidos = ref<string[]>([])
const ruta = ref<PasoRuta[]>([])
const avisos = ref<AvisoRuta[]>([])
const crearClases = ref(true)
const error = ref('')
const aviso = ref('')
const ocupado = ref(false)
const aplicada = ref(false)

// Objetivo propio: mismo formulario para crear y para editar
const vacio = (): Omit<TemaBanco, 'id' | 'clave' | 'propio' | 'items_count'> => ({
  titulo: '',
  descripcion: '',
  tipo: 'habilidad',
  evaluacion: ['recall', 'practica'],
  prerrequisitos: [],
  horas: 4,
  complejidad: ''
})
const propio = reactive(vacio())
const editando = ref<number | null>(null)
const formularioAbierto = ref(false)

const tituloDe = computed(() => new Map(temas.value.map((t) => [t.clave, t.titulo])))
const horasTotales = computed(() => ruta.value.reduce((suma, p) => suma + p.horas, 0))
// Un tema no puede ser requisito de sí mismo
const posiblesRequisitos = computed(() => temas.value.filter((t) => t.id !== editando.value))

async function cargar(): Promise<void> {
  temas.value = await api<TemaBanco[]>('GET', `/banco/temas?lenguaje=${props.curso.lenguaje}`)
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
    const r = await api<{ ruta: PasoRuta[]; avisos: AvisoRuta[] }>('POST', '/banco/ruta', {
      temas: elegidos.value
    })
    ruta.value = r.ruta
    avisos.value = r.avisos
    aplicada.value = false
  })

async function revisar(): Promise<void> {
  avisos.value = ruta.value.length
    ? await api<AvisoRuta[]>('POST', '/banco/ruta/avisos', {
        orden: ruta.value.map((p) => p.clave)
      })
    : []
}

function mover(i: number, delta: number): void {
  const destino = i + delta
  if (destino < 0 || destino >= ruta.value.length) return
  const [paso] = ruta.value.splice(i, 1)
  ruta.value.splice(destino, 0, paso)
  void ejecutar(revisar)
}

function quitar(i: number): void {
  ruta.value.splice(i, 1)
  void ejecutar(revisar)
}

const aplicar = (): Promise<void> =>
  ejecutar(async () => {
    const r = await api<{ objetivos: number; clases: number; reutilizados: number }>(
      'POST',
      `/courses/${props.curso.id}/banco/ruta`,
      { orden: ruta.value.map((p) => p.clave), crear_clases: crearClases.value }
    )
    // La consola trabaja sobre su copia local: se baja de una vez lo que se acaba de crear
    await window.consola.diseno.sincronizar(props.curso.id)
    aplicada.value = true
    aviso.value =
      `Ruta en el diseño del curso: ${r.objetivos} objetivos y ${r.clases} clases de tareas nuevos` +
      (r.reutilizados ? `; ${r.reutilizados} objetivos ya estaban.` : '.')
  })

function abrirFormulario(tema?: TemaBanco): void {
  Object.assign(propio, vacio(), tema ?? {})
  propio.complejidad = tema?.complejidad ?? ''
  editando.value = tema?.id ?? null
  formularioAbierto.value = true
}

const guardarPropio = (): Promise<void> =>
  ejecutar(async () => {
    const cuerpo = { ...propio, complejidad: propio.complejidad || null }
    const guardado = editando.value
      ? await api<TemaBanco>('PUT', `/banco/temas/${editando.value}`, cuerpo)
      : await api<TemaBanco>('POST', '/banco/temas', cuerpo)
    await cargar()
    if (!editando.value) elegidos.value.push(guardado.clave)
    aviso.value = `Objetivo «${guardado.titulo}» guardado en tu banco.`
    formularioAbierto.value = false
    editando.value = null
  })

function eliminarPropio(t: TemaBanco): Promise<void> | undefined {
  if (!confirm(`¿Quitar «${t.titulo}» de tu banco? Lo que ya esté en un curso se queda.`)) return
  return ejecutar(async () => {
    await api('DELETE', `/banco/temas/${t.id}`)
    elegidos.value = elegidos.value.filter((c) => c !== t.clave)
    ruta.value = ruta.value.filter((p) => p.clave !== t.clave)
    await cargar()
    await revisar()
  })
}

onMounted(() => void ejecutar(cargar))
</script>

<template>
  <div class="grid grid-cols-2 items-start gap-4">
    <section class="tarjeta">
      <div class="mb-2 flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold">Banco de objetivos</h2>
          <p class="text-sm text-slate-600">
            Elige los temas de tu curso o agrega los tuyos. Los prerrequisitos que falten se agregan
            a la ruta.
          </p>
        </div>
        <RouterLink
          class="btn-sec shrink-0"
          :to="{
            name: 'curso-estudio',
            params: { id: curso.id },
            query: { accion: 'Proponer objetivos de desempeño' }
          }"
        >
          Proponer con el agente
        </RouterLink>
      </div>

      <div class="mb-3 flex items-center gap-3">
        <button
          id="proponer-ruta"
          class="btn"
          :disabled="ocupado || !elegidos.length"
          @click="proponer"
        >
          Proponer ruta con {{ elegidos.length }} temas
        </button>
        <button id="nuevo-objetivo" class="btn-sec" @click="abrirFormulario()">
          + Objetivo propio
        </button>
      </div>

      <form
        v-if="formularioAbierto"
        id="form-objetivo"
        class="mb-3 space-y-2 rounded border border-slate-200 p-3"
        @submit.prevent="guardarPropio"
      >
        <div>
          <label class="etiqueta" for="propio-titulo">Tema</label>
          <input
            id="propio-titulo"
            v-model="propio.titulo"
            class="campo"
            required
            maxlength="160"
          />
        </div>
        <div>
          <label class="etiqueta" for="propio-descripcion"
            >Objetivo (verbo observable + condición + criterio)</label
          >
          <textarea
            id="propio-descripcion"
            v-model="propio.descripcion"
            class="campo h-20"
            required
            minlength="10"
            maxlength="2000"
          />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="etiqueta" for="propio-tipo">Tipo</label>
            <select id="propio-tipo" v-model="propio.tipo" class="campo">
              <option value="conocimiento">Conocimiento</option>
              <option value="habilidad">Habilidad</option>
              <option value="actitud">Actitud</option>
            </select>
          </div>
          <div>
            <label class="etiqueta" for="propio-horas">Horas estimadas</label>
            <input
              id="propio-horas"
              v-model.number="propio.horas"
              class="campo"
              type="number"
              min="1"
              max="200"
            />
          </div>
        </div>
        <fieldset>
          <legend class="etiqueta">Se evaluará con</legend>
          <label v-for="m in METODOS" :key="m.valor" class="mr-3 inline-flex gap-1 text-sm">
            <input v-model="propio.evaluacion" type="checkbox" :value="m.valor" /> {{ m.texto }}
          </label>
        </fieldset>
        <div>
          <label class="etiqueta" for="propio-complejidad"
            >Qué lo hace más complejo que lo anterior (opcional)</label
          >
          <input
            id="propio-complejidad"
            v-model="propio.complejidad"
            class="campo"
            maxlength="1000"
          />
        </div>
        <fieldset>
          <legend class="etiqueta">Prerrequisitos</legend>
          <div class="grid max-h-32 grid-cols-2 gap-x-3 overflow-y-auto text-sm">
            <label v-for="t in posiblesRequisitos" :key="t.clave" class="flex gap-1">
              <input v-model="propio.prerrequisitos" type="checkbox" :value="t.clave" />
              <span class="truncate">{{ t.titulo }}</span>
            </label>
          </div>
        </fieldset>
        <div class="flex gap-2">
          <button id="guardar-objetivo" class="btn" :disabled="ocupado">Guardar</button>
          <button type="button" class="btn-sec" @click="formularioAbierto = false">Cancelar</button>
        </div>
      </form>

      <ul class="divide-y divide-slate-100 text-sm">
        <li v-for="t in temas" :key="t.clave" class="flex gap-2 py-2">
          <input
            v-model="elegidos"
            type="checkbox"
            :value="t.clave"
            class="mt-1"
            :aria-label="`Elegir ${t.titulo}`"
          />
          <div class="min-w-0 flex-1">
            <p class="font-medium">
              {{ t.titulo }}
              <span v-if="t.propio" class="ml-1 rounded bg-indigo-100 px-1 text-xs text-indigo-800"
                >propio</span
              >
              <span class="ml-1 text-xs font-normal text-slate-500"
                >{{ t.horas }} h · {{ t.items_count }} ítems</span
              >
            </p>
            <p class="text-slate-600">{{ t.descripcion }}</p>
            <p v-if="t.prerrequisitos.length" class="text-xs text-slate-500">
              Requiere:
              {{ t.prerrequisitos.map((c) => tituloDe.get(c) ?? c).join(', ') }}
            </p>
            <p v-if="t.propio" class="space-x-2 text-xs">
              <button class="underline" @click="abrirFormulario(t)">Editar</button>
              <button class="text-red-600 underline" @click="eliminarPropio(t)">Quitar</button>
            </p>
          </div>
        </li>
      </ul>
    </section>

    <section class="tarjeta sticky top-2">
      <h2 class="font-semibold">Ruta de aprendizaje propuesta</h2>
      <p v-if="!ruta.length" class="mt-1 text-sm text-slate-500">
        Elige temas y pulsa «Proponer ruta»: se ordenan de lo simple a lo complejo, cada uno después
        de lo que necesita.
      </p>

      <template v-else>
        <p id="resumen-ruta" class="mt-1 text-sm text-slate-600">
          {{ ruta.length }} temas · {{ horasTotales }} horas estimadas. Puedes reordenar o quitar
          temas; si algo deja de cumplirse, se te avisa.
        </p>
        <ol id="pasos-ruta" class="mt-2 space-y-1 text-sm">
          <li
            v-for="(p, i) in ruta"
            :key="p.clave"
            class="flex items-start gap-2 rounded border border-slate-200 p-2"
          >
            <span class="w-6 shrink-0 text-right font-mono text-slate-400">{{ i + 1 }}</span>
            <div class="min-w-0 flex-1">
              <p class="font-medium">{{ p.titulo }}</p>
              <p v-if="p.agregado_por.length" class="text-xs text-indigo-700">
                Agregado porque lo necesita:
                {{ p.agregado_por.map((c) => tituloDe.get(c) ?? c).join(', ') }}
              </p>
              <p v-if="p.complejidad" class="text-xs text-slate-500">{{ p.complejidad }}</p>
            </div>
            <span class="shrink-0 text-xs text-slate-500">{{ p.horas }} h</span>
            <span class="flex shrink-0 gap-1">
              <button :aria-label="`Subir ${p.titulo}`" @click="mover(i, -1)">↑</button>
              <button :aria-label="`Bajar ${p.titulo}`" @click="mover(i, 1)">↓</button>
              <button
                class="text-red-600"
                :aria-label="`Quitar ${p.titulo} de la ruta`"
                @click="quitar(i)"
              >
                ✕
              </button>
            </span>
          </li>
        </ol>

        <p
          v-for="a in avisos"
          :key="`${a.tema}-${a.requisito}`"
          class="mt-1 rounded bg-amber-50 px-2 py-1 text-sm text-amber-800"
        >
          {{ textoAviso(a, tituloDe) }}
        </p>

        <label class="mt-3 flex items-center gap-2 text-sm">
          <input v-model="crearClases" type="checkbox" />
          Crear también una clase de tareas por tema, vacía, para completarla o generarla con el
          agente
        </label>
        <div class="mt-2 flex items-center gap-3">
          <button id="aplicar-ruta" class="btn" :disabled="ocupado" @click="aplicar">
            Usar esta ruta en el curso
          </button>
          <RouterLink
            v-if="aplicada"
            id="ir-estudio"
            class="text-sm underline"
            :to="{ name: 'curso-estudio', params: { id: curso.id } }"
          >
            Ir al Estudio de diseño
          </RouterLink>
        </div>
      </template>

      <p v-if="aviso" id="aviso-objetivos" class="mt-2 text-sm text-green-700">{{ aviso }}</p>
      <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </section>
  </div>
</template>
