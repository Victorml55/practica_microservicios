import os
from contextlib import asynccontextmanager
from datetime import datetime, timezone

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field
from pymongo import MongoClient
from pymongo.errors import PyMongoError
from starlette.exceptions import HTTPException as StarletteHTTPException

client = MongoClient(os.getenv("MONGO_URL", "mongodb://localhost:27017"),
                     serverSelectionTimeoutMS=5000)
resenas = client[os.getenv("MONGO_DB", "resenas_db")]["resenas"]

# Reseñas semilla (productos 1-10, usuario demo id=1 y otros)
SEMILLA = [
    {"producto_id": 1, "usuario_id": 1, "calificacion": 5, "comentario": "Excelente calidad, llegó rápido."},
    {"producto_id": 1, "usuario_id": 2, "calificacion": 4, "comentario": "Muy bueno, aunque el empaque venía golpeado."},
    {"producto_id": 2, "usuario_id": 1, "calificacion": 3, "comentario": "Cumple, pero esperaba más."},
    {"producto_id": 3, "usuario_id": 3, "calificacion": 5, "comentario": "Lo volvería a comprar."},
    {"producto_id": 4, "usuario_id": 2, "calificacion": 4, "comentario": "Buena relación calidad-precio."},
    {"producto_id": 5, "usuario_id": 1, "calificacion": 2, "comentario": "No era lo que mostraban las fotos."},
    {"producto_id": 5, "usuario_id": 3, "calificacion": 4, "comentario": "A mí sí me gustó."},
    {"producto_id": 7, "usuario_id": 2, "calificacion": 5, "comentario": "Perfecto, tal cual la descripción."},
]


def sembrar() -> None:
    """Inserta las reseñas semilla solo si la colección está vacía."""
    resenas.create_index("producto_id")
    if resenas.count_documents({}) == 0:
        ahora = datetime.now(timezone.utc)
        resenas.insert_many([{**r, "fecha": ahora} for r in SEMILLA])


@asynccontextmanager
async def lifespan(_: FastAPI):
    try:
        sembrar()
    except PyMongoError as e:  # si Mongo aún no está listo, el servicio arranca igual
        print(f"[api-resenas] No se pudo sembrar al arrancar: {e}")
    yield


app = FastAPI(title="api-resenas", lifespan=lifespan)  # Responsable: Ricardo
app.add_middleware(CORSMiddleware, allow_origins=[os.getenv("CORS_ORIGIN", "*")],
                   allow_methods=["*"], allow_headers=["*"])


# ---------- Errores con el formato del equipo: { "error": "mensaje" } ----------
@app.exception_handler(StarletteHTTPException)
async def http_error(_: Request, exc: StarletteHTTPException):
    return JSONResponse(status_code=exc.status_code, content={"error": str(exc.detail)})


@app.exception_handler(RequestValidationError)
async def validation_error(_: Request, exc: RequestValidationError):
    err = exc.errors()[0]
    campo = ".".join(str(p) for p in err["loc"] if p != "body") or "body"
    return JSONResponse(status_code=400, content={"error": f"Dato inválido en '{campo}': {err['msg']}"})


@app.exception_handler(PyMongoError)
async def mongo_error(_: Request, exc: PyMongoError):
    return JSONResponse(status_code=500, content={"error": "Error de base de datos (MongoDB)"})


# ---------- Modelos ----------
class Resena(BaseModel):
    producto_id: int = Field(ge=1)
    usuario_id: int = Field(ge=1)
    calificacion: int = Field(ge=1, le=5)
    comentario: str = Field(min_length=1, max_length=500)


def a_json(doc: dict) -> dict:
    """Convierte un documento de Mongo a JSON: _id (ObjectId) -> id (str), fecha -> ISO."""
    return {
        "id": str(doc["_id"]),
        "producto_id": doc["producto_id"],
        "usuario_id": doc["usuario_id"],
        "calificacion": doc["calificacion"],
        "comentario": doc["comentario"],
        "fecha": doc["fecha"].isoformat() if isinstance(doc.get("fecha"), datetime) else doc.get("fecha"),
    }


# ---------- Endpoints ----------
@app.get("/health")
def health():
    client.admin.command("ping")
    return {"servicio": "api-resenas", "ok": True, "db": "ok"}


@app.get("/api/resenas/producto/{producto_id}")
def por_producto(producto_id: int):
    docs = list(resenas.find({"producto_id": producto_id}).sort("fecha", -1))
    total = len(docs)
    promedio = round(sum(d["calificacion"] for d in docs) / total, 1) if total else 0.0
    return {"producto_id": producto_id, "promedio": promedio, "total": total,
            "resenas": [a_json(d) for d in docs]}


@app.post("/api/resenas", status_code=201)
def crear(data: Resena):
    doc = {**data.model_dump(), "comentario": data.comentario.strip(),
           "fecha": datetime.now(timezone.utc)}
    if not doc["comentario"]:
        raise HTTPException(400, "El comentario no puede estar vacío")
    res = resenas.insert_one(doc)
    doc["_id"] = res.inserted_id
    return a_json(doc)
