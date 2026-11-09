"""Ítems de Python. Los temas de apuntadores y memoria dinámica no aplican: en Python los resuelve el propio lenguaje."""

from base import PYTHON, Coleccion

BANCO = Coleccion(PYTHON)
QUE_IMPRIME = "¿Qué imprime exactamente este programa?"

t = BANCO.tema("algoritmos")
t.pred(QUE_IMPRIME, """
    precio = 12
    cantidad = 3
    total = precio * cantidad
    print(total)
""", "36\n")
t.parsons("Ordena los pasos del programa que calcula el total a pagar y lo imprime: primero los datos, luego el proceso y al final la salida.", """
    precio = 20
    cantidad = 4
    total = precio * cantidad
    print(total)
""", "80\n")
t.prog("Una tienda cobra el precio por la cantidad comprada. Lee dos enteros en una línea, `precio` y `cantidad`, e imprime el total a pagar.", """
    precio, cantidad = map(int, input().split())
    print(precio * cantidad)
""", [("20 4", "80"), ("7 1", "7"), ("0 10", "0")])

t = BANCO.tema("estructura-programa")
t.rc("recall", "¿Qué función de Python muestra un mensaje en pantalla?", ["print", "print()"])
t.om("comprension", "En Python, ¿qué indica la sangría (los espacios al inicio de una línea)?", [
    "Nada: es solo estética",
    "Qué instrucciones forman parte de un mismo bloque",
    "El tipo de una variable",
    "Un comentario",
], 1)
t.pred(QUE_IMPRIME, """
    print("Hola")
    print("mundo")
""", "Hola\nmundo\n")
t.parsons("Ordena las líneas para que el programa imprima `Inicio`, después la suma y al final `Fin`. Debe imprimir `Inicio`, `5` y `Fin`, cada uno en su renglón.", """
    print("Inicio")
    suma = 2 + 3
    print(suma)
    print("Fin")
""", "Inicio\n5\nFin\n")
t.prog("Escribe un programa que imprima exactamente `Hola, mundo`.", """
    print("Hola, mundo")
""", [("", "Hola, mundo")])

t = BANCO.tema("tipos-variables")
t.om("recall", "¿Qué tipo de Python tiene el valor `3.14`?", ["int", "float", "str", "bool"], 1)
t.rc("recall", "¿Qué función de Python devuelve el tipo de un valor?", ["type", "type()"])
t.pred(QUE_IMPRIME, """
    a = 5
    b = a
    a = 9
    print(a, b)
""", "9 5\n")
t.parsons("Ordena las líneas para intercambiar los valores de `a` y `b` usando una variable auxiliar. Debe imprimir `2 1`.", """
    a = 1
    b = 2
    aux = a
    a = b
    b = aux
    print(a, b)
""", "2 1\n")
t.prog("Lee el ancho y el alto de un rectángulo (dos enteros en una línea) e imprime su área.", """
    ancho, alto = map(int, input().split())
    area = ancho * alto
    print(area)
""", [("3 4", "12"), ("10 2", "20"), ("0 5", "0")])

t = BANCO.tema("entrada-salida")
t.om("recall", "¿De qué tipo es siempre lo que devuelve `input()`?", ["int", "float", "str", "Depende de lo que se escriba"], 2)
t.om("comprension", "¿Qué hace falta para sumar 1 a un número leído con `n = input()`?", [
    "Nada: `n + 1` funciona",
    "Convertirlo primero con `int(n)`",
    "Escribir `input(int)`",
    "Declarar `n` como entero antes",
], 1)
t.rc("recall", "¿Qué función de Python lee una línea del teclado?", ["input", "input()"])
t.pred(QUE_IMPRIME, """
    print(3, 4, sep="-")
    print(f"{1.5:.2f}")
""", "3-4\n1.50\n")
t.parsons("Ordena las líneas: el programa lee un entero e imprime su doble. Con la entrada `21` debe imprimir `42`.", """
    texto = input()
    n = int(texto)
    doble = n * 2
    print(doble)
""", "42\n", entrada="21")
t.prog("Lee una hora y un minuto (dos enteros en una línea) e imprímelos con el formato `HH:MM`, siempre con dos dígitos.", """
    h, m = map(int, input().split())
    print(f"{h:02d}:{m:02d}")
""", [("7 5", "07:05"), ("12 30", "12:30"), ("0 0", "00:00")])

