export function applyTheme(theme = 'auto') {
    const root = document.documentElement;
    const preferDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = theme === 'dark' || (theme === 'auto' && preferDark);
    root.classList.toggle('dark', dark);
    root.dataset.theme = dark ? 'dark' : 'light';
}

export function oraTheme(theme = 'auto') {
    return theme === 'dark' || theme === 'light' ? theme : 'auto';
}
