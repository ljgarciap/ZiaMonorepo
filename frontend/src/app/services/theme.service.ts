import { Injectable, signal } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class ThemeService {
    private readonly THEME_KEY = 'zia-prestige-theme';
    isDarkMode = signal<boolean>(false);

    constructor() {
        this.initializeTheme();
    }

    toggleTheme() {
        this.isDarkMode.update(dark => !dark);
        this.applyTheme(this.isDarkMode());
        localStorage.setItem(this.THEME_KEY, this.isDarkMode() ? 'dark' : 'light');
    }

    private initializeTheme() {
        const savedTheme = localStorage.getItem(this.THEME_KEY);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        const shouldBeDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);

        this.isDarkMode.set(shouldBeDark);
        this.applyTheme(shouldBeDark);
    }

    private applyTheme(dark: boolean) {
        if (dark) {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }
    }
}
