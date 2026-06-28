import { Injectable, signal } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class ThemeService {
    // Dark mode is permanent — body.dark-theme is set in index.html
    readonly isDarkMode = signal<boolean>(true);
}
