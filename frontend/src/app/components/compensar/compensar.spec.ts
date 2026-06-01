import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Compensar } from './compensar';

describe('Compensar', () => {
  let component: Compensar;
  let fixture: ComponentFixture<Compensar>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Compensar]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Compensar);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
