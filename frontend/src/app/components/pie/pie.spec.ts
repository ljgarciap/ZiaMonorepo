import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PieComponent } from './pie';

describe('PieComponent', () => {
  let component: PieComponent;
  let fixture: ComponentFixture<PieComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PieComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PieComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
