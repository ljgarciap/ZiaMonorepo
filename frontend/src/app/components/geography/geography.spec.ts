import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Geography } from './geography';

describe('Geography', () => {
  let component: Geography;
  let fixture: ComponentFixture<Geography>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Geography]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Geography);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
