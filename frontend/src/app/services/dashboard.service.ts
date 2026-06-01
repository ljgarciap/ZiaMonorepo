import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class DashboardService {
    private apiUrl = `${environment.apiUrl}/dashboard`;
    private http = inject(HttpClient);

    getSummary(companyId: number, periodId: number): Observable<any> {
        let params = new HttpParams()
            .set('company_id', companyId.toString())
            .set('period_id', periodId.toString());

        return this.http.get(`${this.apiUrl}/summary`, { params });
    }

    getTrends(companyId: number): Observable<any> {
        let params = new HttpParams().set('company_id', companyId.toString());
        return this.http.get(`${this.apiUrl}/trends`, { params });
    }

    downloadPdf(periodId: number): Observable<Blob> {
        return this.http.get(`${environment.apiUrl}/reports/periods/${periodId}/pdf`, { responseType: 'blob' });
    }

    downloadExcel(periodId: number): Observable<Blob> {
        return this.http.get(`${environment.apiUrl}/reports/periods/${periodId}/excel`, { responseType: 'blob' });
    }
}
