import { ComponentFixture, TestBed } from '@angular/core/testing';

import { BarComponent } from './bar';

describe('BarComponent', () => {
  let component: BarComponent;
  let fixture: ComponentFixture<BarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BarComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(BarComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
