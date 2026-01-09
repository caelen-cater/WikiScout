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
    auth: {},
    team_schedule: {},
    event_schedule: {}
};

const pendingRequests = {};

// Get the base dashboard URL
const dashboardUrl = window.location.origin + '/dashboard/';

function validateSession() {
    return cachedFetch('./validate/', {
        method: 'GET'
    }).catch(error => {
        console.error('Error validating session:', error);
    });
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
    } else {
        hideOtpContainer();
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

let otpRefreshInterval = null;
let otpEventSource = null;

function listenForOTPs() {
  if (otpEventSource) return;
  otpEventSource = new EventSource("./otp_sse/");
  otpEventSource.addEventListener("otp_update", ({ data }) => {
    const otpCode = data.padStart(8, "-");
    otpInputs.forEach((input, index) => {
      input.value = otpCode[index] || "-";
    });
  });
}

function showOtpContainer() {
    otpContainer.classList.add('active');
    otpInfoContainer.classList.add('active');
    listenForOTPs();
}

function hideOtpContainer() {
    otpContainer.classList.remove('active');
    otpInfoContainer.classList.remove('active');
    if (otpEventSource) {
        otpEventSource.close();
        otpEventSource  = null;
    }
}

if (menuItems[0].classList.contains('active')) {
    showOtpContainer();
}

// Add this new function near the top with other utility functions
async function retryFetch(url, options = {}, maxRetries = 3, delay = 1000) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            const response = await fetch(url, options);
            
            // Immediately handle 401 and 501
            if (response.status === 401) {
                window.location.href = '../login';
                throw new Error('Unauthorized - Redirecting to login');
            }
            if (response.status === 501) {
                window.location.href = '../activate';
                throw new Error('Not Implemented - Redirecting to activate');
            }

            // For 404, retry
            if (response.status === 404) {
                console.log(`Got 404, attempt ${i + 1} of ${maxRetries}, retrying...`);
                await new Promise(resolve => setTimeout(resolve, delay));
                continue;
            }

            return response;
        } catch (error) {
            if (i === maxRetries - 1) throw error;
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
    throw new Error(`Failed after ${maxRetries} retries`);
}

// Replace the existing handleApiResponse function with this version
async function handleApiResponse(response) {
    // If response is 401, redirect to login
    if (response.status === 401) {
        window.location.href = '../login';
        throw new Error('Unauthorized - Redirecting to login');
    }
    
    // If response is 501, redirect to activate
    if (response.status === 501) {
        window.location.href = '../activate';
        throw new Error('Not Implemented - Redirecting to activate');
    }

    // For empty responses or any other status code, try to parse JSON if exists
    try {
        const text = await response.text();
        return text ? JSON.parse(text) : {};
    } catch (e) {
        return {};
    }
}

deleteBtn.addEventListener('click', () => {
    deleteBtn.disabled = true;
    regenerateBtn.disabled = true;
    fetch('./auth/', {
        method: 'DELETE'
    })
    .then(handleApiResponse)
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
        if (index === 3) {
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

                // Fetch teams at the event
                fetchTeamsAtEvent(data.event.code);
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
    fetch('../../form.json')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Form data loaded:', data);
            if (!data || (Array.isArray(data) && data.length === 0)) {
                console.warn('Form data is empty');
                return;
            }
            const formElements = parseFormData(data);
            console.log('Parsed form elements:', formElements);
            if (formElements && formElements.length > 0) {
                renderForm(formElements);
            } else {
                console.error('No form elements to render');
            }
        })
        .catch(error => {
            console.error('Error fetching form data:', error);
            // Show error message to user
            if (formContainer) {
                formContainer.innerHTML = '<div style="color: red; padding: 1rem;">Error loading form: ' + error.message + '</div>';
            }
        });
}

