import './stimulus_bootstrap.js';
import './styles/app.css';

function isSensitiveRoute() {
    const path = window.location.pathname;

    return path === '/'
        || path === '/login'
        || path === '/register'
        || path === '/logout'
        || path.startsWith('/app')
        || path.startsWith('/admin');
}

window.addEventListener('pageshow', (event) => {
    if (event.persisted && isSensitiveRoute()) {
        window.location.reload();
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
