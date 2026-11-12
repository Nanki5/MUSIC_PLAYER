const Player = {
    audio: null,
    queue: [],
    index: 0,
    playing: false,
    STORAGE_KEY: 'unimusic-player',
    _restoring: false,
    _saveTimer: null,

    init() {
        this.audio = document.getElementById('player__audio');
        this.el = document.getElementById('player');
        this.name = this.el.querySelector('.player__name');
        this.artist = this.el.querySelector('.player__artist');
        this.cover = this.el.querySelector('.player__cover');
        this.fill = this.el.querySelector('.player__progress-fill');
        this.timeCur = this.el.querySelector('.player__time-current');
        this.timeFin = this.el.querySelector('.player__time-final');
        this.icon = this.el.querySelector('#player__play i');
        this._restore();

        this.audio.addEventListener('play',  () => this._save());
        this.audio.addEventListener('pause', () => this._save());

        this.el.querySelector('#player__play').addEventListener('click', () => this.toggle());
        this.el.querySelector('#player__prev').addEventListener('click', () => this.prev());
        this.el.querySelector('#player__next').addEventListener('click', () => this.next());

        this.el.querySelector('#player__progress').addEventListener('click', e => {
            if (!this.audio.duration) return;
            const r = e.currentTarget.getBoundingClientRect();
            const ratio = (e.clientX - r.left) / r.width;
            this.audio.currentTime = ratio * this.audio.duration;
        });

        this.audio.addEventListener('timeupdate', () => {
            if (!this.audio.duration) return;
            this.fill.style.width = (this.audio.currentTime / this.audio.duration * 100) + '%';
            this.timeCur.textContent = this._fmt(this.audio.currentTime);
        });

        this.audio.addEventListener('loadedmetadata', () => {
            if (!this.audio.duration || isNaN(this.audio.duration)) return;
            this.tFin.textContent = this._fmt(this.audio.duration);
        });

        this.audio.addEventListener('ended', () => {
            if (this.queue.length <= 1) {
                this.playing = false;
                this._updateIcon();
                return;
            }
            this.next();
        });
        // Сохранение при выходе
        this._bindPersistence();
    },

    _bindPersistence() {
        window.addEventListener('beforeunload', () => this._save(true));
        window.addEventListener('pagehide', () => this._save(true));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') this._save(true);
        });
        if (this._saveTimer) clearInterval(this._saveTimer);
        this._saveTimer = setInterval(() => {
            if (this.playing) this._save(true);
        }, 2000);
    },

    _save(saveTime = true) {
        const t = this.queue[this.index];
        if (!t) return;
        if (this._restoring) return;
        const state = {
            queue: this.queue,
            index: this.index,
            time:  saveTime ? (this.audio.currentTime || 0) : undefined,
            playing: this.playing,
        };
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(state));
        } catch {}
    },

    _restore() {
        const raw = localStorage.getItem(this.STORAGE_KEY);
        if (!raw) return;
        let state;
        try { state = JSON.parse(raw); } catch { return; }
        if (!state || !Array.isArray(state.queue) || !state.queue.length) return;

        this._restoring = true;
        this.queue = state.queue;
        this.index = Math.min(state.index || 0, this.queue.length - 1);

        this.load();
        this.el.hidden = false;

        const applyTime = () => {
            if (!state.time) return;
            try {
                this.audio.currentTime = state.time;
                if (this.audio.duration) {
                    this.fill.style.width = (state.time / this.audio.duration * 100) + '%';
                }
                this.timeCur.textContent = this._fmt(state.time);
            } catch {}
        };

        if (this.audio.readyState >= 1) {
            applyTime();
        } else {
            this.audio.addEventListener('loadedmetadata', applyTime, { once: true });
        }

        this.playing = false;
        this._updateIcon();
        this._restoring = false;
    },

    _fmt(sec) {
        const m = Math.floor(sec / 60);
        const s = String(Math.floor(sec % 60)).padStart(2, '0');
        return `${m}:${s}`;
    },

    play(tracks, startIndex = 0) {
        if (!tracks.length) return;
        this.queue = tracks;
        this.index = startIndex;
        this.load();
        this.audio.play();
        this.playing = true;
        this._updateIcon();
        this.el.hidden = false;
    },

    load() {
        const t = this.queue[this.index];
        if (!t) return;
        this.name.textContent = t.title;
        this.artist.textContent = t.artist;
        this.cover.src = t.cover || '../../assets/images/images.png';
        this.audio.src = t.audio;
    },

    toggle() {
        if (!this.queue.length) return;
        if (this.audio.paused) {
            this.audio.play();
            this.playing = true;
        } else {
            this.audio.pause();
            this.playing = false;
        }
        this._updateIcon();
    },

    _updateIcon() {
        this.icon.className = this.playing ? 'ri-pause-fill' : 'ri-play-fill';
    },

    next() {
        if (!this.queue.length) return;
        this.index = (this.index + 1) % this.queue.length;
        this.load();
        this.audio.play();
        this.playing = true;
        this._updateIcon();
        this.el.hidden = false;
    },

    prev() {
        if (!this.queue.length) return;
        this.index = (this.index - 1 + this.queue.length) % this.queue.length;
        this.load();
        this.audio.play();
        this.playing = true;
        this._updateIcon();
        this.el.hidden = false;
    },
};

document.addEventListener('DOMContentLoaded', () => Player.init());