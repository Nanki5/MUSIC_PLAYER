document.addEventListener('DOMContentLoaded', async () => {
    // 1. Пользователь + id лайкнутых треков.
    // GET /api/songs не сообщает, лайкнут ли трек текущим пользователем,
    // поэтому берём это из /api/me/likes.
    let user = null;
    let likedIds = new Set();
    try {
        user = await API.getCurrentUser();
        if (user) likedIds = await API.getLikedIds();
    } catch (err) {
        console.error('Ошибка загрузки лайков:', err);
    }

    // 2. Загружаем плейлисты пользователя (если авторизован)
    const albumRow = document.querySelector('.album-row');
    if (albumRow) {
        try {
            const playlists = user ? await API.getMyPlaylists() : [];

            if (playlists.length) {
                albumRow.innerHTML = playlists.map(pl => `
                    <article class="album-card" data-id="${pl.id}">
                        <img src="${pl.cover}" alt="" class="album-card__cover">
                        <h3 class="album-card__title">${pl.name}</h3>
                    </article>
                `).join('');

                albumRow.addEventListener('click', e => {
                    const card = e.target.closest('.album-card');
                    if (card) openPlaylist(card.dataset.id);
                });
            } else {
                albumRow.innerHTML = '<p style="color: var(--color-text-muted, #a1a1a6)">Плейлистов пока нет.</p>';
            }
        } catch (err) {
            console.error('Ошибка загрузки плейлистов:', err);
        }
    }

    // 3. Загружаем «Популярное» (по лайкам) и «Все треки» (новые сверху)
    let popular = [];
    let all = [];
    try {
        [popular, all] = await Promise.all([
            API.getPopularTracks(8),
            API.getTracks({ sort: 'uploaded_at', limit: 20 }),
        ]);
    } catch (err) {
        console.error('Ошибка загрузки треков:', err);
    }

    // Рендер блока «Популярное» (второй блок .album-row на странице)
    const trackRows = document.querySelectorAll('.album-row');
    if (trackRows[1] && popular.length) {
        trackRows[1].innerHTML = popular.map((t, i) => `
            <article class="track__card" data-track-id="${t.id}">
                <img src="${t.cover}" alt="" class="track__card-cover">
                <h3 class="track__card-title">${t.title}</h3>
                <p class="track__card-artist">${t.artist}</p>
                <div class="track__card-actions">
                    <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
                    ${likeButtonHtml(t, likedIds)}
                </div>
            </article>
        `).join('');

        bindTrackEvents(trackRows[1], popular, likedIds);
    }

    // Рендер списка «Все треки»
    const allTracksEl = document.querySelector('.track__list');
    if (allTracksEl && all.length) {
        allTracksEl.innerHTML = all.map((t, i) => `
            <article class="track__row" data-track-id="${t.id}">
                <img src="${t.cover}" alt="" class="track__row-cover">
                <div class="track__row-info">
                    <h3 class="track__row-title">${t.title}</h3>
                    <p class="track__row-artist">${t.artist}</p>
                </div>
                <span class="track__row-duration" data-like-count="${t.id}" data-count="${t.likeCount}">♥ ${t.likeCount}</span>
                <div class="track__row-actions">
                    <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
                    ${likeButtonHtml(t, likedIds)}
                </div>
            </article>
        `).join('');

        bindTrackEvents(allTracksEl, all, likedIds);
    }
});

// Кнопка лайка с учётом того, лайкнут ли трек пользователем
function likeButtonHtml(track, likedIds) {
    const liked = likedIds.has(String(track.id));
    return `<button class="botton__favorite${liked ? ' botton__favorite--active' : ''}"
                    data-like="${track.id}" title="Лайк (${track.likeCount})">
                <i class="${liked ? 'ri-heart-fill' : 'ri-heart-line'}"></i>
            </button>`;
}

// Обновляем все сердечки и счётчики одного трека на странице
function updateLikeUI(songId, liked) {
    const id = String(songId);

    document.querySelectorAll(`[data-like="${id}"]`).forEach(btn => {
        btn.classList.toggle('botton__favorite--active', liked);
        const icon = btn.querySelector('i');
        if (icon) icon.className = liked ? 'ri-heart-fill' : 'ri-heart-line';
    });

    document.querySelectorAll(`[data-like-count="${id}"]`).forEach(el => {
        const count = Math.max(0, Number(el.dataset.count || 0) + (liked ? 1 : -1));
        el.dataset.count = String(count);
        el.textContent = `♥ ${count}`;
    });
}

