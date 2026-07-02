import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { AuditorAssignmentsComponent } from './auditor-assignments';
import { AdminService } from '../../../services/admin.service';
import { MasterDataService } from '../../../services/master-data.service';
import { AuditorAssignmentService } from '../../../services/auditor-assignment.service';

describe('AuditorAssignmentsComponent', () => {
  let component: AuditorAssignmentsComponent;
  let fixture: ComponentFixture<AuditorAssignmentsComponent>;
  let adminServiceMock: { getUsers: ReturnType<typeof vi.fn>; getCompanies: ReturnType<typeof vi.fn> };
  let masterDataMock: { getPeriods: ReturnType<typeof vi.fn> };
  let assignmentServiceMock: {
    getAssignments: ReturnType<typeof vi.fn>;
    grant: ReturnType<typeof vi.fn>;
    revoke: ReturnType<typeof vi.fn>;
  };

  const mockUsers = [
    { id: 1, name: 'Auditor Uno', email: 'a1@ext.co', role: 'auditor' },
    { id: 2, name: 'Usuario Normal', email: 'u@empresa.co', role: 'user' },
  ];
  const mockCompanies = [{ id: 5, name: 'ECONOVA' }];
  const mockPeriods = [{ id: 10, year: 2026, status: 'closed' }];
  const mockAssignments = [
    { id: 1, user: { name: 'Auditor Uno' }, company: { name: 'ECONOVA' }, period: { year: 2026 }, expires_at: null },
  ];

  beforeEach(async () => {
    adminServiceMock = {
      getUsers: vi.fn(() => of(mockUsers)),
      getCompanies: vi.fn(() => of(mockCompanies)),
    };
    masterDataMock = { getPeriods: vi.fn(() => of(mockPeriods)) };
    assignmentServiceMock = {
      getAssignments: vi.fn(() => of(mockAssignments)),
      grant: vi.fn(() => of({ id: 2 })),
      revoke: vi.fn(() => of({})),
    };

    await TestBed.configureTestingModule({
      imports: [AuditorAssignmentsComponent, NoopAnimationsModule],
      providers: [
        { provide: AdminService, useValue: adminServiceMock },
        { provide: MasterDataService, useValue: masterDataMock },
        { provide: AuditorAssignmentService, useValue: assignmentServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AuditorAssignmentsComponent);
    component = fixture.componentInstance;
  });

  it('creates the component', () => {
    expect(component).toBeTruthy();
  });

  it('loads assignments, filters auditors from users, and loads companies on init', () => {
    fixture.detectChanges();

    expect(assignmentServiceMock.getAssignments).toHaveBeenCalled();
    expect(component.assignments).toEqual(mockAssignments);
    expect(component.auditors).toEqual([mockUsers[0]]);
    expect(component.companies).toEqual(mockCompanies);
  });

  it('loadPeriods fetches periods for the selected company', () => {
    fixture.detectChanges();
    component.selectedCompanyId = 5;

    component.loadPeriods();

    expect(masterDataMock.getPeriods).toHaveBeenCalledWith(5);
    expect(component.periods).toEqual(mockPeriods);
  });

  it('loadPeriods clears periods when no company is selected', () => {
    fixture.detectChanges();
    component.selectedCompanyId = null;

    component.loadPeriods();

    expect(masterDataMock.getPeriods).not.toHaveBeenCalled();
    expect(component.periods).toEqual([]);
  });

  it('grant calls the service with the selected auditor, period and expiry', () => {
    fixture.detectChanges();
    component.newGrant = { user_id: 1, period_id: 10 };
    component.expiresAt = '2026-12-31';

    component.grant();

    expect(assignmentServiceMock.grant).toHaveBeenCalledWith({
      user_id: 1,
      period_id: 10,
      expires_at: '2026-12-31',
    });
  });

  it('grant does nothing without an auditor or period selected', () => {
    fixture.detectChanges();
    component.newGrant = { user_id: null, period_id: null };

    component.grant();

    expect(assignmentServiceMock.grant).not.toHaveBeenCalled();
  });

  it('revoke asks for confirmation before calling the service', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    fixture.detectChanges();

    component.revoke(mockAssignments[0]);

    expect(assignmentServiceMock.revoke).toHaveBeenCalledWith(1);
  });

  it('revoke does nothing when confirmation is declined', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    fixture.detectChanges();

    component.revoke(mockAssignments[0]);

    expect(assignmentServiceMock.revoke).not.toHaveBeenCalled();
  });
});
