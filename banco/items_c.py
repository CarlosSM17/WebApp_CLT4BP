"""Ítems de C. El código se comprueba con gcc y con g++, así que también sirven en un curso de C++ (con estilo de C)."""

from base import C, Coleccion

BANCO = Coleccion(C)
QUE_IMPRIME = "¿Qué imprime exactamente este programa?"

t = BANCO.tema("algoritmos")
t.pred(QUE_IMPRIME, """
    int precio = 12;
    int cantidad = 3;
    int total = precio * cantidad;
    printf("%d\\n", total);
""", "36\n")
t.parsons("Ordena los pasos del programa que calcula el total a pagar y lo imprime: primero los datos, luego el proceso y al final la salida.", """
    int precio = 20;
    int cantidad = 4;
    int total = precio * cantidad;
    printf("%d\\n", total);
""", "80\n")
t.prog("Una tienda cobra el precio por la cantidad comprada. Lee dos enteros, `precio` y `cantidad`, e imprime el total a pagar.", """
    #include <stdio.h>

    int main(void) {
        int precio, cantidad;
        scanf("%d %d", &precio, &cantidad);
        printf("%d\\n", precio * cantidad);
        return 0;
    }
""", [("20 4", "80"), ("7 1", "7"), ("0 10", "0")])

t = BANCO.tema("estructura-programa")
t.rc("recall", "¿Cómo se llama la función en la que empieza a ejecutarse todo programa en C?", ["main", "main()"])
t.pred(QUE_IMPRIME, """
    printf("Hola\\n");
    printf("mundo\\n");
""", "Hola\nmundo\n")
t.parsons("Ordena las líneas para formar un programa completo que imprima `Hola`.", """
    #include <stdio.h>
    int main(void) {
        printf("Hola\\n");
        return 0;
    }
""", "Hola\n", completo=True)
t.prog("Escribe un programa que imprima exactamente `Hola, mundo`.", """
    #include <stdio.h>

    int main(void) {
        printf("Hola, mundo\\n");
        return 0;
    }
""", [("", "Hola, mundo")])

t = BANCO.tema("tipos-variables")
t.om("recall", "¿Qué tipo de C es el adecuado para guardar una cantidad entera de alumnos?", ["char", "int", "double", "void"], 1)
t.rc("recall", "Completa la palabra reservada que declara una constante: `____ int MAX = 10;`", ["const"])
t.pred(QUE_IMPRIME, """
    int a = 5;
    int b = a;
    a = 9;
    printf("%d %d\\n", a, b);
""", "9 5\n")
t.parsons("Ordena las líneas para intercambiar los valores de `a` y `b` usando una variable auxiliar. Debe imprimir `2 1`.", """
    int a = 1;
    int b = 2;
    int aux = a;
    a = b;
    b = aux;
    printf("%d %d\\n", a, b);
""", "2 1\n")
t.prog("Lee el ancho y el alto de un rectángulo (dos enteros) e imprime su área.", """
    #include <stdio.h>

    int main(void) {
        int ancho, alto;
        scanf("%d %d", &ancho, &alto);
        int area = ancho * alto;
        printf("%d\\n", area);
        return 0;
    }
""", [("3 4", "12"), ("10 2", "20"), ("0 5", "0")])

t = BANCO.tema("entrada-salida")
t.om("recall", "¿Qué especificador de formato se usa para imprimir un `double` con `printf`?", ["%d", "%f", "%c", "%s"], 1)
t.om("comprension", "¿Por qué `scanf(\"%d\", &n)` lleva `&` antes de `n`?", [
    "Porque `n` es una constante",
    "Porque `scanf` necesita la dirección de la variable para guardar ahí el dato",
    "Porque indica que el número es entero",
    "Es opcional: sirve de adorno",
], 1)
t.rc("recall", "¿Qué función de C lee datos con formato desde el teclado?", ["scanf", "scanf()"])
t.pred(QUE_IMPRIME, """
    printf("%d-%d\\n", 3, 4);
    printf("%.2f\\n", 1.5);
""", "3-4\n1.50\n")
t.parsons("Ordena las líneas: el programa lee un entero e imprime su doble. Con la entrada `21` debe imprimir `42`.", """
    int n;
    scanf("%d", &n);
    int doble = n * 2;
    printf("%d\\n", doble);
""", "42\n", entrada="21")
t.prog("Lee una hora y un minuto (dos enteros) e imprímelos con el formato `HH:MM`, siempre con dos dígitos.", """
    #include <stdio.h>

    int main(void) {
        int h, m;
        scanf("%d %d", &h, &m);
        printf("%02d:%02d\\n", h, m);
        return 0;
    }
""", [("7 5", "07:05"), ("12 30", "12:30"), ("0 0", "00:00")])

