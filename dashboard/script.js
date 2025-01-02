"use strict"; 

const body = document.body;
const bgColorsBody = ["#ffb457", "#ff96bd", "#9999fb", "#ffe797", "#cffff1"];
const menu = body.querySelector(".menu");
const menuItems = menu.querySelectorAll(".menu__item");
const menuBorder = menu.querySelector(".menu__border");
let activeItem = menu.querySelector(".active");

const otpContainer = document.getElementById('otp-container');
const otpInfoContainer = document.getElementById('otp-info-container');
const otpInputs = otpContainer.querySelectorAll('input');
const deleteBtn = document.getElementById('delete-btn');
const regenerateBtn = document.getElementById('regenerate-btn');
const formContainer = document.getElementById('form-container');

const apiCache = {
    matches: {},
    rankings: {},
    teams: {},
    validate: {},
    today: {},
    auth: {}
};

const pendingRequests = {};

function validateSession() {
   return cachedFetch('./validate/', {
        method: 'GET'
    }).catch(error => console.error('Error validating session:', error));
}

window.addEventListener('load', () => {
    validateSession()
        .then(data => {
            if (data.details?.address) {
                localStorage.setItem('myTeam', data.details.address);
            }
        })
        .catch(error => console.error('Error fetching user details:', error));

    if (menuItems[3].classList.contains('active')) {
        showFormContainer();
    }
});

function clickItem(item, index) {
    validateSession();
    menu.style.removeProperty("--timeOut");
    
    if (activeItem == item) return;
    
    if (activeItem) {
        activeItem.classList.remove("active");
    }

    item.classList.add("active");
    activeItem = item;
    offsetMenuBorder(activeItem, menuBorder);
    
    hideAllContainers();
    
    if (index === 0) {
        showOtpContainer();
    } else if (index === 1) {
        showLeaderboard();
    } else if (index === 2) {
        showDataView();
    } else if (index === 3) {
        showFormContainer();
    }
}

function hideAllContainers() {
    hideOtpContainer();
    hideFormContainer();
    document.getElementById('leaderboard-container').classList.remove('active');
    document.getElementById('stats-popup').classList.remove('active');
    document.getElementById('data-container').classList.remove('active');
}

function offsetMenuBorder(element, menuBorder) {

    const offsetActiveItem = element.getBoundingClientRect();
    const left = Math.floor(offsetActiveItem.left - menu.offsetLeft - (menuBorder.offsetWidth  - offsetActiveItem.width) / 2) +  "px";
    menuBorder.style.transform = `translate3d(${left}, 0 , 0)`;

}

function showOtpContainer() {
    otpContainer.classList.add('active');
    otpInfoContainer.classList.add('active');
    fetchOtpCode();
}

function hideOtpContainer() {
    otpContainer.classList.remove('active');
    otpInfoContainer.classList.remove('active');
}

if (menuItems[0].classList.contains('active')) {
    showOtpContainer();
}

function handleApiResponse(response) {
    if (response.status === 501) {
        window.location.href = '../activate';
        return Promise.reject('Redirecting to activate');
    } else if (response.status === 401) {
        window.location.href = '../login';
        return Promise.reject('Redirecting to login');
    }
    return response.json();
}

function fetchOtpCode() {
    fetch('./auth/', {
        method: 'GET'
    })
    .then(handleApiResponse)
    .then(data => {
        const otpCode = data.code.toString().padStart(8, '0');
        otpInputs.forEach((input, index) => {
            input.value = otpCode[index] || '';
        });
    })
    .catch(error => console.error('Error fetching OTP code:', error));
}

deleteBtn.addEventListener('click', () => {
    deleteBtn.disabled = true;
    regenerateBtn.disabled = true;
    fetch('./auth/', {
        method: 'DELETE'
    })
    .then(handleApiResponse)
    .then(data => {
        if (data.message === 'OTP invalidated') {
            fetchOtpCode();
        }
    })
    .catch(error => console.error('Error deleting OTP code:', error))
    .finally(() => {
        deleteBtn.disabled = false;
        regenerateBtn.disabled = false;
    });
});

