import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class AuditObservationService {
    private apiUrl = environment.apiUrl;
    private http = inject(HttpClient);

    getObservations(companyId: number, periodId: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies/${companyId}/periods/${periodId}/observations`);
    }

    createObservation(companyId: number, periodId: number, data: { body: string; verdict?: string | null }): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/periods/${periodId}/observations`, data);
    }

    deleteObservation(companyId: number, periodId: number, observationId: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/companies/${companyId}/periods/${periodId}/observations/${observationId}`);
    }
}
