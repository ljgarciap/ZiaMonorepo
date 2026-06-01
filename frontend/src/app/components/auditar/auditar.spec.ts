import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AuditarComponent } from './auditar';

describe('AuditarComponent', () => {
  let component: AuditarComponent;
  let fixture: ComponentFixture<AuditarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AuditarComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AuditarComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
