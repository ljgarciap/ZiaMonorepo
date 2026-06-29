import { TestBed } from '@angular/core/testing';
import { ThemeService } from './theme.service';

describe('ThemeService', () => {
  function build() {
    TestBed.configureTestingModule({});
    return TestBed.inject(ThemeService);
  }

  beforeEach(() => {
    localStorage.clear();
    document.body.className = '';
  });

  it('defaults to dark mode when localStorage has no saved preference', () => {
    const svc = build();
    expect(svc.isDarkMode()).toBe(true);
  });

  it('restores dark mode from localStorage when saved value is "dark"', () => {
    localStorage.setItem('zia-theme', 'dark');
    const svc = build();
    expect(svc.isDarkMode()).toBe(true);
  });

  it('restores light mode from localStorage when saved value is "light"', () => {
    localStorage.setItem('zia-theme', 'light');
    const svc = build();
    expect(svc.isDarkMode()).toBe(false);
  });

  it('toggleTheme() flips isDarkMode and persists to localStorage', () => {
    const svc = build();
    expect(svc.isDarkMode()).toBe(true); // initial: dark
    svc.toggleTheme();
    expect(svc.isDarkMode()).toBe(false);
    expect(localStorage.getItem('zia-theme')).toBe('light');
    svc.toggleTheme();
    expect(svc.isDarkMode()).toBe(true);
    expect(localStorage.getItem('zia-theme')).toBe('dark');
  });

  it('applies dark-theme class to body when dark mode is active', () => {
    localStorage.setItem('zia-theme', 'dark');
    build();
    expect(document.body.classList.contains('dark-theme')).toBe(true);
    expect(document.body.classList.contains('light-theme')).toBe(false);
  });

  it('applies light-theme class to body when light mode is active', () => {
    localStorage.setItem('zia-theme', 'light');
    build();
    expect(document.body.classList.contains('light-theme')).toBe(true);
    expect(document.body.classList.contains('dark-theme')).toBe(false);
  });
});