t = BANCO.tema("operadores-expresiones")
t.om("comprension", "En C, con `int a = 7;` y `int b = 2;`, ¿cuánto vale `a / b`?", ["3.5", "3", "4", "1"], 1)
t.rc("recall", "¿Qué operador de C obtiene el residuo de una división entera? Escribe solo el símbolo.", ["%"])
t.pred(QUE_IMPRIME, """
    int a = 17;
    printf("%d %d\\n", a / 5, a % 5);
    printf("%.1f\\n", a / 5.0);
""", "3 2\n3.4\n")
t.parsons("Ordena las líneas para calcular el promedio de tres calificaciones. Debe imprimir `8.33`.", """
    int a = 7;
    int b = 8;
    int c = 10;
    int suma = a + b + c;
    double promedio = suma / 3.0;
    printf("%.2f\\n", promedio);
""", "8.33\n")
t.prog("Lee una cantidad de segundos (entero) e imprime cuántas horas, minutos y segundos son, separados por un espacio.", """
    #include <stdio.h>

    int main(void) {
        int s;
        scanf("%d", &s);
        printf("%d %d %d\\n", s / 3600, s % 3600 / 60, s % 60);
        return 0;
    }
""", [("3661", "1 1 1"), ("59", "0 0 59"), ("7200", "2 0 0")])

t = BANCO.tema("condicionales")
t.rc("recall", "¿Qué palabra se usa dentro de un `switch` para que no siga al caso siguiente?", ["break", "break;"])
t.om("comprension", "En C, ¿qué hace `if (x = 5)`?", [
    "Compara `x` con 5",
    "Asigna 5 a `x` y la condición siempre resulta verdadera",
    "No compila nunca",
    "Compara y después asigna",
], 1)
t.pred(QUE_IMPRIME, """
    int n = 15;
    if (n % 3 == 0 && n % 5 == 0) {
        printf("ambos\\n");
    } else if (n % 3 == 0) {
        printf("tres\\n");
    } else {
        printf("otro\\n");
    }
""", "ambos\n")
t.parsons("Ordena las líneas para imprimir el mayor de dos números. Debe imprimir `8`.", """
    int a = 8;
    int b = 3;
    if (a > b) {
        printf("%d\\n", a);
    } else {
        printf("%d\\n", b);
    }
""", "8\n")
t.prog("Lee un año e imprime `BISIESTO` si es bisiesto o `NORMAL` si no. Un año es bisiesto si es múltiplo de 4, salvo los múltiplos de 100 que no sean múltiplos de 400.", """
    #include <stdio.h>

    int main(void) {
        int a;
        scanf("%d", &a);
        if ((a % 4 == 0 && a % 100 != 0) || a % 400 == 0) {
            printf("BISIESTO\\n");
        } else {
            printf("NORMAL\\n");
        }
        return 0;
    }
""", [("2024", "BISIESTO"), ("2023", "NORMAL"), ("1900", "NORMAL"), ("2000", "BISIESTO")])

