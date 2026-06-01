import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Reducir } from './reducir';

describe('Reducir', () => {
  let component: Reducir;
  let fixture: ComponentFixture<Reducir>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Reducir]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Reducir);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
