import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive, Router, NavigationEnd } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatListModule } from '@angular/material/list';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { AuthService } from '../../services/auth';
import { ThemeService } from '../../services/theme.service';
import { ZiaChatComponent } from '../zia-chat/zia-chat';
import { filter, map } from 'rxjs/operators';
import { toSignal } from '@angular/core/rxjs-interop';

const PAGE_TITLES: Record<string, string> = {
  '/dashboard':                  'Dashboard',
  '/form':                       'Huella de Carbono',
  '/smart-intake':               'Smart Intake',
  '/live':                       'Zia Live',
  '/simulator':                  'Simulador',
  '/history':                    'Historial',
  '/admin/platform':             'Plataforma Global',
  '/admin/companies':            'Empresas',
  '/admin/my-company':           'Mi Empresa',
  '/admin/periods':              'Períodos',
  '/admin/operational-units':    'Unidades Operativas',
  '/admin/sectors':              'Sectores',
  '/admin/users':                'Usuarios',
  '/admin/metadata':             'Factores de Emisión',
  '/admin/units':                'Unidades de Medida',
  '/admin/scopes':               'Alcances',
  '/admin/audit':                'Auditoría',
  '/admin/iot-devices':          'Dispositivos IoT',
};

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatListModule,
    MatToolbarModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatTooltipModule,
    ZiaChatComponent,
  ],
  templateUrl: './dashboard.html',
  styleUrls: ['./dashboard.css']
})
export class DashboardComponent {
  public authService = inject(AuthService);
  public themeService = inject(ThemeService);
  private router = inject(Router);

  // SA-03: título dinámico de página
  pageTitle = toSignal(
    this.router.events.pipe(
      filter(e => e instanceof NavigationEnd),
      map(e => PAGE_TITLES[(e as NavigationEnd).urlAfterRedirects] || 'ZIA Carbon Control')
    ),
    { initialValue: PAGE_TITLES[this.router.url] || 'ZIA Carbon Control' }
  );

  toggleTheme() {
    this.themeService.toggleTheme();
  }

  logout() {
    this.authService.logout();
  }
}