regenerateBtn.addEventListener('click', () => {
    deleteBtn.disabled = true;
    regenerateBtn.disabled = true;
    fetch('./auth/', {
        method: 'POST'
    })
    .then(handleApiResponse)
    .then(data => {
        const otpCode = data.code.toString().padStart(8, '0');
        otpInputs.forEach((input, index) => {
            input.value = otpCode[index] || '';
        });
    })
    .catch(error => console.error('Error regenerating OTP code:', error))
    .finally(() => {
        deleteBtn.disabled = false;
        regenerateBtn.disabled = false;
    });
});

offsetMenuBorder(activeItem, menuBorder);

menuItems.forEach((item, index) => {

    item.addEventListener("click", () => {
        clickItem(item, index);
        if (index === 0) {
            fetchOtpCode();
        } else if (index === 3) {
            showFormContainer();
        } else {
            hideFormContainer();
        }
    });
    
})

window.addEventListener("resize", () => {
    offsetMenuBorder(activeItem, menuBorder);
    menu.style.setProperty("--timeOut", "none");
});

function showFormContainer() {
    formContainer.classList.add('active');
    fetchFormData();
}

function hideFormContainer() {
    formContainer.classList.remove('active');
}

function fetchFormData() {
    // First check if user is at an event
    fetch('./me/')
        .then(handleApiResponse)
        .then(data => {
            const eventDisplay = document.getElementById('event-id');
            const eventInput = document.getElementById('event-id-code');
            
            if (data.found && eventDisplay && eventInput) {
                // Store event info
                localStorage.setItem('event', data.event.code);
                localStorage.setItem('eventName', data.event.name);
                
                eventDisplay.value = data.event.name;
                eventInput.value = data.event.code;
                eventDisplay.classList.add('at-event');
                eventDisplay.classList.remove('no-event');
            } else if (eventDisplay) {
                // Clear storage
                localStorage.removeItem('event');
                localStorage.removeItem('eventName');
                
                eventDisplay.value = data.message || 'Not currently at any event';
                eventDisplay.classList.add('no-event');
                eventDisplay.classList.remove('at-event');
                
                if (eventInput) {
                    eventInput.value = '';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching event data:', error);
            const eventDisplay = document.getElementById('event-id');
            if (eventDisplay) {
                eventDisplay.value = 'Error loading event info';
                eventDisplay.classList.add('no-event');
                eventDisplay.classList.remove('at-event');
            }
        });

    // Continue with form data fetch
    fetch('../../form.dat')
        .then(response => response.text())
        .then(data => {
            const formElements = parseFormData(data);
            renderForm(formElements);
        })
        .catch(error => console.error('Error fetching form data:', error));
}

function parseFormData(data) {
    const lines = data.split('\n');
    return lines.map(line => {
        const [type, ...rest] = line.match(/"[^"]+"|\S+/g);
        const label = rest[0].replace(/"/g, '');
        const options = rest.slice(1);
        return { type, label, options };
    });
}

function updateSliderBackground(slider) {
    const value = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
    slider.style.setProperty('--value', `${value}%`);
}

function renderForm(elements) {
    formContainer.innerHTML = '';

    // Event ID Group
    const eventIdGroup = document.createElement('div');
    eventIdGroup.className = 'form-group event-group';
    const eventIdLabel = document.createElement('label');
    eventIdLabel.textContent = 'Event';
    eventIdGroup.appendChild(eventIdLabel);
    
    const inputRow = document.createElement('div');
    inputRow.className = 'event-input-row';
    
    const eventIdInput = document.createElement('input');
    eventIdInput.type = 'hidden';
    eventIdInput.id = 'event-id-code';

    const eventDisplay = document.createElement('input');
    eventDisplay.type = 'text';
    eventDisplay.id = 'event-id';
    eventDisplay.className = 'event-display';
    eventDisplay.readOnly = true;
    eventDisplay.style.width = '100%';
    eventDisplay.value = 'Loading event info...';

    inputRow.appendChild(eventIdInput);
    inputRow.appendChild(eventDisplay);
    eventIdGroup.appendChild(inputRow);
    formContainer.appendChild(eventIdGroup);

    // Team Number Group with Select
    const teamNumberGroup = document.createElement('div');
    teamNumberGroup.className = 'form-group';
    const teamNumberLabel = document.createElement('label');
    teamNumberLabel.textContent = 'Team Number';
    
    const teamSelect = document.createElement('select');
    teamSelect.id = 'team-select';
    teamSelect.required = true;
    teamSelect.disabled = true;
    teamSelect.style.width = '100%';
    teamSelect.innerHTML = '<option value="">Select Team</option>';

    teamNumberGroup.appendChild(teamNumberLabel);
    teamNumberGroup.appendChild(teamSelect);
    formContainer.appendChild(teamNumberGroup);

    // Rest of form elements
    elements.forEach(element => {
        const formGroup = document.createElement('div');
        formGroup.className = 'form-group';

        const label = document.createElement('label');
        label.textContent = element.label;
        formGroup.appendChild(label);

        let input;
        switch (element.type) {
            case 'number':
                input = document.createElement('input');
                input.type = 'number';
                input.className = 'full-width';
                formGroup.appendChild(input);
                break;
            case 'text':
                if (element.options[0] === 'big') {
                    input = document.createElement('textarea');
                    formGroup.classList.add('big-text');
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                }
                formGroup.appendChild(input);
                break;
            case 'checkbox':
                input = document.createElement('input');
                input.type = 'checkbox';
                formGroup.appendChild(input);
                break;
            case 'slider':
                input = document.createElement('input');
                input.type = 'range';
                input.min = element.options[0];
                input.max = element.options[1];
                input.step = element.options[2];
                input.value = element.options[0];

                const numberInput = document.createElement('input');
                numberInput.type = 'number';
                numberInput.min = element.options[0];
                numberInput.max = element.options[1];
                numberInput.step = element.options[2];
                numberInput.value = element.options[0];
                numberInput.className = 'small-text';

                updateSliderBackground(input);

                input.addEventListener('input', () => {
                    numberInput.value = input.value;
                    updateSliderBackground(input);
                });

                numberInput.addEventListener('input', () => {
                    input.value = numberInput.value;
                    updateSliderBackground(input);
                });

                formGroup.appendChild(input);
                formGroup.appendChild(numberInput);
                break;
        }

        formContainer.appendChild(formGroup);
    });

    const submitButton = document.createElement('button');
    submitButton.className = 'submit-btn';
    submitButton.textContent = 'Submit';
    submitButton.addEventListener('click', handleSubmit);
    formContainer.appendChild(submitButton);
}

// Add these helper functions to sync views
function updateLeaderboardView(eventId) {
    if (document.getElementById('leaderboard-container').classList.contains('active')) {
        fetchLeaderboard(eventId);
    }
}

function updateDataView(eventId) {
    if (document.getElementById('data-container').classList.contains('active')) {
        const teamSelect = document.getElementById('data-team-select');
        cachedFetch(`/dashboard/teams/?event=${eventId}`)
            .then(data => {
                teamSelect.innerHTML = '<option value="">Select Team</option>';
                data.teams.sort((a, b) => a - b).forEach(team => {
                    const option = document.createElement('option');
                    option.value = team;
                    option.textContent = team;
                    teamSelect.appendChild(option);
                });
                teamSelect.disabled = false;
            })
            .catch(error => {
                console.error('Error fetching teams:', error);
                teamSelect.disabled = true;
                teamSelect.innerHTML = '<option value="">Error loading teams</option>';
            });
    }
}

// Update showDataView function to properly populate teams
function showDataView() {
    hideAllContainers();
    const dataContainer = document.getElementById('data-container');
    dataContainer.classList.add('active');
    
    const eventId = localStorage.getItem('event');
    if (eventId) {
        updateDataView(eventId);
    }
}

function handleSubmit(event) {
    event.preventDefault();

    const teamNumber = document.getElementById('team-select').value;
    const eventId = document.getElementById('event-id-code').value; // Use hidden input value
    const formGroups = formContainer.querySelectorAll('.form-group');
    const data = [];

    formGroups.forEach(group => {
        const input = group.querySelector('input, textarea');
        if (input && input.id !== 'team-select' && input.id !== 'event-id') {
            if (input.type === 'checkbox') {
                data.push(input.checked ? 'true' : 'false');
            } else {
                data.push(input.value);
            }
        }
    });

    const dataString = data.join('|');
    const submitButton = event.target;
    submitButton.disabled = true;

    fetch('./add/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            team_number: teamNumber,
            event_id: eventId,
            data: dataString
        })
    })
    .then(response => {
        if (response.ok) {
            window.location.reload();
        } else {
            return response.json().then(data => {
                console.log('Form submission error:', data);
                throw new Error('Form submission failed');
            });
        }
    })
    .catch(error => console.error('Error submitting form:', error))
    .finally(() => {
        submitButton.disabled = false;
    });
}

