/*
 * Onesiforo service worker.
 *
 * It exists so the app can be added to the phone home screen. It does not cache pages:
 * the box status must always be live. When the phone has no connection, page loads get a
 * short Italian message instead of the browser's error page.
 */
const OFFLINE_PAGE = `<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nessuna connessione</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#f4f4f5;color:#18181b}
main{max-width:22rem;padding:2rem;text-align:center}h1{font-size:1.25rem}p{color:#52525b;line-height:1.5}
button{margin-top:1rem;padding:.8rem 1.4rem;border:0;border-radius:.75rem;background:#6366f1;color:#fff;font-size:1rem}</style></head>
<body><main><h1>Il telefono non è connesso a Internet</h1>
<p>Controlla il Wi-Fi o i dati mobili, poi riprova.</p>
<button onclick="location.reload()">Riprova</button></main></body></html>`;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => new Response(OFFLINE_PAGE, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        })),
    );
});