t = BANCO.tema("operadores-expresiones")
t.om("comprension", "En Python 3, ¿cuánto vale `7 / 2`?", ["3", "3.5", "4", "1"], 1)
t.rc("recall", "¿Qué operador de Python hace la división entera (sin decimales)? Escribe solo el símbolo.", ["//"])
t.pred(QUE_IMPRIME, """
    a = 17
    print(a // 5, a % 5)
    print(a / 5)
""", "3 2\n3.4\n")
t.parsons("Ordena las líneas para calcular el promedio de tres calificaciones. Debe imprimir `8.33`.", """
    a = 7
    b = 8
    c = 10
    suma = a + b + c
    promedio = suma / 3
    print(f"{promedio:.2f}")
""", "8.33\n")
t.prog("Lee una cantidad de segundos (entero) e imprime cuántas horas, minutos y segundos son, separados por un espacio.", """
    s = int(input())
    print(s // 3600, s % 3600 // 60, s % 60)
""", [("3661", "1 1 1"), ("59", "0 0 59"), ("7200", "2 0 0")])

t = BANCO.tema("condicionales")
t.rc("recall", "¿Qué palabra reservada de Python significa «si no, si…» en una cadena de condiciones?", ["elif"])
t.om("comprension", "¿Qué diferencia hay entre `=` y `==` en Python?", [
    "Ninguna",
    "`=` asigna un valor y `==` compara dos valores",
    "`=` compara y `==` asigna",
    "`==` solo sirve con texto",
], 1)
t.pred(QUE_IMPRIME, """
    n = 15
    if n % 3 == 0 and n % 5 == 0:
        print("ambos")
    elif n % 3 == 0:
        print("tres")
    else:
        print("otro")
""", "ambos\n")
t.parsons("Ordena las líneas para imprimir el mayor de dos números. Debe imprimir `8`.", """
    a = 8
    b = 3
    if a > b:
        print(a)
    else:
        print(b)
""", "8\n")
t.prog("Lee un año e imprime `BISIESTO` si es bisiesto o `NORMAL` si no. Un año es bisiesto si es múltiplo de 4, salvo los múltiplos de 100 que no sean múltiplos de 400.", """
    a = int(input())
    if (a % 4 == 0 and a % 100 != 0) or a % 400 == 0:
        print("BISIESTO")
    else:
        print("NORMAL")
""", [("2024", "BISIESTO"), ("2023", "NORMAL"), ("1900", "NORMAL"), ("2000", "BISIESTO")])

t = BANCO.tema("ciclos")
t.rc("comprension", "¿Cuál es el último número que genera `range(1, 5)`? Escribe solo el número.", ["4"])
t.pred(QUE_IMPRIME, """
    s = 0
    for i in range(1, 5):
        s += i
    print(s)
""", "10\n")
t.parsons("Ordena las líneas para sumar los números pares del 2 al 10. Debe imprimir `30`.", """
    suma = 0
    for i in range(2, 11, 2):
        suma += i
    print(suma)
""", "30\n")
t.prog("Lee enteros positivos, uno por línea, hasta encontrar un `0` (que no cuenta). Imprime cuántos números leíste y su suma, separados por un espacio.", """
    cuantos = 0
    suma = 0
    n = int(input())
    while n != 0:
        cuantos += 1
        suma += n
        n = int(input())
    print(cuantos, suma)
""", [("3\n5\n2\n0", "3 10"), ("0", "0 0"), ("7\n0", "1 7")])

t = BANCO.tema("ciclos-anidados")
t.pred(QUE_IMPRIME, """
    for i in range(1, 3):
        for j in range(1, 4):
            print(i * j, end="")
        print()
""", "123\n246\n")
t.parsons("Ordena las líneas para dibujar este triángulo:\n\n```\n*\n**\n***\n```", """
    for i in range(1, 4):
        for j in range(i):
            print("*", end="")
        print()
""", "*\n**\n***\n")
t.prog("Lee un entero `n` e imprime la tabla de multiplicar de `n` por `n`: `n` filas con los valores separados por un espacio.", """
    n = int(input())
    for i in range(1, n + 1):
        fila = []
        for j in range(1, n + 1):
            fila.append(str(i * j))
        print(" ".join(fila))
""", [("2", "1 2\n2 4"), ("3", "1 2 3\n2 4 6\n3 6 9"), ("1", "1")])

t = BANCO.tema("funciones")
t.rc("recall", "¿Qué palabra reservada de Python define una función?", ["def"])
t.om("comprension", "¿Qué devuelve una función de Python que termina sin ejecutar `return`?", ["0", "`None`", "Una cadena vacía", "Es un error"], 1)
t.pred(QUE_IMPRIME, """
    a = 4
    b = doble(a)
    print(a, b)
""", "4 8\n", antes="""
    def doble(x):
        x = x * 2
        return x
""")
t.parsons("Ordena las líneas para definir `cuadrado` y usarla. Debe imprimir `25`.", """
    def cuadrado(x):
        r = x * x
        return r
    n = 5
    print(cuadrado(n))
""", "25\n")
t.prog("Escribe la función `es_primo(n)` y úsala: lee un entero e imprime `SI` si es primo o `NO` si no lo es.", """
    def es_primo(n):
        if n < 2:
            return False
        d = 2
        while d * d <= n:
            if n % d == 0:
                return False
            d += 1
        return True


    n = int(input())
    print("SI" if es_primo(n) else "NO")
""", [("7", "SI"), ("9", "NO"), ("1", "NO"), ("2", "SI")])

