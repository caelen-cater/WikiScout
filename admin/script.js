document.getElementById('activateButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('teamNumber').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    fetch('activate', {
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
            window.location.reload();
        } else {
            alert('Activation failed');
        }
    });
});
document.getElementById('deactivateButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('deactivateTeamNumber').value;
    console.log('Deactivate clicked for team:', teamNumber); // Debug line

    fetch('deactivate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ teamNumber })
    })
    .then(response => {
        console.log('Deactivate response:', response.status); // Debug line
        if (response.status === 401) {
            window.location.href = '../../login/';
        } else if (response.ok) {
            alert('Deactivation successful');
            window.location.reload();
        } else {
            alert('Deactivation failed');
        }
    })
    .catch(error => {
        console.error('Deactivate error:', error); // Debug line
        alert('Deactivation failed');
    });
});