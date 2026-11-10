const Player = {
    audio: null,
    queue: [],
    index: 0,
    playing: false,
    STORAGE_KEY: 'unimusic-player',
    _restoring: false,

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
        this.audio.addEventListener('timeupdate', () => this._save(false));

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
            this.tCur.textContent = this._fmt(this.audio.currentTime);
        });
        this.audio.addEventListener('loadedmetadata', () => {
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
    },

    _fmt(sec) {
        const m = Math.floor(sec / 60);
        const s = String(Math.floor(sec % 60)).padStart(2, '0');
        return `${m}:${s}`;
    },

    _save(saveTime = true) {
        const t = this.queue[this.index];
        if (!t) return;
        if (this._restoring) return; 
        const state = {
            queue: this.queue,
            index: this.index,
            time:  saveTime ? this.audio.currentTime : undefined,
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
        try {
            state = JSON.parse(raw);
        } catch {
            return;
        }
        if (!state || !Array.isArray(state.queue) || !state.queue.length) return;
        this._restoring = true;
        this.queue = state.queue;
        this.index = Math.min(state.index || 0, this.queue.length - 1);

        this.load();
        this.el.hidden = false;

        if (state.time) {
            const onMeta = () => {
                try { this.audio.currentTime = state.time; } catch {}
                this.audio.removeEventListener('loadedmetadata', onMeta);
            };
            this.audio.addEventListener('loadedmetadata', onMeta);
        }
        this.playing = false;
        this._updateIcon();
        this._restoring = false;
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