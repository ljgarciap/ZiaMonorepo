import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class MasterDataService {
    private apiUrl = environment.apiUrl;

    constructor(private http: HttpClient) { }

    getCompanies(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies`);
    }

    getPeriods(companyId: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies/${companyId}/periods`);
    }

    getEmissionFactors(companyId?: number): Observable<any[]> {
        const url = companyId
            ? `${this.apiUrl}/dictionaries/factors?company_id=${companyId}`
            : `${this.apiUrl}/dictionaries/factors`;
        return this.http.get<any[]>(url);
    }
}
