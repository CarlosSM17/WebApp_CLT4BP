# 0009 — Bancos compartidos: preguntas, objetivos con su ruta y recursos

Fecha: 2026-11-04 · Estado: aceptada

## Contexto

Todo lo que un instructor usaba para diseñar vivía dentro de un curso: los ítems, los objetivos y el material. Un curso
nuevo empezaba vacío, aunque los conceptos básicos de programación son los mismos y el instructor ya hubiera resuelto el
mismo tema en otro curso. Se pidieron tres bancos: preguntas y problemas para armar pruebas diagnósticas de todos los
conceptos básicos; objetivos para elegir o agregar, con una propuesta de ruta; y material ya creado para reutilizar, o
generarlo con el agente. Están en la pestaña **Bancos** de cada curso.

## Decisión

**El banco se copia, no se comparte.** Un curso nunca apunta al banco: toma una copia editable y sigue el flujo de
siempre. Corregir una pregunta en un curso no cambia el banco ni otros cursos, y una prueba ya aplicada no cambia porque el
catálogo cambie. `items.bank_item_id` recuerda el origen para que un ítem del banco entre una sola vez a cada curso; si el
ítem sale del catálogo, la copia se queda.

**El catálogo son datos del repositorio**, como los instrumentos: se escribe en `banco/` y `uv run banco/generar.py` genera
`apps/web/resources/banco/banco.json` (GENERADO). `banco:cargar` lo lleva a la base y se puede repetir; el arranque en
Railway y `desplegar.sh` lo corren en cada versión. El catálogo no se edita desde la consola.

**Ninguna clave se escribe a ciegas.** Cada ítem declara su resultado a mano y `php artisan banco:verificar` lo ejecuta: la
solución contra sus casos, y el programa de cada predicción y cada Parsons contra lo que dice su clave, en cada lenguaje
donde se ofrece (el código de C, con gcc y con g++). Una clave equivocada calificaría mal a todos los estudiantes, así que
**tras cambiar el contenido del banco hay que correr este comando**; `generar.py` solo revisa la forma, incluida la
cobertura mínima de cada tema y que un Parsons no tenga dos líneas iguales (el orden correcto sería ambiguo).

**Ruta de aprendizaje.** `App\Domain\Banco\RutaAprendizaje` agrega los prerrequisitos que falten e indica quién los
necesita, y ordena cada tema después de lo que requiere; entre temas libres manda el orden sugerido. El instructor puede
reordenar o quitar: se le avisa lo que deja de cumplirse, pero decide él. Los prerrequisitos no pueden formar ciclos. Al
aplicarla, cada tema se vuelve un objetivo y, si se pide, una clase de tareas vacía. Esta «ruta» es la secuencia de temas
del curso; no es lo mismo que las rutas por grupo de una tarea (`tarea.rutas`, ADR 0007).

**Objetivos propios.** Son del instructor que los crea (`bank_topics.owner_id`), sirven para todos sus cursos y nadie más
los ve. Decisión pendiente de revisar si se quiere compartirlos entre instructores.

**Selección de una prueba.** `App\Domain\Banco\SelectorItems` propone la misma cantidad por concepto, alternando tipos, sin
repetir lo que el curso ya usó: la forma B no lleva las preguntas de la forma A. Solo propone; la prueba se crea cuando el
instructor la revisa. Quedan aprobados al crearla con la regla de siempre: un problema se aprueba si su solución pasa sus
casos. Eso se ejecuta en el momento (18 problemas tardan unos 8 s en este equipo), por eso una prueba admite hasta 20 y la
consola espera hasta 3 minutos esa respuesta.

**Reutilizar pasa por la misma puerta que la consola.** La copia ocurre en el servidor y entra por
`ServicioSincronizacion`: se valida contra los contratos, queda como borrador, deja versión y la consola la recibe al
sincronizar. `CopiadorElementos` da uid nuevos y reescribe las referencias, también las `media:uid` dentro de un Markdown.
Lo que depende del curso de origen no viaja a ciegas:

| Al copiar | Qué pasa |
|---|---|
| Objetivos de una clase | Se ligan al objetivo con la misma descripción en el destino; si no hay, se quitan con aviso |
| `tarea.rutas` | Solo se conservan los grupos que existen en el destino: una ruta inexistente ocultaría la tarea a todos |
| Variantes por grupo | No se copian (son de los grupos del otro curso); se avisa |
| Medios y documentos | Se copia el archivo; los fragmentos de un documento se copian sin recalcular embeddings |
| Otro lenguaje | Se copia, con aviso: hay que ajustar el código |

Se ofrece lo de los cursos que el instructor imparte (al administrador, todos, igual que `CoursePolicy::view`). No hay
material compartido entre instructores: una grabación de protocolo verbal o un apunte pueden no ser para otros.

**Generar con el agente.** Los botones de los bancos llevan al Estudio de diseño con el asistente abierto y la acción ya
elegida. No hay un segundo camino hacia el agente: sigue valiendo que nada entra al diseño sin aprobación del instructor.

## Consecuencias

- El catálogo de C también se ofrece en cursos de C++, con estilo de C (`printf`, `malloc`). Si hace falta estilo propio de
  C++, se agrega como otra familia en `banco/`.
- Apuntadores y memoria dinámica solo existen para C y C++; en Python el catálogo tiene 16 temas.
- Pendiente: compartir bancos propios entre instructores y guardar un ítem propio en el banco.
