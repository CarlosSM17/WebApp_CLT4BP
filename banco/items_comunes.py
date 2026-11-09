"""Ítems que no dependen del lenguaje: conceptos sin código. Sirven en cualquier curso."""

from base import COMUN, Coleccion

BANCO = Coleccion(COMUN)

t = BANCO.tema("algoritmos")
t.om("recall", "¿Qué es un algoritmo?", [
    "Un programa ya compilado",
    "Una secuencia finita y ordenada de pasos que resuelve un problema",
    "Un lenguaje de programación",
    "Un error frecuente al programar",
], 1)
t.om("comprension", "Un programa debe calcular el promedio de tres calificaciones. ¿Cuáles son sus **entradas**?", [
    "El promedio",
    "La suma de las calificaciones",
    "Las tres calificaciones",
    "El número 3",
], 2)
t.rc("recall", "¿Cómo se llama la notación informal que describe un algoritmo sin usar la sintaxis de un lenguaje de programación?", ["pseudocódigo", "pseudocodigo", "seudocódigo", "seudocodigo"])
t.om("comprension", "Un algoritmo para preparar café dice: «Sirve el café. Hierve el agua. Agrega el café al agua.» ¿Qué propiedad le falta?", [
    "Que sea finito",
    "Que tenga los pasos en el orden correcto",
    "Que use un lenguaje de programación",
    "Que tenga una entrada numérica",
], 1)
t.om("comprension", "En el problema «dado el precio y la cantidad, mostrar el total a pagar», ¿qué parte es el **proceso**?", [
    "Leer el precio",
    "Multiplicar el precio por la cantidad",
    "Mostrar el total",
    "Leer la cantidad",
], 1)

t = BANCO.tema("estructura-programa")
t.om("recall", "Escribir mal una palabra reservada del lenguaje es un error de…", [
    "lógica", "ejecución", "sintaxis", "diseño",
], 2)
t.om("comprension", "Un programa se ejecuta sin ningún mensaje de error, pero calcula mal el total a pagar. ¿Qué tipo de error tiene?", [
    "De sintaxis", "De lógica", "De compilación", "Del sistema operativo",
], 1)
t.om("comprension", "Un programa funciona con casi todos los datos, pero se detiene de golpe al dividir entre cero. ¿Qué tipo de error es?", [
    "De sintaxis", "De ejecución", "De formato", "De comentario",
], 1)

t = BANCO.tema("tipos-variables")
t.om("recall", "¿Qué es una variable?", [
    "Un espacio con nombre para guardar un valor que puede cambiar",
    "Un valor que nunca cambia",
    "Una instrucción que repite código",
    "Un mensaje que se muestra en pantalla",
], 0)
t.om("comprension", "¿Qué tipo de dato conviene para guardar la estatura de una persona en metros, por ejemplo 1.72?", [
    "Entero", "Real (punto flotante)", "Carácter", "Lógico",
], 1)
t.om("comprension", "¿Qué tipo de dato conviene para guardar si un estudiante ya pagó su inscripción?", [
    "Entero", "Real", "Lógico (verdadero o falso)", "Carácter",
], 2)

t = BANCO.tema("entrada-salida")
t.om("comprension", "Un programa pide la edad y muestra si la persona es mayor de edad. ¿Cuál es su **salida**?", [
    "La edad que escribe el usuario",
    "El mensaje que indica si es mayor de edad",
    "El número 18",
    "La comparación entre la edad y 18",
], 1)

t = BANCO.tema("operadores-expresiones")
t.om("recall", "En la expresión `2 + 3 * 4`, ¿qué operación se realiza primero?", [
    "La suma", "La multiplicación", "Se evalúan al mismo tiempo", "Depende de los espacios",
], 1)
t.om("comprension", "Si `x` es verdadero y `y` es falso, ¿cuánto vale `x Y (NO y)`?", [
    "Verdadero", "Falso", "No se puede saber", "Es un error",
], 0)
t.rc("comprension", "¿Cuánto vale la expresión `10 - 2 * 3`? Escribe solo el número.", ["4"])

t = BANCO.tema("condicionales")
t.om("recall", "¿Para qué sirve una estructura condicional?", [
    "Para repetir instrucciones varias veces",
    "Para ejecutar instrucciones solo si se cumple una condición",
    "Para guardar datos en un archivo",
    "Para declarar variables",
], 1)
t.om("comprension", "Un descuento aplica solo si la compra es de 100 o más **y** el cliente es socio. ¿Con qué operador lógico se unen las dos condiciones?", [
    "O (OR)", "Y (AND)", "NO (NOT)", "Ninguno: basta una condición",
], 1)
t.om("comprension", "Para clasificar una calificación en «reprobado», «regular» o «excelente», ¿qué estructura es la más natural?", [
    "Un solo `si` sin alternativa",
    "Una cadena de `si / si no` (condicionales anidados o encadenados)",
    "Un ciclo",
    "Una función recursiva",
], 1)

