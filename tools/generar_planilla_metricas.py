"""Genera la planilla de métricas ágiles de EcoRuta (velocity + burndown).

Uso:
    PYTHONPATH=<ruta a openpyxl> python tools/generar_planilla_metricas.py
"""

from datetime import date, timedelta
from pathlib import Path
import subprocess

from openpyxl import Workbook
from openpyxl.chart import BarChart, LineChart, Reference
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

SALIDA = Path(__file__).resolve().parents[1] / "docs" / "metricas" / "EcoRuta-Velocity.xlsx"

VERDE = "0D3B2A"
VERDE_CLARO = "D7F0E4"
GRIS = "F2F4F3"
BORDE = Border(*[Side(style="thin", color="C9D3CE")] * 4)

CABECERA = Font(bold=True, color="FFFFFF", size=11)
TITULO = Font(bold=True, size=14, color=VERDE)
SUBTITULO = Font(bold=True, size=11, color=VERDE)

BACKLOG = [
    ("HU-01", "Registro de comerciante/repartidor", "M", "S1", 5, "Completada", 5, date(2026, 9, 5)),
    ("HU-02", "Inicio y cierre de sesión seguro", "M", "S1", 3, "Completada", 3, date(2026, 9, 5)),
    ("HU-03", "Recuperación de contraseña por correo", "S", "S1", 3, "Completada", 3, date(2026, 9, 6)),
    ("HU-04", "Solicitud de reparto con tarifa y QR", "M", "S2", 8, "Completada", 8, date(2026, 10, 10)),
    ("HU-19", "Pago efectivo/transferencia/mixto con vuelto", "M", "S2", 5, "Completada", 5, date(2026, 10, 13)),
    ("HU-05", "Asignación de pedidos a repartidores", "M", "S2", 5, "Completada", 5, date(2026, 10, 10)),
    ("HU-06", "Turnos del repartidor (iniciar/pausar/fin)", "M", "S3", 5, "Completada", 5, date(2026, 10, 15)),
    ("HU-07", "Cambio de estado con historial y avisos", "M", "S3", 5, "Completada", 5, date(2026, 10, 15)),
    ("HU-08", "Confirmación de entrega por QR o firma", "M", "S3", 8, "Parcial", 6, date(2026, 10, 15)),
    ("HU-09", "Seguimiento de pedidos y notificaciones", "S", "S3", 5, "Completada", 5, date(2026, 10, 15)),
    ("HU-10", "Panel de métricas y CO₂ evitado", "S", "S3", 5, "Completada", 5, date(2026, 10, 15)),
    ("HU-11", "Timbrado con bloqueo y alertas", "M", "S4", 8, "Completada", 8, date(2026, 10, 28)),
    ("HU-12", "Emisión de factura con IVA y folio", "M", "S4", 8, "Completada", 8, date(2026, 10, 28)),
    ("HU-13", "KuDE/PDF con QR verificable", "M", "S4", 5, "Completada", 5, date(2026, 10, 28)),
    ("HU-14", "Anulación con motivo y auditoría", "S", "S4", 3, "Completada", 3, date(2026, 10, 29)),
    ("HU-15", "Historial de facturas, filtros y descargas", "S", "S4", 3, "Completada", 3, date(2026, 10, 29)),
    ("HU-16", "Cierre de sesión automático por token vencido", "M", "S5", 3, "Completada", 3, date(2026, 10, 29)),
    ("HU-17", "Operación offline con sincronización", "S", "S5", 8, "Completada", 8, date(2026, 10, 29)),
    ("HU-18", "Clientes y carga masiva CSV", "C", "S5", 5, "Completada", 5, date(2026, 10, 29)),
    ("HU-20", "Gestión de usuarios y roles", "S", "S5", 3, "Completada", 3, date(2026, 10, 29)),
]

