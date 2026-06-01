import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface EmissionHistoryParams {
    page?: number;
    per_page?: number;
    search?: string;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
}

@Injectable({
    providedIn: 'root'
})
export class CarbonService {
    private apiUrl = environment.apiUrl;
    private http = inject(HttpClient);

    getHistory(companyId: number, params: EmissionHistoryParams): Observable<any> {
        let httpParams = new HttpParams();

        if (params.page) httpParams = httpParams.set('page', params.page);
        if (params.per_page) httpParams = httpParams.set('per_page', params.per_page);
        if (params.search) httpParams = httpParams.set('search', params.search);
        if (params.sort_by) httpParams = httpParams.set('sort_by', params.sort_by);
        if (params.sort_dir) httpParams = httpParams.set('sort_dir', params.sort_dir);

        return this.http.get(`${this.apiUrl}/companies/${companyId}/emissions/history`, { params: httpParams });
    }

    storeEmission(periodId: number, data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/periods/${periodId}/emissions`, data);
    }
}
