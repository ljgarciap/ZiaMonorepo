import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DashboardComponent } from './dashboard';
import { provideRouter } from '@angular/router';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { AuthService } from '../../services/auth';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';

import { signal } from '@angular/core';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;

  beforeEach(async () => {
    const authMock = {
      logout: () => {},
      currentUser: signal({ name: 'Test User' }),
      currentContext: signal({ role: 'user', label: 'ECONOVA', type: 'company' }),
      availableContexts: signal([])
    };

    await TestBed.configureTestingModule({
      imports: [
        DashboardComponent,
        HttpClientTestingModule,
        NoopAnimationsModule
      ],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authMock }
      ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
