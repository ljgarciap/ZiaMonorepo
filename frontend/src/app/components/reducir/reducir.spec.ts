import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReducirComponent } from './reducir';

describe('ReducirComponent', () => {
  let component: ReducirComponent;
  let fixture: ComponentFixture<ReducirComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReducirComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ReducirComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
