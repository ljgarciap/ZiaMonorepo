/**
 * Shared mock factories for ZIA Carbon Control unit tests.
 *
 * Use the factory functions (createMock*) in beforeEach so every test
 * starts with fresh signal instances and reset vi.fn() call-counts.
 *
 * The named constants (mockAuthService, etc.) are convenience exports
 * for one-off cases — prefer the factories in component tests.
 */
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { vi } from 'vitest';

// ---------------------------------------------------------------------------
// AuthService
// ---------------------------------------------------------------------------
export function createMockAuthService() {
  return {
    currentUser: signal<any>(null),
    currentContext: signal<any>(null),
    availableContexts: signal<any[]>([]),
    login: vi.fn(() => of({ token: 'test-token', user: { id: 1, name: 'Test User' } })),
    logout: vi.fn(),
    getToken: vi.fn(() => 'test-token'),
    isLoggedIn: vi.fn(() => true),
    selectContext: vi.fn(),
  };
}
export const mockAuthService = createMockAuthService();

// ---------------------------------------------------------------------------
// ContextService
// ---------------------------------------------------------------------------
export function createMockContextService() {
  return {
    selectedCompany: signal<any>(null),
    selectedPeriod: signal<any>(null),
    setCompany: vi.fn(),
    setPeriod: vi.fn(),
    reset: vi.fn(),
  };
}
export const mockContextService = createMockContextService();

// ---------------------------------------------------------------------------
// CarbonService
// ---------------------------------------------------------------------------
export function createMockCarbonService() {
  return {
    getHistory: vi.fn(() => of({ data: [], total: 0 })),
    storeEmission: vi.fn(() => of({ id: 1, calculated_co2e: 1.5 })),
  };
}
export const mockCarbonService = createMockCarbonService();

// ---------------------------------------------------------------------------
// MasterDataService
// ---------------------------------------------------------------------------
export function createMockMasterDataService() {
  return {
    getEmissionFactors: vi.fn(() => of([])),
    getQuestionnaire: vi.fn(() => of([])),
    getCompanies: vi.fn(() => of([])),
    getPeriods: vi.fn(() => of([])),
  };
}
export const mockMasterDataService = createMockMasterDataService();

// ---------------------------------------------------------------------------
// DashboardService
// ---------------------------------------------------------------------------
export function createMockDashboardService() {
  return {
    getSummary: vi.fn(() => of({ scope_1: 0, scope_2: 0, scope_3: 0, huella_total: 0 })),
    getTrends: vi.fn(() => of([])),
    downloadPdf: vi.fn(() => of(new Blob())),
    downloadExcel: vi.fn(() => of(new Blob())),
  };
}
export const mockDashboardService = createMockDashboardService();

// ---------------------------------------------------------------------------
// ThemeService
// ---------------------------------------------------------------------------
export function createMockThemeService() {
  return {
    isDarkMode: signal<boolean>(true),
    toggleTheme: vi.fn(),
  };
}
export const mockThemeService = createMockThemeService();
