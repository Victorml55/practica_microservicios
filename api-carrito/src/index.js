// Responsable: Omar
const crypto = require('crypto');
const express = require('express');
const cors = require('cors');

const app = express();
app.use(cors({ origin: process.env.CORS_ORIGIN || '*' }));
app.use(express.json());

const PRODUCTOS_URL = process.env.PRODUCTOS_URL || 'http://api-productos:8002';
const JWT_SECRET = process.env.JWT_SECRET || '';
const INTERNAL_KEY = process.env.INTERNAL_KEY || '';

// ---------- Seguridad ----------
const b64url = (buf) => buf.toString('base64').replace(/=+$/, '').replace(/\+/g, '-').replace(/\//g, '_');
const iguales = (a, b) => a.length === b.length && crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));

// Verifica el JWT HS256 emitido por api-usuarios; devuelve el payload o null
function verificarJwt(req) {
  const m = /^Bearer\s+(\S+)$/i.exec(req.headers.authorization || '');
  if (!m || !JWT_SECRET) return null;
  const partes = m[1].split('.');
  if (partes.length !== 3) return null;
  const [h, p, f] = partes;
  const esperada = b64url(crypto.createHmac('sha256', JWT_SECRET).update(`${h}.${p}`).digest());
  if (!iguales(esperada, f)) return null;
  try {
    if (JSON.parse(Buffer.from(h, 'base64url').toString()).alg !== 'HS256') return null;
    const payload = JSON.parse(Buffer.from(p, 'base64url').toString());
    return payload.exp && payload.exp >= Date.now() / 1000 ? payload : null;
  } catch {
    return null;
  }
}

const esInterno = (req) => !!INTERNAL_KEY && iguales(INTERNAL_KEY, String(req.headers['x-internal-key'] || ''));

// El dueño del carrito (token con sub = usuario_id), un admin o api-pedidos (X-Internal-Key)
function autorizar({ permitirInterno = false } = {}) {
  return (req, res, next) => {
    if (permitirInterno && esInterno(req)) return next();
    const t = verificarJwt(req);
    if (!t) return res.status(401).json({ error: 'Token inválido o ausente' });
    if (Number(t.sub) !== parseInt(req.params.usuario_id, 10) && t.rol !== 'admin') {
      return res.status(403).json({ error: 'No puedes acceder al carrito de otro usuario' });
    }
    next();
  };
}

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

app.get('/api/carrito/:usuario_id', autorizar(), async (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  if (Number.isNaN(uid)) return res.status(400).json({ error: 'usuario_id inválido' });
  const items = await Promise.all((carritos.get(uid) || []).map(enriquecer));
  res.json({ usuario_id: uid, items });
});

app.post('/api/carrito/:usuario_id/agregar', autorizar(), (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  const pid = parseInt(req.body.producto_id, 10);
  const cant = parseInt(req.body.cantidad, 10);
  if ([uid, pid, cant].some(Number.isNaN) || pid < 1 || cant < 1 || cant > 100) {
    return res.status(400).json({ error: 'producto_id y cantidad (entre 1 y 100) son obligatorios' });
  }
  const items = carritos.get(uid) || [];
  const existente = items.find((i) => i.producto_id === pid);
  if (existente) existente.cantidad += cant;
  else items.push({ producto_id: pid, cantidad: cant });
  carritos.set(uid, items);
  res.status(201).json({ usuario_id: uid, items });
});

app.delete('/api/carrito/:usuario_id/vaciar', autorizar({ permitirInterno: true }), (req, res) => {
  const uid = parseInt(req.params.usuario_id, 10);
  if (Number.isNaN(uid)) return res.status(400).json({ error: 'usuario_id inválido' });
  carritos.set(uid, []);
  res.json({ usuario_id: uid, items: [] });
});

app.use((req, res) => res.status(404).json({ error: 'Ruta no encontrada' }));
app.use((err, req, res, next) => {
  // JSON mal formado -> 400; cualquier otra cosa -> 500
  if (err.type === 'entity.parse.failed') return res.status(400).json({ error: 'JSON inválido' });
  console.error(err);
  res.status(500).json({ error: 'Error interno' });
});

app.listen(8005, () => console.log('api-carrito en puerto 8005'));