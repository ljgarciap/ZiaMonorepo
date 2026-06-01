import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Certificar } from './certificar';

describe('Certificar', () => {
  let component: Certificar;
  let fixture: ComponentFixture<Certificar>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Certificar]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Certificar);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
