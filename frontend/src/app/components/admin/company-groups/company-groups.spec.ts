import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { CompanyGroupsComponent } from './company-groups';
import { AdminService } from '../../../services/admin.service';

describe('CompanyGroupsComponent', () => {
  let component: CompanyGroupsComponent;
  let fixture: ComponentFixture<CompanyGroupsComponent>;
  let adminServiceMock: {
    getCompanies: ReturnType<typeof vi.fn>;
    getCompanyGroups: ReturnType<typeof vi.fn>;
    getCompanyGroupSummary: ReturnType<typeof vi.fn>;
    addCompanyToGroup: ReturnType<typeof vi.fn>;
    removeCompanyFromGroup: ReturnType<typeof vi.fn>;
    deleteCompanyGroup: ReturnType<typeof vi.fn>;
    createCompanyGroup: ReturnType<typeof vi.fn>;
  };

  const mockCompanies = [{ id: 1, name: 'UDES' }, { id: 2, name: 'IMEBU' }, { id: 3, name: 'Otra SAS' }];
  const mockGroups = [{ id: 9, name: 'Edificio Parque Tecnológico', description: 'UDES + IMEBU', companies: [{ id: 1 }, { id: 2 }] }];
  const mockSummary = {
    group: { id: 9, name: 'Edificio Parque Tecnológico' },
    total_co2e: 100,
    by_scope: [{ scope_id: 1, scope_name: 'Alcance 1', total_co2e: 100 }],
    by_company: [
      { company_id: 1, company_name: 'UDES', total_co2e: 30 },
      { company_id: 2, company_name: 'IMEBU', total_co2e: 70 },
    ],
  };

  beforeEach(async () => {
    adminServiceMock = {
      getCompanies: vi.fn(() => of(mockCompanies)),
      getCompanyGroups: vi.fn(() => of(mockGroups)),
      getCompanyGroupSummary: vi.fn(() => of(mockSummary)),
      addCompanyToGroup: vi.fn(() => of({})),
      removeCompanyFromGroup: vi.fn(() => of({})),
      deleteCompanyGroup: vi.fn(() => of({})),
      createCompanyGroup: vi.fn(() => of({ id: 10 })),
    };

    await TestBed.configureTestingModule({
      imports: [CompanyGroupsComponent, NoopAnimationsModule],
      providers: [{ provide: AdminService, useValue: adminServiceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(CompanyGroupsComponent);
    component = fixture.componentInstance;
  });

  it('creates the component', () => {
    expect(component).toBeTruthy();
  });

  it('loads groups and all companies on init', () => {
    fixture.detectChanges();

    expect(adminServiceMock.getCompanyGroups).toHaveBeenCalled();
    expect(adminServiceMock.getCompanies).toHaveBeenCalled();
    expect(component.dataSource.data).toEqual(mockGroups);
    expect(component.allCompanies).toEqual(mockCompanies);
  });

  it('selecting a group loads its summary for all periods by default', () => {
    fixture.detectChanges();

    component.selectGroup(mockGroups[0]);

    expect(component.summaryYear).toBeNull();
    expect(adminServiceMock.getCompanyGroupSummary).toHaveBeenCalledWith(9, undefined);
    expect(component.summary()).toEqual(mockSummary);
  });

  it('loadSummary passes the selected year to the service', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);
    adminServiceMock.getCompanyGroupSummary.mockClear();

    component.summaryYear = 2025;
    component.loadSummary();

    expect(adminServiceMock.getCompanyGroupSummary).toHaveBeenCalledWith(9, 2025);
  });

  it('availableCompaniesToAdd excludes companies already in the group summary', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);

    const available = component.availableCompaniesToAdd();

    expect(available).toEqual([{ id: 3, name: 'Otra SAS' }]);
  });

  it('closeDetail clears the selected group and its summary', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);

    component.closeDetail();

    expect(component.selectedGroup()).toBeNull();
    expect(component.summary()).toBeNull();
  });

  it('onAddCompany adds the chosen company and reloads', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);
    component.companyToAdd = 3;

    component.onAddCompany();

    expect(adminServiceMock.addCompanyToGroup).toHaveBeenCalledWith(9, 3);
    expect(component.companyToAdd).toBeNull();
  });

  it('onAddCompany does nothing without a company chosen', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);
    component.companyToAdd = null;

    component.onAddCompany();

    expect(adminServiceMock.addCompanyToGroup).not.toHaveBeenCalled();
  });

  it('onRemoveCompany removes the company from the currently selected group', () => {
    fixture.detectChanges();
    component.selectGroup(mockGroups[0]);

    component.onRemoveCompany(1);

    expect(adminServiceMock.removeCompanyFromGroup).toHaveBeenCalledWith(9, 1);
  });
});
