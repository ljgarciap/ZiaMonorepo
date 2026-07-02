import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';

import { AdminService } from './admin.service';
import { environment } from '../../environments/environment';

describe('AdminService', () => {
  let service: AdminService;
  let http: HttpTestingController;
  const apiUrl = `${environment.apiUrl}/admin`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(AdminService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  // ─── Companies & Periods ────────────────────────────────────────────────

  it('getCompanies() GETs /admin/companies', () => {
    service.getCompanies().subscribe();
    const req = http.expectOne(`${apiUrl}/companies`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('createCompany() POSTs to /admin/companies', () => {
    service.createCompany({ name: 'Acme' }).subscribe();
    const req = http.expectOne(`${apiUrl}/companies`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ name: 'Acme' });
    req.flush({});
  });

  it('updateCompany() PUTs to /admin/companies/:id', () => {
    service.updateCompany(1, { name: 'Acme 2' }).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1`);
    expect(req.request.method).toBe('PUT');
    req.flush({});
  });

  it('deleteCompany() DELETEs /admin/companies/:id', () => {
    service.deleteCompany(1).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1`);
    expect(req.request.method).toBe('DELETE');
    req.flush({});
  });

  it('addPeriod() POSTs to /admin/companies/:id/periods', () => {
    service.addPeriod(1, { year: 2026 }).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1/periods`);
    expect(req.request.method).toBe('POST');
    req.flush({});
  });

  it('updatePeriod() PUTs to /admin/periods/:id', () => {
    service.updatePeriod(5, { year: 2027 }).subscribe();
    const req = http.expectOne(`${apiUrl}/periods/5`);
    expect(req.request.method).toBe('PUT');
    req.flush({});
  });

  it('deletePeriod() DELETEs /admin/periods/:id', () => {
    service.deletePeriod(5).subscribe();
    const req = http.expectOne(`${apiUrl}/periods/5`);
    expect(req.request.method).toBe('DELETE');
    req.flush({});
  });

  // ─── Users ──────────────────────────────────────────────────────────────

  it('getUsers() GETs /admin/users', () => {
    service.getUsers().subscribe();
    http.expectOne(`${apiUrl}/users`).flush([]);
  });

  it('createUser() POSTs to /admin/users', () => {
    service.createUser({ name: 'X' }).subscribe();
    http.expectOne(`${apiUrl}/users`).flush({});
  });

  it('updateUser() PUTs to /admin/users/:id', () => {
    service.updateUser(2, { name: 'Y' }).subscribe();
    http.expectOne(`${apiUrl}/users/2`).flush({});
  });

  it('deleteUser() DELETEs /admin/users/:id', () => {
    service.deleteUser(2).subscribe();
    http.expectOne(`${apiUrl}/users/2`).flush({});
  });

  it('restoreUser() POSTs to /admin/users/:id/restore', () => {
    service.restoreUser(2).subscribe();
    const req = http.expectOne(`${apiUrl}/users/2/restore`);
    expect(req.request.method).toBe('POST');
    req.flush({});
  });

  it('toggleUserBlock() POSTs to /admin/users/:id/toggle-block', () => {
    service.toggleUserBlock(2).subscribe();
    const req = http.expectOne(`${apiUrl}/users/2/toggle-block`);
    expect(req.request.method).toBe('POST');
    req.flush({});
  });

  // ─── Master Data: categories & factors ─────────────────────────────────

  it('getCategories() GETs /admin/categories', () => {
    service.getCategories().subscribe();
    http.expectOne(`${apiUrl}/categories`).flush([]);
  });

  it('createCategory() POSTs to /admin/categories', () => {
    service.createCategory({ name: 'Cat' }).subscribe();
    http.expectOne(`${apiUrl}/categories`).flush({});
  });

  it('updateCategory() PUTs to /admin/categories/:id', () => {
    service.updateCategory(1, { name: 'Cat2' }).subscribe();
    http.expectOne(`${apiUrl}/categories/1`).flush({});
  });

  it('deleteCategory() DELETEs /admin/categories/:id', () => {
    service.deleteCategory(1).subscribe();
    http.expectOne(`${apiUrl}/categories/1`).flush({});
  });

  it('getFactors() GETs /admin/factors', () => {
    service.getFactors().subscribe();
    http.expectOne(`${apiUrl}/factors`).flush([]);
  });

  it('createFactor() POSTs to /admin/factors', () => {
    service.createFactor({ name: 'F' }).subscribe();
    http.expectOne(`${apiUrl}/factors`).flush({});
  });

  it('updateFactor() PUTs to /admin/factors/:id', () => {
    service.updateFactor(1, { name: 'F2' }).subscribe();
    http.expectOne(`${apiUrl}/factors/1`).flush({});
  });

  it('deleteFactor() DELETEs /admin/factors/:id', () => {
    service.deleteFactor(1).subscribe();
    http.expectOne(`${apiUrl}/factors/1`).flush({});
  });

  // ─── Sectors ────────────────────────────────────────────────────────────

  it('getSectors() GETs /admin/sectors', () => {
    service.getSectors().subscribe();
    http.expectOne(`${apiUrl}/sectors`).flush([]);
  });

  it('createSector() POSTs to /admin/sectors', () => {
    service.createSector({ name: 'S' }).subscribe();
    http.expectOne(`${apiUrl}/sectors`).flush({});
  });

  it('updateSector() PUTs to /admin/sectors/:id', () => {
    service.updateSector(1, { name: 'S2' }).subscribe();
    http.expectOne(`${apiUrl}/sectors/1`).flush({});
  });

  it('deleteSector() DELETEs /admin/sectors/:id', () => {
    service.deleteSector(1).subscribe();
    http.expectOne(`${apiUrl}/sectors/1`).flush({});
  });

  // ─── Formulas ───────────────────────────────────────────────────────────

  it('getFormulas() GETs /admin/formulas', () => {
    service.getFormulas().subscribe();
    http.expectOne(`${apiUrl}/formulas`).flush([]);
  });

  it('createFormula() POSTs to /admin/formulas', () => {
    service.createFormula({ name: 'Fm' }).subscribe();
    http.expectOne(`${apiUrl}/formulas`).flush({});
  });

  it('updateFormula() PUTs to /admin/formulas/:id', () => {
    service.updateFormula(1, { name: 'Fm2' }).subscribe();
    http.expectOne(`${apiUrl}/formulas/1`).flush({});
  });

  it('deleteFormula() DELETEs /admin/formulas/:id', () => {
    service.deleteFormula(1).subscribe();
    http.expectOne(`${apiUrl}/formulas/1`).flush({});
  });

  // ─── Measurement Units ──────────────────────────────────────────────────

  it('getUnits() GETs /admin/units', () => {
    service.getUnits().subscribe();
    http.expectOne(`${apiUrl}/units`).flush([]);
  });

  it('createUnit() POSTs to /admin/units', () => {
    service.createUnit({ name: 'kg' }).subscribe();
    http.expectOne(`${apiUrl}/units`).flush({});
  });

  it('updateUnit() PUTs to /admin/units/:id', () => {
    service.updateUnit(1, { name: 'kg2' }).subscribe();
    http.expectOne(`${apiUrl}/units/1`).flush({});
  });

  it('deleteUnit() DELETEs /admin/units/:id', () => {
    service.deleteUnit(1).subscribe();
    http.expectOne(`${apiUrl}/units/1`).flush({});
  });

  it('toggleUnit() POSTs to /admin/units/:id/toggle', () => {
    service.toggleUnit(1).subscribe();
    http.expectOne(`${apiUrl}/units/1/toggle`).flush({});
  });

  // ─── Tags ───────────────────────────────────────────────────────────────

  it('getTags() GETs /admin/tags', () => {
    service.getTags().subscribe();
    http.expectOne(`${apiUrl}/tags`).flush([]);
  });

  it('createTag() POSTs to /admin/tags', () => {
    service.createTag({ name: 'T' }).subscribe();
    http.expectOne(`${apiUrl}/tags`).flush({});
  });

  it('updateTag() PUTs to /admin/tags/:id', () => {
    service.updateTag(1, { name: 'T2' }).subscribe();
    http.expectOne(`${apiUrl}/tags/1`).flush({});
  });

  it('deleteTag() DELETEs /admin/tags/:id', () => {
    service.deleteTag(1).subscribe();
    http.expectOne(`${apiUrl}/tags/1`).flush({});
  });

  it('toggleTag() POSTs to /admin/tags/:id/toggle', () => {
    service.toggleTag(1).subscribe();
    http.expectOne(`${apiUrl}/tags/1/toggle`).flush({});
  });

  // ─── Aprobación metodológica ────────────────────────────────────────────

  it('approveMethodology() POSTs to /admin/companies/:id/approve-methodology', () => {
    service.approveMethodology(1).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1/approve-methodology`);
    expect(req.request.method).toBe('POST');
    req.flush({});
  });

  // ─── Period lifecycle ───────────────────────────────────────────────────

  it('sendPeriodToReview() POSTs to /admin/periods/:id/review', () => {
    service.sendPeriodToReview(1).subscribe();
    http.expectOne(`${apiUrl}/periods/1/review`).flush({});
  });

  it('archivePeriod() POSTs to /admin/periods/:id/archive', () => {
    service.archivePeriod(1).subscribe();
    http.expectOne(`${apiUrl}/periods/1/archive`).flush({});
  });

  it('closePeriod() POSTs to /admin/periods/:id/close', () => {
    service.closePeriod(1).subscribe();
    http.expectOne(`${apiUrl}/periods/1/close`).flush({});
  });

  it('reopenPeriod() POSTs to /admin/periods/:id/reopen', () => {
    service.reopenPeriod(1).subscribe();
    http.expectOne(`${apiUrl}/periods/1/reopen`).flush({});
  });

  // ─── IoT overview ───────────────────────────────────────────────────────

  it('getIotDevicesOverview() GETs /admin/iot-devices', () => {
    service.getIotDevicesOverview().subscribe();
    http.expectOne(`${apiUrl}/iot-devices`).flush({});
  });

  // ─── Scopes ─────────────────────────────────────────────────────────────

  it('getScopes() GETs /admin/scopes', () => {
    service.getScopes().subscribe();
    http.expectOne(`${apiUrl}/scopes`).flush([]);
  });

  it('createScope() POSTs to /admin/scopes', () => {
    service.createScope({ name: 'Scope 1' }).subscribe();
    http.expectOne(`${apiUrl}/scopes`).flush({});
  });

  it('updateScope() PUTs to /admin/scopes/:id', () => {
    service.updateScope(1, { name: 'Scope 1b' }).subscribe();
    http.expectOne(`${apiUrl}/scopes/1`).flush({});
  });

  it('deleteScope() DELETEs /admin/scopes/:id', () => {
    service.deleteScope(1).subscribe();
    http.expectOne(`${apiUrl}/scopes/1`).flush({});
  });

  // ─── Operational Units ──────────────────────────────────────────────────

  it('getOperationalUnits() GETs /companies/:id/units (no /admin prefix)', () => {
    service.getOperationalUnits(1).subscribe();
    const req = http.expectOne(`${environment.apiUrl}/companies/1/units`);
    expect(req.request.method).toBe('GET');
    req.flush([]);
  });

  it('createOperationalUnit() POSTs to /admin/companies/:id/units', () => {
    service.createOperationalUnit(1, { name: 'Piso 1' }).subscribe();
    http.expectOne(`${apiUrl}/companies/1/units`).flush({});
  });

  it('updateOperationalUnit() PUTs to /admin/companies/:id/units/:unitId', () => {
    service.updateOperationalUnit(1, 2, { name: 'Piso 2' }).subscribe();
    http.expectOne(`${apiUrl}/companies/1/units/2`).flush({});
  });

  it('deleteOperationalUnit() DELETEs /admin/companies/:id/units/:unitId', () => {
    service.deleteOperationalUnit(1, 2).subscribe();
    http.expectOne(`${apiUrl}/companies/1/units/2`).flush({});
  });

  it('assignUserToUnit() POSTs user_id to /admin/companies/:id/units/:unitId/assign', () => {
    service.assignUserToUnit(1, 2, 9).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1/units/2/assign`);
    expect(req.request.body).toEqual({ user_id: 9 });
    req.flush({});
  });

  it('unassignUserFromUnit() POSTs user_id to /admin/companies/:id/units/:unitId/unassign', () => {
    service.unassignUserFromUnit(1, 2, 9).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1/units/2/unassign`);
    expect(req.request.body).toEqual({ user_id: 9 });
    req.flush({});
  });

  // ─── Audit Logs ─────────────────────────────────────────────────────────

  it('getAuditLogs() GETs /admin/audit-logs with params', () => {
    service.getAuditLogs({ page: 2 }).subscribe();
    const req = http.expectOne(r => r.url === `${apiUrl}/audit-logs`);
    expect(req.request.params.get('page')).toBe('2');
    req.flush({});
  });

  // ─── Company Factors ────────────────────────────────────────────────────

  it('getCompanyFactors() GETs /admin/companies/:id/factors', () => {
    service.getCompanyFactors(1).subscribe();
    http.expectOne(`${apiUrl}/companies/1/factors`).flush([]);
  });

  it('updateCompanyFactors() PUTs factors payload', () => {
    service.updateCompanyFactors(1, [{ id: 1 }]).subscribe();
    const req = http.expectOne(`${apiUrl}/companies/1/factors`);
    expect(req.request.body).toEqual({ factors: [{ id: 1 }] });
    req.flush({});
  });

  // ─── Platform stats & report ────────────────────────────────────────────

  it('getPlatformStats() GETs /admin/platform-stats', () => {
    service.getPlatformStats().subscribe();
    http.expectOne(`${apiUrl}/platform-stats`).flush({});
  });

  it('downloadPlatformReport() opens the report URL in a new tab', () => {
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
    service.downloadPlatformReport();
    expect(openSpy).toHaveBeenCalledWith(`${apiUrl}/reports/platform`, '_blank');
  });

  // ─── Questionnaires ─────────────────────────────────────────────────────

  it('getQuestionnaires() GETs /admin/questionnaires', () => {
    service.getQuestionnaires().subscribe();
    http.expectOne(`${apiUrl}/questionnaires`).flush([]);
  });

  it('getQuestionnaire() GETs /admin/questionnaires/:id', () => {
    service.getQuestionnaire(1).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1`).flush({});
  });

  it('createQuestionnaire() POSTs to /admin/questionnaires', () => {
    service.createQuestionnaire({ name: 'Q' }).subscribe();
    http.expectOne(`${apiUrl}/questionnaires`).flush({});
  });

  it('updateQuestionnaire() PUTs to /admin/questionnaires/:id', () => {
    service.updateQuestionnaire(1, { name: 'Q2' }).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1`).flush({});
  });

  it('deleteQuestionnaire() DELETEs /admin/questionnaires/:id', () => {
    service.deleteQuestionnaire(1).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1`).flush({});
  });

  it('publishQuestionnaire() POSTs to /admin/questionnaires/:id/publish', () => {
    service.publishQuestionnaire(1).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/publish`).flush({});
  });

  it('archiveQuestionnaire() POSTs to /admin/questionnaires/:id/archive', () => {
    service.archiveQuestionnaire(1).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/archive`).flush({});
  });

  it('newQuestionnaireVersion() POSTs to /admin/questionnaires/:id/version', () => {
    service.newQuestionnaireVersion(1).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/version`).flush({});
  });

  it('addQuestion() POSTs to /admin/questionnaires/:id/questions', () => {
    service.addQuestion(1, { text: 'Q?' }).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/questions`).flush({});
  });

  it('updateQuestion() PUTs to /admin/questionnaires/:id/questions/:qId', () => {
    service.updateQuestion(1, 2, { text: 'Q2?' }).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/questions/2`).flush({});
  });

  it('deleteQuestion() DELETEs /admin/questionnaires/:id/questions/:qId', () => {
    service.deleteQuestion(1, 2).subscribe();
    http.expectOne(`${apiUrl}/questionnaires/1/questions/2`).flush({});
  });

  it('reorderQuestions() POSTs order payload', () => {
    service.reorderQuestions(1, [{ id: 2, order: 1 }]).subscribe();
    const req = http.expectOne(`${apiUrl}/questionnaires/1/questions/reorder`);
    expect(req.request.body).toEqual({ order: [{ id: 2, order: 1 }] });
    req.flush({});
  });
});
