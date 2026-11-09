// Tipos y textos de los bancos compartidos (ADR 0009): preguntas y problemas, objetivos con su ruta, y recursos.
import type { CasoPrueba, TipoItem } from './tipos'

export interface TemaBanco {
  id: number
  clave: string
  titulo: string
  descripcion: string
  tipo: 'conocimiento' | 'habilidad' | 'actitud'
  evaluacion: string[]
  prerrequisitos: string[]
  horas: number
  complejidad: string | null
  propio: boolean
  items_count: number
}

/** Un paso de la ruta propuesta: `agregado_por` dice qué temas elegidos lo necesitan (vacío: lo eligió el instructor). */
export interface PasoRuta extends TemaBanco {
  agregado_por: string[]
}

export interface AvisoRuta {
  tema: string
  requisito: string
  problema: 'falta' | 'despues'
}

export interface ItemBanco {
  id: number
  codigo: string
  tema: string
  tipo: TipoItem
  nivel: 'recall' | 'comprension' | 'practica'
  lenguajes: string[]
  enunciado: {
    md: string
    opciones?: { id: string; texto: string }[]
    lineas?: { id: string; texto: string }[]
    codigo_inicial?: string
  }
  clave: Record<string, unknown> | null
  casos_prueba: CasoPrueba[] | null
  solucion: string | null
  en_curso: boolean
}

export type TipoRecurso =
  'clase' | 'tarea' | 'soporte' | 'procedimental' | 'practica_parcial' | 'medio'

export interface RecursoBanco {
  id: number
  tipo: TipoRecurso
  titulo: string
  estado: string
  autor_tipo: 'humano' | 'agente'
  piezas: number
  curso: { id: number; titulo: string }
  actualizado_at: string
}

export interface PiezaRecurso {
  uid: string
  tipo: TipoRecurso
  titulo: string
  estado: string
  contenido: Record<string, unknown>
}

export interface ItemDeOtroCurso {
  id: number
  tipo: TipoItem
  nivel: string
  objetivo: string | null
  estado: string
  enunciado: { md: string }
  curso: { id: number; titulo: string }
}

export interface DocumentoDeOtroCurso {
  id: number
  titulo: string
  nombre_original: string
  bytes: number
  fragmentos: number
  curso: { id: number; titulo: string }
}

export const NOMBRE_TIPO_ITEM: Record<TipoItem, string> = {
  opcion_multiple: 'Opción múltiple',
  respuesta_corta: 'Respuesta corta',
  prediccion_salida: 'Predicción de salida',
  parsons: 'Parsons',
  programacion: 'Programación'
}

export const NOMBRE_LENGUAJE: Record<string, string> = { c: 'C', cpp: 'C++', python: 'Python' }

export const NOMBRE_NIVEL: Record<ItemBanco['nivel'], string> = {
  recall: 'Recuerdo',
  comprension: 'Comprensión',
  practica: 'Práctica'
}

export const NOMBRE_RECURSO: Record<TipoRecurso, string> = {
  clase: 'Clase de tareas',
  tarea: 'Tarea',
  soporte: 'Información de soporte',
  procedimental: 'Información procedimental',
  practica_parcial: 'Práctica parcial',
  medio: 'Medio'
}

/** Estos recursos necesitan un lugar en el curso de destino para colgarse. */
export function padresPosibles(tipo: TipoRecurso): ('clase' | 'tarea')[] {
  if (tipo === 'tarea' || tipo === 'soporte') return ['clase']
  if (tipo === 'procedimental') return ['clase', 'tarea']
  return []
}

/** Lo mismo que propone la pestaña Pruebas: un problema de programación vale más que una pregunta. */
export function puntosPorOmision(nivel: ItemBanco['nivel']): number {
  return nivel === 'practica' ? 10 : 1
}

/** Una prueba teórica lleva preguntas; una práctica, problemas de programación. */
export function sirveParaPrueba(
  nivel: ItemBanco['nivel'],
  tipoPrueba: 'teorica' | 'practica'
): boolean {
  return (nivel === 'practica') === (tipoPrueba === 'practica')
}

/** Primer renglón con texto de un enunciado, sin marcas de Markdown, para las listas. */
export function resumenEnunciado(md: string): string {
  const linea = md.split('\n').find((l) => l.trim() && !l.trim().startsWith('```')) ?? ''
  return linea.replace(/[*`#]/g, '').trim()
}

/** La respuesta correcta en texto, para que el instructor revise un ítem sin abrir su edición. */
export function respuestaDe(item: Pick<ItemBanco, 'tipo' | 'enunciado' | 'clave'>): string {
  const clave = item.clave ?? {}
  switch (item.tipo) {
    case 'opcion_multiple':
      return item.enunciado.opciones?.find((o) => o.id === clave.correcta)?.texto ?? ''
    case 'respuesta_corta':
      return ((clave.aceptadas as string[]) ?? []).join(' | ')
    case 'prediccion_salida':
      return String(clave.salida ?? '')
    case 'parsons':
      return (item.enunciado.lineas ?? []).map((l) => l.texto).join('\n')
    case 'programacion':
      return ''
  }
}

/** Mensaje de un aviso de la ruta, con los títulos de los temas. */
export function textoAviso(a: AvisoRuta, titulos: Map<string, string>): string {
  const tema = titulos.get(a.tema) ?? a.tema
  const requisito = titulos.get(a.requisito) ?? a.requisito
  return a.problema === 'falta'
    ? `«${tema}» necesita «${requisito}», que no está en la ruta: asegúrate de que el grupo ya lo domine.`
    : `«${tema}» está antes de «${requisito}», que es su prerrequisito.`
}
