import { FactorVersionsDialog } from './admin-dialogs';

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
