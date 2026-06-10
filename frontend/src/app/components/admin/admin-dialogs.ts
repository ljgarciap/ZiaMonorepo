import { Component, Inject, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormGroup, FormBuilder, Validators } from '@angular/forms';
import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatOptionModule } from '@angular/material/core';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { AdminService } from '../../services/admin.service';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-company-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatOptionModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.company?.id ? 'Editar Empresa' : 'Nueva Empresa' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" (ngSubmit)="onSave()" class="zia-form-compact">
          <mat-form-field appearance="outline">
            <mat-label>Nombre de la Empresa</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Zia Corp">
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>NIT</mat-label>
            <input matInput formControlName="nit" placeholder="Ej: 900.123.456-1">
          </mat-form-field>

          <mat-form-field appearance="outline">
            <mat-label>Sector</mat-label>
            <mat-select formControlName="company_sector_id">
              <mat-option *ngFor="let s of data.sectors" [value]="s.id">{{s.name}}</mat-option>
              <mat-option *ngIf="!data.sectors.length" disabled>No hay sectores disponibles</mat-option>
            </mat-select>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.company?.id ? 'Actualizar' : 'Crear Empresa' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class CompanyDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<CompanyDialog>,
    @Inject(MAT_DIALOG_DATA) public data: { company: any, sectors: any[] }
  ) {
    this.form = this.fb.group({
      name: [data.company?.name || '', Validators.required],
      nit: [data.company?.nit || '', Validators.required],
      company_sector_id: [data.company?.company_sector_id || data.company?.sector_id || null]
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-sector-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.id ? 'Editar Sector' : 'Nuevo Sector' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <mat-form-field appearance="outline">
            <mat-label>Nombre del Sector</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Industrial">
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>Descripción</mat-label>
            <textarea matInput formControlName="description" placeholder="Opcional..."></textarea>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.id ? 'Guardar Cambios' : 'Crear Sector' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class SectorDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<SectorDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.form = this.fb.group({
      name: [data.name || '', Validators.required],
      description: [data.description || '']
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-user-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatOptionModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.id ? 'Editar Perfil' : 'Invitar Usuario' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <div class="zia-form-grid">
            <mat-form-field appearance="outline">
              <mat-label>Nombre Completo</mat-label>
              <input matInput formControlName="name">
            </mat-form-field>
            
            <mat-form-field appearance="outline">
              <mat-label>Correo Electrónico</mat-label>
              <input matInput formControlName="email">
            </mat-form-field>
          </div>

            <mat-form-field appearance="outline">
            <mat-label>Rol / Nivel de Acceso</mat-label>
            <mat-select formControlName="role">
              <mat-option value="user">Usuario</mat-option>
              <mat-option value="admin" *ngIf="['superadmin'].includes(currentUserRole)">Administrador</mat-option>
              <mat-option value="superadmin" *ngIf="['superadmin'].includes(currentUserRole)">Super Admin</mat-option>
            </mat-select>
          </mat-form-field>

          <mat-form-field appearance="outline" *ngIf="form.get('role')?.value === 'user'">
            <mat-label>Empresas Asociadas</mat-label>
            <mat-select formControlName="companies" multiple>
              <mat-option *ngFor="let c of data.allCompanies" [value]="c.id">{{c.name}}</mat-option>
            </mat-select>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.id ? 'Guardar Cambios' : 'Enviar Invitación' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class UserDialog {
  form: FormGroup;
  currentUserRole: string = 'user';

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<UserDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private authService: AuthService
  ) {
    const context = authService.currentContext();
    this.currentUserRole = context?.role || authService.currentUser()?.role || 'user';

    const userCompanyIds = data.companies?.map((c: any) => c.id) || [];

    this.form = this.fb.group({
      name: [data.name || '', Validators.required],
      email: [data.email || '', [Validators.required, Validators.email]],
      role: [data.role || 'user', Validators.required],
      companies: [userCompanyIds]
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-factor-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatOptionModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.factor?.id ? 'Ajustar Factor' : 'Nuevo Factor' }}</h2>
      <mat-dialog-content style="min-width: 500px">
        <form [formGroup]="form" class="zia-form-compact">
          <div class="zia-form-grid">
            <mat-form-field appearance="outline">
              <mat-label>Nombre del Elemento</mat-label>
              <input matInput formControlName="name">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Unidad</mat-label>
              <mat-select formControlName="measurement_unit_id">
                <mat-option *ngFor="let u of data.units" [value]="u.id">
                  {{u.name}} ({{u.symbol}})
                </mat-option>
              </mat-select>
            </mat-form-field>
          </div>
          
          <div class="subtitle-premium">Factores de Emisión (kg Gas / Unidad)</div>
          <div class="zia-form-grid-3">
            <mat-form-field appearance="outline">
              <mat-label>CO2</mat-label>
              <input matInput type="number" formControlName="factor_co2">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>CH4</mat-label>
              <input matInput type="number" formControlName="factor_ch4">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>N2O</mat-label>
              <input matInput type="number" formControlName="factor_n2o">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>NF3</mat-label>
              <input matInput type="number" formControlName="factor_nf3">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>SF6</mat-label>
              <input matInput type="number" formControlName="factor_sf6">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Incertidumbre (%)</mat-label>
              <input matInput type="number" formControlName="uncertainty_upper">
            </mat-form-field>
          </div>

          <mat-form-field appearance="outline" class="full-width">
            <mat-label>Fórmula de Cálculo Especial</mat-label>
            <mat-select formControlName="calculation_formula_id">
              <mat-option [value]="null">Cálculo Estándar (Sumatoria gases)</mat-option>
              <mat-option *ngFor="let formula of data.formulas" [value]="formula.id">
                {{formula.name}}
              </mat-option>
            </mat-select>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.factor?.id ? 'Actualizar Factor' : 'Crear Factor' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class FactorDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<FactorDialog>,
    @Inject(MAT_DIALOG_DATA) public data: { factor: any, formulas: any[], units: any[] }
  ) {
    const f = data.factor || {};
    this.form = this.fb.group({
      name: [f.name || '', Validators.required],
      measurement_unit_id: [f.measurement_unit_id || f.unit?.id || null, Validators.required],
      factor_co2: [f.factor_co2 || 0, [Validators.required, Validators.min(0)]],
      factor_ch4: [f.factor_ch4 || 0, [Validators.required, Validators.min(0)]],
      factor_n2o: [f.factor_n2o || 0, [Validators.required, Validators.min(0)]],
      factor_nf3: [f.factor_nf3 || 0, [Validators.required, Validators.min(0)]],
      factor_sf6: [f.factor_sf6 || 0, [Validators.required, Validators.min(0)]],
      uncertainty_upper: [f.uncertainty_upper || 0, [Validators.required, Validators.min(0)]],
      calculation_formula_id: [f.calculation_formula_id || null]
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-formula-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.id ? 'Editar Fórmula' : 'Nueva Fórmula' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <mat-form-field appearance="outline">
            <mat-label>Nombre de la Lógica</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Combustión Estándar">
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>Expresión Matemática</mat-label>
            <textarea matInput formControlName="expression" placeholder="Ej: (activity_data * factor_co2) / 1000" rows="3"></textarea>
            <mat-hint>Variables: activity_data, factor_co2, factor_ch4, factor_n2o, factor_nf3, factor_sf6, gwp_...</mat-hint>
          </mat-form-field>

          <mat-form-field appearance="outline">
            <mat-label>Descripción / Notas</mat-label>
            <textarea matInput formControlName="description" placeholder="Referencia técnica..."></textarea>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.id ? 'Actualizar' : 'Guardar Fórmula' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class FormulaDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<FormulaDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.form = this.fb.group({
      name: [data.name || '', Validators.required],
      expression: [data.expression || '', Validators.required],
      description: [data.description || '']
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}
@Component({
  selector: 'app-category-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatOptionModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.category?.id ? 'Editar Categoría' : 'Nueva Categoría' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <mat-form-field appearance="outline">
            <mat-label>Nombre de la Categoría</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Combustibles Fósiles">
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>Alcance (Scope)</mat-label>
            <mat-select formControlName="scope_id">
              <mat-select-trigger>
                {{ selectedScope?.name }}
              </mat-select-trigger>
              <mat-option *ngFor="let s of data.scopes" [value]="s.id">
                <div style="line-height: 1.3; padding: 4px 0;">
                  <div style="font-weight: 500; font-size: 14px;">{{s.name}}</div>
                  <div style="font-size: 11px; color: #64748b; white-space: normal;">{{s.description}}</div>
                </div>
              </mat-option>
            </mat-select>
          </mat-form-field>

          <mat-form-field appearance="outline">
            <mat-label>Categoría Padre (Para sub-agrupación)</mat-label>
            <mat-select formControlName="parent_id">
              <mat-option [value]="null">-- Ninguna (Categoría Principal) --</mat-option>
              <mat-option *ngFor="let cat of filteredCategories" [value]="cat.id">
                {{cat.name}}
              </mat-option>
            </mat-select>
            <mat-hint>Si se selecciona, esta categoría aparecerá dentro de la principal.</mat-hint>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.category?.id ? 'Guardar Cambios' : 'Crear Categoría' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class CategoryDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<CategoryDialog>,
    @Inject(MAT_DIALOG_DATA) public data: { category?: any, scopes: any[], categories: any[] }
  ) {
    const cat = data.category || {};
    this.form = this.fb.group({
      name: [cat.name || '', Validators.required],
      scope_id: [cat.scope_id || cat.scope?.id || data.scopes?.[0]?.id || 1, Validators.required],
      parent_id: [cat.parent_id || null]
    });
  }

  get filteredCategories() {
    // Avoid circular reference by excluding current category from the list if editing
    return (this.data.categories || []).filter(c => c.id !== this.data.category?.id && !c.parent_id);
  }

  get selectedScope() {
    const id = this.form.get('scope_id')?.value;
    return this.data.scopes?.find((s: any) => s.id === id);
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule, MatDialogModule, MatButtonModule],
  template: `
    <div class="zia-dialog-premium confirmation">
      <h2 mat-dialog-title>{{data.title || '¿Estás seguro?'}}</h2>
      <mat-dialog-content>
        <p>{{data.message}}</p>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">{{data.cancelText || 'Cancelar'}}</button>
        <button mat-flat-button [color]="data.color || 'warn'" (click)="onConfirm()">
          {{data.confirmText || 'Confirmar'}}
        </button>
      </mat-dialog-actions>
    </div>
  `,
  styles: [`
    .confirmation p { margin: 0; color: var(--prestige-text-muted); font-size: 14px; line-height: 1.5; }
  `]
})
export class ConfirmDialog {
  constructor(
    public dialogRef: MatDialogRef<ConfirmDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) { }

  onConfirm() {
    this.dialogRef.close(true);
  }

  onCancel() {
    this.dialogRef.close(false);
  }
}

@Component({
  selector: 'app-unit-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.id ? 'Editar Unidad' : 'Nueva Unidad' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <mat-form-field appearance="outline">
            <mat-label>Nombre</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Kilogramos">
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>Símbolo</mat-label>
            <input matInput formControlName="symbol" placeholder="Ej: kg">
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.id ? 'Guardar Cambios' : 'Crear Unidad' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class UnitDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<UnitDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.form = this.fb.group({
      name: [data.name || '', Validators.required],
      symbol: [data.symbol || '', Validators.required]
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-scope-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>{{ data.id ? 'Editar Alcance: ' + data.name : 'Nuevo Alcance' }}</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <mat-form-field appearance="outline" *ngIf="!data.id">
            <mat-label>Nombre del Alcance</mat-label>
            <input matInput formControlName="name" placeholder="Ej: Alcance 4 (Opcional)">
          </mat-form-field>

          <mat-form-field appearance="outline">
            <mat-label>Descripción Corta</mat-label>
            <textarea matInput formControlName="description" rows="2"></textarea>
          </mat-form-field>
          
          <mat-form-field appearance="outline">
            <mat-label>Documentación / Ayuda</mat-label>
            <textarea matInput formControlName="documentation_text" rows="5"></textarea>
            <mat-hint>Texto que se muestra en el acordeón del formulario</mat-hint>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          {{ data.id ? 'Guardar Cambios' : 'Crear Alcance' }}
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class ScopeDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<ScopeDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.form = this.fb.group({
      name: [data.name || '', data.id ? [] : Validators.required],
      description: [data.description || ''],
      documentation_text: [data.documentation_text || '']
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-period-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  template: `
    <div class="zia-dialog-premium">
      <h2 mat-dialog-title>Nuevo Periodo de Reporte</h2>
      <mat-dialog-content>
        <form [formGroup]="form" class="zia-form-compact">
          <p class="dialog-description" style="color: var(--prestige-text-muted); margin-bottom: 16px; font-size: 14px;">
            Ingresa el año fiscal para el cual esta empresa reportará su huella de carbono.
          </p>
          <mat-form-field appearance="outline" style="width: 100%;">
            <mat-label>Año</mat-label>
            <input matInput formControlName="year" type="number" placeholder="Ej: 2024">
            <mat-error *ngIf="form.get('year')?.hasError('required')">Requerido</mat-error>
            <mat-error *ngIf="form.get('year')?.hasError('min')">Año inválido</mat-error>
          </mat-form-field>
        </form>
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button (click)="onCancel()">Cancelar</button>
        <button mat-flat-button color="primary" [disabled]="form.invalid" (click)="onSave()">
          Crear Periodo
        </button>
      </mat-dialog-actions>
    </div>
  `
})
export class PeriodDialog {
  form: FormGroup;

  constructor(
    private fb: FormBuilder,
    public dialogRef: MatDialogRef<PeriodDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    const currentYear = new Date().getFullYear();
    this.form = this.fb.group({
      year: [currentYear, [Validators.required, Validators.min(2000), Validators.max(2100)]]
    });
  }

  onSave() {
    if (this.form.valid) {
      this.dialogRef.close(this.form.value);
    }
  }

  onCancel() {
    this.dialogRef.close();
  }
}

@Component({
  selector: 'app-user-companies-dialog',
  standalone: true,
  imports: [
    CommonModule, 
    MatDialogModule, 
    MatButtonModule, 
    MatIconModule, 
    MatListModule, 
    MatSelectModule, 
    MatOptionModule, 
    MatFormFieldModule, 
    FormsModule
  ],
  template: `
    <div class="zia-dialog-premium" style="min-width: 480px; padding: 12px 0;">
      <h2 mat-dialog-title style="margin: 0 0 16px 0; font-size: 20px; font-weight: 600;">Empresas Asociadas</h2>
      
      <mat-dialog-content style="overflow: visible;">
        <p class="dialog-description" style="color: var(--prestige-text-muted); margin: 0 0 16px 0; font-size: 14px; line-height: 1.4;">
          Empresas asignadas a <strong>{{ data.user.name }}</strong> ({{ data.user.email }}):
        </p>
        
        <div class="list-container">
          <mat-list *ngIf="userCompanies && userCompanies.length > 0; else noCompanies" style="padding: 0;">
            <mat-list-item *ngFor="let c of userCompanies; let last = last" style="height: auto; padding: 12px 16px; border-bottom: 1px solid var(--prestige-border);">
              <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <mat-icon style="color: var(--prestige-primary); font-size: 20px; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">business</mat-icon>
                  <div>
                    <div style="font-weight: 500; font-size: 14px; color: var(--prestige-text); line-height: 1.2;">{{ c.name }}</div>
                    <div style="font-size: 11px; color: var(--prestige-text-muted); margin-top: 2px;" *ngIf="c.nit">NIT: {{ c.nit }}</div>
                  </div>
                </div>
                <button mat-icon-button color="warn" (click)="onRemoveCompany(c.id)" matTooltip="Quitar asociación" [disabled]="actionLoading" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                  <mat-icon style="font-size: 18px; width: 18px; height: 18px;">delete_outline</mat-icon>
                </button>
              </div>
            </mat-list-item>
          </mat-list>
        </div>
        
        <ng-template #noCompanies>
          <div class="empty-state" style="padding: 40px 16px; text-align: center; color: var(--prestige-text-muted);">
            <mat-icon style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 12px; opacity: 0.6;">info_outline</mat-icon>
            <p style="margin: 0; font-size: 14px; font-weight: 400;">Este usuario no tiene empresas asociadas.</p>
          </div>
        </ng-template>

        <mat-divider style="margin: 24px 0; border-top-color: var(--prestige-border);"></mat-divider>

        <div class="association-panel">
          <h4 style="margin: 0 0 12px 0; font-size: 13.5px; font-weight: 600; color: var(--prestige-text);">Asociar Nueva Empresa</h4>
          
          <div class="association-row">
            <mat-form-field appearance="outline" subscriptSizing="dynamic" class="association-select">
              <mat-label>Selecciona una empresa</mat-label>
              <mat-select [(ngModel)]="selectedCompanyId" [disabled]="actionLoading">
                <mat-option *ngFor="let c of availableCompanies" [value]="c.id">
                  {{ c.name }}
                </mat-option>
                <mat-option *ngIf="availableCompanies.length === 0" disabled>
                  No hay más empresas disponibles
                </mat-option>
              </mat-select>
            </mat-form-field>
            
            <button mat-flat-button color="primary" class="btn-associate" [disabled]="!selectedCompanyId || actionLoading" (click)="onAddCompany()">
              <mat-icon style="margin-right: 4px;">add</mat-icon> Asociar
            </button>
          </div>
          
          <div *ngIf="actionLoading" class="loading-indicator">
            <div class="spinner-mini"></div>
            <span>Guardando cambios en el servidor...</span>
          </div>
        </div>
      </mat-dialog-content>
      
      <mat-dialog-actions align="end" style="padding: 16px 0 0 0; margin-top: 16px;">
        <button mat-flat-button color="primary" (click)="onClose()" [disabled]="actionLoading" style="height: 38px; padding: 0 24px; border-radius: 6px;">Cerrar</button>
      </mat-dialog-actions>
    </div>
    
    <style>
      .list-container {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid var(--prestige-border);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.02);
      }
      .association-panel {
        display: flex;
        flex-direction: column;
      }
      .association-row {
        display: flex;
        gap: 12px;
        align-items: center;
        width: 100%;
      }
      .association-select {
        flex: 1;
      }
      .btn-associate {
        height: 48px !important;
        border-radius: 8px !important;
        padding: 0 16px !important;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .loading-indicator {
        margin-top: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        color: var(--prestige-text-muted);
      }
      .spinner-mini {
        width: 14px;
        height: 14px;
        border: 2px solid var(--prestige-primary);
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 0.8s linear infinite;
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
    </style>
  `
})
export class UserCompaniesDialog implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);
  
  userCompanies: any[] = [];
  availableCompanies: any[] = [];
  selectedCompanyId: number | null = null;
  actionLoading = false;
  hasChanges = false;

  constructor(
    public dialogRef: MatDialogRef<UserCompaniesDialog>,
    @Inject(MAT_DIALOG_DATA) public data: { user: any }
  ) {
    this.userCompanies = [...(data.user.companies || [])];
  }

  ngOnInit() {
    this.loadAllCompanies();
  }

  loadAllCompanies() {
    this.adminService.getCompanies().subscribe({
      next: (companies) => {
        const list = companies || [];
        const associatedIds = new Set(this.userCompanies.map(c => c.id));
        this.availableCompanies = list.filter(c => !associatedIds.has(c.id));
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('[UserCompaniesDialog] Error loading companies:', err);
        this.cdr.detectChanges();
      }
    });
  }

  onAddCompany() {
    if (!this.selectedCompanyId) return;

    this.actionLoading = true;
    this.cdr.detectChanges();

    const companyIdToAdd = this.selectedCompanyId;
    const currentIds = this.userCompanies.map(c => c.id);
    const newIds = [...currentIds, companyIdToAdd];

    const updatePayload = {
      name: this.data.user.name,
      email: this.data.user.email,
      role: this.data.user.role,
      companies: newIds
    };

    this.adminService.updateUser(this.data.user.id, updatePayload).subscribe({
      next: (updatedUser) => {
        this.userCompanies = updatedUser.companies || [];
        this.selectedCompanyId = null;
        this.hasChanges = true;
        this.actionLoading = false;
        this.loadAllCompanies();
      },
      error: (err) => {
        console.error('[UserCompaniesDialog] Error adding company:', err);
        this.actionLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  onRemoveCompany(companyId: number) {
    this.actionLoading = true;
    this.cdr.detectChanges();

    const currentIds = this.userCompanies.map(c => c.id);
    const newIds = currentIds.filter(id => id !== companyId);

    const updatePayload = {
      name: this.data.user.name,
      email: this.data.user.email,
      role: this.data.user.role,
      companies: newIds
    };

    this.adminService.updateUser(this.data.user.id, updatePayload).subscribe({
      next: (updatedUser) => {
        this.userCompanies = updatedUser.companies || [];
        this.hasChanges = true;
        this.actionLoading = false;
        this.loadAllCompanies();
      },
      error: (err) => {
        console.error('[UserCompaniesDialog] Error removing company:', err);
        this.actionLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  onClose() {
    this.dialogRef.close(this.hasChanges);
  }
}
