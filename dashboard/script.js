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
    } else if (index === 3) {
        showFormContainer();
    }
}

function hideAllContainers() {
    hideOtpContainer();
    hideFormContainer();
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

    // Add Team Number field
    const teamNumberGroup = document.createElement('div');
    teamNumberGroup.className = 'form-group';
    const teamNumberLabel = document.createElement('label');
    teamNumberLabel.textContent = 'Team Number';
    const teamNumberInput = document.createElement('input');
    teamNumberInput.type = 'number';
    teamNumberInput.id = 'team-number';
    teamNumberInput.required = true;
    teamNumberInput.className = 'full-width'; // Change to full-width
    teamNumberGroup.appendChild(teamNumberLabel);
    teamNumberGroup.appendChild(teamNumberInput);
    formContainer.appendChild(teamNumberGroup);

    // Add Event ID field with save button
    const eventIdGroup = document.createElement('div');
    eventIdGroup.className = 'form-group';
    const eventIdLabel = document.createElement('label');
    eventIdLabel.textContent = 'Event ID';
    const eventIdInput = document.createElement('input');
    eventIdInput.type = 'text';
    eventIdInput.id = 'event-id';
    eventIdInput.required = true;
    const eventIdSaveButton = document.createElement('button');
    eventIdSaveButton.textContent = 'Save';
    eventIdSaveButton.className = 'btn btn-primary';
    eventIdSaveButton.style.marginLeft = '10px'; // Add padding
    eventIdSaveButton.addEventListener('click', () => {
        localStorage.setItem('event', eventIdInput.value);
    });
    eventIdGroup.appendChild(eventIdLabel);
    eventIdGroup.appendChild(eventIdInput);
    eventIdGroup.appendChild(eventIdSaveButton);
    formContainer.appendChild(eventIdGroup);

    // Set default value for Event ID if available
    const savedEventId = localStorage.getItem('event');
    if (savedEventId) {
        eventIdInput.value = savedEventId;
    }

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
                input.className = 'full-width'; // Change from small-text to full-width
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
                input.value = element.options[0]; // Set default value to minimum

                const numberInput = document.createElement('input');
                numberInput.type = 'number';
                numberInput.min = element.options[0];
                numberInput.max = element.options[1];
                numberInput.step = element.options[2];
                numberInput.value = element.options[0]; // Set default value to minimum
                numberInput.className = 'small-text';

                updateSliderBackground(input); // Initialize slider background

                input.addEventListener('input', () => {
                    numberInput.value = input.value;
                    updateSliderBackground(input); // Update slider background
                });

                numberInput.addEventListener('input', () => {
                    input.value = numberInput.value;
                    updateSliderBackground(input); // Update slider background
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

    const teamNumber = document.getElementById('team-number').value;
    const eventId = document.getElementById('event-id').value;
    const formGroups = formContainer.querySelectorAll('.form-group');
    const data = [];

    formGroups.forEach(group => {
        const input = group.querySelector('input, textarea');
        if (input && input.id !== 'team-number' && input.id !== 'event-id') {
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
            window.location.reload(); // Refresh the page
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