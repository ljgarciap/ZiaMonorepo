import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';

import { ApiCredentialsComponent } from './api-credentials';
import { AdminService } from '../../../services/admin.service';

describe('ApiCredentialsComponent', () => {
  let component: ApiCredentialsComponent;
  let fixture: ComponentFixture<ApiCredentialsComponent>;
  let adminServiceMock: {
    getApiCredentials: ReturnType<typeof vi.fn>;
    updateApiCredential: ReturnType<typeof vi.fn>;
    deleteApiCredential: ReturnType<typeof vi.fn>;
  };

  const mockRows = [
    { key: 'MISTRAL_API_KEY', description: 'IA primaria', is_set: true, masked_value: '****1234', updated_at: '2026-07-06', updated_by: 'Ana' },
    { key: 'ANTHROPIC_API_KEY', description: 'IA de respaldo', is_set: false, masked_value: null, updated_at: null, updated_by: null },
  ];

  async function build() {
    adminServiceMock = {
      getApiCredentials: vi.fn(() => of(mockRows)),
      updateApiCredential: vi.fn(() => of({ key: 'MISTRAL_API_KEY', is_set: true, masked_value: '****9999' })),
      deleteApiCredential: vi.fn(() => of(null)),
    };

    await TestBed.configureTestingModule({
      imports: [ApiCredentialsComponent, NoopAnimationsModule],
      providers: [{ provide: AdminService, useValue: adminServiceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(ApiCredentialsComponent);
    component = fixture.componentInstance;
  }

  afterEach(() => TestBed.resetTestingModule());

  it('loads and displays the managed keys on init', async () => {
    await build();
    fixture.detectChanges();

    expect(adminServiceMock.getApiCredentials).toHaveBeenCalled();
    expect(component.rows().length).toBe(2);
    expect(component.rows()[0].key).toBe('MISTRAL_API_KEY');
  });

  it('does not save when the draft value is empty', async () => {
    await build();
    fixture.detectChanges();

    const row = { key: 'MISTRAL_API_KEY', description: '', is_set: true, masked_value: '****1234', updated_at: null, updated_by: null, draftValue: '', saving: false };

    component.onSave(row);

    expect(adminServiceMock.updateApiCredential).not.toHaveBeenCalled();
  });

  it('saves a new value and reloads the list', async () => {
    await build();
    fixture.detectChanges();

    const row = component.rows()[1];
    row.draftValue = 'sk-ant-new-key';

    component.onSave(row);

    expect(adminServiceMock.updateApiCredential).toHaveBeenCalledWith('ANTHROPIC_API_KEY', 'sk-ant-new-key');
    expect(adminServiceMock.getApiCredentials).toHaveBeenCalledTimes(2); // initial load + reload after save
  });

  it('shows an error and stops the spinner when saving fails', async () => {
    await build();
    adminServiceMock.updateApiCredential.mockReturnValue(throwError(() => new Error('boom')));
    fixture.detectChanges();

    const row = component.rows()[0];
    row.draftValue = 'sk-broken';

    component.onSave(row);

    expect(row.saving).toBe(false);
  });

  it('asks for confirmation before clearing a key, and does nothing if declined', async () => {
    await build();
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    component.onClear(component.rows()[0]);

    expect(adminServiceMock.deleteApiCredential).not.toHaveBeenCalled();
  });

  it('clears a key after confirmation and reloads', async () => {
    await build();
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.onClear(component.rows()[0]);

    expect(adminServiceMock.deleteApiCredential).toHaveBeenCalledWith('MISTRAL_API_KEY');
  });
});
