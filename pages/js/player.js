const Player = {
    audio:null, queue:[], index:0, playing: false,
    init() {
        this.audio = document.getElementById('player__audio');
        this.el    = document.getElementById('player');
        this.name  = this.el.querySelector('.player__name');
        this.artist= this.el.querySelector('.player__artist');
        this.cover = this.el.querySelector('.player__cover');
        this.fill  = this.el.querySelector('.player__progress-fill');
        this.tCur  = this.el.querySelector('.player__time-current');
        this.tFin  = this.el.querySelector('.player__time-final');
        this.icon  = this.el.querySelector('#player__play i');

        this.el.querySelector('#player__play').addEventListener('click', () => this.toggle());
        this.el.querySelector('#player__prev').addEventListener('click', () => this.prev());
        this.el.querySelector('#player__next').addEventListener('click', () => this.next());

        this.el.querySelector('#player__progress').addEventListener('click', e => {
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
        this.audio.addEventListener('ended', () => this.next());
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
        this.name.textContent   = t.title;
        this.artist.textContent = t.artist;
        this.cover.src          = t.cover || '../../assets/images/images.png';
        this.audio.src          = t.audio;
    },

    toggle() {
        if (this.audio.paused) { this.audio.play(); this.playing = true; }
        else { this.audio.pause(); this.playing = false; }
        this._updateIcon();
    },

    _updateIcon() {
        this.icon.className = this.playing ? 'ri-pause-fill' : 'ri-play-fill';
    },

    next() {
        this.index = (this.index + 1) % this.queue.length;
        this.load();
        this.audio.play();
        this.playing = true;
        this._updateIcon();
    },

    prev() {
        this.index = (this.index - 1 + this.queue.length) % this.queue.length;
        this.load();
        this.audio.play();
        this.playing = true;
        this._updateIcon();
    },
};

document.addEventListener('DOMContentLoaded', () => Player.init());