t = BANCO.tema("ciclos")
t.rc("recall", "¿Qué ciclo de C ejecuta su cuerpo al menos una vez, porque revisa la condición al final?", ["do-while", "do while", "do...while", "do"])
t.pred(QUE_IMPRIME, """
    int s = 0;
    for (int i = 1; i <= 4; i++) {
        s += i;
    }
    printf("%d\\n", s);
""", "10\n")
t.parsons("Ordena las líneas para sumar los números pares del 2 al 10. Debe imprimir `30`.", """
    int suma = 0;
    for (int i = 2; i <= 10; i += 2) {
        suma += i;
    }
    printf("%d\\n", suma);
""", "30\n")
t.prog("Lee enteros positivos hasta encontrar un `0` (que no cuenta). Imprime cuántos números leíste y su suma, separados por un espacio.", """
    #include <stdio.h>

    int main(void) {
        int n, cuantos = 0, suma = 0;
        scanf("%d", &n);
        while (n != 0) {
            cuantos++;
            suma += n;
            scanf("%d", &n);
        }
        printf("%d %d\\n", cuantos, suma);
        return 0;
    }
""", [("3 5 2 0", "3 10"), ("0", "0 0"), ("7 0", "1 7")])

t = BANCO.tema("ciclos-anidados")
t.pred(QUE_IMPRIME, """
    for (int i = 1; i <= 2; i++) {
        for (int j = 1; j <= 3; j++) {
            printf("%d", i * j);
        }
        printf("\\n");
    }
""", "123\n246\n")
t.parsons("Ordena las líneas para dibujar este triángulo:\n\n```\n*\n**\n***\n```", """
    for (int i = 1; i <= 3; i++) {
        for (int j = 1; j <= i; j++) {
            printf("*");
        } // fin del ciclo de j
        printf("\\n");
    } // fin del ciclo de i
""", "*\n**\n***\n")
t.prog("Lee un entero `n` e imprime la tabla de multiplicar de `n` por `n`: `n` filas con los valores separados por un espacio.", """
    #include <stdio.h>

    int main(void) {
        int n;
        scanf("%d", &n);
        for (int i = 1; i <= n; i++) {
            for (int j = 1; j <= n; j++) {
                if (j > 1) {
                    printf(" ");
                }
                printf("%d", i * j);
            }
            printf("\\n");
        }
        return 0;
    }
""", [("2", "1 2\n2 4"), ("3", "1 2 3\n2 4 6\n3 6 9"), ("1", "1")])

t = BANCO.tema("funciones")
t.rc("recall", "¿Qué palabra se escribe como tipo de retorno de una función de C que no devuelve ningún valor?", ["void"])
t.om("comprension", "En C, si una función modifica su parámetro `int x`, ¿qué le pasa a la variable que se usó como argumento?", [
    "Cambia igual que `x`",
    "No cambia: el parámetro recibe una copia del valor",
    "Se borra al terminar la función",
    "El programa no compila",
], 1)
t.pred(QUE_IMPRIME, """
    int a = 4;
    int b = doble(a);
    printf("%d %d\\n", a, b);
""", "4 8\n", antes="""
    int doble(int x) {
        x = x * 2;
        return x;
    }
""")
t.parsons("Ordena las líneas para formar un programa que defina `cuadrado` y la use. Debe imprimir `25`.", """
    #include <stdio.h>
    int cuadrado(int x) {
        return x * x;
    } // fin de cuadrado
    int main(void) {
        printf("%d\\n", cuadrado(5));
        return 0;
    } // fin de main
""", "25\n", completo=True)
t.prog("Escribe la función `int es_primo(int n)` y úsala: lee un entero e imprime `SI` si es primo o `NO` si no lo es.", """
    #include <stdio.h>

    int es_primo(int n) {
        if (n < 2) {
            return 0;
        }
        for (int d = 2; d * d <= n; d++) {
            if (n % d == 0) {
                return 0;
            }
        }
        return 1;
    }

    int main(void) {
        int n;
        scanf("%d", &n);
        printf("%s\\n", es_primo(n) ? "SI" : "NO");
        return 0;
    }
""", [("7", "SI"), ("9", "NO"), ("1", "NO"), ("2", "SI")])

