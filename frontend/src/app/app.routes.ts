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
            { path: 'form', component: FormComponent },
            {
                path: 'smart-intake',
                loadComponent: () => import('./components/smart-intake/smart-intake').then(m => m.SmartIntakeComponent)
            },
            {
                path: 'live',
                loadComponent: () => import('./components/zia-live/zia-live').then(m => m.ZiaLiveComponent)
            },
            {
                path: 'simulator',
                loadComponent: () => import('./components/simulator/simulator').then(m => m.SimulatorComponent)
            },


            // Admin Routes
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

            { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
        ]
    },
    // Fallback
    { path: '**', redirectTo: '/dashboard' }
];
