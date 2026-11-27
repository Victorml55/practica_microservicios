import os
import threading
from datetime import datetime, timezone

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field
from starlette.exceptions import HTTPException as StarletteHTTPException

class JSONUTF8(JSONResponse):
    """JSON con charset=utf-8 para que el navegador muestre bien los acentos."""
    media_type = "application/json; charset=utf-8"


app = FastAPI(title="api-envios", default_response_class=JSONUTF8)  # Responsable: Ricardo
app.add_middleware(CORSMiddleware, allow_origins=[os.getenv("CORS_ORIGIN", "*")],
                   allow_methods=["*"], allow_headers=["*"])


# ---------- Errores con el formato del equipo: { "error": "mensaje" } ----------
@app.exception_handler(StarletteHTTPException)
async def http_error(_: Request, exc: StarletteHTTPException):
    return JSONUTF8(status_code=exc.status_code, content={"error": str(exc.detail)})


@app.exception_handler(RequestValidationError)
async def validation_error(_: Request, exc: RequestValidationError):
    # FastAPI responde 422 por defecto; el acuerdo del equipo es 400
    err = exc.errors()[0]
    campo = ".".join(str(p) for p in err["loc"] if p != "body") or "body"
    return JSONUTF8(status_code=400, content={"error": f"Dato inválido en '{campo}': {err['msg']}"})


# ---------- Modelos ----------
class Cotizacion(BaseModel):
    codigo_postal: str = Field(pattern=r"^\d{5}$")  # CP de México: 5 dígitos
    cantidad: int = Field(ge=1)                     # número de piezas del pedido


class GenerarGuia(BaseModel):
    pedido_id: int = Field(ge=1)


# ---------- Regla de tarifa ----------
# Zona según el primer dígito del CP: (costo base, días estimados)
ZONAS = {
    "0": (79.0, 2), "1": (79.0, 2),                      # CDMX y área metropolitana
    "2": (99.0, 3), "3": (99.0, 3), "4": (99.0, 3),      # centro / bajío
    "5": (109.0, 4), "6": (109.0, 4), "7": (119.0, 4),   # occidente / norte / sureste
    "8": (129.0, 5), "9": (129.0, 5),                    # noroeste / península
}
COSTO_POR_PIEZA_EXTRA = 15.0


def calcular_costo(codigo_postal: str, cantidad: int) -> tuple[float, int]:
    base, dias = ZONAS[codigo_postal[0]]
    costo = base + COSTO_POR_PIEZA_EXTRA * (cantidad - 1)
    if cantidad > 10:  # pedidos grandes tardan un día más
        dias += 1
    return round(costo, 2), dias


# ---------- Guías en memoria ----------
ESTADOS = ("preparando", "en_transito", "entregado")
_lock = threading.Lock()
_consecutivo = 0
guias: dict[str, dict] = {}        # guia -> envío
guia_por_pedido: dict[int, str] = {}  # pedido_id -> guia


def _nueva_guia() -> str:
    global _consecutivo
    _consecutivo += 1
    return f"TM-{_consecutivo:06d}"


# ---------- Endpoints ----------
@app.get("/health")
def health():
    return {"servicio": "api-envios", "ok": True}


@app.post("/api/envios/cotizar")
def cotizar(data: Cotizacion):
    costo, dias = calcular_costo(data.codigo_postal, data.cantidad)
    return {"codigo_postal": data.codigo_postal, "cantidad": data.cantidad,
            "costo_envio": costo, "dias_estimados": dias}


@app.post("/api/envios/generar", status_code=201)
def generar(data: GenerarGuia):
    with _lock:
        # Si el pedido ya tenía guía, se regresa la misma (no se duplica)
        if data.pedido_id in guia_por_pedido:
            return guias[guia_por_pedido[data.pedido_id]]
        guia = _nueva_guia()
        envio = {"guia": guia, "pedido_id": data.pedido_id, "estado": "preparando",
                 "creado_en": datetime.now(timezone.utc).isoformat()}
        guias[guia] = envio
        guia_por_pedido[data.pedido_id] = guia
    return envio


@app.get("/api/envios/rastreo/{guia}")
def rastreo(guia: str):
    envio = guias.get(guia.upper())
    if not envio:
        raise HTTPException(404, "Guía no encontrada")
    return envio


@app.patch("/api/envios/rastreo/{guia}/avanzar")
def avanzar(guia: str):
    """Extra para la demo: pasa la guía al siguiente estado
    (preparando -> en_transito -> entregado)."""
    with _lock:
        envio = guias.get(guia.upper())
        if not envio:
            raise HTTPException(404, "Guía no encontrada")
        i = ESTADOS.index(envio["estado"])
        if i == len(ESTADOS) - 1:
            raise HTTPException(400, "El envío ya fue entregado")
        envio["estado"] = ESTADOS[i + 1]
    return envio
