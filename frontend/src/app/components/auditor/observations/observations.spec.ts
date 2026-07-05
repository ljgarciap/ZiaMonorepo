import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { AuditObservationsComponent } from './observations';
import { AuthService } from '../../../services/auth';
import { MasterDataService } from '../../../services/master-data.service';
import { AuditObservationService } from '../../../services/audit-observation.service';
import { createMockAuthService } from '../../../../testing/mocks';

describe('AuditObservationsComponent', () => {
  let component: AuditObservationsComponent;
  let fixture: ComponentFixture<AuditObservationsComponent>;
  let authMock: ReturnType<typeof createMockAuthService>;
  let masterDataMock: { getPeriods: ReturnType<typeof vi.fn> };
  let observationServiceMock: {
    getObservations: ReturnType<typeof vi.fn>;
    createObservation: ReturnType<typeof vi.fn>;
    deleteObservation: ReturnType<typeof vi.fn>;
  };

  const mockPeriods = [{ id: 10, year: 2026, status: 'closed' }];
  const mockObservations = [
    { id: 1, body: 'Factor de emisión inconsistente', verdict: 'observado', user: { name: 'Auditor Uno' }, created_at: '2026-07-01T00:00:00Z' },
  ];

  function setup(role: string) {
    authMock = createMockAuthService();
    authMock.currentContext.set({ type: 'company', id: 7, label: 'ECONOVA', role });

    masterDataMock = { getPeriods: vi.fn(() => of(mockPeriods)) };
    observationServiceMock = {
      getObservations: vi.fn(() => of(mockObservations)),
      createObservation: vi.fn(() => of({ id: 2 })),
      deleteObservation: vi.fn(() => of({})),
    };
  }

  async function build() {
    await TestBed.configureTestingModule({
      imports: [AuditObservationsComponent, NoopAnimationsModule],
      providers: [
        { provide: AuthService, useValue: authMock },
        { provide: MasterDataService, useValue: masterDataMock },
        { provide: AuditObservationService, useValue: observationServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AuditObservationsComponent);
    component = fixture.componentInstance;
  }

  it('creates the component', async () => {
    setup('auditor');
    await build();
    expect(component).toBeTruthy();
  });

  it('loads periods and defaults to the first one, then loads its observations', async () => {
    setup('auditor');
    await build();
    fixture.detectChanges();

    expect(masterDataMock.getPeriods).toHaveBeenCalledWith(7);
    expect(component.selectedPeriodId).toBe(10);
    expect(observationServiceMock.getObservations).toHaveBeenCalledWith(7, 10);
    expect(component.observations()).toEqual(mockObservations);
  });

  it('does not load periods when there is no company in context', async () => {
    setup('auditor');
    authMock.currentContext.set(null);
    await build();
    fixture.detectChanges();

    expect(masterDataMock.getPeriods).not.toHaveBeenCalled();
  });

  it('auditor can create an observation', async () => {
    setup('auditor');
    await build();
    fixture.detectChanges();

    expect(component.canCreate).toBe(true);
    component.newObservation = { body: 'Nuevo hallazgo', verdict: 'no_conforme' };
    component.createObservation();

    expect(observationServiceMock.createObservation).toHaveBeenCalledWith(7, 10, { body: 'Nuevo hallazgo', verdict: 'no_conforme' });
  });

  it('admin cannot create but can moderate (delete)', async () => {
    setup('admin');
    await build();
    fixture.detectChanges();

    expect(component.canCreate).toBe(false);
    expect(component.canModerate).toBe(true);

    vi.spyOn(window, 'confirm').mockReturnValue(true);
    component.deleteObservation(mockObservations[0]);

    expect(observationServiceMock.deleteObservation).toHaveBeenCalledWith(7, 10, 1);
  });

  it('user role cannot create nor moderate', async () => {
    setup('user');
    await build();
    fixture.detectChanges();

    expect(component.canCreate).toBe(false);
    expect(component.canModerate).toBe(false);
  });

  it('deleteObservation does nothing when confirmation is declined', async () => {
    setup('admin');
    await build();
    fixture.detectChanges();

    vi.spyOn(window, 'confirm').mockReturnValue(false);
    component.deleteObservation(mockObservations[0]);

    expect(observationServiceMock.deleteObservation).not.toHaveBeenCalled();
  });

  it('renders the observation body in the template', async () => {
    setup('auditor');
    await build();
    fixture.detectChanges();

    const text = (fixture.nativeElement as HTMLElement).textContent || '';
    expect(text).toContain('Factor de emisión inconsistente');
  });
});
