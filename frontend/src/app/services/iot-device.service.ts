import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class IotDeviceService {
    private apiUrl = environment.apiUrl;
    private http = inject(HttpClient);

    getDevices(companyId: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/companies/${companyId}/iot-devices`);
    }

    createDevice(companyId: number, data: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/companies/${companyId}/iot-devices`, data);
    }

    updateDevice(deviceId: number, data: any): Observable<any> {
        return this.http.put(`${this.apiUrl}/iot-devices/${deviceId}`, data);
    }

    deleteDevice(deviceId: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/iot-devices/${deviceId}`);
    }

    calibrateDevice(deviceId: number, notes: string): Observable<any> {
        return this.http.post(`${this.apiUrl}/iot-devices/${deviceId}/calibrate`, { notes });
    }

    resolveAlert(alertId: number, resolutionNote: string): Observable<any> {
        return this.http.post(`${this.apiUrl}/telemetry/alerts/${alertId}/resolve`, { resolution_note: resolutionNote });
    }

    getLiveAlerts(): Observable<any> {
        return this.http.get(`${this.apiUrl}/telemetry/live`);
    }
}
