
import { Component, OnInit, ViewChild, AfterViewInit, inject, Injectable } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule, MatPaginatorIntl } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { FormsModule } from '@angular/forms';
import { CarbonService } from '../../services/carbon.service';
import { ContextService } from '../../services/context.service';
import { debounceTime, distinctUntilChanged, Subject, tap } from 'rxjs';

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
    const endIndex = startIndex < length ? Math.min(startIndex + pageSize, length) : startIndex + pageSize;
    return `${startIndex + 1} - ${endIndex} de ${length}`;
  };
}

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatInputModule,
    MatFormFieldModule,
    MatIconModule,
    MatButtonModule,
    MatCardModule,
    MatProgressBarModule,
    FormsModule
  ],
  providers: [{ provide: MatPaginatorIntl, useClass: CustomPaginatorIntl }],
  template: `
    <div class="history-container">
      <div class="header-section">
        <h1>Historial de Registros</h1>
        <p>Consulta y gestiona los registros históricos de emisiones de tu empresa.</p>
      </div>

      <div class="filter-section glass-card">
        <mat-form-field appearance="outline" class="search-field">
          <mat-label>Buscar registros...</mat-label>
          <mat-icon matPrefix>search</mat-icon>
          <input matInput (keyup)="applyFilter($event)" placeholder="Ej. Gasolina, Alcance 1, Notas..." #input>
        </mat-form-field>
      </div>

      <div class="table-container glass-card">
        <mat-progress-bar mode="indeterminate" *ngIf="loading"></mat-progress-bar>

        <table mat-table [dataSource]="dataSource" matSort (matSortChange)="onSortChange($event)">

          <!-- Date Column -->
          <ng-container matColumnDef="created_at">
            <th mat-header-cell *matHeaderCellDef mat-sort-header> Fecha </th>
            <td mat-cell *matCellDef="let row"> {{row.created_at | date:'dd/MM/yyyy HH:mm'}} </td>
          </ng-container>

          <!-- Period Column -->
          <ng-container matColumnDef="period_year">
              <th mat-header-cell *matHeaderCellDef mat-sort-header> Año </th>
              <td mat-cell *matCellDef="let row"> {{row.period?.year}} </td>
          </ng-container>

          <!-- Scope Column -->
          <ng-container matColumnDef="scope">
            <th mat-header-cell *matHeaderCellDef> Alcance </th>
            <td mat-cell *matCellDef="let row">
              <span class="scope-badge" [ngClass]="'scope-' + (row.factor?.category?.scope_id || row.factor?.category?.scope?.id)">
                {{row.factor?.category?.scope?.name || ('Alcance ' + (row.factor?.category?.scope_id || '?'))}}
              </span>
            </td>
          </ng-container>

          <!-- Source Column -->
          <ng-container matColumnDef="source">
            <th mat-header-cell *matHeaderCellDef> Fuente de Emisión </th>
            <td mat-cell *matCellDef="let row"> 
                <div class="source-info">
                    <span class="source-name">{{row.factor?.name}}</span>
                    <span class="source-cat">{{row.factor?.category?.name}}</span>
                </div>
            </td>
          </ng-container>

          <!-- Quantity Column -->
          <ng-container matColumnDef="quantity">
            <th mat-header-cell *matHeaderCellDef mat-sort-header> Cantidad </th>
            <td mat-cell *matCellDef="let row"> {{row.quantity | number:'1.2-2'}} {{row.factor?.unit?.symbol || 'u'}} </td>
          </ng-container>

          <!-- Total (tCO2e) Column -->
          <ng-container matColumnDef="calculated_co2e">
            <th mat-header-cell *matHeaderCellDef mat-sort-header> Total (tCO2e) </th>
            <td mat-cell *matCellDef="let row"> <strong>{{row.calculated_co2e | number:'1.4-4'}}</strong> </td>
          </ng-container>

          <!-- Actions Column (Optional for now) -->
          <!-- <ng-container matColumnDef="actions">
            <th mat-header-cell *matHeaderCellDef> Acciones </th>
            <td mat-cell *matCellDef="let row">
              <button mat-icon-button color="primary" matTooltip="Ver detalles">
                <mat-icon>visibility</mat-icon>
              </button>
            </td>
          </ng-container> -->

          <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
          <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>

          <!-- Row shown when there is no matching data. -->
          <tr class="mat-row" *matNoDataRow>
            <td class="mat-cell" colspan="6" style="text-align: center; padding: 20px;">
                <span *ngIf="input.value">No hay datos que coincidan con "{{input.value}}"</span>
                <span *ngIf="!input.value && !loading">No hay registros históricos disponibles.</span>
            </td>
          </tr>
        </table>

        <mat-paginator [length]="totalResults"
                       [pageSize]="5"
                       [pageSizeOptions]="[5, 10, 25, 100]"
                       (page)="onPageChange($event)"
                       aria-label="Seleccionar página de registros">
        </mat-paginator>
      </div>
    </div>
  `,
  styles: [`
    .history-container { padding: 24px; max-width: 1400px; margin: 0 auto; font-family: 'Outfit', sans-serif; }
    .header-section { margin-bottom: 24px; }
    .header-section h1 { color: #1a237e; font-size: 28px; font-weight: 700; margin: 0; }
    .header-section p { color: #666; margin-top: 8px; }
    
    /* Dark Mode Text */
    :host-context(.dark-theme) .header-section h1 { color: var(--prestige-primary); }
    :host-context(.dark-theme) .header-section p { color: var(--prestige-text-muted); }

    .glass-card { background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07); border-radius: 16px; overflow: hidden; }
    
    /* Dark Mode Card */
    :host-context(.dark-theme) .glass-card {
        background: var(--prestige-card-bg);
        border-color: var(--prestige-border);
    }
    
    .filter-section { padding: 16px 24px; margin-bottom: 24px; display: flex; align-items: center; }
    .search-field { width: 100%; max-width: 400px; }
    
    .table-container { position: relative; min-height: 200px; }
    table { width: 100%; background: transparent; }
    
    /* Table Styles */
    th.mat-header-cell { color: #1a237e; font-weight: 600; font-size: 13px; text-transform: uppercase; background: rgba(0,0,0,0.02); }
    td.mat-cell { color: #333; border-bottom-color: rgba(0,0,0,0.05); }

    /* Dark Mode Table */
    :host-context(.dark-theme) th.mat-header-cell { 
        color: var(--prestige-text-muted); 
        background: rgba(255,255,255,0.05); 
    }
    :host-context(.dark-theme) td.mat-cell { 
        color: var(--prestige-text); 
        border-bottom-color: var(--prestige-border); 
    }

    /* Dark Mode Paginator */
    :host-context(.dark-theme) .mat-mdc-paginator { 
        background: transparent; 
        color: var(--prestige-text-muted); 
    }
    :host-context(.dark-theme) .mat-mdc-paginator .mat-mdc-select-value { color: var(--prestige-text); }
    :host-context(.dark-theme) .mat-mdc-icon-button { color: var(--prestige-text); }
    :host-context(.dark-theme) .mat-mdc-select-arrow { color: var(--prestige-text-muted); }
    
    .scope-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; color: white; display: inline-block; font-weight: 500; }
    .scope-1 { background: #1a237e; }
    .scope-2 { background: #00897b; }
    .scope-3 { background: #f59e0b; }
    
    .source-info { display: flex; flex-direction: column; }
    .source-name { font-weight: 500; }
    .source-cat { font-size: 11px; color: #888; }
    
    /* Dark Mode Source Info */
    :host-context(.dark-theme) .source-cat { color: var(--prestige-text-muted); }
    
    th.mat-header-cell { color: #1a237e; font-weight: 600; font-size: 13px; text-transform: uppercase; }
  `]
})
export class HistoryComponent implements OnInit, AfterViewInit {
  displayedColumns: string[] = ['created_at', 'period_year', 'scope', 'source', 'quantity', 'calculated_co2e'];
  dataSource = new MatTableDataSource<any>([]);

