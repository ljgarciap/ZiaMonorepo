import { Component, inject, ChangeDetectorRef, ViewChildren, QueryList, AfterViewInit, Injectable, ViewEncapsulation } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatIconModule } from '@angular/material/icon';
import { MatDividerModule } from '@angular/material/divider';
import { MatCardModule } from '@angular/material/card';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule, MatPaginatorIntl } from '@angular/material/paginator';
import { HttpClient } from '@angular/common/http';
import { MasterDataService } from '../../services/master-data.service';
import { ContextService } from '../../services/context.service';
import { AuthService } from '../../services/auth';

@Injectable()
export class CustomPaginatorIntl extends MatPaginatorIntl {
  override itemsPerPageLabel = 'Ítems por página:';
  override nextPageLabel = 'Siguiente página';
  override previousPageLabel = 'Página anterior';
  override firstPageLabel = 'Primera página';
  override lastPageLabel = 'Última página';

  override getRangeLabel = (page: number, pageSize: number, length: number) => {
    if (length === 0 || pageSize === 0) {
      return `0 de ${length}`;
    }
    length = Math.max(length, 0);
    const startIndex = page * pageSize;
    // If the start index exceeds the list length, do not try and fix the end index to the end.
    const endIndex = startIndex < length ? Math.min(startIndex + pageSize, length) : startIndex + pageSize;
    return `${startIndex + 1} - ${endIndex} de ${length}`;
  };
}

@Component({
  selector: 'app-form',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatExpansionModule,
    MatIconModule,
    MatDividerModule,
    MatCardModule,
    MatTableModule,
    MatPaginatorModule
  ],
  providers: [
    { provide: MatPaginatorIntl, useClass: CustomPaginatorIntl }
  ],
  templateUrl: './form.html',
  styleUrls: ['./form.css'],
  styles: [`
    /* --- FORCEFUL DARK MODE FIXES --- */

    /* 1. Container & Global Overrides */
    :host-context(.dark-theme) .staged-data-container {
        background: var(--prestige-card-bg) !important;
        border-color: var(--prestige-border) !important;
    }

    /* 2. Table Headers & Cells */
    :host-context(.dark-theme) th.mat-header-cell {
        background-color: var(--table-header-bg) !important;
        color: var(--prestige-text-muted) !important;
        border-bottom-color: var(--prestige-border) !important;
    }

    :host-context(.dark-theme) td.mat-cell {
        color: var(--prestige-text) !important;
        border-bottom-color: var(--prestige-border) !important;
    }

    /* 3. Paginator (The Nuclear Option) */
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator,
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator-container { 
        background: transparent !important; 
        background-color: transparent !important;
        color: var(--prestige-text-muted) !important; 
    }

    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator .mat-mdc-select-value { color: var(--prestige-text) !important; }
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator .mat-mdc-select-arrow { color: var(--prestige-text-muted) !important; }

    /* Button Colors */
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator .mat-mdc-icon-button { 
        color: var(--prestige-text) !important; 
    }
    
    /* Disabled logic: Reduce opacity but keep color visible */
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator .mat-mdc-icon-button[disabled] { 
        color: var(--prestige-text) !important; 
        opacity: 0.5 !important;
    }

    /* Force icon color inheritance */
    :host-context(.dark-theme) ::ng-deep .mat-mdc-paginator .mat-mdc-icon-button .mat-icon {
        color: currentColor !important;
    }
    
    /* 4. Light/Dark Mode Border Consistency */
    ::ng-deep .mat-mdc-paginator { border-top: 1px solid var(--prestige-border) !important; }
  `]
})
export class FormComponent implements AfterViewInit {
  private http = inject(HttpClient);
  private masterDataService = inject(MasterDataService);
  private contextService = inject(ContextService);
  private authService = inject(AuthService);
  private cdr = inject(ChangeDetectorRef);

  // Context Selection
  companies: any[] = [];
  periods: any[] = [];
  selectedCompany: any;
  selectedPeriod: any;

  // Dynamic Data
  scopes: any[] = [];

  // General Info
  year = '2022';
  huella = '';
  showDebug = false; // Toggle for JSON debug

  // Data Store (Dynamic)
  dataSources: { [key: number]: MatTableDataSource<any> } = {};

  get hasData(): boolean {
    return Object.values(this.dataSources).some(ds => ds.data.length > 0);
  }

  get debugKeys(): number[] {
    return Object.keys(this.dataSources).map(k => parseInt(k));
  }

  // Common properties
  Object = Object; // Expose Object to template
  displayedColumns: string[] = ['source', 'quantity', 'totalCO2e', 'actions'];

  @ViewChildren(MatPaginator) paginators!: QueryList<MatPaginator>;

  ngOnInit() {
    this.loadMasterData();

    const context = this.authService.currentContext();
    const initialCompany = this.contextService.selectedCompany();

    if (context && context.type === 'company') {
      // Lock to this company
      this.selectedCompany = { id: context.id, name: context.label };
      this.companies = [this.selectedCompany];
      // Load data immediately for this context
      this.loadPeriods(context.id!);
      this.loadMasterData(context.id);
    } else {
      // Global admin or no context - load all
      this.loadCompanies();

      // Use contextService selection if available and allowed
      if (initialCompany) {
        this.selectedCompany = initialCompany;
        this.loadPeriods(initialCompany.id);
        this.loadMasterData(initialCompany.id);
      }
    }
  }

