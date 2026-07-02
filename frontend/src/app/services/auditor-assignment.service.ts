import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class AuditorAssignmentService {
    private apiUrl = `${environment.apiUrl}/admin/auditor-assignments`;
    private http = inject(HttpClient);

    getAssignments(): Observable<any[]> {
        return this.http.get<any[]>(this.apiUrl);
    }

    grant(data: { user_id: number; period_id: number; expires_at?: string | null }): Observable<any> {
        return this.http.post(this.apiUrl, data);
    }

    revoke(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/${id}`);
    }
}
