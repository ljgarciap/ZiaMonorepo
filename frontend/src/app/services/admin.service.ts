import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class AdminService {
    private apiUrl = `${environment.apiUrl}/admin`;
    private http = inject(HttpClient);

    // Companies & Periods
    getCompanies(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies`);
    }

    createCompany(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies`, data);
    }

    updateCompany(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/companies/${id}`, data);
    }

    deleteCompany(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/companies/${id}`);
    }

    addPeriod(companyId: number, data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/periods`, data);
    }

    updatePeriod(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/periods/${id}`, data);
    }

    deletePeriod(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/periods/${id}`);
    }

    // Users
    getUsers(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/users`);
    }

    createUser(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/users`, data);
    }

    updateUser(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/users/${id}`, data);
    }

    deleteUser(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/users/${id}`);
    }

    restoreUser(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/users/${id}/restore`, {});
    }

    toggleUserBlock(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/users/${id}/toggle-block`, {});
    }

    // Master Data (SuperAdmin)
    getCategories(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/categories`);
    }

    createCategory(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/categories`, data);
    }

    updateCategory(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/categories/${id}`, data);
    }

    deleteCategory(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/categories/${id}`);
    }

    getFactors(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/factors`);
    }

    createFactor(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/factors`, data);
    }

    updateFactor(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/factors/${id}`, data);
    }

    deleteFactor(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/factors/${id}`);
    }

    // Sectors
    getSectors(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/sectors`);
    }

    createSector(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/sectors`, data);
    }

    updateSector(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/sectors/${id}`, data);
    }

    deleteSector(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/sectors/${id}`);
    }

    // Formulas
    getFormulas(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/formulas`);
    }

    createFormula(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/formulas`, data);
    }

    updateFormula(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/formulas/${id}`, data);
    }

    deleteFormula(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/formulas/${id}`);
    }

    // Measurement Units
    getUnits(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/units`);
    }

    createUnit(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/units`, data);
    }

    updateUnit(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/units/${id}`, data);
    }

    deleteUnit(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/units/${id}`);
    }

    toggleUnit(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/units/${id}/toggle`, {});
    }

    // Tags (catálogo global, spec 1.2.3)
    getTags(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/tags`);
    }

    createTag(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/tags`, data);
    }

    updateTag(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/tags/${id}`, data);
    }

    deleteTag(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/tags/${id}`);
    }

    toggleTag(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/tags/${id}/toggle`, {});
    }

    // Aprobación metodológica (spec 1.2.3)
    approveMethodology(companyId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/approve-methodology`, {});
    }

    // SA-15: period lifecycle
    sendPeriodToReview(periodId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/periods/${periodId}/review`, {});
    }

    archivePeriod(periodId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/periods/${periodId}/archive`, {});
    }

    // SA-12: IoT device overview
    getIotDevicesOverview(): Observable<any> {
        return this.http.get(`${this.apiUrl}/iot-devices`);
    }

    // Scopes
    getScopes(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/scopes`);
    }

    createScope(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/scopes`, data);
    }

    updateScope(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/scopes/${id}`, data);
    }

    deleteScope(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/scopes/${id}`);
    }

    // Period close / reopen (A11)
    closePeriod(periodId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/periods/${periodId}/close`, {});
    }

    reopenPeriod(periodId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/periods/${periodId}/reopen`, {});
    }

    // Operational Units (A03)
    getOperationalUnits(companyId: number): Observable<any[]> {
        return this.http.get<any[]>(`${environment.apiUrl}/companies/${companyId}/units`);
    }

    createOperationalUnit(companyId: number, data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/units`, data);
    }

    updateOperationalUnit(companyId: number, unitId: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/companies/${companyId}/units/${unitId}`, data);
    }

    deleteOperationalUnit(companyId: number, unitId: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/companies/${companyId}/units/${unitId}`);
    }

    assignUserToUnit(companyId: number, unitId: number, userId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/units/${unitId}/assign`, { user_id: userId });
    }

    unassignUserFromUnit(companyId: number, unitId: number, userId: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/units/${unitId}/unassign`, { user_id: userId });
    }

    // Audit Logs
    getAuditLogs(params: any = {}): Observable<any> {
        return this.http.get(`${this.apiUrl}/audit-logs`, { params });
    }

    // Company Factors
    getCompanyFactors(companyId: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies/${companyId}/factors`);
    }

    updateCompanyFactors(companyId: number, factors: any[]): Observable<any> {
        return this.http.put(`${this.apiUrl}/companies/${companyId}/factors`, { factors });
    }

    // SA-17: estadísticas globales de plataforma
    getPlatformStats(): Observable<any> {
        return this.http.get(`${this.apiUrl}/platform-stats`);
    }

    // SA-11: reporte PDF global
    downloadPlatformReport(): void {
        window.open(`${this.apiUrl}/reports/platform`, '_blank');
    }

    // SA-10: Gestión de cuestionarios
    getQuestionnaires(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/questionnaires`);
    }

    getQuestionnaire(id: number): Observable<any> {
        return this.http.get(`${this.apiUrl}/questionnaires/${id}`);
    }

    createQuestionnaire(data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires`, data);
    }

    updateQuestionnaire(id: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/questionnaires/${id}`, data);
    }

    deleteQuestionnaire(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/questionnaires/${id}`);
    }

    publishQuestionnaire(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires/${id}/publish`, {});
    }

    archiveQuestionnaire(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires/${id}/archive`, {});
    }

    newQuestionnaireVersion(id: number): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires/${id}/version`, {});
    }

    addQuestion(templateId: number, data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires/${templateId}/questions`, data);
    }

    updateQuestion(templateId: number, questionId: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/questionnaires/${templateId}/questions/${questionId}`, data);
    }

    deleteQuestion(templateId: number, questionId: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/questionnaires/${templateId}/questions/${questionId}`);
    }

    reorderQuestions(templateId: number, order: { id: number; order: number }[]): Observable<any> {
        return this.http.post(`${this.apiUrl}/questionnaires/${templateId}/questions/reorder`, { order });
    }
}
