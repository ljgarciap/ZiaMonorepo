import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormComponent } from './form';
import { MasterDataService } from '../../services/master-data.service';
import { ContextService } from '../../services/context.service';
import { AuthService } from '../../services/auth';
import { of } from 'rxjs';
import { signal } from '@angular/core';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';

describe('FormComponent', () => {
  let component: FormComponent;
  let fixture: ComponentFixture<FormComponent>;

  beforeEach(async () => {
    const masterDataMock = {
      getCompanies: () => of([]),
      getPeriods: () => of([]),
      getEmissionFactors: () => of([])
    };

    const contextMock = {
      selectedCompany: signal(null),
      selectedPeriod: signal(null)
    };

    const authMock = {
      currentContext: signal({ role: 'user', label: 'ECONOVA', type: 'company', id: 1 })
    };

    await TestBed.configureTestingModule({
      imports: [
        FormComponent,
        NoopAnimationsModule
      ],
      providers: [
        { provide: MasterDataService, useValue: masterDataMock },
        { provide: ContextService, useValue: contextMock },
        { provide: AuthService, useValue: authMock }
      ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