SPRINTS = [
    ("S1", date(2026, 9, 1), date(2026, 9, 7)),
    ("S2", date(2026, 10, 8), date(2026, 10, 13)),
    ("S3", date(2026, 10, 14), date(2026, 10, 20)),
    ("S4", date(2026, 10, 21), date(2026, 10, 29)),
    ("S5", date(2026, 10, 29), date(2026, 11, 20)),
]

BURNDOWN = {
    "S4": {
        "inicio": date(2026, 10, 21),
        "dias": 8,
    },
    "S5": {
        "inicio": date(2026, 10, 29),
        "dias": 22,
    },
}

MESES = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"]


def fecha_corta(d: date) -> str:
    return f"{d.day:02d}-{MESES[d.month - 1]}"


def git(*args: str) -> str | None:
    """Ejecuta git en la raíz del repo; devuelve None si no está disponible."""
    try:
        salida = subprocess.run(
            ["git", *args],
            cwd=Path(__file__).resolve().parents[1],
            capture_output=True,
            text=True,
            check=True,
        )
        return salida.stdout.strip()
    except Exception:
        return None


def estilo_cabecera(fila, columnas):
    for col in range(1, columnas + 1):
        c = fila[col - 1]
        c.font = CABECERA
        c.fill = PatternFill("solid", fgColor=VERDE)
        c.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        c.border = BORDE


