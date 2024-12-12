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

function clickItem(item, index) {

    menu.style.removeProperty("--timeOut");
    
    if (activeItem == item) return;
    
    if (activeItem) {
        activeItem.classList.remove("active");
    }

    
    item.classList.add("active");
    activeItem = item;
    offsetMenuBorder(activeItem, menuBorder);
    
    if (index === 0) {
        showOtpContainer();
    } else {
        hideOtpContainer();
    }
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

function fetchOtpCode() {
    fetch('./auth', {
        method: 'GET'
    })
    .then(response => response.json())
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
    fetch('./auth', {
        method: 'DELETE'
    })
    .then(response => response.json())
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
    .then(response => response.json())
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
        }
    });
    
})

window.addEventListener("resize", () => {
    offsetMenuBorder(activeItem, menuBorder);
    menu.style.setProperty("--timeOut", "none");
});