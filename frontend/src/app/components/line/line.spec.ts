import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LineComponent } from './line';

describe('LineComponent', () => {
  let component: LineComponent;
  let fixture: ComponentFixture<LineComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LineComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(LineComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