let cachedMatches = null;

function showLeaderboard() {
    hideAllContainers();
    const leaderboardContainer = document.getElementById('leaderboard-container');
    leaderboardContainer.classList.add('active');
    
    // Check if this is the first time viewing leaderboard
    if (!localStorage.getItem('leaderboardMessage')) {
        showLeaderboardInstructions();
    }
    
    const eventId = document.getElementById('event-id-code')?.value || localStorage.getItem('event');
    if (eventId) {
        // Single promise chain instead of multiple parallel requests
        fetchLeaderboard(eventId)
            .then(() => fetchAllMatches(eventId))
            .catch(error => console.error('Error fetching leaderboard data:', error));
    }
}

function showLeaderboardInstructions() {
    const container = document.getElementById('leaderboard-container');
    const instructions = document.createElement('div');
    instructions.className = 'leaderboard-instructions show';
    instructions.innerHTML = `
        <h3>Welcome to the Leaderboard!</h3>
        <p>Here you can see all teams ranked by their performance.</p>
        <p>Click on any team to see their detailed stats including wins, ties, and losses.</p>
        <p>Click anywhere outside the stats popup to close it.</p>
        <button class="dismiss-btn" onclick="dismissLeaderboardInstructions(this)">Dismiss</button>
    `;
    container.insertBefore(instructions, container.firstChild);
}