t = BANCO.tema("ciclos")
t.om("recall", "¿Qué es un ciclo infinito?", [
    "Un ciclo que usa números muy grandes",
    "Un ciclo cuya condición de término nunca se cumple",
    "Un ciclo dentro de otro ciclo",
    "Un ciclo que no se ejecuta ninguna vez",
], 1)
t.om("comprension", "¿Qué variable se usa normalmente para ir sumando valores dentro de un ciclo?", [
    "Una bandera", "Un contador", "Un acumulador", "Una constante",
], 2)
t.rc("recall", "¿Cómo se llama la variable que aumenta de uno en uno para llevar la cuenta de cuántas veces ocurre algo?", ["contador", "un contador"])

t = BANCO.tema("ciclos-anidados")
t.om("comprension", "Un ciclo externo se repite 3 veces y, en cada vuelta, un ciclo interno se repite 4 veces. ¿Cuántas veces se ejecuta el cuerpo del ciclo interno?", [
    "4", "7", "12", "34",
], 2)
t.rc("comprension", "Un ciclo externo se repite 5 veces y el interno 2 veces por cada vuelta del externo. ¿Cuántas veces se ejecuta el cuerpo del interno? Escribe solo el número.", ["10"])
t.om("comprension", "Para imprimir una tabla de 5 filas y 8 columnas, ¿qué suele controlar el ciclo **externo**?", [
    "Las columnas", "Las filas", "El ancho de cada celda", "El total de celdas",
], 1)

t = BANCO.tema("funciones")
t.om("recall", "¿Cuál es una ventaja principal de dividir un programa en funciones?", [
    "El programa siempre se ejecuta más rápido",
    "Se reutiliza código y cada parte se entiende y se prueba por separado",
    "Ya no hacen falta variables",
    "Se evitan todos los errores",
], 1)
t.om("comprension", "En la llamada `area(3, 5)`, ¿qué son `3` y `5`?", [
    "Parámetros formales", "Argumentos", "Variables globales", "Valores de retorno",
], 1)
t.om("comprension", "Una variable declarada dentro de una función…", [
    "se puede usar desde cualquier parte del programa",
    "solo existe dentro de esa función",
    "conserva su valor al terminar el programa",
    "se vuelve constante",
], 1)

t = BANCO.tema("recursion")
t.om("recall", "En una función recursiva, ¿qué es el **caso base**?", [
    "La primera línea de la función",
    "La condición en la que la función deja de llamarse a sí misma",
    "La llamada recursiva",
    "El valor de mayor tamaño que acepta",
], 1)
t.om("comprension", "¿Qué ocurre si una función recursiva no tiene caso base alcanzable?", [
    "Devuelve cero",
    "Se llama a sí misma sin fin hasta agotar la memoria de la pila",
    "El compilador la convierte en un ciclo",
    "Se ejecuta una sola vez",
], 1)
t.rc("recall", "¿Cómo se llama el caso que detiene la recursión?", ["caso base", "el caso base", "base"])

t = BANCO.tema("arreglos")
t.om("recall", "En C, C++ y Python, ¿qué índice tiene el primer elemento de un arreglo o lista?", [
    "0", "1", "-1", "Depende del tamaño",
], 0)
t.om("comprension", "Un arreglo tiene 10 elementos. ¿Cuál es el índice del último?", [
    "10", "9", "11", "1",
], 1)

t = BANCO.tema("matrices")
t.om("comprension", "Una matriz tiene 3 filas y 4 columnas. ¿Cuántos elementos tiene?", [
    "7", "12", "34", "43",
], 1)
t.om("recall", "En `m[i][j]`, con la convención habitual, ¿qué indica `i`?", [
    "La columna", "La fila", "El tamaño", "El valor",
], 1)

t = BANCO.tema("archivos")
t.om("recall", "¿Por qué hay que cerrar un archivo al terminar de usarlo?", [
    "Para borrarlo del disco",
    "Para asegurar que los datos se guarden y liberar el recurso",
    "Para cambiarle el nombre",
    "No es necesario nunca",
], 1)

t = BANCO.tema("busqueda-ordenamiento")
t.om("comprension", "¿Qué condición necesitan los datos para aplicar búsqueda **binaria**?", [
    "Que sean números pares", "Que estén ordenados", "Que no se repitan", "Que sean menos de 100",
], 1)
t.om("comprension", "En el peor caso, ¿cuántas comparaciones hace una búsqueda lineal en una lista de `n` elementos?", [
    "1", "n / 2", "n", "n al cuadrado",
], 2)
t.rc("recall", "¿Cómo se llama el ordenamiento que compara pares de elementos vecinos y los intercambia si están desordenados?", ["burbuja", "ordenamiento de burbuja", "ordenamiento burbuja", "bubble sort", "método de la burbuja"])