t = BANCO.tema("recursion")
t.pred(QUE_IMPRIME, """
    f(3);
""", "1\n2\n3\n", antes="""
    void f(int n) {
        if (n == 0) {
            return;
        }
        f(n - 1);
        printf("%d\\n", n);
    }
""")
t.parsons("Ordena las líneas para formar un programa con el factorial recursivo. Debe imprimir `120`.", """
    #include <stdio.h>
    int fact(int n) {
        if (n <= 1) return 1;
        return n * fact(n - 1);
    } // fin de fact
    int main(void) {
        printf("%d\\n", fact(5));
        return 0;
    } // fin de main
""", "120\n", completo=True)
t.prog("Escribe una función recursiva para Fibonacci, con `F(0) = 0` y `F(1) = 1`. Lee `n` e imprime `F(n)`.", """
    #include <stdio.h>

    int fib(int n) {
        if (n < 2) {
            return n;
        }
        return fib(n - 1) + fib(n - 2);
    }

    int main(void) {
        int n;
        scanf("%d", &n);
        printf("%d\\n", fib(n));
        return 0;
    }
""", [("0", "0"), ("1", "1"), ("10", "55"), ("7", "13")])

t = BANCO.tema("arreglos")
t.rc("comprension", "Con la declaración `int v[5];`, ¿cuántos enteros caben en `v`? Escribe solo el número.", ["5"])
t.om("comprension", "En C, ¿qué pasa al escribir en `v[10]` si `v` tiene 5 elementos?", [
    "El arreglo crece solo",
    "Es un acceso fuera de los límites: el comportamiento no está definido",
    "Se guarda en `v[4]`",
    "El compilador siempre lo rechaza",
], 1)
t.pred(QUE_IMPRIME, """
    int v[4] = {3, 1, 4, 1};
    int s = 0;
    for (int i = 0; i < 4; i++) {
        s += v[i];
    }
    printf("%d %d\\n", s, v[2]);
""", "9 4\n")
t.parsons("Ordena las líneas para encontrar el mayor elemento del arreglo. Debe imprimir `9`.", """
    int v[5] = {4, 9, 2, 7, 5};
    int max = v[0];
    for (int i = 1; i < 5; i++) {
        if (v[i] > max) max = v[i];
    }
    printf("%d\\n", max);
""", "9\n")
t.prog("Lee un entero `n` y después `n` enteros. Imprímelos en orden inverso, separados por un espacio.", """
    #include <stdio.h>

    int main(void) {
        int n;
        scanf("%d", &n);
        int v[1000];
        for (int i = 0; i < n; i++) {
            scanf("%d", &v[i]);
        }
        for (int i = n - 1; i >= 0; i--) {
            printf("%d", v[i]);
            if (i > 0) {
                printf(" ");
            }
        }
        printf("\\n");
        return 0;
    }
""", [("3\n1 2 3", "3 2 1"), ("1\n9", "9"), ("4\n5 6 7 8", "8 7 6 5")])

t = BANCO.tema("matrices")
t.rc("comprension", "En `int m[2][3];`, ¿cuál es el último índice válido de **fila**? Escribe solo el número.", ["1"])
t.pred(QUE_IMPRIME, """
    int m[2][3] = {{1, 2, 3}, {4, 5, 6}};
    printf("%d %d\\n", m[1][0], m[0][2]);
""", "4 3\n")
t.parsons("Ordena las líneas para sumar la diagonal principal de la matriz. Debe imprimir `15`.", """
    int m[3][3] = {{1, 2, 3}, {4, 5, 6}, {7, 8, 9}};
    int suma = 0;
    for (int i = 0; i < 3; i++) {
        suma += m[i][i];
    }
    printf("%d\\n", suma);
""", "15\n")
t.prog("Lee `f` y `c` y después una matriz de `f` filas y `c` columnas. Imprime la suma de cada fila, una por renglón.", """
    #include <stdio.h>

    int main(void) {
        int f, c, x;
        scanf("%d %d", &f, &c);
        for (int i = 0; i < f; i++) {
            int suma = 0;
            for (int j = 0; j < c; j++) {
                scanf("%d", &x);
                suma += x;
            }
            printf("%d\\n", suma);
        }
        return 0;
    }
""", [("2 2\n1 2\n3 4", "3\n7"), ("1 3\n1 1 1", "3"), ("3 1\n5\n6\n7", "5\n6\n7")])

