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

function validateSession() {
    fetch('./validate/', {
        method: 'GET'
    })
    .then(handleApiResponse)
    .catch(error => console.error('Error validating session:', error));
}

window.addEventListener('load', () => {
    validateSession();
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
    } else if (index === 3) {
        showFormContainer();
    }
}

function hideAllContainers() {
    hideOtpContainer();
    hideFormContainer();
    document.getElementById('leaderboard-container').classList.remove('active');
    document.getElementById('stats-popup').classList.remove('active');
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

    const eventIdSelect = document.createElement('select');
    eventIdSelect.id = 'event-id';
    eventIdSelect.required = true;
    eventIdSelect.style.width = '100%';

    const eventIdSaveButton = document.createElement('button');
    eventIdSaveButton.textContent = 'Save';
    eventIdSaveButton.className = 'btn btn-primary';
    eventIdSaveButton.style.margin = '0';
    eventIdSaveButton.addEventListener('click', () => {
        if (eventIdInput.value) {
            localStorage.setItem('event', eventIdInput.value);
        }
    });

    fetch('./today/')
        .then(response => response.json())
        .then(data => {
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select an event';
            eventIdSelect.appendChild(defaultOption);

            const eventData = {};
            data.events.forEach(event => {
                eventData[event.name] = event.code;
                const option = document.createElement('option');
                option.value = event.name;
                option.textContent = event.name;
                eventIdSelect.appendChild(option);
            });

            // Handle event selection changes
            eventIdSelect.addEventListener('change', () => {
                const selectedName = eventIdSelect.value;
                const eventCode = eventData[selectedName] || '';
                eventIdInput.value = eventCode;
                
                // Update team select when event changes
                const teamSelect = document.getElementById('team-select');
                if (eventCode) {
                    fetch(`/dashboard/teams/?event=${eventCode}`)
                        .then(response => response.json())
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
                } else {
                    teamSelect.disabled = true;
                    teamSelect.innerHTML = '<option value="">Select Team</option>';
                }
            });

            // Set saved event if it exists
            const savedEventId = localStorage.getItem('event');
            if (savedEventId) {
                const savedEventName = Object.keys(eventData).find(name => 
                    eventData[name] === savedEventId
                );
                if (savedEventName) {
                    eventIdSelect.value = savedEventName;
                    eventIdInput.value = savedEventId;
                    eventIdSelect.dispatchEvent(new Event('change'));
                }
            }
        });

    inputRow.appendChild(eventIdInput);
    inputRow.appendChild(eventIdSelect);
    inputRow.appendChild(eventIdSaveButton);
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
        fetchLeaderboard(eventId);
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
    fetch(`./rankings/?event=${eventId}`)
        .then(handleApiResponse)
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
                
                item.addEventListener('click', () => showStatsPopup(team));
                container.appendChild(item);
            });
        })
        .catch(error => console.error('Error fetching leaderboard:', error));
}

function showStatsPopup(team) {
    const popup = document.getElementById('stats-popup');
    const content = popup.querySelector('.stats-content');
    
    content.querySelector('.stat-wins').textContent = team.wins;
    content.querySelector('.stat-ties').textContent = team.ties;
    content.querySelector('.stat-losses').textContent = team.losses;
    content.querySelector('.matches-played').textContent = `Matches Played: ${team.matchesPlayed}`;
    
    popup.classList.add('active');
}

function hideStatsPopup(event) {
    if (event.target.classList.contains('stats-popup')) {
        document.getElementById('stats-popup').classList.remove('active');
    }
}