function dismissLeaderboardInstructions(button) {
    localStorage.setItem('leaderboardMessage', 'true');
    const instructions = button.parentElement;
    instructions.classList.remove('show');
    // Remove just the instructions element after animation
    setTimeout(() => {
        if (instructions && instructions.parentElement) {
            instructions.remove();
        }
    }, 300);
    
    // Ensure the leaderboard data is still shown
    const eventId = document.getElementById('event-id-code')?.value || localStorage.getItem('event');
    if (eventId) {
        fetchLeaderboard(eventId);
    }
}

function fetchLeaderboard(eventId) {
    return cachedFetch(`./rankings/?event=${eventId}`)
        .then(data => {
            const container = document.getElementById('leaderboard-container');
            // Preserve instructions if they exist
            const instructions = container.querySelector('.leaderboard-instructions');
            container.innerHTML = '';
            if (instructions) {
                container.appendChild(instructions);
            }
            
            data.rankings.forEach(team => {
                const item = document.createElement('div');
                item.className = 'leaderboard-item';
                item.innerHTML = `
                    <div class="team-info">${team.teamNumber} - ${team.teamName}</div>
                    <div class="rank-badge">#${team.rank}</div>
                `;
                
                item.addEventListener('click', () => {
                    showStatsPopup(team);
                    // Also check for cached matches data
                    const cacheKey = `./matches/?event=${eventId}`;
                    if (apiCache.matches[cacheKey]?.data) {
                        const matches = apiCache.matches[cacheKey].data.matches.filter(match => 
                            match.red.teams.includes(parseInt(team.teamNumber)) || 
                            match.blue.teams.includes(parseInt(team.teamNumber))
                        );
                        renderMatchResults(matches, parseInt(team.teamNumber));
                    }
                });
                
                container.appendChild(item);
            });
        })
        .catch(error => console.error('Error fetching leaderboard:', error));
}

function fetchAllMatches(eventId) {
    return cachedFetch(`./matches/?event=${eventId}`)
        .then(data => {
            cachedMatches = data.matches;
            return data;
        });
}

function showStatsPopup(team) {
    const popup = document.getElementById('stats-popup');
    const content = popup.querySelector('.stats-content');
    
    content.querySelector('.stats-header').textContent = `${team.teamNumber} - ${team.teamName}`;
    content.querySelector('.stat-wins').textContent = team.wins;
    content.querySelector('.stat-ties').textContent = team.ties;
    content.querySelector('.stat-losses').textContent = team.losses;
    content.querySelector('.matches-played').textContent = `Matches Played: ${team.matchesPlayed}`;
    
    popup.classList.add('active');
    
    const eventId = document.getElementById('event-id-code')?.value || localStorage.getItem('event');
    
    // Force a fresh fetch of match data if we don't have it cached
    if (!apiCache.matches[`./matches/?event=${eventId}`]?.data) {
        fetchAllMatches(eventId).then(data => {
            const matches = data.matches.filter(match => 
                match.red.teams.includes(parseInt(team.teamNumber)) || 
                match.blue.teams.includes(parseInt(team.teamNumber))
            );
            renderMatchResults(matches, parseInt(team.teamNumber));
        });
    }
}

