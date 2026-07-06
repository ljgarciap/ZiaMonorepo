import { TestBed } from '@angular/core/testing';
import { FormBuilder } from '@angular/forms';
import { vi } from 'vitest';
import {
  CompanyDialog,
  SectorDialog,
  UserDialog,
  FactorDialog,
  FactorVersionsDialog,
  FormulaDialog,
  CategoryDialog,
  ConfirmDialog,
  UnitDialog,
  ScopeDialog,
  PeriodDialog,
  UserCompaniesDialog,
  CompanyGroupDialog,
} from './admin-dialogs';
import { AdminService } from '../../services/admin.service';
import { AuthService } from '../../services/auth';
import { ChangeDetectorRef } from '@angular/core';
import { of } from 'rxjs';

function mockDialogRef() {
  return { close: vi.fn() } as any;
}

describe('FactorVersionsDialog', () => {
  function build(versions: any[] = []) {
    return new FactorVersionsDialog({ factorName: 'Diésel', versions });
  }

  it('returns an empty diff for a "created" version (no old/new pair)', () => {
    const dialog = build();
    expect(dialog.diffFields(null)).toEqual([]);
  });

  it('only lists fields that actually changed between old and new', () => {
    const dialog = build();
    const changes = {
      old: { name: 'Diésel', factor_co2: 2.5, uncertainty_upper: 5 },
      new: { name: 'Diésel', factor_co2: 2.7, uncertainty_upper: 5 },
    };

    expect(dialog.diffFields(changes)).toEqual([
      { key: 'factor_co2', old: 2.5, new: 2.7 },
    ]);
  });

  it('excludes bookkeeping fields (id, timestamps, FKs) from the diff', () => {
    const dialog = build();
    const changes = {
      old: { id: 1, updated_at: '2026-01-01', factor_co2: 2.5 },
      new: { id: 1, updated_at: '2026-01-02', factor_co2: 2.9 },
    };

    expect(dialog.diffFields(changes)).toEqual([
      { key: 'factor_co2', old: 2.5, new: 2.9 },
    ]);
  });
});

