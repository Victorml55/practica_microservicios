import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../core/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  template: `
    <div class="panel" style="max-width:380px;margin:auto">
      <h2>{{ modoRegistro() ? 'Crear cuenta' : 'Iniciar sesión' }}</h2>
      @if (error()) { <p class="error">{{ error() }}</p> }
      @if (modoRegistro()) {
        <input placeholder="Nombre" [(ngModel)]="nombre" style="width:100%" />
      }
      <input placeholder="Correo" [(ngModel)]="correo" style="width:100%" />
      <input placeholder="Contraseña" type="password" [(ngModel)]="contrasena" style="width:100%" />
      <button class="btn" (click)="enviar()">{{ modoRegistro() ? 'Registrarme' : 'Entrar' }}</button>
      <p>
        <a href="#" (click)="$event.preventDefault(); modoRegistro.set(!modoRegistro())">
          {{ modoRegistro() ? 'Ya tengo cuenta' : 'Crear una cuenta' }}
        </a>
      </p>
      <small>Demo: demo&#64;tienda.com / demo123</small>
    </div>
  `,
})
export class LoginComponent {
  private auth = inject(AuthService);
  private router = inject(Router);

  modoRegistro = signal(false);
  error = signal('');
  nombre = '';
  correo = 'demo@tienda.com';
  contrasena = 'demo123';

  enviar() {
    this.error.set('');
    if (this.modoRegistro()) {
      this.auth.registro(this.nombre, this.correo, this.contrasena).subscribe({
        next: () => this.modoRegistro.set(false),
        error: (e) => this.error.set(e.error?.error ?? 'No se pudo registrar'),
      });
    } else {
      this.auth.login(this.correo, this.contrasena).subscribe({
        next: () => this.router.navigate(['/']),
        error: (e) => this.error.set(e.error?.error ?? 'Credenciales inválidas'),
      });
    }
  }
}