def generar():
    wb = Workbook()

    # ---------- Backlog ----------
    ws = wb.active
    ws.title = "Backlog"
    ws.append(["ID", "Historia de usuario", "Prioridad", "Sprint", "Puntos",
               "Estado", "Puntos completados", "Fecha de cierre (Git)"])
    estilo_cabecera(ws[1], 8)
    for fila in BACKLOG:
        ws.append([fila[0], fila[1], fila[2], fila[3], fila[4], fila[5], fila[6], fila[7]])
    for r in ws.iter_rows(min_row=2, max_row=ws.max_row, max_col=8):
        for c in r:
            c.border = BORDE
            c.alignment = Alignment(vertical="center", wrap_text=(c.column == 2))
        r[7].number_format = "dd/mm/yyyy"
    anchos = [9, 52, 11, 8, 8, 13, 18, 20]
    for i, a in enumerate(anchos, start=1):
        ws.column_dimensions[get_column_letter(i)].width = a
    ws.freeze_panes = "A2"
    ws.auto_filter.ref = f"A1:H{ws.max_row}"
    ws.sheet_view.showGridLines = False

    # ---------- Sprints + Velocity ----------
    vs = wb.create_sheet("Velocity")
    vs["A1"] = "Cuadro de mando ágil — Velocity por sprint"
    vs["A1"].font = TITULO
    vs["A2"] = "Comprometidos y completados se calculan automáticamente desde la hoja Backlog."
    vs["A2"].font = Font(italic=True, color="4B5F57")

    encabezados = ["Sprint", "Inicio", "Fin", "Comprometidos", "Completados", "Velocity", "% cumplimiento"]
    vs.append([])
    vs.append(encabezados)
    estilo_cabecera(vs[4], len(encabezados))

    fila_ini = 5
    for i, (sprint, inicio, fin) in enumerate(SPRINTS):
        r = fila_ini + i
        vs.cell(r, 1, sprint)
        vs.cell(r, 2, inicio).number_format = "dd/mm/yyyy"
        vs.cell(r, 3, fin).number_format = "dd/mm/yyyy"
        vs.cell(r, 4, f"=SUMIF(Backlog!$D$2:$D$21,$A{r},Backlog!$E$2:$E$21)")
        vs.cell(r, 5, f"=SUMIF(Backlog!$D$2:$D$21,$A{r},Backlog!$G$2:$G$21)")
        vs.cell(r, 6, f"=E{r}")
        vs.cell(r, 7, f"=IF(D{r}=0,0,E{r}/D{r})")
        vs.cell(r, 7).number_format = "0.0%"
        for c in range(1, 8):
            vs.cell(r, c).border = BORDE
            vs.cell(r, c).alignment = Alignment(horizontal="center")

    fin_tabla = fila_ini + len(SPRINTS) - 1
    fila_total = fin_tabla + 1
    vs.cell(fila_total, 1, "Total / promedio").font = SUBTITULO
    vs.cell(fila_total, 4, f"=SUM(D{fila_ini}:D{fin_tabla})").font = Font(bold=True)
    vs.cell(fila_total, 5, f"=SUM(E{fila_ini}:E{fin_tabla})").font = Font(bold=True)
    vs.cell(fila_total, 6, f"=AVERAGE(F{fila_ini}:F{fin_tabla})").font = Font(bold=True)
    vs.cell(fila_total, 7, f"=E{fila_total}/D{fila_total}")
    vs.cell(fila_total, 7).number_format = "0.0%"
    for c in range(1, 8):
        vs.cell(fila_total, c).border = BORDE
        vs.cell(fila_total, c).fill = PatternFill("solid", fgColor=VERDE_CLARO)
        vs.cell(fila_total, c).alignment = Alignment(horizontal="center")

    vs["A13"] = "Fórmula usada (en Excel en español se muestra como =SUMAR.SI):"
    vs["A13"].font = SUBTITULO
    vs["A14"] = "SUMIF(Backlog!$D$2:$D$21, $A5, Backlog!$G$2:$G$21)"
    vs["A14"].font = Font(name="Consolas", size=10, color="0B5E47")
    vs["A15"] = "Velocity = puntos completados del sprint. El promedio alimenta la planificación del próximo sprint."
    vs["A15"].font = Font(italic=True, color="4B5F57")

    for i, a in enumerate([10, 12, 12, 16, 14, 10, 15], start=1):
        vs.column_dimensions[get_column_letter(i)].width = a

    chart = BarChart()
    chart.type = "col"
    chart.title = "Velocity por sprint"
    chart.y_axis.title = "Puntos"
    chart.x_axis.title = "Sprint"
    data = Reference(vs, min_col=4, max_col=5, min_row=4, max_row=fin_tabla)
    cats = Reference(vs, min_col=1, min_row=fila_ini, max_row=fin_tabla)
    chart.add_data(data, titles_from_data=True)
    chart.set_categories(cats)
    chart.height = 7.5
    chart.width = 15
    vs.add_chart(chart, "A18")
    vs.sheet_view.showGridLines = False

    # ---------- Burndown ----------
    for nombre, info in BURNDOWN.items():
        bs = wb.create_sheet(f"Burndown {nombre}")
        bs["A1"] = f"Burndown — Sprint {nombre[1]}"
        bs["A1"].font = TITULO
        bs["A2"] = "Reconstruido con las fechas reales de cierre de cada historia (historial Git)."
        bs["A2"].font = Font(italic=True, color="4B5F57")
        bs["A3"] = ("El día 0 arranca con el total comprometido; el saldo baja en la fecha de "
                    "cierre de cada historia. En el Sprint 5 el trabajo funcional se cerró el "
                    "29-10 y el resto del ciclo fue documentación y hardening.")
        bs["A3"].font = Font(italic=True, color="4B5F57")
        bs["A3"].alignment = Alignment(wrap_text=True, vertical="top")
        bs.row_dimensions[3].height = 30

        bs.append(["Día", "Fecha", "Ideal", "Real"])
        estilo_cabecera(bs[4], 4)

        # Comprometidos del sprint = suma de puntos planificados (no completados).
        comprometidos = sum(b[4] for b in BACKLOG if b[3] == nombre)
        dias = info["dias"]
        inicio = info["inicio"]
        for d in range(dias + 1):
            fecha = inicio + timedelta(days=d)
            # Serie real: saldo de puntos cuya historia todavía no estaba cerrada.
            restantes = comprometidos - sum(
                b[4] for b in BACKLOG
                if b[3] == nombre and d > 0 and b[7] <= fecha
            )
            fila = 5 + d
            bs.cell(fila, 1, d)
            bs.cell(fila, 2, fecha).number_format = "dd/mm/yyyy"
            bs.cell(fila, 3, round(comprometidos * (1 - d / dias), 2))
            bs.cell(fila, 4, restantes)
            for c in range(1, 5):
                bs.cell(fila, c).border = BORDE
                bs.cell(fila, c).alignment = Alignment(horizontal="center")

        fin = 5 + dias
        linea = LineChart()
        linea.title = f"Burndown Sprint {nombre[1]} — ideal vs real"
        linea.y_axis.title = "Puntos restantes"
        linea.x_axis.title = "Día del sprint"
        datos = Reference(bs, min_col=3, max_col=4, min_row=4, max_row=fin)
        cats = Reference(bs, min_col=1, min_row=5, max_row=fin)
        linea.add_data(datos, titles_from_data=True)
        linea.set_categories(cats)
        linea.height = 7.5
        linea.width = 16
        bs.add_chart(linea, "F5")

        for i, a in enumerate([8, 12, 10, 10], start=1):
            bs.column_dimensions[get_column_letter(i)].width = a
        bs.sheet_view.showGridLines = False

    # ---------- Métricas Git ----------
    gs = wb.create_sheet("Git")
    gs["A1"] = "Métricas del repositorio"
    gs["A1"].font = TITULO
    commits = git("rev-list", "--count", "HEAD") or "—"
    autores = git("shortlog", "-sne", "--all")
    n_autores = len([l for l in (autores or "").splitlines() if l.strip()]) or "—"
    ultima = git("log", "-1", "--date=short", "--pretty=%ad") or "—"
    filas = [
        ("Repositorio", "github.com/mathiasparedes202-droid/ecoruta"),
        ("Rama principal", "main"),
        ("Commits en main", commits),
        ("Autores registrados", n_autores),
        ("Pull Requests", 0),
        ("Workflow CI", "React Doctor (.github/workflows/react-doctor.yml)"),
        ("Cobertura de pruebas móvil", "vitest: QR + offlineSync"),
        ("Última actualización", ultima),
    ]
    gs.append([])
    for i, (k, v) in enumerate(filas, start=3):
        gs.cell(i, 1, k).font = SUBTITULO
        gs.cell(i, 2, v)
        gs.cell(i, 1).border = BORDE
        gs.cell(i, 2).border = BORDE
    gs.column_dimensions["A"].width = 28
    gs.column_dimensions["B"].width = 56
    gs.sheet_view.showGridLines = False

    # ---------- Notas ----------
    ns = wb.create_sheet("Notas")
    ns["A1"] = "Metodología y acuerdos"
    ns["A1"].font = TITULO
    notas = [
        "1. Las fechas de cierre surgen de los commits reales del repositorio; las ventanas de sprint son una reconstrucción y deben validarse con el calendario oficial del curso.",
        "2. 'Comprometidos' es la suma de puntos planificados por sprint. 'Completados' usa la columna de puntos completados (una historia parcial suma sólo lo entregado).",
        "3. Velocity = puntos completados. El promedio de los 5 sprints se usa para dimensionar el siguiente ciclo.",
        "4. El burndown ideal es lineal desde los puntos comprometidos hasta 0. El real se marca en las fechas con commits del sprint.",
        "5. HU-08 quedó parcial: el QR está implementado y validado; la firma es nominal (no trazo biométrico).",
        "6. Completar nombres del equipo y fechas de ceremonias en docs/01-gestion-agil-sprints-1-2.md.",
    ]
    for i, texto in enumerate(notas, start=3):
        ns.cell(i, 1, texto)
        ns.cell(i, 1).alignment = Alignment(wrap_text=True, vertical="top")
        ns.row_dimensions[i].height = 32
    ns.column_dimensions["A"].width = 130
    ns.sheet_view.showGridLines = False

    SALIDA.parent.mkdir(parents=True, exist_ok=True)
    wb.save(SALIDA)
    print("OK:", SALIDA)


if __name__ == "__main__":
    generar()
