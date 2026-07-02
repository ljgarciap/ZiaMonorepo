import { Routes } from '@angular/router';
import { LoginComponent } from './components/login/login';
import { RegisterComponent } from './components/register/register';
import { DashboardComponent } from './components/dashboard/dashboard';
import { DashboardContentComponent } from './components/dashboard/dashboard-content';
import { FormComponent } from './components/form/form';
import { authGuard } from './guards/auth-guard';
import { roleGuard } from './guards/role-guard';
import { CompanyManagementComponent } from './components/admin/company-management/company-management';
import { UserManagementComponent } from './components/admin/user-management/user-management';
import { SectorManagementComponent } from './components/admin/sector-management/sector-management';
import { MetadataManagementComponent } from './components/admin/metadata-management/metadata-management';
import { AdminPeriodsComponent } from './components/admin/admin-periods/admin-periods';
import { AdminMyCompanyComponent } from './components/admin/admin-my-company/admin-my-company';
import { OperationalUnitManagementComponent } from './components/admin/operational-unit-management/operational-unit-management';
import { SuperadminDashboardComponent } from './components/admin/superadmin-dashboard/superadmin-dashboard';

export const routes: Routes = [
    { path: 'login', component: LoginComponent },
    { path: 'register', component: RegisterComponent },
    {
        path: '',
        component: DashboardComponent, // Dashboard is the layout
        canActivate: [authGuard],
        children: [
            { path: 'dashboard', component: DashboardContentComponent }, // Default view
            {
                path: 'history',
                loadComponent: () => import('./components/history/history').then(m => m.HistoryComponent)
            },
            {
                path: 'form',
                component: FormComponent,
                canActivate: [roleGuard],
                // SA-01: superadmin no captura datos operativos. Auditor (solo lectura) e
                // iot_tech (sin acceso a datos de emisiones) tampoco escriben en el formulario.
                data: { roles: ['admin', 'user'] }
            },
            {
                path: 'smart-intake',
                loadComponent: () => import('./components/smart-intake/smart-intake').then(m => m.SmartIntakeComponent),
                canActivate: [roleGuard],
                data: { roles: ['admin', 'user'] }
            },
            {
                path: 'live',
                loadComponent: () => import('./components/zia-live/zia-live').then(m => m.ZiaLiveComponent)
            },
            {
                path: 'simulator',
                loadComponent: () => import('./components/simulator/simulator').then(m => m.SimulatorComponent)
            },
            {
                path: 'iot/devices',
                loadComponent: () => import('./components/iot/device-management/device-management').then(m => m.IotDeviceManagementComponent),
                canActivate: [roleGuard],
                data: { roles: ['iot_tech', 'superadmin'] } // Técnico IoT: registro/config/calibración de dispositivos
            },
            {
                path: 'audit/observations',
                loadComponent: () => import('./components/auditor/observations/observations').then(m => m.AuditObservationsComponent),
                canActivate: [roleGuard],
                data: { roles: ['auditor', 'admin', 'superadmin'] } // Auditor: hallazgos/dictamen; Admin/Superadmin: ver y moderar
            },


            // Admin Routes
            {
                path: 'admin/platform',
                component: SuperadminDashboardComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] } // SA-17: dashboard ejecutivo global
            },
            {
                path: 'admin/companies',
                component: CompanyManagementComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] }          // A02: write-capable view — superadmin only
            },
            {
                path: 'admin/my-company',
                component: AdminMyCompanyComponent,
                canActivate: [roleGuard],
                data: { roles: ['admin'] }               // A02: read-only view for admin
            },
            {
                path: 'admin/periods',
                component: AdminPeriodsComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin', 'admin'] } // A11: period close/reopen
            },
            {
                path: 'admin/operational-units',
                component: OperationalUnitManagementComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin', 'admin'] } // A03: manage operational units
            },
            {
                path: 'admin/sectors',
                component: SectorManagementComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] }
            },
            {
                path: 'admin/users',
                component: UserManagementComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin', 'admin'] }
            },
            {
                path: 'admin/metadata',
                component: MetadataManagementComponent,
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] }
            },
            {
                path: 'admin/units',
                loadComponent: () => import('./components/admin/unit-management/unit-management').then(m => m.UnitManagementComponent),
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] }
            },
            {
                path: 'admin/scopes',
                loadComponent: () => import('./components/admin/scope-management/scope-management').then(m => m.ScopeManagementComponent),
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] }
            },
            {
                path: 'admin/audit',
                loadComponent: () => import('./components/admin/audit-logs/audit-logs').then(m => m.AuditLogsComponent),
                canActivate: [roleGuard],
                data: { roles: ['superadmin', 'admin'] }
            },
            {
                path: 'admin/iot-devices',
                loadComponent: () => import('./components/admin/iot-devices/iot-devices').then(m => m.IotDevicesComponent),
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] } // SA-12
            },
            {
                path: 'admin/questionnaires',
                loadComponent: () => import('./components/admin/questionnaire-management/questionnaire-management').then(m => m.QuestionnaireMgmtComponent),
                canActivate: [roleGuard],
                data: { roles: ['superadmin'] } // SA-10
            },

            { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
        ]
    },
    // Fallback
    { path: '**', redirectTo: '/dashboard' }
];
