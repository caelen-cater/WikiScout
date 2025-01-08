const form = document.querySelector('form')
const inputs = form.querySelectorAll('input')
const KEYBOARDS = {
  backspace: 8,
  arrowLeft: 37,
  arrowRight: 39,
}

function handleInput(e) {
  const input = e.target
  const nextInput = input.nextElementSibling
  if (nextInput && input.value) {
    nextInput.focus()
    if (nextInput.value) {
      nextInput.select()
    }
  }
}

function handlePaste(e) {
  e.preventDefault()
  const paste = e.clipboardData.getData('text')
  inputs.forEach((input, i) => {
    input.value = paste[i] || ''
  })
}

function handleBackspace(e) { 
  const input = e.target
  if (input.value) {
    input.value = ''
    return
  }
  
  input.previousElementSibling.focus()
}

function handleArrowLeft(e) {
  const previousInput = e.target.previousElementSibling
  if (!previousInput) return
  previousInput.focus()
}

function handleArrowRight(e) {
  const nextInput = e.target.nextElementSibling
  if (!nextInput) return
  nextInput.focus()
}

function isMobile() {
  return /Mobi|Android/i.test(navigator.userAgent);
}

if (isMobile()) {
  document.documentElement.classList.add('mobile');
}

form.addEventListener('input', handleInput)
inputs[0].addEventListener('paste', handlePaste)

inputs.forEach(input => {
  input.addEventListener('focus', e => {
    setTimeout(() => {
      e.target.select()
    }, 0)
  })
  
  input.addEventListener('keydown', e => {
    switch(e.keyCode) {
      case KEYBOARDS.backspace:
        handleBackspace(e)
        break
      case KEYBOARDS.arrowLeft:
        handleArrowLeft(e)
        break
      case KEYBOARDS.arrowRight:
        handleArrowRight(e)
        break
      default:  
    }
  })
})

let isSubmitting = false;

form.addEventListener('submit', handleSubmit)

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

function handleSubmit(e) {
  e.preventDefault()
  if (isSubmitting) return

  isSubmitting = true
  const otp = Array.from(inputs).map(input => input.value).join('')
  const submitButton = form.querySelector('button[type="submit"]')
  submitButton.disabled = true
  submitButton.textContent = 'Loading...'

  trackInsight({
    message: 'OTP verification attempt',
    method: 'POST',
    trace: '/workspaces/WikiScout/code/auth/index.php',
    metadata: { otpLength: otp.length }
  });

  fetch('auth/', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: `otp=${otp}`
  })
  .then(response => {
    if (response.status === 200) {
      trackInsight({
        message: 'OTP verification success',
        trace: '/workspaces/WikiScout/code/auth/index.php'
      });
      window.location.href = '../../'
    } else if (response.status === 401) {
      trackInsight({
        message: 'OTP verification failed',
        trace: '/workspaces/WikiScout/code/auth/index.php',
        metadata: { error: 'Invalid code' }
      });
      alert('Invalid code')
      submitButton.disabled = false
      submitButton.textContent = 'Login'
      isSubmitting = false
    }
  })
  .catch(error => {
    trackInsight({
      message: 'OTP verification error',
      trace: '/workspaces/WikiScout/code/auth/index.php',
      metadata: { error: error.message }
    });
    console.error('Error:', error)
    submitButton.disabled = false
    submitButton.textContent = 'Login'
    isSubmitting = false
  })
}

inputs.forEach(input => {
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      handleSubmit(e)
    }
  })
})

document.addEventListener('DOMContentLoaded', () => {
  const otpInputs = document.querySelectorAll('.form-control');

  otpInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      if (input.value.length === 1 && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
        otpInputs[index - 1].focus();
      }
    });
  });
});