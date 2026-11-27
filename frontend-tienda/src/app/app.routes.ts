import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';
import { LoginComponent } from './pages/login.component';
import { CatalogoComponent } from './pages/catalogo.component';
import { DetalleProductoComponent } from './pages/detalle-producto.component';
import { CheckoutComponent } from './pages/checkout.component';
import { MisPedidosComponent } from './pages/mis-pedidos.component';

export const routes: Routes = [
  { path: '', component: CatalogoComponent },
  { path: 'login', component: LoginComponent },
  { path: 'producto/:id', component: DetalleProductoComponent },
  { path: 'checkout', component: CheckoutComponent, canActivate: [authGuard] },
  { path: 'mis-pedidos', component: MisPedidosComponent, canActivate: [authGuard] },
  { path: '**', redirectTo: '' },
];
