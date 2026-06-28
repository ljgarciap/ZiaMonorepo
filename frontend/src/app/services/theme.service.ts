import { Injectable, signal } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class ThemeService {
    private readonly STORAGE_KEY = 'zia-theme';
    isDarkMode = signal<boolean>(true);

    constructor() {
        this.initializeTheme();
    }

    toggleTheme() {
        this.isDarkMode.update(dark => !dark);
        this.applyTheme(this.isDarkMode());
        localStorage.setItem(this.STORAGE_KEY, this.isDarkMode() ? 'dark' : 'light');
    }

    private initializeTheme() {
        const saved = localStorage.getItem(this.STORAGE_KEY);
        const shouldBeDark = saved !== 'light'; // dark is default
        this.isDarkMode.set(shouldBeDark);
        this.applyTheme(shouldBeDark);
    }

    private applyTheme(dark: boolean) {
        document.body.classList.toggle('dark-theme', dark);
        document.body.classList.toggle('light-theme', !dark);
    }
}
