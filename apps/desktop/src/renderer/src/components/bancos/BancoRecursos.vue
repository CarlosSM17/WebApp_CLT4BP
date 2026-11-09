<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  NOMBRE_LENGUAJE,
  NOMBRE_RECURSO,
  NOMBRE_TIPO_ITEM,
  padresPosibles,
  resumenEnunciado,
  type DocumentoDeOtroCurso,
  type ItemDeOtroCurso,
  type PiezaRecurso,
  type RecursoBanco,
  type TipoRecurso
} from '@shared/banco'
import type { ClaseTareas, Tarea } from '@shared/contracts'
import type { ElementoLocal } from '@shared/diseno'
import type { Curso } from '@shared/tipos'
import { api } from '../../lib/api'
import VistaMarkdown from '../VistaMarkdown.vue'

const props = defineProps<{ curso: Curso }>()

type Vista = 'diseno' | 'items' | 'documentos'

const vista = ref<Vista>('diseno')
const filtro = reactive({ tipo: '' as '' | TipoRecurso, texto: '', mismoLenguaje: true })
const recursos = ref<RecursoBanco[]>([])
const items = ref<ItemDeOtroCurso[]>([])
const documentos = ref<DocumentoDeOtroCurso[]>([])
const itemsElegidos = ref<number[]>([])

const abierto = ref<number | null>(null)
const piezas = ref<PiezaRecurso[]>([])
const copiando = ref<RecursoBanco | null>(null)
const padre = ref('')
const destinos = ref<ElementoLocal[]>([])

const error = ref('')
const aviso = ref('')
const avisosCopia = ref<string[]>([])
const ocupado = ref(false)

const consulta = computed(() => {
  const q = new URLSearchParams({ excluir_curso: String(props.curso.id) })
  if (filtro.mismoLenguaje) q.set('lenguaje', props.curso.lenguaje)
  return q
})

// Dónde se puede colgar lo que se está copiando, con su clase al lado si es una tarea
const padresDisponibles = computed(() => {
  if (!copiando.value) return []
  const tipos: string[] = padresPosibles(copiando.value.tipo)
  return destinos.value
    .filter((e) => tipos.includes(e.tipo))
    .map((e) => ({
      uid: e.uid,
      texto:
        e.tipo === 'clase'
          ? `Clase: ${(e.contenido as ClaseTareas).titulo}`
          : `Tarea: ${(e.contenido as Tarea).titulo}`
    }))
})

async function ejecutar(accion: () => Promise<void>): Promise<void> {
  error.value = ''
  ocupado.value = true
  try {
    await accion()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    ocupado.value = false
  }
}

async function cargar(): Promise<void> {
  const q = consulta.value
  if (vista.value === 'diseno') {
    const d = new URLSearchParams(q)
    if (filtro.tipo) d.set('tipo', filtro.tipo)
    if (filtro.texto.trim()) d.set('q', filtro.texto.trim())
    recursos.value = await api<RecursoBanco[]>('GET', `/banco/recursos?${d}`)
  } else if (vista.value === 'items') {
    items.value = await api<ItemDeOtroCurso[]>('GET', `/banco/items-cursos?${q}`)
  } else {
    documentos.value = await api<DocumentoDeOtroCurso[]>('GET', `/banco/documentos?${q}`)
  }
}

function recargar(): void {
  void ejecutar(cargar)
}

function ver(r: RecursoBanco): Promise<void> | undefined {
  if (abierto.value === r.id) {
    abierto.value = null
    return
  }
  return ejecutar(async () => {
    piezas.value = await api<PiezaRecurso[]>('GET', `/banco/recursos/${r.id}`)
    abierto.value = r.id
  })
}

/** El texto principal de una pieza, para revisarla antes de reutilizarla. */
function textoDe(p: PiezaRecurso): string {
  const c = p.contenido
  return String(c.enunciado_md ?? c.cuerpo_md ?? c.descripcion_complejidad ?? c.transcripcion ?? '')
}

function reutilizar(r: RecursoBanco): Promise<void> {
  aviso.value = ''
  avisosCopia.value = []
  if (!padresPosibles(r.tipo).length) return copiar(r)
  // Sincroniza antes para ofrecer también lo que se acaba de crear en esta computadora
  return ejecutar(async () => {
    await window.consola.diseno.sincronizar(props.curso.id)
    destinos.value = await window.consola.diseno.listar(props.curso.id)
    copiando.value = r
    padre.value = padresDisponibles.value[0]?.uid ?? ''
  })
}

