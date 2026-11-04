const content = document.querySelector(".player__content");
const Playimage = content.querySelector(".player__music-image img");
const musicName = content.querySelector(".player__music-titles .player__name");
const musicArtist = content.querySelector(".player__music-titles .player__artist");

const Audio = document.querySelector(".player__main-song");  // ← через document!
const playButton = content.querySelector(".player__play-pause");
const playButtonIcon = content.querySelector(".player__play-pause span");
const prevButton = content.querySelector("#previous");
const nextButton = content.querySelector("#next");
const progressBar = content.querySelector(".player__progress-bar");
const progressDetails = content.querySelector(".player__progress-details");
const repeatButton = content.querySelector("#repeat");
const Shuffle = content.querySelector("#shuffle");

let index = 1;

window.addEventListener("load", () => {
    loadData(index);
    // Audio.play() убран — браузер заблокирует
});

function loadData(indexValue){
    musicName.innerHTML = songs[indexValue - 1].name;
    musicArtist.innerHTML = songs[indexValue - 1].artist;
    Playimage.src = "../../assets/images/" + songs[indexValue - 1].img + ".jpg";
    Audio.src = "../../assets/audio/" + songs[indexValue - 1].audio + ".mp3";
}

playButton.addEventListener("click", () => {
    const isPlaying = content.classList.contains("paused");
    if (isPlaying) {
        pauseSong();
    } else {
        playSong();
    }
});

function playSong(){
    content.classList.add("paused");        // ваш класс означает "играет"
    playButtonIcon.innerHTML = "pause";
    Audio.play();
}
function pauseSong(){
    content.classList.remove("paused");
    playButtonIcon.innerHTML = "play_arrow";
    Audio.pause();
}

nextButton.addEventListener("click", nextSong);
prevButton.addEventListener("click", prevSong);

function nextSong(){
    index++;
    if (index > songs.length) index = 1;
    loadData(index);
    playSong();
}
function prevSong(){
    index--;
    if (index <= 0) index = songs.length;
    loadData(index);
    playSong();
}

// ---- вынесено ИЗ timeupdate ----

progressDetails.addEventListener("click", (e) => {
    const rect = progressDetails.getBoundingClientRect();
    const ratio = (e.clientX - rect.left) / rect.width;
    Audio.currentTime = ratio * Audio.duration;
});

repeatButton.addEventListener("click", () => {
    Audio.currentTime = 0;
});

Audio.addEventListener("loadeddata", () => {
    const finalTimeData = content.querySelector(".player__time .final");
    const dur = Audio.duration;
    const min = Math.floor(dur / 60);
    const sec = String(Math.floor(dur % 60)).padStart(2, "0");
    finalTimeData.innerText = min + ":" + sec;
});

Audio.addEventListener("timeupdate", () => {
    const cur = Audio.currentTime;
    const dur = Audio.duration || 0;
    progressBar.style.width = (cur / dur) * 100 + "%";

    const currentTimeData = content.querySelector(".player__time .current");
    const min = Math.floor(cur / 60);
    const sec = String(Math.floor(cur % 60)).padStart(2, "0");
    currentTimeData.innerText = min + ":" + sec;
});

Shuffle.addEventListener("click", () => {
    const randIndex = Math.floor(Math.random() * songs.length) + 1;
    loadData(randIndex);
    playSong();
});

Audio.addEventListener("ended", () => {
    index++;
    if (index > songs.length) index = 1;
    loadData(index);
    playSong();
});