import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';
import { of } from 'rxjs';

import { IotDeviceManagementComponent } from './device-management';
import { AuthService } from '../../../services/auth';
import { IotDeviceService } from '../../../services/iot-device.service';
import { createMockAuthService } from '../../../../testing/mocks';

describe('IotDeviceManagementComponent', () => {
  let component: IotDeviceManagementComponent;
  let fixture: ComponentFixture<IotDeviceManagementComponent>;
  let authMock: ReturnType<typeof createMockAuthService>;
  let iotServiceMock: {
    getDevices: ReturnType<typeof vi.fn>;
    getLiveAlerts: ReturnType<typeof vi.fn>;
    createDevice: ReturnType<typeof vi.fn>;
    updateDevice: ReturnType<typeof vi.fn>;
    deleteDevice: ReturnType<typeof vi.fn>;
    calibrateDevice: ReturnType<typeof vi.fn>;
    resolveAlert: ReturnType<typeof vi.fn>;
  };

  const mockDevices = [
    { id: 1, name: 'Medidor Piso 1', type: 'energy', location: 'Piso 1', readings_count: 10, pending_alerts_count: 0, last_calibrated_at: null },
  ];
  const mockAlerts = [
    { id: 5, message: 'Consumo fuera de horario', device: { name: 'Medidor Piso 1' } },
  ];

  beforeEach(async () => {
    authMock = createMockAuthService();
    authMock.currentContext.set({ type: 'company', id: 42, label: 'ECONOVA', role: 'iot_tech' });

    iotServiceMock = {
      getDevices: vi.fn(() => of(mockDevices)),
      getLiveAlerts: vi.fn(() => of({ alerts: mockAlerts })),
      createDevice: vi.fn(() => of({ id: 2 })),
      updateDevice: vi.fn(() => of({})),
      deleteDevice: vi.fn(() => of({})),
      calibrateDevice: vi.fn(() => of({})),
      resolveAlert: vi.fn(() => of({})),
    };

    await TestBed.configureTestingModule({
      imports: [IotDeviceManagementComponent, NoopAnimationsModule],
      providers: [
        { provide: AuthService, useValue: authMock },
        { provide: IotDeviceService, useValue: iotServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(IotDeviceManagementComponent);
    component = fixture.componentInstance;
  });

  it('creates the component', () => {
    expect(component).toBeTruthy();
  });

  it('loads devices and alerts for the technician company on init', () => {
    fixture.detectChanges();

    expect(iotServiceMock.getDevices).toHaveBeenCalledWith(42);
    expect(iotServiceMock.getLiveAlerts).toHaveBeenCalled();
    expect(component.devices).toEqual(mockDevices);
    expect(component.alerts).toEqual(mockAlerts);
  });

  it('does not call getDevices when there is no company context', () => {
    authMock.currentContext.set(null);
    fixture.detectChanges();

    expect(iotServiceMock.getDevices).not.toHaveBeenCalled();
  });

  it('renders registered devices in the table', () => {
    fixture.detectChanges();
    const text = (fixture.nativeElement as HTMLElement).textContent || '';
    expect(text).toContain('Medidor Piso 1');
  });

  it('createDevice calls the service with the company id and resets the form', () => {
    fixture.detectChanges();
    component.newDevice = { name: 'Nuevo Sensor', type: 'water', location: '', unit: '', thingsboard_id: '' };

    component.createDevice();

    expect(iotServiceMock.createDevice).toHaveBeenCalledWith(42, expect.objectContaining({ name: 'Nuevo Sensor' }));
    expect(component.newDevice.name).toBe('');
  });

  it('createDevice does nothing without a name', () => {
    fixture.detectChanges();
    component.newDevice = { name: '', type: 'energy', location: '', unit: '', thingsboard_id: '' };

    component.createDevice();

    expect(iotServiceMock.createDevice).not.toHaveBeenCalled();
  });

  it('calibrate prompts for notes and calls the service', () => {
    vi.spyOn(window, 'prompt').mockReturnValue('Sensor OK');
    fixture.detectChanges();

    component.calibrate(mockDevices[0]);

    expect(iotServiceMock.calibrateDevice).toHaveBeenCalledWith(1, 'Sensor OK');
  });

  it('deleteDevice asks for confirmation before calling the service', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    fixture.detectChanges();

    component.deleteDevice(mockDevices[0]);

    expect(iotServiceMock.deleteDevice).toHaveBeenCalledWith(1);
  });

  it('deleteDevice does nothing when confirmation is declined', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    fixture.detectChanges();

    component.deleteDevice(mockDevices[0]);

    expect(iotServiceMock.deleteDevice).not.toHaveBeenCalled();
  });

  it('resolveAlert prompts for a diagnostic note and calls the service', () => {
    vi.spyOn(window, 'prompt').mockReturnValue('Falso positivo');
    fixture.detectChanges();

    component.resolveAlert(mockAlerts[0]);

    expect(iotServiceMock.resolveAlert).toHaveBeenCalledWith(5, 'Falso positivo');
  });
});
