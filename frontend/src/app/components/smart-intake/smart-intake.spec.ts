import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SmartIntakeComponent } from './smart-intake';
import { ContextService } from '../../services/context.service';
import { CarbonService } from '../../services/carbon.service';
import { AuthService } from '../../services/auth';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { of } from 'rxjs';

describe('SmartIntakeComponent', () => {
  let component: SmartIntakeComponent;
  let fixture: ComponentFixture<SmartIntakeComponent>;
  let contextService: ContextService;

  beforeEach(async () => {
    const carbonMock = {
      storeEmission: () => of({ id: 1 })
    };

    const authMock = {
      currentUser: () => ({ role: 'user' })
    };

    await TestBed.configureTestingModule({
      imports: [
        SmartIntakeComponent,
        HttpClientTestingModule,
        MatSnackBarModule,
        NoopAnimationsModule
      ],
      providers: [
        ContextService,
        { provide: CarbonService, useValue: carbonMock },
        { provide: AuthService, useValue: authMock }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(SmartIntakeComponent);
    component = fixture.componentInstance;
    contextService = TestBed.inject(ContextService);
    fixture.detectChanges();
  });

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('should dynamically calculate mathjs formula for Gas Natural', () => {
    const gasSection = component.questionnaireSections.find(s => s.id === 'combustion_gas')!;
    
    // Set question values
    gasSection.questions[0].value = '500';
    
    // Evaluate
    component.evaluateSection(gasSection);
    
    // 500 * 1.956 = 978 kg CO2e -> 0.978 tCO2e
    expect(gasSection.calculatedResult).toBeCloseTo(0.978, 4);
  });

  it('should reactively filter sections based on company name tags', () => {
    // 1. Initial State (No company - tags empty - all sections show)
    contextService.setCompany(null);
    fixture.detectChanges();
    expect(component.filteredSections().length).toEqual(component.questionnaireSections.length);

    // 2. Set energy company
    contextService.setCompany({ id: 1, name: 'ECONOVA Energy Solutions' });
    fixture.detectChanges();
    
    // Should filter to only 'energia' and 'agua' tags
    const active = component.activeTags();
    expect(active).toContain('energia');
    expect(active).toContain('agua');
    expect(active).not.toContain('transporte');

    const filtered = component.filteredSections();
    expect(filtered.every(s => s.tag === 'energia' || s.tag === 'agua')).toBe(true);
  });
});
