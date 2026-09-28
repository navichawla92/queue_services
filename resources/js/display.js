/*
 * Lobby TV app (lobby-display spec). Renders from /display/snapshot; a ping
 * on the device's channel (or a poll) reloads it. Calls are detected by
 * diffing snapshots (call_key changes on every call/recall), so highlights
 * work the same over WebSocket or polling. The last snapshot is cached so a
 * power-cycled TV shows the queue immediately, even offline.
 */
import Alpine from 'alpinejs';
import './echo';

const CACHE_KEY = 'display_snapshot_v1';
const CONNECTED_POLL_MS = 30000; // config/layout safety net (spec: ≤ 30 s)
const OFFLINE_POLL_MS = 5000;
const FRESH_CALL_SECONDS = 90; // after an outage, only highlight recent calls

const store = {
    get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* storage unavailable */ } },
};

Alpine.data('lobbyDisplay', ({ snapshotUrl, pairUrl, channelKey }) => ({
    snap: null,
    online: false,
    stale: false,
    highlight: null,
    queue: [],
    seen: new Set(),
    clock: '',
    needsTap: false,
    audio: null,
    pollTimer: null,

    init() {
        const cached = store.get(CACHE_KEY);
        if (cached) this.apply(cached, { silent: true });

        this.load({ silent: true });
        this.tickClock();
        setInterval(() => this.tickClock(), 1000);
        this.schedulePoll(OFFLINE_POLL_MS);
        this.connect();
        this.keepAwake();
        this.prepareAudio();
    },

    async load({ silent = false } = {}) {
        try {
            const res = await fetch(snapshotUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.status === 401) { window.location.replace(pairUrl); return; }
            if (!res.ok) throw new Error(res.status);
            const snap = await res.json();
            this.stale = false;
            store.set(CACHE_KEY, snap);
            this.apply(snap, { silent });
        } catch {
            this.stale = true; // keep showing the last known state
        }
    },

    apply(snap, { silent }) {
        const now = Date.now();
        for (const t of snap.serving) {
            if (this.seen.has(t.call_key)) continue;
            this.seen.add(t.call_key);
            const age = (now - Date.parse(t.called_at)) / 1000;
            if (!silent && t.status === 'called' && age < FRESH_CALL_SECONDS) this.enqueue(t);
        }
        this.snap = snap;
        document.documentElement.style.setProperty('--brand', snap.brand.primary_color);
        document.documentElement.style.setProperty('--brand-accent', snap.brand.accent_color);
    },

    // Sequential call highlights: every call is shown, none skipped.
    enqueue(ticket) {
        this.queue.push(ticket);
        if (!this.highlight) this.nextHighlight();
    },

    nextHighlight() {
        this.highlight = this.queue.shift() || null;
        if (!this.highlight) return;
        if (this.snap?.config.chime) this.chime();
        window.dispatchEvent(new CustomEvent('display-call', { detail: this.highlight }));
        setTimeout(() => { this.highlight = null; this.$nextTick(() => this.nextHighlight()); },
            (this.snap?.config.highlight_seconds ?? 10) * 1000);
    },

    connect() {
        const echo = window.Echo;
        const conn = echo?.connector?.pusher?.connection;
        if (!echo || !conn || !channelKey) return;

        echo.channel('display.' + channelKey).listen('.display.changed', (e) => {
            if (e.reason === 'revoked') { window.location.replace(pairUrl); return; }
            this.load();
        });

        const setState = (state) => {
            const was = this.online;
            this.online = state === 'connected';
            if (this.online && !was) this.load(); // resync after reconnect
            this.schedulePoll(this.online ? CONNECTED_POLL_MS : OFFLINE_POLL_MS);
        };
        setState(conn.state);
        conn.bind('state_change', (s) => setState(s.current));
    },

    schedulePoll(ms) {
        clearInterval(this.pollTimer);
        this.pollTimer = setInterval(() => this.load(), ms);
    },

    tickClock() {
        const tz = this.snap?.timezone;
        try {
            this.clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: tz });
        } catch {
            this.clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    },

    async keepAwake() {
        if (!('wakeLock' in navigator)) return;
        const request = async () => { try { await navigator.wakeLock.request('screen'); } catch { /* not allowed */ } };
        await request();
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') request(); });
    },

    prepareAudio() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        this.audio = new Ctx();
        this.needsTap = this.audio.state === 'suspended';
    },

    unlockAudio() {
        this.audio?.resume();
        this.needsTap = false;
    },

    // Two-tone "ding-dong" generated in the browser (no media file needed).
    chime() {
        const ctx = this.audio;
        if (!ctx || ctx.state !== 'running') return;
        [[880, 0], [660, 0.35]].forEach(([freq, at]) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime + at);
            gain.gain.exponentialRampToValueAtTime(0.4, ctx.currentTime + at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.6);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + at);
            osc.stop(ctx.currentTime + at + 0.65);
        });
    },

    avgWaitFor(departmentId) {
        return this.snap?.avg_wait.find((a) => a.department_id === departmentId)?.minutes;
    },
}));

window.Alpine = Alpine;
Alpine.start();
