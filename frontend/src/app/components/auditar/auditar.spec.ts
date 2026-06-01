import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Auditar } from './auditar';

describe('Auditar', () => {
  let component: Auditar;
  let fixture: ComponentFixture<Auditar>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Auditar]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Auditar);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