// Единый обработчик для кнопок Play и Like
function bindTrackEvents(container, tracksList, likedIds) {
    container.addEventListener('click', async e => {
        const playBtn = e.target.closest('[data-play]');
        if (playBtn) {
            const i = parseInt(playBtn.dataset.play, 10);
            Player.play(tracksList, i);
            return;
        }

        const likeBtn = e.target.closest('[data-like]');
        if (likeBtn) {
            const songId = likeBtn.dataset.like;
            try {
                const res = await API.toggleLike(songId);
                const liked = !!res.liked;

                if (liked) likedIds.add(String(songId));
                else likedIds.delete(String(songId));

                updateLikeUI(songId, liked);
            } catch (err) {
                if (err.status === 401) {
                    alert('Войдите в аккаунт, чтобы ставить лайки');
                    location.href = 'login.html';
                } else {
                    alert(err.message);
                }
            }
        }
    });
}

// Модальное окно плейлиста
async function openPlaylist(id) {
    let playList;
    try {
        playList = await API.getPlaylist(id);
    } catch (err) {
        return alert(err.message);
    }

    const tracks = playList.tracks;

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
                    ${tracks.map((t, i) => `
                        <article class="track__row" data-i="${i}" style="cursor: pointer;">
                            <img src="${t.cover}" alt="" class="track__row-cover">
                            <div class="track__row-info">
                                <h3 class="track__row-title">${t.title}</h3>
                                <p class="track__row-artist">${t.artist}</p>
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
                    <img src="${tracks[0].cover}" alt="" class="mini-player__image" id="miniImg">
                    <h3 class="mini-player__title" id="miniTitle">${tracks[0].title}</h3>
                    <p class="mini-player__artist" id="miniArtist">${tracks[0].artist}</p>
                    <div class="mini-player__controls">
                        <button class="mini-player__btn" id="miniPrev" type="button"><i class="ri-skip-back-fill"></i></button>
                        <button class="mini-player__btn mini-player__btn--play" id="miniPlay" type="button"><i class="ri-play-fill"></i></button>
                        <button class="mini-player__btn" id="miniNext" type="button"><i class="ri-skip-forward-fill"></i></button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const img     = modal.querySelector('#miniImg');
    const title   = modal.querySelector('#miniTitle');
    const artist  = modal.querySelector('#miniArtist');
    const audio   = document.getElementById('player__audio');
    const playBtn = modal.querySelector('#miniPlay');

    let currentIndex = 0;
    function loadMini(index) {
        currentIndex = index;
        const t = tracks[index];
        img.src            = t.cover;
        title.textContent  = t.title;
        artist.textContent = t.artist;
        audio.src          = t.audio;
    }

    function setPlayingUI(isPlaying) {
        playBtn.innerHTML = isPlaying ? '<i class="ri-pause-fill"></i>' : '<i class="ri-play-fill"></i>';
    }

    playBtn.onclick = () => {
        if (audio.paused) { audio.play(); setPlayingUI(true); }
        else { audio.pause(); setPlayingUI(false); }
    };

    modal.querySelector('#miniNext').onclick = () => { loadMini((currentIndex + 1) % tracks.length); audio.play(); setPlayingUI(true); };
    modal.querySelector('#miniPrev').onclick = () => { loadMini((currentIndex - 1 + tracks.length) % tracks.length); audio.play(); setPlayingUI(true); };

    modal.querySelectorAll('.track__row').forEach((row, i) => {
        row.addEventListener('click', () => { loadMini(i); audio.play(); setPlayingUI(true); });
    });

    audio.onended = () => { loadMini((currentIndex + 1) % tracks.length); audio.play(); setPlayingUI(true); };


    function close() {
        modal.remove();
        document.removeEventListener('keydown', onEsc);
    }
    const onEsc = e => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onEsc);
    modal.querySelector('.album-modal__close').onclick = close;
    modal.addEventListener('click', e => { if (e.target === modal) close(); });

    loadMini(0);
}