function copiar(r: RecursoBanco, padreUid?: string): Promise<void> {
  return ejecutar(async () => {
    const resultado = await api<{ elementos: number; avisos: string[] }>(
      'POST',
      `/courses/${props.curso.id}/banco/recursos`,
      { elemento_id: r.id, padre_uid: padreUid ?? null }
    )
    await window.consola.diseno.sincronizar(props.curso.id)
    aviso.value = `«${r.titulo}» copiado a este curso (${resultado.elementos} piezas), como borrador: revísalo en el Estudio de diseño.`
    avisosCopia.value = resultado.avisos
    copiando.value = null
  })
}

const copiarItems = (): Promise<void> =>
  ejecutar(async () => {
    const r = await api<{ nuevos: number }>(
      'POST',
      `/courses/${props.curso.id}/banco/items-cursos`,
      { ids: itemsElegidos.value }
    )
    aviso.value = `${r.nuevos} ítems copiados al Banco de ítems del curso, como borrador.`
    itemsElegidos.value = []
  })

const copiarDocumento = (d: DocumentoDeOtroCurso): Promise<void> =>
  ejecutar(async () => {
    await api('POST', `/courses/${props.curso.id}/banco/documentos/${d.id}`)
    aviso.value = `«${d.titulo}» ya está en el material de este curso, sin volver a procesarlo.`
  })

watch(vista, () => {
  aviso.value = ''
  avisosCopia.value = []
  recargar()
})
watch(() => [filtro.tipo, filtro.mismoLenguaje], recargar)

onMounted(recargar)
</script>

