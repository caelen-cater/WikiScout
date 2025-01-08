function trackInsight(data) {
    const headers = new Headers({
        'X-Action-Message': data.message,
        'X-Metadata': JSON.stringify(data.metadata || {})
    });

    return fetch('../insight/', {
        method: 'GET',
        headers: headers,
        referrer: window.location.origin + data.trace
    }).catch(error => console.error('Error tracking insight:', error));
}

document.getElementById('activateButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('teamNumber').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    trackInsight({
        message: 'Team activation attempt',
        method: 'POST',
        trace: '/workspaces/WikiScout/admin/activate/index.php',
        metadata: { teamNumber, username }
    });

    fetch('activate/', {
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
            trackInsight({
                message: 'Team activation success',
                trace: '/workspaces/WikiScout/admin/activate/index.php',
                metadata: { teamNumber }
            });
            alert('Activation successful');
            window.location.reload();
        } else {
            trackInsight({
                message: 'Team activation failed',
                trace: '/workspaces/WikiScout/admin/activate/index.php',
                metadata: { teamNumber, error: 'API error' }
            });
            alert('Activation failed');
        }
    });
});

document.getElementById('deactivateButton').addEventListener('click', function() {
    const teamNumber = document.getElementById('deactivateTeamNumber').value;

    trackInsight({
        message: 'Team deactivation attempt',
        method: 'POST',
        trace: '/workspaces/WikiScout/admin/deactivate/index.php',
        metadata: { teamNumber }
    });

    fetch('deactivate/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ teamNumber })
    })
    .then(response => {
        if (response.status === 401) {
            window.location.href = '../../login/';
        } else if (response.ok) {
            trackInsight({
                message: 'Team deactivation success',
                trace: '/workspaces/WikiScout/admin/deactivate/index.php',
                metadata: { teamNumber }
            });
            alert('Deactivation successful');
            window.location.reload();
        } else {
            trackInsight({
                message: 'Team deactivation failed',
                trace: '/workspaces/WikiScout/admin/deactivate/index.php',
                metadata: { teamNumber, error: 'API error' }
            });
            alert('Deactivation failed');
        }
    })
    .catch(error => {
        trackInsight({
            message: 'Team deactivation error',
            trace: '/workspaces/WikiScout/admin/deactivate/index.php',
            metadata: { teamNumber, error: error.message }
        });
        alert('Deactivation failed');
    });
});