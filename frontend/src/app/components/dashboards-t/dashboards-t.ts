import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';

// TEMPORAL — pedido de Ricardo para presentación del 2026-07-25.
// Embeds públicos de ThingsBoard (sin auth, sin backend propio). Remover ruta,
// componente y link de sidebar ("Dashboards T") una vez pasada la presentación.
interface EmbeddedDashboard {
  title: string;
  icon: string;
  url: SafeResourceUrl;
}

@Component({
  selector: 'app-dashboards-t',
  standalone: true,
  imports: [CommonModule, MatCardModule, MatIconModule],
  template: `
    <div class="dashboards-t-container">
      <div class="header-section">
        <h1>Dashboards T</h1>
        <p>Visualización en tiempo real vía ThingsBoard (contenido temporal para demo).</p>
      </div>

      <div class="dashboard-grid">
        <div class="glass-card dashboard-card" *ngFor="let d of dashboards">
          <div class="card-header">
            <mat-icon>{{ d.icon }}</mat-icon>
            <span>{{ d.title }}</span>
          </div>
          <iframe [src]="d.url" width="100%" height="600" style="border:none;" loading="lazy"></iframe>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .dashboards-t-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
    .header-section { margin-bottom: 24px; }
    .header-section h1 { font-size: 28px; font-weight: 700; color: var(--prestige-primary); margin: 0; }
    .header-section p { color: var(--prestige-text-muted); margin-top: 6px; }

    .dashboard-grid { display: flex; flex-direction: column; gap: 24px; }

    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid var(--prestige-border);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
      border-radius: 16px;
      padding: 16px;
    }
    :host-context(.dark-theme) .glass-card {
      background: var(--prestige-card-bg);
      border-color: var(--prestige-border);
    }

    .card-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-weight: 600; color: var(--prestige-text); }
  `]
})
export class DashboardsTComponent {
  dashboards: EmbeddedDashboard[];

  constructor(private sanitizer: DomSanitizer) {
    // Asignado en el cuerpo del constructor (no como inicializador de campo):
    // con useDefineForClassFields los inicializadores de campo corren antes que
    // esta asignación de parámetro, y this.sanitizer llegaba undefined a trust().
    this.dashboards = [
      {
        title: 'Energía',
        icon: 'bolt',
        url: this.trust('https://thingsboard.meeldavlab.xyz/dashboard/d1e602a0-746f-11f1-910d-f136ed7a87e0?publicId=306ecd50-86b0-11f1-8769-27a77cf6584f')
      },
      {
        title: 'Imagen',
        icon: 'photo_camera',
        url: this.trust('https://thingsboard.meeldavlab.xyz/dashboard/ae5b2000-7496-11f1-910d-f136ed7a87e0?publicId=306ecd50-86b0-11f1-8769-27a77cf6584f')
      },
      {
        title: 'Peso',
        icon: 'scale',
        url: this.trust('https://thingsboard.meeldavlab.xyz/dashboard/a4fb30a0-74a4-11f1-910d-f136ed7a87e0?publicId=306ecd50-86b0-11f1-8769-27a77cf6584f')
      }
    ];
  }

  private trust(url: string): SafeResourceUrl {
    return this.sanitizer.bypassSecurityTrustResourceUrl(url);
  }
}
