import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatTabsModule } from '@angular/material/tabs';
import { AdminService } from '../../../services/admin.service';
import { CategoryDialog, FactorDialog, ConfirmDialog, FormulaDialog } from '../admin-dialogs';

@Component({
  selector: 'app-metadata-management',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatTabsModule
  ],
  template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Motor de Cálculo</h1>
          <p class="subtitle">Configura las categorías de emisión y actualiza los factores de conversión globales.</p>
        </div>
        <div class="header-actions">
           <button mat-flat-button class="btn-prestige" (click)="onCreateCategory()" *ngIf="activeTab === 0">
             <mat-icon>add</mat-icon> Nueva Categoría
           </button>
           <button mat-flat-button class="btn-prestige" (click)="onCreateFormula()" *ngIf="activeTab === 1">
             <mat-icon>functions</mat-icon> Nueva Fórmula
           </button>
        </div>
      </div>

      <mat-tab-group class="zia-tabs-premium" (selectedTabChange)="activeTab = $event.index">
        <mat-tab label="Categorías y Factores">
          <div class="tab-content-inner">
            <div class="filters-bar-prestige">
              <mat-form-field appearance="outline" class="search-field global-search">
                <mat-label>Búsqueda global de factores</mat-label>
                <input matInput (keyup)="applyGlobalFilter($event)" placeholder="Ej: Gas Natural o Energía" #input>
                <mat-icon matSuffix>search</mat-icon>
              </mat-form-field>
            </div>

            <div class="spinner-container-prestige" *ngIf="loading">
              <mat-spinner diameter="40"></mat-spinner>
              <p>Cargando factores de emisión...</p>
            </div>

            <div class="hierarchy-container" *ngIf="!loading">
              <div class="parent-group" *ngFor="let group of filteredGroups">
                <!-- Parent Header -->
                <div class="parent-header">
                  <div class="parent-title">
                    <div class="scope-dot" [style.background-color]="getScopeColor(group.scope_id || group.scope?.id)"></div>
                    <h2>{{group.name}}</h2>
                    <div class="parent-actions">
                      <button mat-icon-button class="small-action-btn" (click)="onEditCategory(group)" matTooltip="Editar Categoría Principal">
                        <mat-icon>edit</mat-icon>
                      </button>
                      <button mat-icon-button class="small-action-btn warn" (click)="onDeleteCategory(group)" matTooltip="Eliminar Categoría Principal">
                        <mat-icon>delete_outline</mat-icon>
                      </button>
                    </div>
                  </div>
                  <div class="parent-stats">
                    {{ group.children?.length || 0 }} Subcategorías
                  </div>
                </div>

                <div class="category-grid">
                  <!-- Parent's own factors (if any) -->
                  <div class="glass-card category-premium-card" *ngIf="group.factors?.length > 0">
                    <div class="cat-brand-header">
                        <div class="cat-ident">
                            <div class="cat-text">
                              <h3>{{group.name}} (Principal)</h3>
                              <span class="cat-meta">{{group.factors.length}} Factores vinculados</span>
                            </div>
                        </div>
                        <div class="cat-actions">
                            <button mat-icon-button class="cat-action-btn" matTooltip="Agregar Factor" (click)="onCreateFactor(group)">
                                <mat-icon>add_circle</mat-icon>
                            </button>
                        </div>
                    </div>
                    <!-- Table for parent factors -->
                    <div class="table-container">
                      <table mat-table [dataSource]="group.dataSource" class="factors-premium-table">
                        <ng-container matColumnDef="name">
                          <th mat-header-cell *matHeaderCellDef>Elemento / Actividad</th>
                          <td mat-cell *matCellDef="let f" class="factor-name-td">
                            <div class="name-with-formula">
                              {{f.name}}
                              <span class="formula-tag-mini" *ngIf="f.calculation_formula_id">
                                <mat-icon>bolt</mat-icon> 
                                {{ getFormulaName(f.calculation_formula_id) }}
                              </span>
                            </div>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="unit">
                          <th mat-header-cell *matHeaderCellDef>Unidad</th>
                          <td mat-cell *matCellDef="let f">
                            <span class="unit-tag">{{f.unit?.symbol || f.unit?.name || 'N/A'}}</span>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="factor">
                          <th mat-header-cell *matHeaderCellDef>Factores de Gas (kg/u)</th>
                          <td mat-cell *matCellDef="let f" class="factor-value-td">
                            <div class="gases-breakdown" [matTooltip]="'CO2: ' + f.factor_co2 + ' | CH4: ' + f.factor_ch4 + ' | N2O: ' + f.factor_n2o + ' | NF3: ' + f.factor_nf3 + ' | SF6: ' + f.factor_sf6">
                              <span class="gas">CO₂: {{f.factor_co2}}</span>
                              <span class="gas-sep">|</span>
                              <span class="gas">CH₄: {{f.factor_ch4}}</span>
                              <span class="gas-sep">|</span>
                              <span class="gas">N₂O: {{f.factor_n2o}}</span>
                            </div>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="actions">
                          <th mat-header-cell *matHeaderCellDef>Acciones</th>
                          <td mat-cell *matCellDef="let f">
                            <div class="factor-actions">
                              <button mat-icon-button class="small-action-btn" (click)="onEditFactor(f)" matTooltip="Editar"><mat-icon>edit</mat-icon></button>
                              <button mat-icon-button class="small-action-btn warn" (click)="onDeleteFactor(f)" matTooltip="Eliminar"><mat-icon>delete_outline</mat-icon></button>
                            </div>
                          </td>
                        </ng-container>

                        <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                        <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="factor-row"></tr>
                      </table>
                      <div class="empty-factors-msg" *ngIf="(group.factors || []).length === 0">
                         No hay factores vinculados a esta categoría.
                      </div>
                    </div>
                  </div>

                  <!-- Children Category Cards -->
                  <div class="glass-card category-premium-card" *ngFor="let cat of group.children">
                    <div class="cat-brand-header">
                        <div class="cat-ident">
                            <div class="cat-text">
                              <h3>{{cat.name}}</h3>
                              <span class="cat-meta">{{(cat.factors || []).length}} Factores vinculados</span>
                            </div>
                        </div>
                        <div class="cat-actions">
                            <button mat-icon-button class="cat-action-btn" matTooltip="Editar Subcategoría" (click)="onEditCategory(cat)">
                                <mat-icon>edit</mat-icon>
                            </button>
                            <button mat-icon-button class="cat-action-btn" matTooltip="Eliminar Subcategoría" (click)="onDeleteCategory(cat)">
                                <mat-icon>delete_outline</mat-icon>
                            </button>
                            <button mat-icon-button class="cat-action-btn" matTooltip="Agregar Factor" (click)="onCreateFactor(cat)">
                                <mat-icon>add_circle</mat-icon>
                            </button>
                        </div>
                    </div>
                    <!-- Table for child factors -->
                    <div class="table-container">
                      <table mat-table [dataSource]="cat.dataSource" class="factors-premium-table">
                        <ng-container matColumnDef="name">
                          <th mat-header-cell *matHeaderCellDef>Elemento / Actividad</th>
                          <td mat-cell *matCellDef="let f" class="factor-name-td">
                            <div class="name-with-formula">
                              {{f.name}}
                              <span class="formula-tag-mini" *ngIf="f.calculation_formula_id">
                                <mat-icon>bolt</mat-icon> 
                                {{ getFormulaName(f.calculation_formula_id) }}
                              </span>
                            </div>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="unit">
                          <th mat-header-cell *matHeaderCellDef>Unidad</th>
                          <td mat-cell *matCellDef="let f">
                            <span class="unit-tag">{{f.unit?.symbol || f.unit?.name || 'N/A'}}</span>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="factor">
                          <th mat-header-cell *matHeaderCellDef>Factores de Gas (kg/u)</th>
                          <td mat-cell *matCellDef="let f" class="factor-value-td">
                            <div class="gases-breakdown" [matTooltip]="'CO2: ' + f.factor_co2 + ' | CH4: ' + f.factor_ch4 + ' | N2O: ' + f.factor_n2o + ' | NF3: ' + f.factor_nf3 + ' | SF6: ' + f.factor_sf6">
                              <span class="gas">CO₂: {{f.factor_co2}}</span>
                              <span class="gas-sep">|</span>
                              <span class="gas">CH₄: {{f.factor_ch4}}</span>
                              <span class="gas-sep">|</span>
                              <span class="gas">N₂O: {{f.factor_n2o}}</span>
                            </div>
                          </td>
                        </ng-container>

                        <ng-container matColumnDef="actions">
                          <th mat-header-cell *matHeaderCellDef>Acciones</th>
                          <td mat-cell *matCellDef="let f">
                            <div class="factor-actions">
                              <button mat-icon-button class="small-action-btn" (click)="onEditFactor(f)" matTooltip="Editar"><mat-icon>edit</mat-icon></button>
                              <button mat-icon-button class="small-action-btn warn" (click)="onDeleteFactor(f)" matTooltip="Eliminar"><mat-icon>delete_outline</mat-icon></button>
                            </div>
                          </td>
                        </ng-container>

                        <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                        <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="factor-row"></tr>
                      </table>
                      <div class="empty-factors-msg" *ngIf="(cat.factors || []).length === 0">
                         No hay factores vinculados a esta categoría.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Orphan categories (no parent) - shouldn't happen with current seeder but for robustness -->
              <div class="parent-group" *ngIf="orphanCategories.length > 0">
                 <div class="parent-header">
                   <div class="parent-title">
                     <div class="scope-dot" style="background-color: #64748b"></div>
                     <h2>Otras Categorías</h2>
                   </div>
                 </div>
                 <div class="category-grid">
                    <div class="glass-card category-premium-card" *ngFor="let cat of orphanCategories">
                      <div class="cat-brand-header">
                          <div class="cat-ident">
                              <div class="cat-text">
                                <h3>{{cat.name}}</h3>
                                <span class="cat-meta">{{(cat.factors || []).length}} Factores vinculados</span>
                              </div>
                          </div>
                          <div class="cat-actions">
                              <button mat-icon-button class="cat-action-btn" matTooltip="Editar Categoría" (click)="onEditCategory(cat)">
                                  <mat-icon>edit</mat-icon>
                              </button>
                              <button mat-icon-button class="cat-action-btn" matTooltip="Eliminar Categoría" (click)="onDeleteCategory(cat)">
                                  <mat-icon>delete_outline</mat-icon>
                              </button>
                              <button mat-icon-button class="cat-action-btn" matTooltip="Agregar Factor" (click)="onCreateFactor(cat)">
                                  <mat-icon>add_circle</mat-icon>
                              </button>
                          </div>
                      </div>
                      <div class="table-container">
                        <table mat-table [dataSource]="cat.dataSource" class="factors-premium-table">
                          <ng-container matColumnDef="name">
                            <th mat-header-cell *matHeaderCellDef>Elemento / Actividad</th>
                            <td mat-cell *matCellDef="let f" class="factor-name-td">
                              <div class="name-with-formula">
                                {{f.name}}
                                <span class="formula-tag-mini" *ngIf="f.calculation_formula_id">
                                  <mat-icon>bolt</mat-icon> 
                                  {{ getFormulaName(f.calculation_formula_id) }}
                                </span>
                              </div>
                            </td>
                          </ng-container>

                          <ng-container matColumnDef="unit">
                            <th mat-header-cell *matHeaderCellDef>Unidad</th>
                            <td mat-cell *matCellDef="let f">
                              <span class="unit-tag">{{f.unit?.symbol || f.unit?.name || 'N/A'}}</span>
                            </td>
                          </ng-container>

                          <ng-container matColumnDef="factor">
                            <th mat-header-cell *matHeaderCellDef>Factores de Gas (kg/u)</th>
                            <td mat-cell *matCellDef="let f" class="factor-value-td">
                              <div class="gases-breakdown" [matTooltip]="'CO2: ' + f.factor_co2 + ' | CH4: ' + f.factor_ch4 + ' | N2O: ' + f.factor_n2o + ' | NF3: ' + f.factor_nf3 + ' | SF6: ' + f.factor_sf6">
                                <span class="gas">CO₂: {{f.factor_co2}}</span>
                                <span class="gas-sep">|</span>
                                <span class="gas">CH₄: {{f.factor_ch4}}</span>
                                <span class="gas-sep">|</span>
                                <span class="gas">N₂O: {{f.factor_n2o}}</span>
                              </div>
                            </td>
                          </ng-container>

                          <ng-container matColumnDef="actions">
                            <th mat-header-cell *matHeaderCellDef>Acciones</th>
                            <td mat-cell *matCellDef="let f">
                              <div class="factor-actions">
                                <button mat-icon-button class="small-action-btn" (click)="onEditFactor(f)" matTooltip="Editar"><mat-icon>edit</mat-icon></button>
                                <button mat-icon-button class="small-action-btn warn" (click)="onDeleteFactor(f)" matTooltip="Eliminar"><mat-icon>delete_outline</mat-icon></button>
                              </div>
                            </td>
                          </ng-container>

                          <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                          <tr mat-row *matRowDef="let row; columns: displayedColumns;" class="factor-row"></tr>
                        </table>
                        <div class="empty-factors-msg" *ngIf="(cat.factors || []).length === 0">
                           No hay factores vinculados a esta categoría.
                        </div>
                      </div>
                    </div>
                 </div>
              </div>
            </div>
          </div>
        </mat-tab>

        <mat-tab label="Lógica de Fórmulas">
          <div class="tab-content-inner">
            <div class="glass-card formulas-table-card">
              <table mat-table [dataSource]="formulasDataSource" class="formulas-premium-table">
                <ng-container matColumnDef="name">
                  <th mat-header-cell *matHeaderCellDef>Nombre de la Fórmula</th>
                  <td mat-cell *matCellDef="let formula">
                    <div class="formula-name-cell">
                      <mat-icon class="f-icon">terminal</mat-icon>
                      <span>{{formula.name}}</span>
                    </div>
                  </td>
                </ng-container>

                <ng-container matColumnDef="expression">
                  <th mat-header-cell *matHeaderCellDef>Expresión Matemática</th>
                  <td mat-cell *matCellDef="let formula">
                    <code class="formula-code">{{formula.expression}}</code>
                  </td>
                </ng-container>

                <ng-container matColumnDef="description">
                  <th mat-header-cell *matHeaderCellDef>Descripción</th>
                  <td mat-cell *matCellDef="let formula" class="text-muted small">
                    {{formula.description || 'Sin descripción'}}
                  </td>
                </ng-container>

                <ng-container matColumnDef="actions">
                  <th mat-header-cell *matHeaderCellDef>Acciones</th>
                  <td mat-cell *matCellDef="let formula">
                    <div class="factor-actions">
                      <button mat-icon-button class="small-action-btn" (click)="onEditFormula(formula)" matTooltip="Editar"><mat-icon>edit</mat-icon></button>
                      <button mat-icon-button class="small-action-btn warn" (click)="onDeleteFormula(formula)" matTooltip="Eliminar"><mat-icon>delete_outline</mat-icon></button>
                    </div>
                  </td>
                </ng-container>

                <tr mat-header-row *matHeaderRowDef="['name', 'expression', 'description', 'actions']"></tr>
                <tr mat-row *matRowDef="let row; columns: ['name', 'expression', 'description', 'actions'];"></tr>
              </table>
              
              <div class="empty-state" *ngIf="formulas.length === 0">
                <mat-icon>functions</mat-icon>
                <p>No hay fórmulas personalizadas registradas.</p>
                <button mat-stroked-button (click)="onCreateFormula()">Crear Primera Fórmula</button>
              </div>
            </div>
          </div>
        </mat-tab>
      </mat-tab-group>
    </div>
  `,
  styles: [`
    .management-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
    
    .header-section { 
      display: flex; justify-content: space-between; align-items: flex-end; 
      margin-bottom: 24px; gap: 20px;
    }
    .title-group h1 { 
      font-size: 28px; font-weight: 600; color: var(--prestige-primary); 
      margin: 0 0 4px 0; letter-spacing: -0.02em;
    }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }

    .header-actions { display: flex; gap: 8px; }
    .btn-prestige { 
      background: var(--prestige-primary); color: white; padding: 0 20px; 
      border-radius: 10px; font-weight: 500; height: 42px; font-size: 14px;
    }

    .tab-content-inner { padding: 24px 0; }
    
    .filters-bar-prestige { margin-bottom: 24px; }
    .global-search { width: 100%; max-width: 400px; font-size: 13px; }

    .parent-group { margin-bottom: 40px; }
    .parent-header { 
      display: flex; justify-content: space-between; align-items: center; 
      padding: 0 8px 12px 8px; border-bottom: 1px solid var(--prestige-border); margin-bottom: 20px;
    }
    .parent-title { display: flex; align-items: center; gap: 12px; }
    .parent-title h2 { margin: 0; font-size: 18px; font-weight: 700; color: var(--prestige-primary); text-transform: uppercase; letter-spacing: 0.05em; margin-right: 8px; }
    .parent-actions { display: flex; gap: 4px; }
    .scope-dot { width: 12px; height: 12px; border-radius: 50%; }
    .parent-stats { font-size: 12px; color: var(--prestige-text-muted); font-weight: 500; }

    .category-grid { display: flex; flex-direction: column; gap: 24px; }
    .category-premium-card { padding: 0; overflow: hidden; border: 1px solid var(--prestige-border); }
    
    .cat-brand-header { 
      padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;
      background: var(--table-header-bg); border-bottom: 1px solid var(--prestige-border);
    }
    .cat-ident { display: flex; align-items: center; gap: 16px; }
    .cat-text h3 { margin: 0; font-size: 16px; font-weight: 600; color: var(--prestige-text); }
    .cat-meta { font-size: 11px; color: var(--prestige-text-muted); font-weight: 500; }
    .cat-action-btn { color: var(--prestige-primary); width: 40px; height: 40px; }

    .empty-factors-msg { padding: 24px; text-align: center; color: var(--prestige-text-muted); font-size: 13px; font-style: italic; }

    .factors-premium-table, .formulas-premium-table { width: 100%; background: transparent; }
    .table-container { width: 100%; overflow-x: auto; }

    .factor-row, .formulas-premium-table tr { height: 60px; }
    .factor-row:hover, .formulas-premium-table tr:hover { background: var(--row-hover-bg) !important; }
    
    .factor-name-td, .formula-name-cell { font-weight: 500; color: var(--prestige-text); font-size: 13px; }
    .formula-name-cell { display: flex; align-items: center; gap: 12px; }
    .f-icon { color: var(--prestige-primary); font-size: 18px; width: 18px; height: 18px; }

    .name-with-formula { display: flex; flex-direction: column; gap: 4px; }
    .formula-tag-mini { display: flex; align-items: center; gap: 4px; font-size: 10px; color: var(--prestige-primary); font-weight: 600; }
    .formula-tag-mini mat-icon { font-size: 12px; width: 12px; height: 12px; }

    .unit-tag { 
      background: var(--status-neutral-bg) !important; color: var(--status-neutral-text) !important; padding: 2px 8px; 
      border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid var(--prestige-border);
    }
    .factor-value-td { font-family: 'Monaco', monospace; }
    .formula-code { font-family: 'Monaco', monospace; font-size: 12px; background: rgba(0,0,0,0.04); padding: 4px 8px; border-radius: 4px; color: var(--prestige-primary); }
    
    .gases-breakdown { display: flex; gap: 8px; font-size: 11px; color: var(--prestige-text-muted); }
    .gas-sep { color: var(--prestige-border); }

    .factor-actions { display: flex; gap: 2px; }
    .small-action-btn { width: 32px; height: 32px; line-height: 32px; color: var(--prestige-text-muted); }
    .small-action-btn:hover { color: var(--prestige-primary); background: rgba(0,0,0,0.03); }
    .small-action-btn.warn:hover { color: #dc2626; background: #fff1f2; }

    .spinner-container-prestige { padding: 60px; text-align: center; color: var(--prestige-text-muted); font-size: 14px; }
    
    .empty-state { text-align: center; padding: 60px 20px; color: var(--prestige-text-muted); }
    .empty-state mat-icon { font-size: 48px; width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.2; }
    .empty-state p { margin-bottom: 24px; }

    @media (max-width: 768px) {
      .management-page { padding: 16px; }
      .header-section { flex-direction: column; align-items: flex-start; gap: 16px; }
      .global-search { max-width: 100%; }
    }
  `]
})
export class MetadataManagementComponent implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);
  private dialog = inject(MatDialog);

  activeTab = 0;
  categories: any[] = [];
  formulas: any[] = [];
  filteredGroups: any[] = [];
  orphanCategories: any[] = [];
  formulasDataSource = new MatTableDataSource<any>([]);

  displayedColumns = ['name', 'unit', 'factor', 'actions'];
  loading = true;

  scopes: any[] = [];
  units: any[] = [];

  ngOnInit() {
    this.loadData();
    this.loadOptions();
  }

  loadOptions() {
    this.adminService.getScopes().subscribe(data => this.scopes = data || []);
    this.adminService.getUnits().subscribe(data => this.units = data || []);
  }

  loadData() {
    this.loading = true;
    this.adminService.getFormulas().subscribe({
      next: (fData) => {
        this.formulas = fData || [];
        this.formulasDataSource.data = this.formulas;
        this.loadMetadata();
      },
      error: () => this.loadMetadata()
    });
  }

  loadMetadata() {
    this.adminService.getCategories().subscribe({
      next: (data) => {
        this.categories = (data || []).map(cat => ({
          ...cat,
          dataSource: new MatTableDataSource(cat.factors || [])
        }));
        this.rebuildHierarchy(this.categories);
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('[Metadata] Error:', err);
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  rebuildHierarchy(allCats: any[], filter: string = '') {
    const val = filter.toLowerCase();

    // 1. Identify Parents (no parent_id)
    const parents = allCats.filter(c => !c.parent_id);
    const children = allCats.filter(c => c.parent_id);

    this.filteredGroups = parents.map(p => {
      let matchingChildren = children.filter(c => c.parent_id === p.id);

      if (val) {
        matchingChildren = matchingChildren.filter(c =>
          c.name.toLowerCase().includes(val) ||
          (c.factors || []).some((f: any) => f.name.toLowerCase().includes(val))
        );
      }

      const parentMatch = p.name.toLowerCase().includes(val) ||
        (p.factors || []).some((f: any) => f.name.toLowerCase().includes(val));

      if (val && !parentMatch && matchingChildren.length === 0) return null;

      return { ...p, children: matchingChildren };
    }).filter(p => p !== null);

    // 2. Orphan categories (parents that are actually children but parent is missing)
    this.orphanCategories = children.filter(c => !parents.some(p => p.id === c.parent_id));

    // If filtering, also filter orphans
    if (val) {
      this.orphanCategories = this.orphanCategories.filter(c =>
        c.name.toLowerCase().includes(val) ||
        (c.factors || []).some((f: any) => f.name.toLowerCase().includes(val))
      );
    }
  }

  getFormulaName(id: number): string {
    return this.formulas.find(f => f.id === id)?.name || 'Fórmula';
  }

  onCreateFormula() {
    const dialogRef = this.dialog.open(FormulaDialog, { data: {} });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createFormula(result).subscribe(() => this.loadData());
      }
    });
  }

  onEditFormula(formula: any) {
    const dialogRef = this.dialog.open(FormulaDialog, { data: { ...formula } });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateFormula(formula.id, result).subscribe(() => this.loadData());
      }
    });
  }

  onDeleteFormula(formula: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Eliminar Fórmula',
        message: `¿Estás seguro de que deseas eliminar "${formula.name}"? Los factores asociados volverán al cálculo estándar.`,
        confirmText: 'Eliminar',
        color: 'warn'
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteFormula(formula.id).subscribe(() => this.loadData());
      }
    });
  }

  onCreateCategory() {
    const dialogRef = this.dialog.open(CategoryDialog, {
      width: '400px',
      data: { scopes: this.scopes, categories: this.categories }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createCategory(result).subscribe(() => this.loadMetadata());
      }
    });
  }

  onEditCategory(category: any) {
    const dialogRef = this.dialog.open(CategoryDialog, {
      width: '400px',
      data: { category: { ...category }, scopes: this.scopes, categories: this.categories }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateCategory(category.id, result).subscribe(() => this.loadMetadata());
      }
    });
  }

  onDeleteCategory(category: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Eliminar Categoría',
        message: `¿Estás seguro de que deseas eliminar la categoría "${category.name}"? Los factores y subcategorías asociados podrían verse afectados.`,
        confirmText: 'Eliminar',
        color: 'warn'
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteCategory(category.id).subscribe(() => this.loadMetadata());
      }
    });
  }

  onCreateFactor(category: any) {
    const dialogRef = this.dialog.open(FactorDialog, {
      data: { factor: {}, formulas: this.formulas, units: this.units }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.createFactor({ ...result, emission_category_id: category.id }).subscribe(() => this.loadMetadata());
      }
    });
  }

  onEditFactor(factor: any) {
    const dialogRef = this.dialog.open(FactorDialog, {
      data: { factor: { ...factor }, formulas: this.formulas, units: this.units }
    });
    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.updateFactor(factor.id, result).subscribe(() => this.loadMetadata());
      }
    });
  }

  onDeleteFactor(factor: any) {
    const dialogRef = this.dialog.open(ConfirmDialog, {
      data: {
        title: 'Desactivar Factor',
        message: `¿Estás seguro de que deseas desactivar el factor "${factor.name}"? Esta acción no se puede deshacer.`,
        confirmText: 'Desactivar',
        color: 'warn'
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.adminService.deleteFactor(factor.id).subscribe(() => this.loadMetadata());
      }
    });
  }

  applyGlobalFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.rebuildHierarchy(this.categories, filterValue);
  }

  getScopeColor(id?: number): string {
    switch (id) {
      case 1: return '#1a237e';
      case 2: return '#00897b';
      case 3: return '#f59e0b';
      default: return '#64748b';
    }
  }
}
