import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Line } from './line';

describe('Line', () => {
  let component: Line;
  let fixture: ComponentFixture<Line>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Line]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Line);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