t = BANCO.tema("cadenas")
t.om("recall", "¿Con qué carácter termina toda cadena en C?", ["`\\n`", "`\\0`", "Un espacio", "`.`"], 1)
t.om("comprension", "¿Por qué `char s[5] = \"Hola\";` cabe exactamente?", [
    "Porque cada letra ocupa medio espacio",
    "Porque son 4 letras más el carácter de fin de cadena",
    "Porque C agrega espacio solo",
    "No cabe: hacen falta 10 espacios",
], 1)
t.rc("recall", "¿Qué función de `<string.h>` devuelve la longitud de una cadena?", ["strlen", "strlen()"])
t.pred(QUE_IMPRIME, """
    char s[] = "Hola";
    printf("%d %c\\n", (int) strlen(s), s[1]);
""", "4 o\n", incluye=("string.h",))
t.parsons("Ordena las líneas para contar cuántas letras `a` tiene la palabra. Debe imprimir `3`.", """
    char s[] = "banana";
    int n = 0;
    for (int i = 0; s[i] != '\\0'; i++) {
        if (s[i] == 'a') n++;
    }
    printf("%d\\n", n);
""", "3\n")
t.prog("Lee una palabra (sin espacios, de 100 letras como máximo) e imprímela al revés.", """
    #include <stdio.h>
    #include <string.h>

    int main(void) {
        char s[101];
        scanf("%100s", s);
        for (int i = (int) strlen(s) - 1; i >= 0; i--) {
            printf("%c", s[i]);
        }
        printf("\\n");
        return 0;
    }
""", [("hola", "aloh"), ("a", "a"), ("abcde", "edcba")])

t = BANCO.tema("apuntadores")
t.om("recall", "¿Qué devuelve el operador `&` aplicado a una variable?", [
    "Su valor", "Su dirección de memoria", "Su tamaño", "Su tipo",
], 1)
t.om("comprension", "Después de `int x = 5; int *p = &x; *p = 8;`, ¿cuánto vale `x`?", ["5", "8", "La dirección de `p`", "No se puede saber"], 1)
t.rc("recall", "¿Qué operador se usa para acceder al valor al que apunta un apuntador? Escribe solo el símbolo.", ["*"])
t.pred(QUE_IMPRIME, """
    int a = 1;
    int *p = &a;
    *p += 4;
    printf("%d %d\\n", a, *p);
""", "5 5\n")
t.parsons("Ordena las líneas para formar un programa que intercambie dos variables mediante apuntadores. Debe imprimir `2 1`.", """
    #include <stdio.h>
    void intercambia(int *a, int *b) {
        int t = *a;
        *a = *b;
        *b = t;
    } // fin de intercambia
    int main(void) {
        int x = 1, y = 2;
        intercambia(&x, &y);
        printf("%d %d\\n", x, y);
        return 0;
    } // fin de main
""", "2 1\n", completo=True)
t.prog("Escribe `void minmax(int *v, int n, int *min, int *max)`, que deja en `*min` y `*max` el menor y el mayor del arreglo. Lee `n` y `n` enteros e imprime el menor y el mayor, separados por un espacio.", """
    #include <stdio.h>

    void minmax(int *v, int n, int *min, int *max) {
        *min = v[0];
        *max = v[0];
        for (int i = 1; i < n; i++) {
            if (v[i] < *min) {
                *min = v[i];
            }
            if (v[i] > *max) {
                *max = v[i];
            }
        }
    }

    int main(void) {
        int n, v[1000], menor, mayor;
        scanf("%d", &n);
        for (int i = 0; i < n; i++) {
            scanf("%d", &v[i]);
        }
        minmax(v, n, &menor, &mayor);
        printf("%d %d\\n", menor, mayor);
        return 0;
    }
""", [("3\n5 1 9", "1 9"), ("1\n4", "4 4"), ("4\n-1 -5 0 2", "-5 2")])

