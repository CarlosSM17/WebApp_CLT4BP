"""Forma de escribir el banco: funciones cortas que arman los ítems con la estructura de la tabla `items`.

Cada ítem se escribe con su resultado esperado a mano y `php artisan banco:verificar` lo comprueba ejecutándolo: si el
autor y la máquina no coinciden, hay un error en el enunciado o en el programa.
"""

import textwrap
from dataclasses import dataclass, field


def _sangrar(texto: str, n: int = 4) -> str:
    return textwrap.indent(texto, " " * n)


def _limpio(texto: str) -> str:
    return textwrap.dedent(texto).strip("\n")


def programa_c(cuerpo: str, antes: str = "", incluye: tuple[str, ...] = ()) -> str:
    """Un programa completo de C alrededor de `cuerpo` (lo de adentro de main)."""
    cabecera = "\n".join(f"#include <{h}>" for h in ("stdio.h", *incluye))
    previo = f"{_limpio(antes)}\n\n" if antes else ""
    return f"{cabecera}\n\n{previo}int main(void) {{\n{_sangrar(_limpio(cuerpo))}\n    return 0;\n}}"


def programa_python(cuerpo: str, antes: str = "", incluye: tuple[str, ...] = ()) -> str:
    previo = f"{_limpio(antes)}\n\n" if antes else ""
    return f"{previo}{_limpio(cuerpo)}"


@dataclass
class Familia:
    """Un grupo de lenguajes que comparte ítems (el código de C también se comprueba con g++)."""

    clave: str
    lenguajes: list[str]
    fence: str
    programa: object


C = Familia("c", ["c", "cpp"], "c", programa_c)
PYTHON = Familia("py", ["python"], "python", programa_python)
COMUN = Familia("comun", [], "", None)


@dataclass
class Tema:
    clave: str
    familia: Familia
    items: list[dict] = field(default_factory=list)

    def _agregar(self, tipo: str, nivel: str, enunciado: dict, **resto) -> None:
        codigo = f"{self.clave}-{self.familia.clave}-{len(self.items) + 1:02d}"
        self.items.append({
            "codigo": codigo, "tema": self.clave, "tipo": tipo, "nivel": nivel,
            "lenguajes": self.familia.lenguajes, "enunciado": enunciado, **resto,
        })

    def om(self, nivel: str, md: str, opciones: list[str], correcta: int) -> None:
        """Opción múltiple; `correcta` es la posición (desde 0) en `opciones`."""
        letras = "abcdefgh"
        self._agregar(
            "opcion_multiple", nivel,
            {"md": _limpio(md), "opciones": [{"id": letras[i], "texto": t} for i, t in enumerate(opciones)]},
            clave={"correcta": letras[correcta]},
        )

    def rc(self, nivel: str, md: str, aceptadas: list[str]) -> None:
        """Respuesta corta: se compara sin distinguir mayúsculas y con los espacios normalizados."""
        self._agregar("respuesta_corta", nivel, {"md": _limpio(md)}, clave={"aceptadas": aceptadas})

    def pred(self, md: str, cuerpo: str, salida: str, antes: str = "", incluye: tuple[str, ...] = (), entrada: str = "") -> None:
        """Predicción de salida: el programa completo va en el enunciado."""
        programa = self.familia.programa(cuerpo, antes, incluye)
        self._agregar(
            "prediccion_salida", "comprension",
            {"md": f"{_limpio(md)}\n\n```{self.familia.fence}\n{programa}\n```"},
            clave={"salida": salida},
            verificacion={"programa": programa, "entrada": entrada, "salida": salida},
        )

    def parsons(self, md: str, lineas: str, salida: str, antes: str = "", incluye: tuple[str, ...] = (), completo: bool = False, entrada: str = "") -> None:
        """Parsons: las líneas se escriben en orden correcto y el aula las desordena.

        Si `completo` es falso, las líneas son el cuerpo de main y el programa que se comprueba las envuelve.
        """
        texto = _limpio(lineas)
        separadas = [l for l in texto.split("\n") if l.strip()]
        programa = texto if completo else self.familia.programa(texto, antes, incluye)
        self._agregar(
            "parsons", "comprension",
            {"md": _limpio(md), "lineas": [{"id": f"l{i + 1}", "texto": l} for i, l in enumerate(separadas)]},
            clave={"orden": [f"l{i + 1}" for i in range(len(separadas))]},
            verificacion={"programa": programa, "entrada": entrada, "salida": salida},
        )

    def prog(self, md: str, solucion: str, casos: list[tuple[str, str]], inicial: str = "") -> None:
        """Programación. El último caso queda oculto: el estudiante ve ejemplos, no todas las pruebas."""
        visibles = max(1, len(casos) - 1)
        enunciado = {"md": _limpio(md)}
        if inicial:
            enunciado["codigo_inicial"] = _limpio(inicial)
        self._agregar(
            "programacion", "practica", enunciado,
            solucion=_limpio(solucion),
            casos_prueba=[
                {"entrada": e, "salida_esperada": s, "oculto": i >= visibles} for i, (e, s) in enumerate(casos)
            ],
        )


class Coleccion:
    def __init__(self, familia: Familia) -> None:
        self.familia = familia
        self.temas: list[Tema] = []

    def tema(self, clave: str) -> Tema:
        t = Tema(clave, self.familia)
        self.temas.append(t)
        return t

    def items(self) -> list[dict]:
        return [i for t in self.temas for i in t.items]
