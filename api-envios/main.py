import os
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

app = FastAPI(title="api-envios")  # Responsable: Ricardo
app.add_middleware(CORSMiddleware, allow_origins=[os.getenv("CORS_ORIGIN", "*")],
                   allow_methods=["*"], allow_headers=["*"])


class Cotizacion(BaseModel):
    codigo_postal: str
    cantidad: int  # o peso_kg, acordarlo con Omar


class GenerarGuia(BaseModel):
    pedido_id: int


@app.get("/health")
def health():
    return {"servicio": "api-envios", "ok": True}


@app.post("/api/envios/cotizar")
def cotizar(data: Cotizacion):
    # TODO: regla de tarifa -> { costo_envio, dias_estimados }
    raise HTTPException(501, "No implementado")


@app.post("/api/envios/generar", status_code=201)
def generar(data: GenerarGuia):
    # TODO: generar guia unica, estado inicial 'preparando' -> { guia, pedido_id, estado }
    raise HTTPException(501, "No implementado")


@app.get("/api/envios/rastreo/{guia}")
def rastreo(guia: str):
    # TODO: { guia, estado: preparando|en_transito|entregado } o 404
    raise HTTPException(501, "No implementado")