t = BANCO.tema("recursion")
t.pred(QUE_IMPRIME, """
    f(3)
""", "1\n2\n3\n", antes="""
    def f(n):
        if n == 0:
            return
        f(n - 1)
        print(n)
""")
t.parsons("Ordena las líneas del factorial recursivo. Debe imprimir `120`.", """
    def fact(n):
        if n <= 1:
            return 1
        return n * fact(n - 1)
    print(fact(5))
""", "120\n")
t.prog("Escribe una función recursiva para Fibonacci, con `F(0) = 0` y `F(1) = 1`. Lee `n` e imprime `F(n)`.", """
    def fib(n):
        if n < 2:
            return n
        return fib(n - 1) + fib(n - 2)


    print(fib(int(input())))
""", [("0", "0"), ("1", "1"), ("10", "55"), ("7", "13")])

t = BANCO.tema("arreglos")
t.rc("recall", "¿Qué función de Python devuelve el número de elementos de una lista?", ["len", "len()"])
t.om("comprension", "¿Qué devuelve `v[-1]` si `v = [3, 1, 4]`?", ["3", "4", "Un error", "`None`"], 1)
t.pred(QUE_IMPRIME, """
    v = [3, 1, 4, 1]
    s = 0
    for x in v:
        s += x
    print(s, v[2])
""", "9 4\n")
t.parsons("Ordena las líneas para encontrar el mayor elemento de la lista sin usar `max`. Debe imprimir `9`.", """
    v = [4, 9, 2, 7, 5]
    mayor = v[0]
    for x in v:
        if x > mayor:
            mayor = x
    print(mayor)
""", "9\n")
t.prog("Lee un entero `n` y, en la línea siguiente, `n` enteros. Imprímelos en orden inverso, separados por un espacio.", """
    n = int(input())
    v = input().split()
    print(" ".join(reversed(v[:n])))
""", [("3\n1 2 3", "3 2 1"), ("1\n9", "9"), ("4\n5 6 7 8", "8 7 6 5")])

t = BANCO.tema("matrices")
t.rc("comprension", "Con `m = [[1, 2, 3], [4, 5, 6]]`, ¿cuánto vale `m[1][0]`? Escribe solo el número.", ["4"])
t.pred(QUE_IMPRIME, """
    m = [[1, 2, 3], [4, 5, 6]]
    print(len(m), len(m[0]))
    print(m[0][2])
""", "2 3\n3\n")
t.parsons("Ordena las líneas para sumar la diagonal principal de la matriz. Debe imprimir `15`.", """
    m = [[1, 2, 3], [4, 5, 6], [7, 8, 9]]
    suma = 0
    for i in range(3):
        suma += m[i][i]
    print(suma)
""", "15\n")
t.prog("Lee `f` y `c` en una línea y después `f` líneas con `c` enteros cada una. Imprime la suma de cada fila, una por renglón.", """
    f, c = map(int, input().split())
    for _ in range(f):
        fila = map(int, input().split())
        print(sum(fila))
""", [("2 2\n1 2\n3 4", "3\n7"), ("1 3\n1 1 1", "3"), ("3 1\n5\n6\n7", "5\n6\n7")])

t = BANCO.tema("cadenas")
t.om("recall", "¿Qué significa que las cadenas de Python sean inmutables?", [
    "Que no se pueden imprimir",
    "Que no se puede cambiar un carácter en su lugar: se crea otra cadena",
    "Que solo aceptan letras",
    "Que no se pueden comparar",
], 1)
t.om("comprension", "¿Qué devuelve `\"hola\"[1:3]`?", ["`ho`", "`ol`", "`ola`", "`hol`"], 1)
t.rc("recall", "¿Qué método de cadena convierte todo el texto a mayúsculas?", ["upper", "upper()", ".upper()"])
t.pred(QUE_IMPRIME, """
    s = "Hola"
    print(len(s), s[1])
""", "4 o\n")
t.parsons("Ordena las líneas para contar cuántas letras `a` tiene la palabra sin usar `count`. Debe imprimir `3`.", """
    s = "banana"
    n = 0
    for letra in s:
        if letra == "a":
            n += 1
    print(n)
""", "3\n")
t.prog("Lee una palabra e imprímela al revés.", """
    s = input().strip()
    print(s[::-1])
""", [("hola", "aloh"), ("a", "a"), ("abcde", "edcba")])