describe('CompanyDialog', () => {
  function build(company: any = {}, sectors: any[] = []) {
    return new CompanyDialog(new FormBuilder(), mockDialogRef(), { company, sectors });
  }

  it('defaults methodology to GHG_PROTOCOL for a new company', () => {
    const dialog = build();
    expect(dialog.form.get('methodology')?.value).toBe('GHG_PROTOCOL');
    expect(dialog.form.valid).toBe(false); // name is required
  });

  it('prefills the form when editing an existing company', () => {
    const dialog = build({ id: 5, name: 'ECONOVA', nit: '900-1', methodology: 'ISO_14064' });
    expect(dialog.form.get('name')?.value).toBe('ECONOVA');
    expect(dialog.form.get('methodology')?.value).toBe('ISO_14064');
  });

  it('closes the dialog with the form value on save when valid', () => {
    const dialogRef = mockDialogRef();
    const dialog = new CompanyDialog(new FormBuilder(), dialogRef, { company: { name: 'X' }, sectors: [] });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ name: 'X' }));
  });

  it('does not close on save when the form is invalid', () => {
    const dialogRef = mockDialogRef();
    const dialog = new CompanyDialog(new FormBuilder(), dialogRef, { company: {}, sectors: [] });
    dialog.onSave();
    expect(dialogRef.close).not.toHaveBeenCalled();
  });

  it('closes without data on cancel', () => {
    const dialogRef = mockDialogRef();
    const dialog = new CompanyDialog(new FormBuilder(), dialogRef, { company: {}, sectors: [] });
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('SectorDialog', () => {
  it('requires a name', () => {
    const dialog = new SectorDialog(new FormBuilder(), mockDialogRef(), {});
    expect(dialog.form.valid).toBe(false);
  });

  it('saves when valid and prefills from data when editing', () => {
    const dialogRef = mockDialogRef();
    const dialog = new SectorDialog(new FormBuilder(), dialogRef, { id: 2, name: 'Industria' });
    expect(dialog.form.get('name')?.value).toBe('Industria');
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ name: 'Industria' }));
  });

  it('cancels without saving', () => {
    const dialogRef = mockDialogRef();
    const dialog = new SectorDialog(new FormBuilder(), dialogRef, {});
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('UserDialog', () => {
  function authServiceMock(role = 'superadmin') {
    return {
      currentContext: () => ({ role }),
      currentUser: () => ({ role }),
    } as unknown as AuthService;
  }

  it('exposes a role hint for the selected role', () => {
    const dialog = new UserDialog(new FormBuilder(), mockDialogRef(), { role: 'auditor' }, authServiceMock());
    expect(dialog.roleHint()).toContain('período');
  });

  it('returns an empty hint for an unknown role', () => {
    const dialog = new UserDialog(new FormBuilder(), mockDialogRef(), {}, authServiceMock());
    dialog.form.patchValue({ role: 'nope' });
    expect(dialog.roleHint()).toBe('');
  });

  it('prefills associated companies as an id list', () => {
    const dialog = new UserDialog(
      new FormBuilder(),
      mockDialogRef(),
      { companies: [{ id: 1 }, { id: 2 }] },
      authServiceMock()
    );
    expect(dialog.form.get('companies')?.value).toEqual([1, 2]);
  });

  it('requires a valid email and a name', () => {
    const dialog = new UserDialog(new FormBuilder(), mockDialogRef(), { email: 'not-an-email' }, authServiceMock());
    expect(dialog.form.valid).toBe(false);
  });

  it('saves valid data and cancels without saving', () => {
    const dialogRef = mockDialogRef();
    const dialog = new UserDialog(
      new FormBuilder(),
      dialogRef,
      { name: 'Ana', email: 'ana@zia.co' },
      authServiceMock()
    );
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ email: 'ana@zia.co' }));

    dialogRef.close.mockClear();
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });

  it('falls back to currentUser role when there is no active context', () => {
    const auth = { currentContext: () => null, currentUser: () => ({ role: 'admin' }) } as unknown as AuthService;
    const dialog = new UserDialog(new FormBuilder(), mockDialogRef(), {}, auth);
    expect(dialog.currentUserRole).toBe('admin');
  });
});

describe('FactorDialog', () => {
  function build(factor: any = {}) {
    let dialog!: FactorDialog;
    TestBed.configureTestingModule({
      providers: [
        { provide: AdminService, useValue: { getFactorVersions: vi.fn(() => of({ versions: [] })) } },
      ],
    });
    TestBed.runInInjectionContext(() => {
      dialog = new FactorDialog(new FormBuilder(), mockDialogRef(), { factor, formulas: [], units: [] });
    });
    return dialog;
  }

  it('defaults all gas factors to 0 for a new factor', () => {
    const dialog = build();
    expect(dialog.form.get('factor_co2')?.value).toBe(0);
    expect(dialog.form.valid).toBe(false); // name/unit required
  });

  it('prefills values when editing an existing factor', () => {
    const dialog = build({ id: 9, name: 'Diésel', measurement_unit_id: 1, factor_co2: 2.7 });
    expect(dialog.form.get('name')?.value).toBe('Diésel');
    expect(dialog.form.get('factor_co2')?.value).toBe(2.7);
  });

  it('saves and cancels', () => {
    let dialog!: FactorDialog;
    const dialogRef = mockDialogRef();
    TestBed.configureTestingModule({
      providers: [{ provide: AdminService, useValue: { getFactorVersions: vi.fn() } }],
    });
    TestBed.runInInjectionContext(() => {
      dialog = new FactorDialog(new FormBuilder(), dialogRef, {
        factor: { name: 'X', measurement_unit_id: 1 },
        formulas: [],
        units: [],
      });
    });

    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ name: 'X' }));

    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });

  afterEach(() => TestBed.resetTestingModule());
});