function fetchTeamsAtEvent(eventCode) {
    const teamSelect = document.getElementById('team-select');
    teamSelect.disabled = true;
    teamSelect.innerHTML = '<option value="">Loading teams...</option>';

    cachedFetch(`/dashboard/teams/?event=${eventCode}`)
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

function parseFormData(data) {
    // Expect JSON array format
    if (Array.isArray(data)) {
        return data;
    }
    
    console.error('Invalid form data format - expected JSON array');
    return [];
}

function updateSliderBackground(slider) {
    const value = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
    slider.style.setProperty('--value', `${value}%`);
}

function showWarningTooltip(element, message) {
    const existingTooltip = document.querySelector('.warning-tooltip');
    if (existingTooltip) {
        existingTooltip.remove();
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'warning-tooltip';
    tooltip.textContent = 'This field is locked. Please select a team number first to enable data entry.';

    // Position tooltip near the element
    const rect = element.getBoundingClientRect();
    tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
    tooltip.style.left = `${rect.left + window.scrollX}px`;

    // Ensure tooltip stays within viewport
    document.body.appendChild(tooltip);
    const tooltipRect = tooltip.getBoundingClientRect();
    if (tooltipRect.right > window.innerWidth) {
        tooltip.style.left = `${window.innerWidth - tooltipRect.width - 10}px`;
    }

    // Remove tooltip after animation
    tooltip.addEventListener('animationend', () => {
        tooltip.remove();
    });
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

    // Fix: Pass teamSelect to loadExistingTeamData
    teamSelect.addEventListener('change', (e) => {
        const formFields = formContainer.querySelectorAll('input, textarea, select, button.submit-btn');
        const selectedTeam = e.target.value;
        
        if (selectedTeam) {
            // Show loading spinner immediately
            const loadingSpinner = document.createElement('div');
            loadingSpinner.className = 'loading-spinner active';
            formContainer.appendChild(loadingSpinner);

            // Keep fields disabled
            formFields.forEach(field => {
                if (field !== e.target && field.id !== 'event-id' && field.id !== 'event-id-code') {
                    field.disabled = true;
                }
            });

            // Load existing data
            const eventCode = document.getElementById('event-id-code').value;
            loadExistingTeamData(selectedTeam, eventCode, formFields, e.target);
        } else {
            formFields.forEach(field => {
                if (field !== e.target && field.id !== 'event-id' && field.id !== 'event-id-code') {
                    field.disabled = true;
                }
            });
        }
    });

    teamNumberGroup.appendChild(teamNumberLabel);
    teamNumberGroup.appendChild(teamSelect);
    formContainer.appendChild(teamNumberGroup);

    // Add this function to handle disabled field clicks
    function handleDisabledFieldClick(e) {
        if (e.target.disabled) {
            showWarningTooltip(e.target, 'Please select a team first');
            e.preventDefault();
        }
    }

    // Rest of form elements
    elements.forEach(element => {
        // Handle separator
        if (element.type === 'separator') {
            const separator = document.createElement('div');
            separator.className = 'form-separator';
            if (element.visible === false) {
                separator.classList.add('invisible');
            }
            formContainer.appendChild(separator);
            return; // Skip to next element
        }

        // Handle header
        if (element.type === 'header') {
            const header = document.createElement('div');
            header.className = 'form-header';
            header.textContent = element.label;
            formContainer.appendChild(header);
            return; // Skip to next element
        }

        const formGroup = document.createElement('div');
        formGroup.className = 'form-group';

        const label = document.createElement('label');
        label.textContent = element.label;
        formGroup.appendChild(label);

        // Add description/subtext if provided
        if (element.description) {
            const description = document.createElement('div');
            description.className = 'field-description';
            description.textContent = element.description;
            formGroup.appendChild(description);
        }

        let input;
        switch (element.type) {
            case 'number':
                input = document.createElement('input');
                input.type = 'number';
                input.className = 'full-width';
                input.disabled = true; // Initially disabled
                input.addEventListener('click', handleDisabledFieldClick);
                formGroup.appendChild(input);
                break;
            case 'text':
                if (element.big || (element.options && element.options[0] === 'big')) {
                    input = document.createElement('textarea');
                    formGroup.classList.add('big-text');
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                }
                input.disabled = true; // Initially disabled
                input.addEventListener('click', handleDisabledFieldClick);
                formGroup.appendChild(input);
                break;
            case 'checkbox':
                input = document.createElement('input');
                input.type = 'checkbox';
                input.disabled = true; // Initially disabled
                input.addEventListener('click', handleDisabledFieldClick);
                formGroup.appendChild(input);
                break;
            case 'slider':
                input = document.createElement('input');
                input.type = 'range';
                input.min = element.min || (element.options && element.options[0]) || 0;
                input.max = element.max || (element.options && element.options[1]) || 100;
                input.step = element.step || (element.options && element.options[2]) || 1;
                input.value = input.min;
                input.disabled = true; // Initially disabled
                input.addEventListener('click', handleDisabledFieldClick);

                const numberInput = document.createElement('input');
                numberInput.type = 'number';
                numberInput.min = input.min;
                numberInput.max = input.max;
                numberInput.step = input.step;
                numberInput.value = input.value;
                numberInput.className = 'small-text';
                numberInput.disabled = true; // Initially disabled
                numberInput.addEventListener('click', handleDisabledFieldClick);

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
            case 'options':
                input = document.createElement('select');
                input.className = 'full-width';
                input.disabled = true; // Initially disabled
                input.addEventListener('click', handleDisabledFieldClick);
                
                // Add default empty option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select an option...';
                input.appendChild(defaultOption);
                
                // Add options from element.options array
                if (element.options && Array.isArray(element.options)) {
                    element.options.forEach(option => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option;
                        optionElement.textContent = option;
                        input.appendChild(optionElement);
                    });
                }
                
                formGroup.appendChild(input);
                break;
        }

        formContainer.appendChild(formGroup);
    });

    const submitButton = document.createElement('button');
    submitButton.className = 'submit-btn';
    submitButton.textContent = 'Submit';
    submitButton.disabled = true; // Initially disabled
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
    
    const container = document.getElementById('data-content');
    container.innerHTML = '<div class="loading-spinner active"></div>';
    
    Promise.all([
        fetch(`./rankings/?event=${eventId}`).then(handleApiResponse),
        fetch(`./score/?team=${teamNumber}`).then(handleApiResponse),
        fetch(`./view/?team=${teamNumber}&event=${eventId}`).then(handleApiResponse)
    ])
    .then(([rankingsData, scoreData, data]) => {
        const teamRank = rankingsData.rankings.find(t => t.teamNumber == teamNumber)?.rank || 'N/A';
        const teamScore = scoreData.score || '0';

        container.innerHTML = '';
        let hasContent = false;

        // Team info header
        const teamInfoContainer = document.createElement('div');
        teamInfoContainer.className = 'team-info-container';
        teamInfoContainer.innerHTML = `
            <div class="team-info">
                <div class="team-header">
                    <span class="rank-badge">#${teamRank}</span>
                    <span class="score-badge">${teamScore}</span>
                    Team ${teamNumber}
                </div>
            </div>
            <button class="match-history-btn" onclick="showMatchHistory(${teamNumber}, '${eventId}')">Match History</button>
        `;
        container.appendChild(teamInfoContainer);

        // Private data section
        if (data.private_data?.data?.length > 0) {
            const privateSection = document.createElement('div');
            privateSection.className = 'data-section';
            privateSection.innerHTML = `<h3>Your Scouting Data</h3>`;
            
            const entryDiv = document.createElement('div');
            entryDiv.className = 'data-entry';
            
            data.fields.forEach((field, index) => {
                entryDiv.innerHTML += `
                    <div class="field-value">
                        <span>${field}:</span>
                        <span>${formatFieldValue(data.private_data.data[index])}</span>
                    </div>
                `;
            });
            
            privateSection.appendChild(entryDiv);
            container.appendChild(privateSection);
            hasContent = true;
        }

        // Public data section
        if (data.public_data?.length > 0) {
            if (hasContent) {
                const divider = document.createElement('div');
                divider.className = 'data-divider';
                container.appendChild(divider);
            }

            const publicSection = document.createElement('div');
            publicSection.className = 'data-section';
            publicSection.innerHTML = '<h3>Other Teams\' Scouting Data</h3>';

            data.public_data.forEach(entry => {
                const entryDiv = document.createElement('div');
                entryDiv.className = 'data-entry';
                entryDiv.innerHTML = `<div class="scouting-team-header">Scouted by Team ${entry.scouting_team}</div>`;
                
                data.fields.forEach((field, index) => {
                    entryDiv.innerHTML += `
                        <div class="field-value">
                            <span>${field}:</span>
                            <span>${formatFieldValue(entry.data[index])}</span>
                        </div>
                    `;
                });
                
                publicSection.appendChild(entryDiv);
            });
            
            container.appendChild(publicSection);
            hasContent = true;
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

        // Log the data for debugging
        console.log('Received data:', data);
    })
    .catch(error => {
        console.error('Error fetching team data:', error);
        container.innerHTML = `
            <div class="data-section">
                <div class="data-entry">
                    <p style="text-align: center; color: red;">Error loading team data. Please try again.</p>
                </div>
            </div>
        `;
    });
}

// Update formatFieldValue function to handle all cases
function formatFieldValue(value) {
    if (!value) return 'N/A';
    if (value === 'true') return '<span class="bool-value bool-true">✓</span>';
    if (value === 'false') return '<span class="bool-value bool-false">✕</span>';
    if (value === 'Redacted Field') return '<span class="redacted">Redacted</span>';
    return value;
}

function showMatchHistory(teamNumber, eventId) {
    const popup = document.getElementById('stats-popup');
    const content = popup.querySelector('.stats-content');
    
    // Fetch team details for the popup header
    fetch(`./rankings/?event=${eventId}`)
        .then(handleApiResponse)
        .then(data => {
            const team = data.rankings.find(t => t.teamNumber == teamNumber);
            if (team) {
                content.querySelector('.stats-header').textContent = `${team.teamNumber} - ${team.teamName}`;
                content.querySelector('.stat-wins').textContent = team.wins;
                content.querySelector('.stat-ties').textContent = team.ties;
                content.querySelector('.stat-losses').textContent = team.losses;
                content.querySelector('.matches-played').textContent = `Matches Played: ${team.matchesPlayed}`;
            }
        })
        .catch(error => console.error('Error fetching team details:', error));

    // Fetch and render match results
    fetchAllMatches(eventId).then(data => {
        const matches = data.matches.filter(match => 
            match.red.teams.includes(teamNumber) || 
            match.blue.teams.includes(teamNumber)
        );
        renderMatchResults(matches, teamNumber);
    });

    popup.classList.add('active');
}

function handleSubmit(event) {
    event.preventDefault();

    const teamNumber = document.getElementById('team-select').value;
    const eventId = document.getElementById('event-id-code').value; // Use hidden input value
    const formGroups = formContainer.querySelectorAll('.form-group');
    const data = [];

    formGroups.forEach(group => {
        // Skip separators and headers - they don't have inputs
        if (group.classList.contains('form-separator') || group.classList.contains('form-header')) {
            return;
        }
        
        const input = group.querySelector('input, textarea, select');
        if (input && input.id !== 'team-select' && input.id !== 'event-id' && input.id !== 'event-id-code') {
            if (input.type === 'checkbox') {
                data.push(input.checked ? 'true' : 'false');
            } else if (input.tagName === 'SELECT') {
                // Handle select/options
                data.push(input.value || '');
            } else {
                data.push(input.value);
            }
        }
    });

    const dataJson = JSON.stringify(data);
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
            data: dataJson
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
    else if (url.includes('/event_schedule/')) cacheCategory = 'event_schedule';
    else if (url.includes('/team_schedule/')) cacheCategory = 'team_schedule';

    // Different cache durations for different endpoints
    const cacheDuration = {
        matches: 300000, // 5 minutes
        rankings: 300000, // 5 minutes
        teams: 300000, // 5 minutes
        event_schedule: 300000, // 5 minutes
        team_schedule: 300000, // 5 minutes
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
    const requestPromise = retryFetch(url, options)
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

// Add this new function to handle data loading
function loadExistingTeamData(teamNumber, eventCode, formFields, teamSelect) {
    // Use existing spinner if it exists, or create new one
    let loadingSpinner = formContainer.querySelector('.loading-spinner');
    if (!loadingSpinner) {
        loadingSpinner = document.createElement('div');
        loadingSpinner.className = 'loading-spinner active';
        formContainer.appendChild(loadingSpinner);
    }

    // Keep fields disabled during load
    formFields.forEach(field => {
        if (field !== teamSelect && field.id !== 'event-id' && field.id !== 'event-id-code') {
            field.disabled = true;
        }
    });

    console.log('Fetching data for team:', teamNumber, 'event:', eventCode); // Debug log

    // Fetch existing data
    return fetch(`./view/?team=${teamNumber}&event=${eventCode}`)
        .then(handleApiResponse)
        .then(data => {
            console.log('Received data:', data); // Debug log
            loadingSpinner.remove();

            if (data.private_data?.data?.length > 0) {
                // Get form groups in correct order, excluding event and team selectors
                const formGroups = Array.from(formContainer.querySelectorAll('.form-group'))
                    .filter(group => !group.querySelector('#event-id-code, #team-select'));
                
                // Populate each field with saved data
                data.private_data.data.forEach((value, index) => {
                    const formGroup = formGroups[index];
                    if (!formGroup) return;

                    if (formGroup.querySelector('input[type="checkbox"]')) {
                        // Handle checkbox
                        const checkbox = formGroup.querySelector('input[type="checkbox"]');
                        checkbox.checked = value === 'true';
                    } else if (formGroup.querySelector('input[type="range"]')) {
                        // Handle slider and its number input together
                        const slider = formGroup.querySelector('input[type="range"]');
                        const numberInput = formGroup.querySelector('input[type="number"]');
                        slider.value = value;
                        numberInput.value = value; // Sync number input
                        updateSliderBackground(slider);
                    } else if (formGroup.querySelector('textarea')) {
                        // Handle textarea
                        formGroup.querySelector('textarea').value = value;
                    } else if (formGroup.querySelector('select')) {
                        // Handle select/options
                        const select = formGroup.querySelector('select');
                        select.value = value || '';
                    } else if (formGroup.querySelector('input[type="text"], input[type="number"]')) {
                        // Handle text/number inputs
                        formGroup.querySelector('input').value = value;
                    }
                });
            }

            // Enable all fields after loading
            formFields.forEach(field => {
                if (field !== teamSelect && field.id !== 'event-id' && field.id !== 'event-id-code') {
                    field.disabled = false;
                }
            });
        })
        .catch(error => {
            console.error('Error loading team data:', error);
            loadingSpinner.remove();
            // Enable fields even if there's an error
            formFields.forEach(field => {
                if (field !== teamSelect && field.id !== 'event-id' && field.id !== 'event-id-code') {
                    field.disabled = false;
                }
            });
        });
}