  ngAfterViewInit() {
    this.paginators.changes.subscribe(() => {
      this.assignPaginators();
    });
  }

  loadCompanies() {
    this.masterDataService.getCompanies().subscribe(data => {
      this.companies = data;
      if (this.selectedCompany) {
        const found = this.companies.find(c => c.id === this.selectedCompany.id);
        if (found) this.selectedCompany = found;
      }
      this.cdr.detectChanges();
    });
  }

  compareObjects(o1: any, o2: any): boolean {
    return o1 && o2 ? o1.id === o2.id : o1 === o2;
  }

  onCompanyChange(company: any) {
    this.selectedPeriod = null;
    this.periods = [];
    if (company) {
      this.loadPeriods(company.id);
      this.loadMasterData(company.id);
    }
  }

  loadPeriods(companyId: number) {
    this.masterDataService.getPeriods(companyId).subscribe(data => {
      this.periods = data;
      const ctxPeriod = this.contextService.selectedPeriod();
      if (ctxPeriod && data.find((p: any) => p.id === ctxPeriod.id)) {
        this.selectedPeriod = ctxPeriod;
      }
      this.cdr.detectChanges();
    });
  }

  loadMasterData(companyId?: number) {
    this.masterDataService.getEmissionFactors(companyId).subscribe((scopes: any[]) => {
      this.scopes = scopes.map(scope => ({
        ...scope,
        categories: scope.categories.map((cat: any) => this.processCategory(cat))
      }));

      // Initialize DataSources for each scope
      this.scopes.forEach(scope => {
        if (!this.dataSources[scope.id]) {
          this.dataSources[scope.id] = new MatTableDataSource<any>([]);
        }
      });

      this.cdr.detectChanges();
    });
  }

  processCategory(category: any): any {
    const processed = {
      ...category,
      selectedFactor: null,
      inputAmount: '',
      children: category.children ? category.children.map((child: any) => this.processCategory(child)) : []
    };

    if (processed.factors && processed.factors.length > 0 && processed.name.toLowerCase().includes('electricidad')) {
      processed.selectedFactor = processed.factors[0];
    }

    return processed;
  }

  private cleanQuantity(value: string | number): number {
    const cleaned = parseFloat(value.toString());
    return isNaN(cleaned) ? 0 : cleaned;
  }

  addEmission(category: any, scopeId: number, parentName?: string) {
    if (!this.selectedCompany || !this.selectedPeriod) {
      alert('Por favor selecciona una Empresa y un Periodo antes de cargar datos.');
      return;
    }

    if (!category.selectedFactor || !category.inputAmount) return;

    const amount = this.cleanQuantity(category.inputAmount);
    const factor = category.selectedFactor;
    const totalCO2e = amount * parseFloat(factor.factor_total_co2e);

    const item = {
      type: parentName ? `${parentName} > ${category.name}` : category.name, // Include parent hierarchy
      subtype: factor.name,
      quantity: amount,
      unit: factor.unit?.symbol || factor.unit?.name || '',
      emissionFactorId: factor.id,
      totalCO2e: totalCO2e,
      source: category.name,
      originalCategory: parentName || category.name
    };

    // Dynamic Add
    if (!this.dataSources[scopeId]) {
      this.dataSources[scopeId] = new MatTableDataSource<any>([]);
    }

    const dataSource = this.dataSources[scopeId];
    const data = dataSource.data;
    data.unshift(item);
    dataSource.data = data;

    // Trigger change detection to render the table and paginator
    this.cdr.detectChanges();

    // Attempt to link paginator after view update
    setTimeout(() => this.assignPaginators());

    category.selectedFactor = null;
    category.inputAmount = '';

    if (category.name.toLowerCase().includes('electricidad') && category.factors.length > 0) {
      category.selectedFactor = category.factors[0];
    }
  }

  assignPaginators() {
    // We need to match paginators to scopes.
    // The *ngFor iterates scopes. If a scope has data, it renders a table and a paginator.

    const visibleScopes = this.scopes.filter(s => this.dataSources[s.id]?.data.length > 0);
    const paginators = this.paginators.toArray();

    visibleScopes.forEach((scope, index) => {
      if (paginators[index] && this.dataSources[scope.id]) {
        this.dataSources[scope.id].paginator = paginators[index];
      }
    });
  }

  removeEmission(item: any, scopeId: number) {
    if (this.dataSources[scopeId]) {
      const dataSource = this.dataSources[scopeId];
      const data = dataSource.data;
      const index = data.indexOf(item);
      if (index > -1) {
        data.splice(index, 1);
        dataSource.data = data;
        this.assignPaginators();
      }
    }
  }

  onSubmit() {
    const apiData = {
      uid: 'CURRENT_USER_ID',
      data: {} as any
    };

    Object.keys(this.dataSources).forEach(key => {
      apiData.data[`scope${key}`] = this.dataSources[parseInt(key)].data;
    });

    console.log('Submitting Data:', apiData);
  }
}
