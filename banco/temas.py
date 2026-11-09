"""Banco de objetivos: los temas básicos de programación, de lo simple a lo complejo.

`prerrequisitos` es lo que el tema necesita de verdad; de ahí sale la ruta de aprendizaje que propone la plataforma.
`descripcion` sigue la forma de un objetivo de desempeño: verbo observable + condición + criterio.
"""

C_CPP = ["c", "cpp"]
EVAL = ["recall", "comprension", "practica"]

TEMAS = [
    {
        "clave": "algoritmos", "titulo": "Algoritmos y resolución de problemas", "horas": 4, "prerrequisitos": [],
        "tipo": "habilidad", "evaluacion": ["recall", "comprension"],
        "descripcion": "Describir en pasos ordenados o en pseudocódigo la solución de un problema sencillo, identificando entradas, proceso y salidas, de modo que otra persona pueda seguirla sin ambigüedad.",
        "complejidad": "Primer contacto: se razona la solución sin escribir código real.",
    },
    {
        "clave": "estructura-programa", "titulo": "Estructura de un programa y tipos de error", "horas": 2, "prerrequisitos": ["algoritmos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Escribir y ejecutar un programa mínimo, y clasificar los errores que aparezcan en sintaxis, ejecución o lógica a partir del mensaje del sistema y de su comportamiento.",
        "complejidad": "Se pasa del pseudocódigo a un programa real que se ejecuta.",
    },
    {
        "clave": "tipos-variables", "titulo": "Tipos de datos, variables y constantes", "horas": 3, "prerrequisitos": ["estructura-programa"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Declarar variables y constantes con el tipo adecuado a cada dato de un problema y asignarles valores, prediciendo correctamente su contenido después de cada asignación.",
        "complejidad": "El programa empieza a guardar datos: aparece el modelo mental de la memoria.",
    },
    {
        "clave": "entrada-salida", "titulo": "Entrada y salida estándar", "horas": 3, "prerrequisitos": ["tipos-variables"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Leer datos del teclado y mostrar resultados con el formato pedido en programas sencillos, de modo que la salida coincida exactamente con la especificada.",
        "complejidad": "El programa deja de ser fijo: responde a datos que da el usuario.",
    },
    {
        "clave": "operadores-expresiones", "titulo": "Operadores y expresiones", "horas": 3, "prerrequisitos": ["tipos-variables"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Evaluar y escribir expresiones aritméticas, relacionales y lógicas aplicando la precedencia y la conversión de tipos, y obtener el resultado correcto sin ejecutarlas.",
        "complejidad": "Se combinan varios datos y operaciones en una sola expresión.",
    },
    {
        "clave": "condicionales", "titulo": "Estructuras condicionales", "horas": 4, "prerrequisitos": ["operadores-expresiones", "entrada-salida"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Resolver problemas que exigen decidir entre varios caminos usando condicionales simples, anidados y de selección múltiple, cubriendo todos los casos que plantea el enunciado.",
        "complejidad": "El flujo deja de ser lineal: el programa elige entre caminos.",
    },
    {
        "clave": "ciclos", "titulo": "Ciclos, contadores y acumuladores", "horas": 6, "prerrequisitos": ["condicionales"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Resolver problemas que exigen repetir un proceso, eligiendo el ciclo adecuado y usando contadores y acumuladores, de modo que el programa termine y dé el resultado correcto en los casos límite.",
        "complejidad": "El flujo se repite: hay que razonar sobre muchas vueltas y sobre cuándo terminar.",
    },
    {
        "clave": "ciclos-anidados", "titulo": "Ciclos anidados", "horas": 3, "prerrequisitos": ["ciclos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Escribir y trazar ciclos anidados para generar patrones y tablas, determinando cuántas veces se ejecuta cada instrucción.",
        "complejidad": "Repeticiones dentro de repeticiones: se multiplica lo que hay que seguir mentalmente.",
    },
    {
        "clave": "funciones", "titulo": "Funciones: parámetros, retorno y alcance", "horas": 6, "prerrequisitos": ["ciclos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Descomponer un problema en funciones con parámetros y valor de retorno, distinguiendo el alcance de sus variables, de modo que cada función se pueda probar por separado.",
        "complejidad": "El programa se divide en piezas que se comunican entre sí.",
    },
    {
        "clave": "recursion", "titulo": "Recursión", "horas": 4, "prerrequisitos": ["funciones"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Escribir y trazar funciones recursivas con su caso base y su caso recursivo, explicando cómo se reduce el problema en cada llamada.",
        "complejidad": "Una función se llama a sí misma: hay que seguir varias llamadas abiertas a la vez.",
    },
    {
        "clave": "arreglos", "titulo": "Arreglos y listas", "horas": 6, "prerrequisitos": ["ciclos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Almacenar y recorrer colecciones de datos con arreglos o listas para contar, sumar y encontrar máximos o mínimos, sin salirse de los índices válidos.",
        "complejidad": "Muchos datos bajo un mismo nombre: aparecen los índices.",
    },
    {
        "clave": "matrices", "titulo": "Matrices", "horas": 4, "prerrequisitos": ["arreglos", "ciclos-anidados"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Representar tablas de datos con matrices y recorrerlas por filas y columnas con ciclos anidados para calcular resultados por fila, por columna o en la diagonal.",
        "complejidad": "Dos índices a la vez, combinando arreglos y ciclos anidados.",
    },
    {
        "clave": "cadenas", "titulo": "Cadenas de caracteres", "horas": 4, "prerrequisitos": ["arreglos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Leer, recorrer y transformar cadenas de caracteres para resolver problemas de texto, respetando cómo representa el lenguaje el fin de la cadena o su longitud.",
        "complejidad": "Los arreglos se aplican a texto, con sus reglas propias.",
    },
    {
        "clave": "apuntadores", "titulo": "Apuntadores", "horas": 6, "prerrequisitos": ["arreglos", "funciones"], "lenguajes": C_CPP,
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Declarar y usar apuntadores para acceder a variables y modificar datos desde una función, dibujando correctamente qué dirección guarda cada apuntador en cada paso.",
        "complejidad": "Se razona sobre direcciones de memoria, no solo sobre valores.",
    },
    {
        "clave": "estructuras", "titulo": "Estructuras y registros", "horas": 4, "prerrequisitos": ["arreglos", "funciones"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Agrupar datos relacionados en estructuras o registros y procesar colecciones de ellos, accediendo a cada campo por su nombre.",
        "complejidad": "Los datos se organizan por significado: colecciones de registros con varios campos.",
    },
    {
        "clave": "archivos", "titulo": "Archivos de texto", "horas": 3, "prerrequisitos": ["cadenas", "ciclos"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Escribir datos en un archivo de texto y volver a leerlos para procesarlos, abriendo y cerrando el archivo correctamente y atendiendo el caso de que no se pueda abrir.",
        "complejidad": "Los datos sobreviven al programa: aparecen recursos externos que pueden fallar.",
    },
    {
        "clave": "memoria-dinamica", "titulo": "Memoria dinámica", "horas": 4, "prerrequisitos": ["apuntadores", "estructuras"], "lenguajes": C_CPP,
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Reservar y liberar memoria en tiempo de ejecución para datos cuyo tamaño se conoce hasta ejecutar el programa, sin dejar fugas de memoria.",
        "complejidad": "El programador administra la memoria y es responsable de liberarla.",
    },
    {
        "clave": "busqueda-ordenamiento", "titulo": "Búsqueda y ordenamiento", "horas": 4, "prerrequisitos": ["arreglos", "funciones", "ciclos-anidados"],
        "tipo": "habilidad", "evaluacion": EVAL,
        "descripcion": "Implementar búsqueda lineal y un algoritmo de ordenamiento básico, comparando cuántas operaciones requieren según el número de datos.",
        "complejidad": "Se integra todo lo anterior en algoritmos clásicos y se empieza a pensar en su eficiencia.",
    },
]