describe('FormulaDialog', () => {
  it('requires name and expression', () => {
    const dialog = new FormulaDialog(new FormBuilder(), mockDialogRef(), {});
    expect(dialog.form.valid).toBe(false);
  });

  it('saves valid data and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new FormulaDialog(new FormBuilder(), dialogRef, {
      name: 'Estándar',
      expression: 'activity_data * factor_co2',
    });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ name: 'Estándar' }));

    dialogRef.close.mockClear();
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('CategoryDialog', () => {
  const scopes = [{ id: 1, name: 'Alcance 1' }, { id: 2, name: 'Alcance 2' }];
  const categories = [{ id: 10, name: 'Padre', parent_id: null }, { id: 11, name: 'Hijo', parent_id: 10 }];

  it('defaults to the first scope when creating a new category', () => {
    const dialog = new CategoryDialog(new FormBuilder(), mockDialogRef(), { scopes, categories: [] });
    expect(dialog.form.get('scope_id')?.value).toBe(1);
  });

  it('excludes the category itself and non-root categories from filteredCategories', () => {
    const dialog = new CategoryDialog(new FormBuilder(), mockDialogRef(), {
      category: { id: 10 },
      scopes,
      categories,
    });
    const filtered = dialog.filteredCategories;
    expect(filtered.find((c: any) => c.id === 10)).toBeUndefined(); // self excluded
    expect(filtered.find((c: any) => c.id === 11)).toBeUndefined(); // not a root category
  });

  it('resolves selectedScope from the current scope_id', () => {
    const dialog = new CategoryDialog(new FormBuilder(), mockDialogRef(), { scopes, categories: [] });
    dialog.form.patchValue({ scope_id: 2 });
    expect(dialog.selectedScope?.name).toBe('Alcance 2');
  });

  it('saves valid data and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new CategoryDialog(new FormBuilder(), dialogRef, {
      category: { name: 'Combustibles' },
      scopes,
      categories: [],
    });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalled();

    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('ConfirmDialog', () => {
  it('closes with true on confirm', () => {
    const dialogRef = mockDialogRef();
    const dialog = new ConfirmDialog(dialogRef, { message: '¿Seguro?' });
    dialog.onConfirm();
    expect(dialogRef.close).toHaveBeenCalledWith(true);
  });

  it('closes with false on cancel', () => {
    const dialogRef = mockDialogRef();
    const dialog = new ConfirmDialog(dialogRef, {});
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith(false);
  });
});

describe('UnitDialog', () => {
  it('requires name and symbol', () => {
    const dialog = new UnitDialog(new FormBuilder(), mockDialogRef(), {});
    expect(dialog.form.valid).toBe(false);
  });

  it('saves valid data and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new UnitDialog(new FormBuilder(), dialogRef, { name: 'Kilogramos', symbol: 'kg' });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ symbol: 'kg' }));

    dialogRef.close.mockClear();
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('ScopeDialog', () => {
  it('requires a name only when creating (not when editing)', () => {
    const creating = new ScopeDialog(new FormBuilder(), mockDialogRef(), {});
    expect(creating.form.get('name')?.valid).toBe(false);

    const editing = new ScopeDialog(new FormBuilder(), mockDialogRef(), { id: 1, name: 'Alcance 1' });
    expect(editing.form.get('name')?.valid).toBe(true);
  });

  it('saves and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new ScopeDialog(new FormBuilder(), dialogRef, { id: 1, name: 'Alcance 1' });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalled();

    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('PeriodDialog', () => {
  it('defaults the year to the current year', () => {
    const dialog = new PeriodDialog(new FormBuilder(), mockDialogRef(), {});
    expect(dialog.form.get('year')?.value).toBe(new Date().getFullYear());
  });

  it('rejects a year out of range', () => {
    const dialog = new PeriodDialog(new FormBuilder(), mockDialogRef(), {});
    dialog.form.patchValue({ year: 1500 });
    expect(dialog.form.valid).toBe(false);
  });

  it('saves and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new PeriodDialog(new FormBuilder(), dialogRef, {});
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ year: expect.any(Number) }));

    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('CompanyGroupDialog', () => {
  it('requires a name', () => {
    const dialog = new CompanyGroupDialog(new FormBuilder(), mockDialogRef(), { allCompanies: [] });
    expect(dialog.form.valid).toBe(false);
  });

  it('saves valid data and cancels', () => {
    const dialogRef = mockDialogRef();
    const dialog = new CompanyGroupDialog(new FormBuilder(), dialogRef, { allCompanies: [] });
    dialog.form.patchValue({ name: 'Parque Industrial' });
    dialog.onSave();
    expect(dialogRef.close).toHaveBeenCalledWith(expect.objectContaining({ name: 'Parque Industrial' }));

    dialogRef.close.mockClear();
    dialog.onCancel();
    expect(dialogRef.close).toHaveBeenCalledWith();
  });
});

describe('UserCompaniesDialog', () => {
  function build(user: any, companies: any[] = []) {
    let dialog!: UserCompaniesDialog;
    const adminServiceMock = {
      getCompanies: vi.fn(() => of(companies)),
      updateUser: vi.fn((id: number, payload: any) =>
        of({ ...user, ...payload, companies: (payload.companies || []).map((cid: number) => ({ id: cid })) })
      ),
    };
    TestBed.configureTestingModule({
      providers: [
        { provide: AdminService, useValue: adminServiceMock },
        { provide: ChangeDetectorRef, useValue: { detectChanges: vi.fn() } },
      ],
    });
    TestBed.runInInjectionContext(() => {
      dialog = new UserCompaniesDialog(mockDialogRef(), { user });
    });
    dialog.ngOnInit();
    return { dialog, adminServiceMock };
  }

  afterEach(() => TestBed.resetTestingModule());

  it('loads companies not already associated to the user as available', () => {
    const { dialog } = build(
      { id: 1, name: 'Ana', email: 'ana@zia.co', companies: [{ id: 1, name: 'A' }] },
      [{ id: 1, name: 'A' }, { id: 2, name: 'B' }]
    );
    expect(dialog.availableCompanies).toEqual([{ id: 2, name: 'B' }]);
  });

  it('adds a company association and reloads the available list', () => {
    const { dialog, adminServiceMock } = build(
      { id: 1, name: 'Ana', email: 'ana@zia.co', companies: [] },
      [{ id: 2, name: 'B' }]
    );
    dialog.selectedCompanyId = 2;

    dialog.onAddCompany();

    expect(adminServiceMock.updateUser).toHaveBeenCalledWith(1, expect.objectContaining({ companies: [2] }));
    expect(dialog.hasChanges).toBe(true);
    expect(dialog.selectedCompanyId).toBeNull();
  });

  it('does nothing when adding without a selected company', () => {
    const { dialog, adminServiceMock } = build({ id: 1, name: 'Ana', email: 'ana@zia.co', companies: [] });
    dialog.selectedCompanyId = null;
    dialog.onAddCompany();
    expect(adminServiceMock.updateUser).not.toHaveBeenCalled();
  });

  it('removes a company association', () => {
    const { dialog, adminServiceMock } = build({
      id: 1,
      name: 'Ana',
      email: 'ana@zia.co',
      companies: [{ id: 1 }, { id: 2 }],
    });

    dialog.onRemoveCompany(2);

    expect(adminServiceMock.updateUser).toHaveBeenCalledWith(1, expect.objectContaining({ companies: [1] }));
    expect(dialog.hasChanges).toBe(true);
  });

  it('closes with hasChanges as the result', () => {
    const { dialog } = build({ id: 1, name: 'Ana', email: 'ana@zia.co', companies: [] });
    const dialogRef = (dialog as any).dialogRef;
    dialog.hasChanges = true;
    dialog.onClose();
    expect(dialogRef.close).toHaveBeenCalledWith(true);
  });
});
