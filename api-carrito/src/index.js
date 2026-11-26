// Responsable: Omar
const express = require('express');
const cors = require('cors');

const app = express();
app.use(cors({ origin: process.env.CORS_ORIGIN || '*' }));
app.use(express.json());

// Almacenamiento temporal en memoria: { usuario_id: [{ producto_id, cantidad }] }
const carritos = new Map();

app.get('/health', (req, res) => res.json({ servicio: 'api-carrito', ok: true }));

app.get('/api/carrito/:usuario_id', (req, res) => {
  // TODO: { usuario_id, items: [...] }
  res.status(501).json({ error: 'No implementado' });
});

app.post('/api/carrito/:usuario_id/agregar', (req, res) => {
  // TODO: body { producto_id, cantidad } -> si ya existe, sumar cantidad
  res.status(501).json({ error: 'No implementado' });
});

app.delete('/api/carrito/:usuario_id/vaciar', (req, res) => {
  // TODO: limpiar carrito (lo llama api-pedidos al aprobarse el pago)
  res.status(501).json({ error: 'No implementado' });
});

app.listen(8005, () => console.log('api-carrito en puerto 8005'));