function renderRawApiResponse(data) {
    const container = document.getElementById('raw-api-response-container');
    container.innerHTML = ''; // Clear previous content
    const rawResponse = document.createElement('pre');
    rawResponse.textContent = JSON.stringify(data, null, 2);
    container.appendChild(rawResponse);
}

function renderMatchResults(matches, teamNumber) {
    const container = document.querySelector('.match-results-scroll');
    container.innerHTML = '';

    matches.sort((a, b) => {
        if (a.tournamentLevel !== b.tournamentLevel) {
            return a.tournamentLevel === 'QUALIFICATION' ? -1 : 1;
        }
        return a.matchNumber - b.matchNumber;
    });

    matches.forEach((match, index) => {
        const matchItem = document.createElement('div');
        matchItem.className = 'match-result-item';
        matchItem.dataset.index = index;  // Add index for scrolling

        const teamAlliance = match.red.teams.includes(teamNumber) ? 'red' : 'blue';
        const opponentAlliance = teamAlliance === 'red' ? 'blue' : 'red';

        matchItem.innerHTML = `
            <div class="match-label">${match.description}</div>
            <div class="match-result-scores">
                <span class="${teamAlliance}-score">${match[teamAlliance].total}</span>
                <span class="dash">-</span>
                <span class="${opponentAlliance}-score">${match[opponentAlliance].total}</span>
            </div>
            <div class="match-result-details">
                <div class="value ${teamAlliance}-alliance">${match[teamAlliance].teams.join(' ')}</div>
                <div class="label">VS</div>
                <div class="value ${opponentAlliance}-alliance">${match[opponentAlliance].teams.join(' ')}</div>
            </div>
            <div class="match-result-details">
                <div class="value">${match[teamAlliance].auto}</div>
                <div class="label">Auto</div>
                <div class="value">${match[opponentAlliance].auto}</div>
            </div>
            <div class="match-result-details">
                <div class="value">${match[teamAlliance].foul}</div>
                <div class="label">Foul</div>
                <div class="value">${match[opponentAlliance].foul}</div>
            </div>
        `;

        matchItem.addEventListener('click', () => {
            matchItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        container.appendChild(matchItem);
    });
}

// Update hideStatsPopup to properly hide both containers
function hideStatsPopup(event) {
    if (event.target.classList.contains('stats-popup')) {
        document.getElementById('stats-popup').classList.remove('active');
        const matchResultsContainer = document.getElementById('match-results-container');
        matchResultsContainer.classList.remove('active');
        matchResultsContainer.style.display = 'none';
    }
}

function fetchMatchResults(teamNumber, eventId) {
    fetch(`./matches/?event=${eventId}`)
        .then(handleApiResponse)
        .then(data => {
            const matches = data.matches.filter(match => 
                match.teams.some(team => team.teamNumber === teamNumber)
            );
            renderMatchResults(matches, teamNumber);
        })
        .catch(error => console.error('Error fetching match results:', error));
}

function showDataView() {
    hideAllContainers();
    const dataContainer = document.getElementById('data-container');
    dataContainer.classList.add('active');
    
    const eventId = localStorage.getItem('event');
    if (eventId) {
        updateDataView(eventId);
    }
}

function fetchTeamData(teamNumber, eventId) {
    if (!teamNumber || !eventId) return;
    
    fetch(`./view/?team=${teamNumber}&event=${eventId}`)
        .then(handleApiResponse)
        .then(data => {
            const container = document.getElementById('data-content');
            container.innerHTML = '';
            let hasContent = false;

            // Your team's private data section first
            if (data.private_data?.data) {
                const privateSection = document.createElement('div');
                privateSection.className = 'data-section';
                privateSection.innerHTML = `<h3>Your Scouting Data</h3>`;
                
                const entryDiv = document.createElement('div');
                entryDiv.className = 'data-entry';
                
                const fieldValues = data.private_data.data.split('|');
                data.fields.forEach((field, index) => {
                    entryDiv.innerHTML += `
                        <div class="field-value">
                            <span>${field}:</span>
                            <span>${fieldValues[index] || 'N/A'}</span>
                        </div>
                    `;
                });
                privateSection.appendChild(entryDiv);
                container.appendChild(privateSection);
                hasContent = true;
            }

            // Other teams' data section
            if (data.public_data?.data) {
                const myTeam = localStorage.getItem('myTeam');
                let hasPublicData = false;

                Object.entries(data.public_data.data).forEach(([teamId, teamEntries]) => {
                    Object.entries(teamEntries).forEach(([scoutingTeam, entryData]) => {
                        if (scoutingTeam === myTeam || teamId.split('-')[0] === myTeam) return;
                        
                        if (!hasPublicData && hasContent) {
                            const divider = document.createElement('div');
                            divider.className = 'data-divider';
                            container.appendChild(divider);
                            hasPublicData = true;
                        }

                        const publicSection = document.createElement('div');
                        publicSection.className = 'data-section';
                        publicSection.innerHTML = `<h3>Scouted by Team ${scoutingTeam}</h3>`;
                        
                        const entryDiv = document.createElement('div');
                        entryDiv.className = 'data-entry';
                        
                        const fieldValues = entryData.split('|');
                        data.fields.forEach((field, index) => {
                            // Display all fields regardless of their content
                            entryDiv.innerHTML += `
                                <div class="field-value">
                                    <span>${field}:</span>
                                    <span>${fieldValues[index] || 'N/A'}</span>
                                </div>
                            `;
                        });
                        publicSection.appendChild(entryDiv);
                        container.appendChild(publicSection);
                        hasContent = true;
                    });
                });
            }

            if (!hasContent) {
                container.innerHTML = `
                    <div class="data-section">
                        <div class="data-entry">
                            <p style="text-align: center; color: #666;">No scouting data available for this team.</p>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching team data:', error);
            const container = document.getElementById('data-content');
            container.innerHTML = `
                <div class="data-section">
                    <h3>Error</h3>
                    <div class="data-entry">
                        <pre style="color: red;">Failed to load team data</pre>
                    </div>
                </div>
            `;
        });
}

function cachedFetch(url, options = {}) {
    const cacheKey = url + (options.method || 'GET');
    
    // Determine cache category based on URL
    let cacheCategory = null;
    if (url.includes('/matches/')) cacheCategory = 'matches';
    else if (url.includes('/rankings/')) cacheCategory = 'rankings';
    else if (url.includes('/teams/')) cacheCategory = 'teams';
    else if (url.includes('/validate/')) cacheCategory = 'validate';
    else if (url.includes('/today/')) cacheCategory = 'today';
    else if (url.includes('/auth/')) cacheCategory = 'auth';

    // Different cache durations for different endpoints
    const cacheDuration = {
        matches: 300000, // 5 minutes
        rankings: 300000, // 5 minutes
        teams: 300000, // 5 minutes
        validate: 30000, // 30 seconds
        today: 3600000, // 1 hour
        auth: 0 // No caching for auth
    }[cacheCategory] || 0;
    
    // Check cache first if we have a cache duration
    if (cacheDuration && cacheCategory && apiCache[cacheCategory][cacheKey]?.timestamp > Date.now() - cacheDuration) {
        return Promise.resolve(apiCache[cacheCategory][cacheKey].data);
    }

    // Check for pending request
    if (pendingRequests[cacheKey]?.timestamp > Date.now() - 500) {
        return pendingRequests[cacheKey].promise;
    }

    // Make new request
    const requestPromise = fetch(url, options)
        .then(handleApiResponse)
        .then(data => {
            if (cacheDuration && cacheCategory) {
                apiCache[cacheCategory][cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
            }
            delete pendingRequests[cacheKey];
            return data;
        });

    pendingRequests[cacheKey] = {
        promise: requestPromise,
        timestamp: Date.now()
    };

    return requestPromise;
}

function clearApiCache() {
    Object.keys(apiCache).forEach(category => {
        apiCache[category] = {};
    });
    Object.keys(pendingRequests).forEach(key => {
        delete pendingRequests[key];
    });
}