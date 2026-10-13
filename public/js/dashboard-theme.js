(() => {
    let savedTheme;
    try { savedTheme = localStorage.getItem('theme'); } catch { /* Use the default theme. */ }
    const useLightTheme = savedTheme === 'light';

    document.documentElement.classList.toggle('light', useLightTheme);
    document.documentElement.classList.toggle('dark', !useLightTheme);
})();
