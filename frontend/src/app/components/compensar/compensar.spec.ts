import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CompensarComponent } from './compensar';

describe('CompensarComponent', () => {
  let component: CompensarComponent;
  let fixture: ComponentFixture<CompensarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CompensarComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CompensarComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
