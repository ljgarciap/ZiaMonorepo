import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { FormsModule } from '@angular/forms';
import { MasterDataService } from '../../services/master-data.service';
import { ContextService } from '../../services/context.service';

@Component({
  selector: 'app-context-selector',
  standalone: true,
  imports: [CommonModule, MatFormFieldModule, MatSelectModule, FormsModule],
  template: `
    <div class="context-container">
      <div class="selector-group">
        <span class="selector-label">Empresa</span>
        <mat-select [(ngModel)]="selectedCompany" (selectionChange)="onCompanyChange($event.value)" class="zia-select">
          <mat-option *ngFor="let company of companies" [value]="company">
            {{ company.name }}
          </mat-option>
        </mat-select>
      </div>

      <div class="selector-group" *ngIf="selectedCompany">
        <span class="selector-label">Año</span>
        <mat-select [(ngModel)]="selectedPeriod" (selectionChange)="onPeriodChange($event.value)" class="zia-select">
          <mat-option *ngFor="let period of periods" [value]="period">
            {{ period.year }}
          </mat-option>
        </mat-select>
      </div>
    </div>
  `,
  styles: [`
    .context-container {
      display: flex;
      gap: 24px;
      align-items: center;
      margin-right: 20px;
    }
    .selector-group {
      display: flex;
      flex-direction: column;
      min-width: 140px;
    }
    .selector-label {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--prestige-text-muted);
      margin-bottom: 2px;
      font-weight: 600;
    }
    :host-context(.dark-theme) .selector-label {
      color: var(--prestige-text);
      opacity: 0.9;
    }
    .zia-select {
      color: var(--prestige-text);
      font-size: 14px;
      border-bottom: 1px solid var(--prestige-border);
      padding: 4px 0;
      transition: all 0.2s ease;
    }
    .zia-select:hover {
      border-bottom-color: var(--prestige-primary);
    }
    ::ng-deep .zia-select .mat-mdc-select-value {
      color: var(--prestige-text) !important;
      font-weight: 500;
    }
    ::ng-deep .zia-select .mat-mdc-select-arrow svg {
      fill: var(--prestige-text) !important;
      opacity: 0.7;
    }
    /* Panel is handled globally in styles.css */
  `]
})
export class ContextSelectorComponent implements OnInit {
  masterData = inject(MasterDataService);
  context = inject(ContextService);

  companies: any[] = [];
  periods: any[] = [];

  selectedCompany: any;
  selectedPeriod: any;

  ngOnInit() {
    this.selectedCompany = this.context.selectedCompany();
    this.selectedPeriod = this.context.selectedPeriod();

    this.loadCompanies();
    if (this.selectedCompany) {
      this.loadPeriods(this.selectedCompany.id);
    }
  }

  loadCompanies() {
    this.masterData.getCompanies().subscribe({
      next: (data) => {
        console.log('Loaded companies:', data);
        this.companies = data;
        // Auto-select first if none selected
        if (!this.selectedCompany && this.companies.length > 0) {
          this.onCompanyChange(this.companies[0]);
        }
      },
      error: (e) => console.error('Error loading companies:', e)
    });
  }

  loadPeriods(companyId: number) {
    this.masterData.getPeriods(companyId).subscribe(data => {
      this.periods = data;
      // Auto-select active/newest period
      if (!this.selectedPeriod && this.periods.length > 0) {
        this.onPeriodChange(this.periods[0]);
      }
    });
  }

  onCompanyChange(company: any) {
    this.selectedCompany = company;
    this.context.setCompany(company);
    this.loadPeriods(company.id);
  }

  onPeriodChange(period: any) {
    this.selectedPeriod = period;
    this.context.setPeriod(period);
  }
}
