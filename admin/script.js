document.getElementById('activateButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('teamNumber').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    fetch('/admin/activate/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ teamNumber, username, password })
    })
    .then(response => {
        if (response.status === 401) {
            window.location.href = '../../login/';
        } else if (response.ok) {
            alert('Activation successful');
        } else {
            alert('Activation failed');
        }
    });
});

document.getElementById('submitOtpButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('teamNumber').value;
    const otp = document.getElementById('otpInput').value;

    fetch('/admin/activate/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ teamNumber, otp })
    })
    .then(response => {
        if (response.status === 401) {
            window.location.href = '../../login/';
        } else if (response.ok) {
            alert('Activation successful');
        } else {
            alert('Activation failed');
        }
    });
});
