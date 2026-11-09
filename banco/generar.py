"""Genera apps/web/resources/banco/banco.json. Uso: uv run banco/generar.py

Aquí se revisa la forma de cada ítem. Que los programas den lo que dicen se comprueba ejecutándolos:
`php artisan banco:verificar`.
"""
import collections
import json
import sys
from pathlib import Path

aqui = Path(__file__).parent
sys.path.insert(0, str(aqui))

import items_c  # noqa: E402
import items_comunes  # noqa: E402
import items_python  # noqa: E402
from temas import TEMAS  # noqa: E402

TIPOS = {"opcion_multiple", "respuesta_corta", "prediccion_salida", "parsons", "programacion"}
NIVELES = {"recall", "comprension", "practica"}
# Lo mínimo que debe tener cada tema, por lenguaje, para armar una prueba teórica y una práctica
MINIMO_TEORICOS = 4
MINIMO_PRACTICOS = 1


def errores_de(item: dict, temas: set[str]) -> list[str]:
    e = []
    tipo = item["tipo"]
    if item["tema"] not in temas:
        e.append(f"tema desconocido: {item['tema']}")
    if tipo not in TIPOS:
        e.append(f"tipo desconocido: {tipo}")
    if item["nivel"] not in NIVELES:
        e.append(f"nivel desconocido: {item['nivel']}")
    if (tipo == "programacion") != (item["nivel"] == "practica"):
        e.append("solo la programación es de nivel práctica")
    if not item["enunciado"]["md"].strip():
        e.append("enunciado vacío")
    if tipo == "opcion_multiple":
        ids = {o["id"] for o in item["enunciado"]["opciones"]}
        textos = [o["texto"] for o in item["enunciado"]["opciones"]]
        if len(textos) < 3 or len(set(textos)) != len(textos):
            e.append("opciones insuficientes o repetidas")
        if item["clave"]["correcta"] not in ids:
            e.append("la opción correcta no existe")
    if tipo == "parsons":
        # Dos líneas iguales no se distinguen: el orden correcto sería ambiguo y se calificaría mal al estudiante
        textos = [l["texto"].strip() for l in item["enunciado"]["lineas"]]
        repetidas = [t for t, n in collections.Counter(textos).items() if n > 1]
        if repetidas:
            e.append(f"líneas repetidas: {repetidas}")
        if len(textos) < 3:
            e.append("muy pocas líneas para ordenar")
    if tipo == "programacion" and not any(not c["oculto"] for c in item["casos_prueba"]):
        e.append("necesita al menos un caso visible")
    return e


def main() -> None:
    items = items_comunes.BANCO.items() + items_c.BANCO.items() + items_python.BANCO.items()
    claves_temas = {t["clave"] for t in TEMAS}

    errores = []
    for item in items:
        errores += [f"{item['codigo']}: {x}" for x in errores_de(item, claves_temas)]
    if len({i["codigo"] for i in items}) != len(items):
        errores.append("hay códigos repetidos")
    for tema in TEMAS:
        for requisito in tema["prerrequisitos"]:
            if requisito not in claves_temas:
                errores.append(f"{tema['clave']}: prerrequisito desconocido {requisito}")

    # Cobertura: todo concepto básico debe poder evaluarse en cada lenguaje donde aplica
    resumen = {}
    for lenguaje in ("c", "cpp", "python"):
        for tema in TEMAS:
            aplica = tema.get("lenguajes")
            if aplica and lenguaje not in aplica:
                continue
            del_tema = [i for i in items if i["tema"] == tema["clave"] and (not i["lenguajes"] or lenguaje in i["lenguajes"])]
            teoricos = sum(1 for i in del_tema if i["nivel"] != "practica")
            practicos = sum(1 for i in del_tema if i["nivel"] == "practica")
            resumen.setdefault(lenguaje, []).append(len(del_tema))
            if teoricos < MINIMO_TEORICOS or practicos < MINIMO_PRACTICOS:
                errores.append(f"{tema['clave']} en {lenguaje}: {teoricos} teóricos y {practicos} prácticos")

    if errores:
        print("\n".join(errores))
        raise SystemExit(f"{len(errores)} error(es): no se escribió el banco.")

    temas = [
        {"lenguajes": [], "orden": (i + 1) * 10, **t} for i, t in enumerate(TEMAS)
    ]
    salida = aqui.parent / "apps" / "web" / "resources" / "banco" / "banco.json"
    salida.parent.mkdir(parents=True, exist_ok=True)
    salida.write_text(
        json.dumps(
            {"_nota": "GENERADO por banco/generar.py: no lo edites a mano.", "temas": temas, "items": items},
            ensure_ascii=False, indent=1,
        ) + "\n",
        encoding="utf-8",
    )
    por_tipo = collections.Counter(i["tipo"] for i in items)
    print(f"{len(temas)} temas y {len(items)} ítems -> {salida.relative_to(aqui.parent)}")
    print(dict(por_tipo))
    for lenguaje, cuentas in resumen.items():
        print(f"{lenguaje}: {len(cuentas)} temas, {sum(cuentas)} ítems (mínimo por tema: {min(cuentas)})")


if __name__ == "__main__":
    main()
