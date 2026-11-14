/*
 * Onesiforo service worker.
 *
 * It exists so the app can be added to the phone home screen. It does not cache pages:
 * the box status must always be live. When a page cannot be loaded, the user gets a short
 * Italian message instead of the browser's error page: the page itself tells apart "the
 * phone is not connected" from "Onesiforo is not answering" (navigator.onLine), because the
 * service worker only knows that the request failed.
 */
const OFFLINE_PAGE = `<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Onesiforo non raggiungibile</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#f4f4f5;color:#18181b}
main{max-width:22rem;padding:2rem;text-align:center}h1{font-size:1.25rem}p{color:#52525b;line-height:1.5}
button{margin-top:1rem;min-height:2.75rem;padding:.8rem 1.4rem;border:0;border-radius:.75rem;background:#6366f1;color:#fff;font-size:1rem}</style></head>
<body><main>
<h1 id="title">Onesiforo non risponde</h1>
<p id="text">Non siamo riusciti ad aprire la pagina. Riprova tra poco.</p>
<button onclick="location.reload()">Riprova</button>
</main>
<script>
(function () {
    var messages = {
        offline: ['Il telefono non è connesso a Internet', 'Controlla il Wi-Fi o i dati mobili, poi riprova.'],
        unreachable: ['Onesiforo non risponde', 'Il telefono è connesso, ma Onesiforo in questo momento non risponde. Riprova tra poco.'],
    };
    function show() {
        var message = navigator.onLine === false ? messages.offline : messages.unreachable;
        document.title = message[0];
        document.getElementById('title').textContent = message[0];
        document.getElementById('text').textContent = message[1];
    }
    show();
    window.addEventListener('offline', show);
    window.addEventListener('online', function () { location.reload(); });
})();
</script>
</body></html>`;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => new Response(OFFLINE_PAGE, {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
        })),
    );
});
