document.addEventListener('DOMContentLoaded', () => {
    DB.seed();
    const playlists = DB.getPublicPlaylists();//загрузка публичных
    const albumRow = document.querySelector('.album-row');
    if (albumRow) {
        if (playlists.length) {
            albumRow.innerHTML = playlists.map(playlist => `
                <article class="album-card" data-id="${playlist.id}">
                    <img src="${playlist.cover || '../../assets/images/images.png'}" alt="" class="album-card__cover">
                    <h3 class="album-card__title">${playlist.name}</h3>
                    <p class="album-card__subtitle">${playlist.trackIds.length} треков</p>
                </article>
            `).join('');

            albumRow.addEventListener('click', e => {
                const card = e.target.closest('.album-card');
                if (!card) return;
                openPlaylist(card.dataset.id);
            });
        } else {
            albumRow.innerHTML = '<p style="color: var(--color-text-muted, #a1a1a6)">Плейлистов пока нет.</p>';
        }
    }

    const popular = DB.getPublicTracks().slice(0, 8);
    const trackRows = document.querySelectorAll('.album-row');
    if (trackRows[1] && popular.length) {
        trackRows[1].innerHTML = popular.map((track, i) => `
            <article class="track__card" data-track-id="${track.id}">
                <img src="${track.cover || '../../assets/images/images.png'}" alt="" class="track__card-cover">
                <h3 class="track__card-title">${track.title}</h3>
                <p class="track__card-artist">${track.artist}</p>
                <div class="track__card-actions">
                    <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
                    <button class="botton__favorite"><i class="ri-heart-line"></i></button>
                </div>
            </article>
        `).join('');

        trackRows[1].addEventListener('click', e => {
            const button = e.target.closest('[data-play]');
            if (button) {
                const i = parseInt(button.dataset.play);
                Player.play(popular, i);
            }
        });
    }

    const allTracks = document.querySelector('.track__list');
    if (allTracks && popular.length) {
        allTracks.innerHTML = popular.map((track, i) => `
            <article class="track__row" data-track-id="${track.id}">
                <img src="${track.cover || '../../assets/images/images.png'}" alt="" class="track__row-cover">
                <div class="track__row-info">
                    <h3 class="track__row-title">${track.title}</h3>
                    <p class="track__row-artist">${track.artist}</p>
                </div>
                <span class="track__row-duration"></span>
                <div class="track__row-actions">
                    <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
                    <button class="botton__favorite"><i class="ri-heart-line"></i></button>
                </div>
            </article>
        `).join('');

        allTracks.addEventListener('click', e => {
            const button = e.target.closest('[data-play]');
            if (button) {
                const i = parseInt(button.dataset.play);
                Player.play(popular, i);
            }
        });
    }
});

function openPlaylist(id) {
    const playList = DB.getPlaylist(id);
    if (!playList) return;
    const tracks = playList.trackIds.map(tracksID => DB.getTracks().find(track => track.id === tracksID)).filter(Boolean);
    if (!tracks.length) return alert('Плейлист пуст');

    const modal = document.createElement('div');
    modal.className = 'album-modal album-modal--open';
    modal.innerHTML = `
        <div class="album-modal__content">
            <div class="album-modal__left">
                <div class="album-modal__header">
                    <h3 class="album-modal__title">${playList.name}</h3>
                    <button class="album-modal__close" type="button" aria-label="Закрыть">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="track__list" style="flex:1; overflow-y:auto;">
                    ${tracks.map((track, i) => `
                        <article class="track__row" data-i="${i}" style="cursor: pointer;">
                            <img src="${track.cover || '../../assets/images/images.png'}" alt="" class="track__row-cover">
                            <div class="track__row-info">
                                <h3 class="track__row-title">${track.title}</h3>
                                <p class="track__row-artist">${track.artist}</p>
                            </div>
                            <button class="botton__play" data-i="${i}">
                                <i class="ri-play-fill"></i>
                            </button>
                        </article>
                    `).join('')}
                </div>
            </div>
            <div class="album-modal__right">
                <div class="mini-player">
                    <img src="${tracks[0].cover || '../../assets/images/images.png'}" alt="" class="mini-player__image" id="miniImg">
                    <h3 class="mini-player__title" id="miniTitle">${tracks[0].title}</h3>
                    <p class="mini-player__artist" id="miniArtist">${tracks[0].artist}</p>
                    <div class="mini-player__controls">
                        <button class="mini-player__btn" id="miniPrev" type="button"><i class="ri-skip-back-fill"></i></button>
                        <button class="mini-player__btn mini-player__btn--play" id="miniPlay" type="button"><i class="ri-play-fill"></i></button>
                        <button class="mini-player__btn" id="miniNext" type="button"><i class="ri-skip-forward-fill"></i></button>
                    </div>
                    <audio id="miniAudio"></audio>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    let currentIndex = 0;
    const audio = modal.querySelector('#miniAudio');
    const img  = modal.querySelector('#miniImg');
    const title = modal.querySelector('#miniTitle');
    const artist = modal.querySelector('#miniArtist');
    const playBtn = modal.querySelector('#miniPlay');

    function loadMini(index) {
        currentIndex = index;
        const track = tracks[index];
        img.src = track.cover || '../../assets/images/images.png';
        title.textContent = track.title;
        artist.textContent = track.artist;
        audio.src = track.audio;
    }

    function setPlayingUI(isPlaying) {
        playBtn.innerHTML = isPlaying ? '<i class="ri-pause-fill"></i>' : '<i class="ri-play-fill"></i>';
    }

    function Play() {
        if (audio.paused) {
            audio.play();
            setPlayingUI(true);
        } else {
            audio.pause();
            setPlayingUI(false);
        }
    }

    playBtn.onclick = Play;

    modal.querySelector('#miniNext').onclick = () => {
        loadMini((currentIndex + 1) % tracks.length);
        audio.play();
        setPlayingUI(true);
    };
    modal.querySelector('#miniPrev').onclick = () => {
        loadMini((currentIndex - 1 + tracks.length) % tracks.length);
        audio.play();
        setPlayingUI(true);
    };

    modal.querySelectorAll('.track__row').forEach((row, i) => {
        row.addEventListener('click', (e) => {
            const playBtnInside = e.target.closest('.botton__play');
            if (playBtnInside) return; 
            loadMini(i);
            audio.play();
            setPlayingUI(true);
        });
    });

    modal.querySelectorAll('.botton__play').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const i = parseInt(btn.dataset.i, 10);
            loadMini(i);
            audio.play();
            setPlayingUI(true);
        });
    });

    //автопереход
    audio.onended = () => {
        loadMini((currentIndex + 1) % tracks.length);
        audio.play();
        setPlayingUI(true);
    };

    function close() {
        audio.pause();
        modal.remove();
        document.removeEventListener('keydown', onEsc);
    }
    function onEsc(e) {
        if (e.key === 'Escape') close();
    }
    document.addEventListener('keydown', onEsc);

    modal.querySelector('.album-modal__close').onclick = close;
    modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });

    loadMini(0);
}