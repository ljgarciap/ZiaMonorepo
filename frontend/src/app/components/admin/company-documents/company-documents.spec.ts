import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { CompanyDocumentsComponent } from './company-documents';
import { AdminService } from '../../../services/admin.service';
import { ContextService } from '../../../services/context.service';

describe('CompanyDocumentsComponent', () => {
  let component: CompanyDocumentsComponent;
  let fixture: ComponentFixture<CompanyDocumentsComponent>;
  let adminServiceMock: {
    getCompanyDocuments: ReturnType<typeof vi.fn>;
    uploadCompanyDocument: ReturnType<typeof vi.fn>;
    deleteCompanyDocument: ReturnType<typeof vi.fn>;
  };

  const mockDocuments = [
    { id: 1, title: 'factura.pdf', status: 'processed', uploader: { name: 'Admin' }, created_at: '2026-07-05' },
  ];

  async function build(company: { id: number; name: string } | null) {
    localStorage.clear();
    adminServiceMock = {
      getCompanyDocuments: vi.fn(() => of(mockDocuments)),
      uploadCompanyDocument: vi.fn(() => of({ id: 2 })),
      deleteCompanyDocument: vi.fn(() => of({})),
    };

    await TestBed.configureTestingModule({
      imports: [CompanyDocumentsComponent, NoopAnimationsModule],
      providers: [
        ContextService,
        { provide: AdminService, useValue: adminServiceMock },
      ],
    }).compileComponents();

    const contextService = TestBed.inject(ContextService);
    if (company) {
      contextService.setCompany(company);
    }

    fixture = TestBed.createComponent(CompanyDocumentsComponent);
    component = fixture.componentInstance;
  }

  afterEach(() => {
    localStorage.clear();
    TestBed.resetTestingModule();
  });

  it('creates the component and reads the company from context', async () => {
    await build({ id: 7, name: 'ECONOVA' });

    expect(component).toBeTruthy();
    expect(component.companyId()).toBe(7);
  });

  it('loads documents for the selected company on init', async () => {
    await build({ id: 7, name: 'ECONOVA' });
    fixture.detectChanges();

    expect(adminServiceMock.getCompanyDocuments).toHaveBeenCalledWith(7);
    expect(component.dataSource.data).toEqual(mockDocuments);
  });

  it('does not load anything when there is no company in context', async () => {
    await build(null);
    fixture.detectChanges();

    expect(component.companyId()).toBeNull();
    expect(adminServiceMock.getCompanyDocuments).not.toHaveBeenCalled();
  });

  it('uploads the selected file for the current company', async () => {
    await build({ id: 7, name: 'ECONOVA' });
    fixture.detectChanges();

    const file = new File(['contenido'], 'reporte.txt', { type: 'text/plain' });
    const input = { files: [file], value: 'reporte.txt' } as unknown as HTMLInputElement;
    const event = { target: input } as unknown as Event;

    component.onFileSelected(event);

    expect(adminServiceMock.uploadCompanyDocument).toHaveBeenCalledWith(7, file);
  });

  it('deletes a document after confirmation', async () => {
    await build({ id: 7, name: 'ECONOVA' });
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    fixture.detectChanges();

    component.onDelete(mockDocuments[0]);

    expect(adminServiceMock.deleteCompanyDocument).toHaveBeenCalledWith(7, 1);
  });

  it('does not delete when confirmation is declined', async () => {
    await build({ id: 7, name: 'ECONOVA' });
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    fixture.detectChanges();

    component.onDelete(mockDocuments[0]);

    expect(adminServiceMock.deleteCompanyDocument).not.toHaveBeenCalled();
  });

  it('maps unknown statuses to the pending label as a safe default', async () => {
    await build({ id: 7, name: 'ECONOVA' });

    expect(component.statusInfo('something-unexpected').label).toBe('En cola');
  });
});
