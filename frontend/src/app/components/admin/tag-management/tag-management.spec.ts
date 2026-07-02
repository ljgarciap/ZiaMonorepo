import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { TagManagementComponent } from './tag-management';
import { AdminService } from '../../../services/admin.service';

describe('TagManagementComponent', () => {
  let component: TagManagementComponent;
  let fixture: ComponentFixture<TagManagementComponent>;
  let adminServiceMock: {
    getTags: ReturnType<typeof vi.fn>;
    getSectors: ReturnType<typeof vi.fn>;
    createTag: ReturnType<typeof vi.fn>;
    toggleTag: ReturnType<typeof vi.fn>;
    deleteTag: ReturnType<typeof vi.fn>;
  };

  const mockTags = [
    { id: 1, name: 'ISO 14001', sector: null, is_active: true },
    { id: 2, name: 'Manufactura Pesada', sector: { id: 3, name: 'Manufactura' }, is_active: true },
  ];
  const mockSectors = [{ id: 3, name: 'Manufactura' }];

  beforeEach(async () => {
    adminServiceMock = {
      getTags: vi.fn(() => of(mockTags)),
      getSectors: vi.fn(() => of(mockSectors)),
      createTag: vi.fn(() => of({ id: 3 })),
      toggleTag: vi.fn(() => of({})),
      deleteTag: vi.fn(() => of({})),
    };

    await TestBed.configureTestingModule({
      imports: [TagManagementComponent, NoopAnimationsModule],
      providers: [
        { provide: AdminService, useValue: adminServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(TagManagementComponent);
    component = fixture.componentInstance;
  });

  it('creates the component', () => {
    expect(component).toBeTruthy();
  });

  it('loads tags and sectors on init', () => {
    fixture.detectChanges();

    expect(adminServiceMock.getTags).toHaveBeenCalled();
    expect(adminServiceMock.getSectors).toHaveBeenCalled();
    expect(component.tags).toEqual(mockTags);
    expect(component.sectors).toEqual(mockSectors);
  });

  it('renders tag names and their sector in the table', () => {
    fixture.detectChanges();
    const text = (fixture.nativeElement as HTMLElement).textContent || '';
    expect(text).toContain('ISO 14001');
    expect(text).toContain('Manufactura');
  });

  it('createTag calls the service and resets the form', () => {
    fixture.detectChanges();
    component.newTag = { name: 'Nuevo Tag', company_sector_id: null };

    component.createTag();

    expect(adminServiceMock.createTag).toHaveBeenCalledWith({ name: 'Nuevo Tag', company_sector_id: null });
    expect(component.newTag.name).toBe('');
  });

  it('createTag does nothing without a name', () => {
    fixture.detectChanges();
    component.newTag = { name: '', company_sector_id: null };

    component.createTag();

    expect(adminServiceMock.createTag).not.toHaveBeenCalled();
  });

  it('toggle calls the service', () => {
    fixture.detectChanges();
    component.toggle(mockTags[0]);
    expect(adminServiceMock.toggleTag).toHaveBeenCalledWith(1);
  });

  it('deleteTag asks for confirmation before calling the service', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    fixture.detectChanges();

    component.deleteTag(mockTags[0]);

    expect(adminServiceMock.deleteTag).toHaveBeenCalledWith(1);
  });

  it('deleteTag does nothing when confirmation is declined', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    fixture.detectChanges();

    component.deleteTag(mockTags[0]);

    expect(adminServiceMock.deleteTag).not.toHaveBeenCalled();
  });
});
