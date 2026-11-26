import os
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from pymongo import MongoClient

app = FastAPI(title="api-resenas")  # Responsable: Ricardo
app.add_middleware(CORSMiddleware, allow_origins=[os.getenv("CORS_ORIGIN", "*")],
                   allow_methods=["*"], allow_headers=["*"])

client = MongoClient(os.getenv("MONGO_URL", "mongodb://localhost:27017"))
resenas = client[os.getenv("MONGO_DB", "resenas_db")]["resenas"]


class Resena(BaseModel):
    producto_id: int
    usuario_id: int
    calificacion: int = Field(ge=1, le=5)
    comentario: str


@app.get("/health")
def health():
    client.admin.command("ping")
    return {"servicio": "api-resenas", "ok": True, "db": "ok"}


@app.get("/api/resenas/producto/{producto_id}")
def por_producto(producto_id: int):
    # TODO: { producto_id, promedio, total, resenas: [...] }  (quitar _id de Mongo o convertirlo a str)
    raise HTTPException(501, "No implementado")


@app.post("/api/resenas", status_code=201)
def crear(data: Resena):
    # TODO: insertar en Mongo con fecha y devolver la resena creada
    raise HTTPException(501, "No implementado")
