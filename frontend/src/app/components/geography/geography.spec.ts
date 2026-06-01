import { ComponentFixture, TestBed } from '@angular/core/testing';

import { GeographyComponent } from './geography';

describe('GeographyComponent', () => {
  let component: GeographyComponent;
  let fixture: ComponentFixture<GeographyComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GeographyComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(GeographyComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