  totalResults = 0;
  loading = false;

  private searchSubject = new Subject<string>();

  // Current Filter State
  currentPage = 0;
  pageSize = 5;
  currentSearch = '';
  currentSortBy = 'created_at';
  currentSortDir: 'asc' | 'desc' = 'desc';

  private carbonService = inject(CarbonService);
  private contextService = inject(ContextService);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;

  ngOnInit() {
    // Debounce search input
    this.searchSubject.pipe(
      debounceTime(500),
      distinctUntilChanged()
    ).subscribe(searchValue => {
      this.currentSearch = searchValue;
      this.currentPage = 0; // Reset to first page
      this.loadData();
    });

    // Initial Load
    this.loadData();
  }

  ngAfterViewInit() {
    // If we used client-side sort: this.dataSource.sort = this.sort;
    // But we use server-side sort, so we listen to events.
  }

  loadData() {
    const company = this.contextService.selectedCompany();
    if (!company) return;

    this.loading = true;

    this.carbonService.getHistory(company.id, {
      page: this.currentPage + 1, // API is 1-indexed
      per_page: this.pageSize,
      search: this.currentSearch,
      sort_by: this.currentSortBy,
      sort_dir: this.currentSortDir
    }).subscribe({
      next: (res) => {
        this.dataSource.data = res.data;
        this.totalResults = res.total;
        this.loading = false;
      },
      error: (err) => {
        console.error(err);
        this.loading = false;
      }
    });
  }

  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.searchSubject.next(filterValue.trim());
  }

  onPageChange(event: any) {
    this.currentPage = event.pageIndex;
    this.pageSize = event.pageSize;
    this.loadData();
  }

  onSortChange(sortState: any) {
    this.currentSortBy = sortState.active;
    this.currentSortDir = sortState.direction || 'desc';
    this.loadData();
  }
}
