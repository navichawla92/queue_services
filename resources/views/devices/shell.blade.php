<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $type === 'kiosk' ? __('Check-in') : __('Lobby display') }}</title>
    @vite(['resources/css/app.css'])
    <style>body { cursor: none; }</style>
</head>
<body class="min-h-screen bg-slate-900 text-white antialiased">
    {{-- Pairing / status shell. The live display (phase 9) and kiosk (phase 7) replace the "paired" panel. --}}
    <div id="app" class="flex min-h-screen items-center justify-center p-8 text-center" data-type="{{ $type }}">
        <div id="pairing" hidden>
            <p class="text-2xl text-slate-300">{{ __('To connect this screen, go to Admin → Devices and enter the code:') }}</p>
            <p id="pairing-code" class="mt-6 font-mono text-8xl font-bold tracking-[0.3em]"></p>
            <p id="pairing-hint" class="mt-6 text-slate-400"></p>
            <button id="unlock-audio" class="mt-8 rounded bg-white/10 px-6 py-3 text-lg">{{ __('Tap once to enable sound') }}</button>
        </div>
        <div id="paired" hidden>
            <p id="paired-name" class="text-4xl font-semibold"></p>
            <p id="paired-location" class="mt-2 text-2xl text-slate-300"></p>
        </div>
        <div id="unavailable" hidden>
            <p class="text-4xl font-semibold">{{ __('Service unavailable') }}</p>
        </div>
    </div>

    <script>
    (() => {
        const type = document.getElementById('app').dataset.type;
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const KEY = 'device_token_' + type;
        const store = {
            get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
            set: (k, v) => { try { localStorage.setItem(k, v); } catch {} },
            del: (k) => { try { localStorage.removeItem(k); } catch {} },
        };
        const show = (id) => ['pairing', 'paired', 'unavailable'].forEach(p => document.getElementById(p).hidden = p !== id);
        const post = (url, body) => fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(body),
        });

        document.getElementById('unlock-audio').addEventListener('click', (e) => {
            try { const ctx = new (window.AudioContext || window.webkitAudioContext)(); ctx.resume(); window.__audioCtx = ctx; } catch {}
            e.target.hidden = true;
        });

        async function checkPaired() {
            const token = store.get(KEY);
            const res = await fetch('{{ route('display.device.me') }}', {
                credentials: 'same-origin',
                headers: token ? {'Authorization': 'Bearer ' + token, 'Accept': 'application/json'} : {'Accept': 'application/json'},
            }).catch(() => null);

            if (!res) return 'offline';
            if (res.status === 401) { store.del(KEY); return 'unpaired'; }
            if (res.status === 503) { show('unavailable'); return 'unavailable'; }
            if (!res.ok) return 'offline';

            const me = await res.json();
            const apps = { kiosk: '{{ route('display.kiosk.app') }}', display: '{{ route('display.app') }}' };
            if (apps[me.device.type]) { window.location.replace(apps[me.device.type]); return 'paired'; }
            document.getElementById('paired-name').textContent = me.device.name;
            document.getElementById('paired-location').textContent = me.tenant.name + ' — ' + me.location.name;
            show('paired');
            return 'paired';
        }

        async function startPairing() {
            const res = await post('{{ route('display.pairing.request') }}', {type});
            if (!res.ok) { setTimeout(startPairing, 10000); return; }
            const p = await res.json();
            document.getElementById('pairing-code').textContent = p.code;
            document.getElementById('pairing-hint').textContent = '{{ __('Code expires in :minutes minutes.', ['minutes' => \App\Domain\Access\DevicePairingService::TTL_MINUTES]) }}';
            show('pairing');

            const poll = setInterval(async () => {
                const r = await post('{{ route('display.pairing.status') }}', {code: p.code, claim: p.claim}).catch(() => null);
                if (!r || !r.ok) return;
                const s = await r.json();
                if (s.status === 'paired') { clearInterval(poll); store.set(KEY, s.token); boot(); }
                if (s.status === 'expired' || s.status === 'invalid') { clearInterval(poll); startPairing(); }
            }, 3000);
        }

        async function boot() {
            const state = await checkPaired();
            if (state === 'unpaired') startPairing();
        }

        boot();
        // Re-check every 30 s: a revoked device drops back to pairing within a minute.
        setInterval(async () => {
            if (!document.getElementById('pairing').hidden) return;
            if (await checkPaired() === 'unpaired') startPairing();
        }, 30000);
    })();
    </script>
</body>
</html>