<template>
  <section class="tarjeta mb-4">
    <div class="mb-3 flex items-start justify-between gap-4">
      <div>
        <h2 class="font-semibold">Banco de recursos</h2>
        <p class="text-sm text-slate-600">
          Reutiliza lo que ya creaste en tus otros cursos. Se copia con todas sus piezas y queda
          como borrador: el original no cambia y la copia se vuelve a verificar y aprobar aquí.
        </p>
      </div>
      <RouterLink
        id="generar-agente"
        class="btn-sec shrink-0"
        :to="{
          name: 'curso-estudio',
          params: { id: curso.id },
          query: { accion: 'Generar tema completo' }
        }"
      >
        Generar con el agente
      </RouterLink>
    </div>

    <div class="flex flex-wrap items-end gap-3">
      <div>
        <label class="etiqueta" for="recurso-vista">Qué buscas</label>
        <select id="recurso-vista" v-model="vista" class="campo">
          <option value="diseno">Material instruccional</option>
          <option value="items">Ítems de otros cursos</option>
          <option value="documentos">Documentos (apuntes, bibliografía)</option>
        </select>
      </div>
      <template v-if="vista === 'diseno'">
        <div>
          <label class="etiqueta" for="recurso-tipo">Tipo</label>
          <select id="recurso-tipo" v-model="filtro.tipo" class="campo">
            <option value="">Todos</option>
            <option v-for="(nombre, clave) in NOMBRE_RECURSO" :key="clave" :value="clave">
              {{ nombre }}
            </option>
          </select>
        </div>
        <form class="flex items-end gap-2" @submit.prevent="recargar">
          <div>
            <label class="etiqueta" for="recurso-texto">Buscar por título o curso</label>
            <input id="recurso-texto" v-model="filtro.texto" class="campo" maxlength="100" />
          </div>
          <button id="buscar-recursos" class="btn-sec">Buscar</button>
        </form>
      </template>
      <label class="flex items-center gap-1.5 pb-2 text-sm">
        <input v-model="filtro.mismoLenguaje" type="checkbox" />
        Solo cursos de {{ NOMBRE_LENGUAJE[curso.lenguaje] }}
      </label>
    </div>

    <p v-if="aviso" id="aviso-recursos" class="mt-3 text-sm text-green-700">{{ aviso }}</p>
    <p
      v-for="a in avisosCopia"
      :key="a"
      class="mt-1 rounded bg-amber-50 px-2 py-1 text-sm text-amber-800"
    >
      {{ a }}
    </p>
    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
  </section>

  <section
    v-if="copiando"
    id="elegir-padre"
    class="tarjeta mb-4 border-2 border-indigo-200 text-sm"
  >
    <p class="mb-2 font-medium">¿Dónde va «{{ copiando.titulo }}» en este curso?</p>
    <p v-if="!padresDisponibles.length" class="text-amber-700">
      Este curso aún no tiene dónde colocarlo. Crea primero una clase de tareas (puedes hacerlo
      desde «Objetivos y ruta») o reutiliza la clase completa.
    </p>
    <div v-else class="flex items-end gap-2">
      <select v-model="padre" class="campo max-w-md" aria-label="Lugar de destino">
        <option v-for="p in padresDisponibles" :key="p.uid" :value="p.uid">{{ p.texto }}</option>
      </select>
      <button class="btn" :disabled="ocupado || !padre" @click="copiar(copiando!, padre)">
        Copiar aquí
      </button>
    </div>
    <button class="mt-2 underline" @click="copiando = null">Cancelar</button>
  </section>

  <section v-if="vista === 'diseno'" class="tarjeta">
    <p v-if="!recursos.length" id="sin-recursos" class="text-sm text-slate-500">
      No hay material con esos filtros. Puedes crearlo en el Estudio de diseño o pedirlo con
      «Generar con el agente».
    </p>
    <table v-else class="w-full text-sm">
      <thead class="text-left text-slate-500">
        <tr>
          <th class="py-1 pr-4">Recurso</th>
          <th class="pr-4">Tipo</th>
          <th class="pr-4">Curso</th>
          <th>Estado</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <template v-for="r in recursos" :key="r.id">
          <tr class="border-t border-slate-100 align-top">
            <td class="max-w-sm py-2 pr-4">
              {{ r.titulo }}
              <span v-if="r.piezas" class="text-xs text-slate-500">+ {{ r.piezas }} piezas</span>
              <span v-if="r.autor_tipo === 'agente'" class="text-xs text-indigo-700">
                · del agente</span
              >
            </td>
            <td class="py-2 pr-4">{{ NOMBRE_RECURSO[r.tipo] }}</td>
            <td class="py-2 pr-4 text-slate-600">{{ r.curso.titulo }}</td>
            <td class="py-2">{{ r.estado }}</td>
            <td class="space-x-2 whitespace-nowrap py-2 text-right">
              <button class="underline" @click="ver(r)">
                {{ abierto === r.id ? 'Ocultar' : 'Ver' }}
              </button>
              <button class="btn-sec py-0.5" :disabled="ocupado" @click="reutilizar(r)">
                Reutilizar
              </button>
            </td>
          </tr>
          <tr v-if="abierto === r.id">
            <td colspan="5" class="space-y-3 rounded bg-slate-50 p-3">
              <div v-for="p in piezas" :key="p.uid">
                <p class="font-medium">
                  {{ p.titulo }}
                  <span class="text-xs font-normal text-slate-500">{{
                    NOMBRE_RECURSO[p.tipo]
                  }}</span>
                </p>
                <VistaMarkdown v-if="textoDe(p)" :md="textoDe(p)" />
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </section>

  <section v-else-if="vista === 'items'" class="tarjeta">
    <div class="mb-2 flex items-center gap-3">
      <button class="btn" :disabled="ocupado || !itemsElegidos.length" @click="copiarItems">
        Copiar {{ itemsElegidos.length }} ítems a este curso
      </button>
    </div>
    <p v-if="!items.length" class="text-sm text-slate-500">
      Tus otros cursos no tienen ítems propios todavía.
    </p>
    <table v-else class="w-full text-sm">
      <tbody>
        <tr v-for="i in items" :key="i.id" class="border-t border-slate-100 align-top">
          <td class="w-8 py-2">
            <input
              v-model="itemsElegidos"
              type="checkbox"
              :value="i.id"
              :aria-label="`Elegir ítem ${i.id}`"
            />
          </td>
          <td class="max-w-md truncate py-2 pr-4">{{ resumenEnunciado(i.enunciado.md) }}</td>
          <td class="py-2 pr-4">{{ NOMBRE_TIPO_ITEM[i.tipo] }}</td>
          <td class="py-2 text-slate-600">{{ i.curso.titulo }}</td>
        </tr>
      </tbody>
    </table>
  </section>

  <section v-else class="tarjeta">
    <p v-if="!documentos.length" class="text-sm text-slate-500">
      Tus otros cursos no tienen documentos procesados.
    </p>
    <table v-else class="w-full text-sm">
      <tbody>
        <tr v-for="d in documentos" :key="d.id" class="border-t border-slate-100 align-top">
          <td class="py-2">
            {{ d.titulo }}
            <span class="block text-xs text-slate-400">{{ d.nombre_original }}</span>
          </td>
          <td class="py-2">{{ d.fragmentos }} fragmentos</td>
          <td class="py-2 text-slate-600">{{ d.curso.titulo }}</td>
          <td class="py-2 text-right">
            <button class="btn-sec py-0.5" :disabled="ocupado" @click="copiarDocumento(d)">
              Reutilizar
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