t = BANCO.tema("estructuras")
t.om("recall", "¿Qué palabra reservada de C define un registro con varios campos?", ["class", "struct", "record", "enum"], 1)
t.om("comprension", "Con `struct P { int x; };`, después de `struct P a = {1}; struct P b = a; b.x = 9;`, ¿cuánto vale `a.x`?", ["1", "9", "0", "No compila"], 0)
t.rc("recall", "¿Qué operador accede a un campo a través de un apuntador a estructura? Escribe solo el símbolo.", ["->"])
t.pred(QUE_IMPRIME, """
    struct P a = {2, 3};
    a.x = a.x * 2;
    printf("%d %d\\n", a.x, a.y);
""", "4 3\n", antes="""
    struct P {
        int x;
        int y;
    };
""")
t.parsons("Ordena las líneas para formar un programa que declare una estructura y use su campo. Debe imprimir `20`.", """
    #include <stdio.h>
    struct Alumno {
        int edad;
    }; // fin de la estructura
    int main(void) {
        struct Alumno a;
        a.edad = 20;
        printf("%d\\n", a.edad);
        return 0;
    } // fin de main
""", "20\n", completo=True)
t.prog("Lee `n` y después `n` alumnos, cada uno con su nombre (sin espacios) y su calificación entera. Imprime el nombre del alumno con la calificación más alta (el primero, si hay empate). Usa una estructura.", """
    #include <stdio.h>

    struct Alumno {
        char nombre[50];
        int calificacion;
    };

    int main(void) {
        int n;
        scanf("%d", &n);
        struct Alumno mejor, a;
        mejor.calificacion = -1;
        for (int i = 0; i < n; i++) {
            scanf("%49s %d", a.nombre, &a.calificacion);
            if (a.calificacion > mejor.calificacion) {
                mejor = a;
            }
        }
        printf("%s\\n", mejor.nombre);
        return 0;
    }
""", [("3\nAna 80\nLuis 95\nEva 70", "Luis"), ("1\nZoe 10", "Zoe"), ("2\nAna 90\nEva 90", "Ana")])

t = BANCO.tema("archivos")
t.om("recall", "¿Qué función de C abre un archivo?", ["open_file", "fopen", "fread", "fclose"], 1)
t.om("comprension", "¿Qué devuelve `fopen` si no puede abrir el archivo?", ["0 bytes", "`NULL`", "-1", "Termina el programa"], 1)
t.rc("recall", "¿Qué modo se pasa a `fopen` para abrir un archivo de texto para escritura? Escribe solo la letra.", ["w"])
t.pred(QUE_IMPRIME, """
    FILE *f = fopen("d.txt", "w");
    fprintf(f, "7 8\\n");
    fclose(f);
    int a, b;
    f = fopen("d.txt", "r");
    fscanf(f, "%d %d", &a, &b);
    fclose(f);
    printf("%d\\n", a + b);
""", "15\n")
t.parsons("Ordena las líneas: el programa escribe 1, 2 y 3 en un archivo, lo vuelve a abrir y suma lo que lee. Debe imprimir `6`.", """
    FILE *f = fopen("n.txt", "w");
    for (int i = 1; i <= 3; i++) fprintf(f, "%d\\n", i);
    fclose(f);
    int n, s = 0;
    FILE *g = fopen("n.txt", "r");
    while (fscanf(g, "%d", &n) == 1) s += n;
    fclose(g);
    printf("%d\\n", s);
""", "6\n")
t.prog("Lee `n` y `n` enteros del teclado y escríbelos en el archivo `datos.txt`. Después vuelve a abrir ese archivo, lee los números e imprime su suma.", """
    #include <stdio.h>

    int main(void) {
        int n, x, suma = 0;
        scanf("%d", &n);
        FILE *f = fopen("datos.txt", "w");
        for (int i = 0; i < n; i++) {
            scanf("%d", &x);
            fprintf(f, "%d\\n", x);
        }
        fclose(f);
        f = fopen("datos.txt", "r");
        while (fscanf(f, "%d", &x) == 1) {
            suma += x;
        }
        fclose(f);
        printf("%d\\n", suma);
        return 0;
    }
""", [("3\n1 2 3", "6"), ("1\n10", "10"), ("2\n-4 4", "0")])

