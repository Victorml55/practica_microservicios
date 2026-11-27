// Responsable: Omar
const express = require('express');
const cors = require('cors');

const app = express();
app.use(cors({ origin: process.env.CORS_ORIGIN || '*' }));
app.use(express.json());

const PRODUCTOS_URL = process.env.PRODUCTOS_URL || 'http://api-productos:8002';

// Almacenamiento temporal en memoria: { usuario_id: [{ producto_id, cantidad }] }
const carritos = new Map();

// Datos semilla: usuario demo (id = 1)
carritos.set(1, [
  { producto_id: 2, cantidad: 1 },
  { producto_id: 8, cantidad: 1 },
]);

app.get('/health', (req, res) => res.json({ servicio: 'api-carrito', ok: true }));

// Opcional: agrega nombre y precio consultando api-productos.
// Si el servicio no responde, devuelve el item sin enriquecer.
async function enriquecer(item) {
  try {
    const r = await fetch(`${PRODUCTOS_URL}/api/productos/${item.producto_id}`);
    if (!r.ok) return item;
    const p = await r.json();
    return { ...item, nombre: p.nombre, precio: p.precio };
  } catch {
    return item;
  }
}

app.get('/api/carrito/:usuario_id', async (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  if (Number.isNaN(uid)) return res.status(400).json({ error: 'usuario_id inválido' });
  const items = await Promise.all((carritos.get(uid) || []).map(enriquecer));
  res.json({ usuario_id: uid, items });
});

app.post('/api/carrito/:usuario_id/agregar', (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  const pid = parseInt(req.body.producto_id, 10);
  const cant = parseInt(req.body.cantidad, 10);
  if ([uid, pid, cant].some(Number.isNaN) || cant < 1) {
    return res.status(400).json({ error: 'producto_id y cantidad (>= 1) son obligatorios' });
  }
  const items = carritos.get(uid) || [];
  const existente = items.find((i) => i.producto_id === pid);
  if (existente) existente.cantidad += cant;
  else items.push({ producto_id: pid, cantidad: cant });
  carritos.set(uid, items);
  res.status(201).json({ usuario_id: uid, items });
});

app.delete('/api/carrito/:usuario_id/vaciar', (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  if (Number.isNaN(uid)) return res.status(400).json({ error: 'usuario_id inválido' });
  carritos.set(uid, []);
  res.json({ usuario_id: uid, items: [] });
});

app.use((req, res) => res.status(404).json({ error: 'Ruta no encontrada' }));
app.use((err, req, res, next) => res.status(500).json({ error: 'Error interno' }));

app.listen(8005, () => console.log('api-carrito en puerto 8005'));