import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CertificarComponent } from './certificar';

describe('CertificarComponent', () => {
  let component: CertificarComponent;
  let fixture: ComponentFixture<CertificarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CertificarComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CertificarComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