t = BANCO.tema("estructuras")
t.om("recall", "En Python, ¿qué estructura guarda datos como pares de clave y valor?", ["list", "tuple", "dict", "range"], 2)
t.om("comprension", "Después de `a = {\"x\": 1}`, `b = a` y `b[\"x\"] = 9`, ¿cuánto vale `a[\"x\"]`?", ["1", "9", "Un error", "`None`"], 1)
t.rc("recall", "Dado `p = {\"edad\": 20}`, ¿qué expresión obtiene la edad? Escribe la expresión sin espacios.", ["p[\"edad\"]", "p['edad']", "p.get(\"edad\")", "p.get('edad')"])
t.pred(QUE_IMPRIME, """
    p = {"x": 2, "y": 3}
    p["x"] = p["x"] * 2
    print(p["x"], p["y"])
""", "4 3\n")
t.parsons("Ordena las líneas para crear el registro de un alumno y usar su campo. Debe imprimir `20`.", """
    alumno = {}
    alumno["nombre"] = "Ana"
    alumno["edad"] = 20
    print(alumno["edad"])
""", "20\n")
t.prog("Lee `n` y después `n` líneas, cada una con un nombre (sin espacios) y una calificación entera. Imprime el nombre del alumno con la calificación más alta (el primero, si hay empate). Guarda cada alumno en un diccionario.", """
    n = int(input())
    mejor = None
    for _ in range(n):
        nombre, calificacion = input().split()
        alumno = {"nombre": nombre, "calificacion": int(calificacion)}
        if mejor is None or alumno["calificacion"] > mejor["calificacion"]:
            mejor = alumno
    print(mejor["nombre"])
""", [("3\nAna 80\nLuis 95\nEva 70", "Luis"), ("1\nZoe 10", "Zoe"), ("2\nAna 90\nEva 90", "Ana")])

t = BANCO.tema("archivos")
t.om("recall", "¿Qué función de Python abre un archivo?", ["file", "open", "read", "load"], 1)
t.om("comprension", "¿Qué ventaja tiene abrir un archivo con `with open(...) as f:`?", [
    "Lo lee más rápido",
    "Se cierra solo al terminar el bloque, incluso si ocurre un error",
    "Lo convierte en una lista",
    "Evita tener que indicar el nombre",
], 1)
t.rc("recall", "¿Qué modo se pasa a `open` para abrir un archivo de texto para escritura? Escribe solo la letra.", ["w"])
t.pred(QUE_IMPRIME, """
    with open("d.txt", "w") as f:
        f.write("7 8\\n")
    with open("d.txt") as f:
        a, b = f.read().split()
    print(int(a) + int(b))
""", "15\n")
t.parsons("Ordena las líneas: el programa escribe 1, 2 y 3 en un archivo, lo vuelve a abrir y suma lo que lee. Debe imprimir `6`.", """
    with open("n.txt", "w") as f:
        f.write("1\\n2\\n3\\n")
    s = 0
    with open("n.txt") as g:
        for linea in g:
            s += int(linea)
    print(s)
""", "6\n")
t.prog("Lee `n` y, en la línea siguiente, `n` enteros. Escríbelos en el archivo `datos.txt`, uno por línea. Después vuelve a abrir ese archivo, lee los números e imprime su suma.", """
    n = int(input())
    numeros = input().split()
    with open("datos.txt", "w") as f:
        for x in numeros[:n]:
            f.write(x + "\\n")
    suma = 0
    with open("datos.txt") as f:
        for linea in f:
            suma += int(linea)
    print(suma)
""", [("3\n1 2 3", "6"), ("1\n10", "10"), ("2\n-4 4", "0")])

t = BANCO.tema("busqueda-ordenamiento")
t.pred("Este programa hace **una** pasada del ordenamiento de burbuja. ¿Qué imprime exactamente?", """
    v = [4, 3, 2, 1]
    for i in range(3):
        if v[i] > v[i + 1]:
            v[i], v[i + 1] = v[i + 1], v[i]
    print(v)
""", "[3, 2, 1, 4]\n")
t.parsons("Ordena las líneas de una búsqueda lineal: imprime la posición del número buscado. Debe imprimir `2`.", """
    v = [8, 3, 5, 1, 9]
    buscado = 5
    pos = -1
    for i in range(5):
        if v[i] == buscado:
            pos = i
    print(pos)
""", "2\n")
t.prog("Lee `n` y, en la línea siguiente, `n` enteros. Ordénalos de menor a mayor con burbuja (sin usar `sort` ni `sorted`) e imprímelos separados por un espacio.", """
    n = int(input())
    v = list(map(int, input().split()))
    for i in range(n - 1):
        for j in range(n - 1 - i):
            if v[j] > v[j + 1]:
                v[j], v[j + 1] = v[j + 1], v[j]
    print(" ".join(map(str, v)))
""", [("4\n3 1 4 2", "1 2 3 4"), ("1\n5", "5"), ("3\n9 9 1", "1 9 9")])
