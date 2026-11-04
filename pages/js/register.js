document.querySelector('.registration__form').addEventListener('submit', e => {
    e.preventDefault();
    const email  = e.target.email.value.trim();
    const password = e.target.password.value;
    const confirm = e.target.confirm_password.value;

    if (password !== confirm) return alert('Пароли не совпадают');
    try {
        Auth.register({ email, password });
        location.href = 'home.html';
    } catch (err) {
        alert(err.message);
    }
});