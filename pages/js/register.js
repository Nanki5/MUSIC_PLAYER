document.querySelector('.registration__form').addEventListener('submit', async e => {
    e.preventDefault();
    const login    = (e.target.login || e.target.email).value.trim();
    const password = e.target.password.value;
    const confirm  = e.target.confirm_password.value;

    if (password !== confirm) return alert('Пароли не совпадают');
    try {
        await API.register(login, password);
        location.href = 'home.html';
    } catch (err) {
        alert(err.message);
    }
});