t = BANCO.tema("memoria-dinamica")
t.om("recall", "¿Qué función libera la memoria reservada con `malloc`?", ["delete", "free", "release", "fclose"], 1)
t.om("comprension", "¿Qué es una fuga de memoria?", [
    "Escribir fuera de un arreglo",
    "Reservar memoria y nunca liberarla",
    "Leer un archivo vacío",
    "Usar demasiadas variables locales",
], 1)
t.rc("recall", "¿Qué función de `<stdlib.h>` reserva un bloque de memoria y recibe su tamaño en bytes?", ["malloc", "malloc()"])
t.pred(QUE_IMPRIME, """
    int *p = (int *) malloc(3 * sizeof(int));
    for (int i = 0; i < 3; i++) {
        p[i] = i * i;
    }
    printf("%d\\n", p[2]);
    free(p);
""", "4\n", incluye=("stdlib.h",))
t.parsons("Ordena las líneas: reserva un arreglo de 4 enteros, llénalo con el doble de cada índice, imprime el último y libera la memoria. Debe imprimir `6`.", """
    int n = 4;
    int *v = (int *) malloc(n * sizeof(int));
    for (int i = 0; i < n; i++) {
        v[i] = i * 2;
    }
    printf("%d\\n", v[n - 1]);
    free(v);
""", "6\n", incluye=("stdlib.h",))
t.prog("Lee `n` y `n` enteros, guardándolos en un arreglo reservado con `malloc`. Imprime su promedio con 2 decimales y libera la memoria.", """
    #include <stdio.h>
    #include <stdlib.h>

    int main(void) {
        int n;
        scanf("%d", &n);
        int *v = (int *) malloc(n * sizeof(int));
        int suma = 0;
        for (int i = 0; i < n; i++) {
            scanf("%d", &v[i]);
            suma += v[i];
        }
        printf("%.2f\\n", (double) suma / n);
        free(v);
        return 0;
    }
""", [("3\n1 2 3", "2.00"), ("2\n1 2", "1.50"), ("1\n7", "7.00")])

t = BANCO.tema("busqueda-ordenamiento")
t.pred("Este programa hace **una** pasada del ordenamiento de burbuja. ¿Qué imprime exactamente?", """
    int v[4] = {4, 3, 2, 1};
    for (int i = 0; i < 3; i++) {
        if (v[i] > v[i + 1]) {
            int t = v[i];
            v[i] = v[i + 1];
            v[i + 1] = t;
        }
    }
    printf("%d%d%d%d\\n", v[0], v[1], v[2], v[3]);
""", "3214\n")
t.parsons("Ordena las líneas de una búsqueda lineal: imprime la posición del número buscado. Debe imprimir `2`.", """
    int v[5] = {8, 3, 5, 1, 9};
    int buscado = 5, pos = -1;
    for (int i = 0; i < 5; i++) {
        if (v[i] == buscado) pos = i;
    }
    printf("%d\\n", pos);
""", "2\n")
t.prog("Lee `n` y `n` enteros, ordénalos de menor a mayor e imprímelos separados por un espacio.", """
    #include <stdio.h>

    int main(void) {
        int n, v[1000];
        scanf("%d", &n);
        for (int i = 0; i < n; i++) {
            scanf("%d", &v[i]);
        }
        for (int i = 0; i < n - 1; i++) {
            for (int j = 0; j < n - 1 - i; j++) {
                if (v[j] > v[j + 1]) {
                    int t = v[j];
                    v[j] = v[j + 1];
                    v[j + 1] = t;
                }
            }
        }
        for (int i = 0; i < n; i++) {
            printf("%d", v[i]);
            if (i < n - 1) {
                printf(" ");
            }
        }
        printf("\\n");
        return 0;
    }
""", [("4\n3 1 4 2", "1 2 3 4"), ("1\n5", "5"), ("3\n9 9 1", "1 9 9")])
