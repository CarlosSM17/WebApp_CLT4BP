import { describe, expect, it } from 'vitest'
import {
  padresPosibles,
  puntosPorOmision,
  respuestaDe,
  resumenEnunciado,
  sirveParaPrueba,
  textoAviso
} from './banco'

describe('ítems del banco', () => {
  it('resume el enunciado sin marcas ni bloques de código', () => {
    expect(resumenEnunciado('```c\nint a;\n```')).toBe('int a;')
    expect(resumenEnunciado('\n¿Qué **imprime** `x`?\n\n```c\nint a;\n```')).toBe('¿Qué imprime x?')
  })

  it('muestra la respuesta correcta de cada tipo', () => {
    const opciones = [
      { id: 'a', texto: 'Sí' },
      { id: 'b', texto: 'No' }
    ]
    expect(
      respuestaDe({
        tipo: 'opcion_multiple',
        enunciado: { md: '', opciones },
        clave: { correcta: 'b' }
      })
    ).toBe('No')
    expect(
      respuestaDe({
        tipo: 'respuesta_corta',
        enunciado: { md: '' },
        clave: { aceptadas: ['a', 'b'] }
      })
    ).toBe('a | b')
    expect(
      respuestaDe({ tipo: 'prediccion_salida', enunciado: { md: '' }, clave: { salida: '5\n' } })
    ).toBe('5\n')
    expect(
      respuestaDe({
        tipo: 'parsons',
        enunciado: {
          md: '',
          lineas: [
            { id: 'l1', texto: 'int a;' },
            { id: 'l2', texto: '  a = 1;' }
          ]
        },
        clave: { orden: ['l1', 'l2'] }
      })
    ).toBe('int a;\n  a = 1;')
    expect(respuestaDe({ tipo: 'programacion', enunciado: { md: '' }, clave: null })).toBe('')
  })

  it('una prueba teórica lleva preguntas y una práctica, problemas', () => {
    expect(sirveParaPrueba('recall', 'teorica')).toBe(true)
    expect(sirveParaPrueba('comprension', 'teorica')).toBe(true)
    expect(sirveParaPrueba('practica', 'teorica')).toBe(false)
    expect(sirveParaPrueba('practica', 'practica')).toBe(true)
    expect(sirveParaPrueba('recall', 'practica')).toBe(false)
    expect(puntosPorOmision('practica')).toBe(10)
    expect(puntosPorOmision('recall')).toBe(1)
  })
})

describe('ruta y recursos', () => {
  it('explica los avisos de la ruta con los títulos de los temas', () => {
    const titulos = new Map([
      ['ciclos', 'Ciclos'],
      ['condicionales', 'Condicionales']
    ])
    expect(
      textoAviso({ tema: 'ciclos', requisito: 'condicionales', problema: 'despues' }, titulos)
    ).toBe('«Ciclos» está antes de «Condicionales», que es su prerrequisito.')
    expect(
      textoAviso({ tema: 'ciclos', requisito: 'condicionales', problema: 'falta' }, titulos)
    ).toContain('no está en la ruta')
  })

  it('dice dónde se puede colgar cada recurso', () => {
    expect(padresPosibles('tarea')).toEqual(['clase'])
    expect(padresPosibles('soporte')).toEqual(['clase'])
    expect(padresPosibles('procedimental')).toEqual(['clase', 'tarea'])
    expect(padresPosibles('clase')).toEqual([])
    expect(padresPosibles('practica_parcial')).toEqual([])
  })
})
