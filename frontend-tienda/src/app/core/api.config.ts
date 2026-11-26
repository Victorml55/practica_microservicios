// Responsable: Omar
// OJO: Angular corre en el NAVEGADOR, no dentro de Docker,
// por eso aquí se usa localhost:<puerto> y NO http://api-xxx:<puerto>.
export const API = {
  usuarios:   'http://localhost:8001/api/usuarios',
  productos:  'http://localhost:8002/api/productos',
  envios:     'http://localhost:8003/api/envios',
  inventario: 'http://localhost:8004/api/inventario',
  carrito:    'http://localhost:8005/api/carrito',
  pedidos:    'http://localhost:8006/api/pedidos',
  pagos:      'http://localhost:8007/api/pagos',
  resenas:    'http://localhost:8008/api/resenas',
};
