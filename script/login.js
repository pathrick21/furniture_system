// FurniPlace Login System - Security & Validation Module

/**
 * Security Configuration
 */
const SECURITY_CONFIG = {
    MAX_ATTEMPTS: 3,
    INITIAL_LOCKOUT: 15, // seconds
    MAX_LOCKOUT: 60,     // seconds
    STORAGE_KEYS: {
        ERROR_COUNT: 'fp_errorCount',
        LOCKOUT_TIME: 'fp_lockoutTime',
        LOCKOUT_DURATION: 'fp_lockoutDuration'
    }
};

/**
 * State Management
 */
let AppState = {
    errorCount: 0,
    lockoutTime: 0,
    currentLockoutDuration: SECURITY_CONFIG.INITIAL_LOCKOUT,
    timerInterval: null
};

/**
 * Initialize Application
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeSecurity();
    setupEventListeners();
});

/**
 * Security Initialization
 */
function initializeSecurity() {
    loadSecurityState();
    
    if (AppState.lockoutTime > 0) {
        activateLockout();
    }
}

/**
 * Load State from Storage
 */
function loadSecurityState() {
    const savedErrorCount = localStorage.getItem(SECURITY_CONFIG.STORAGE_KEYS.ERROR_COUNT);
    const savedLockoutTime = localStorage.getItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_TIME);
    const savedDuration = localStorage.getItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_DURATION);

    if (savedErrorCount) AppState.errorCount = parseInt(savedErrorCount);
    if (savedLockoutTime) AppState.lockoutTime = parseInt(savedLockoutTime);
    if (savedDuration) AppState.currentLockoutDuration = parseInt(savedDuration);
}

/**
 * Save State to Storage
 */
function saveSecurityState() {
    localStorage.setItem(SECURITY_CONFIG.STORAGE_KEYS.ERROR_COUNT, AppState.errorCount);
    localStorage.setItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_TIME, AppState.lockoutTime);
    localStorage.setItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_DURATION, AppState.currentLockoutDuration);
}

/**
 * Setup Event Listeners
 */
function setupEventListeners() {
    const form = document.querySelector('.LoginForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
}

/**
 * Handle Form Submission
 */
async function handleFormSubmit(event) {
    event.preventDefault();
    
    if (AppState.lockoutTime > 0) {
        // Shake animation for visual feedback
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            countdownEl.style.animation = 'shake 0.5s ease';
            setTimeout(() => {
                countdownEl.style.animation = 'slideDown 0.4s ease';
            }, 500);
        }
        return;
    }

    if (!validateForm()) return;

    const formData = new FormData(event.target);
    const submitBtn = document.getElementById('login-btn');
    
    setLoadingState(true);

    try {
        const response = await fetch('login.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        handleLoginResponse(data);
        
    } catch (error) {
        console.error('Login error:', error);
        showError('Connection error. Please try again.');
    } finally {
        setLoadingState(false);
    }
}

/**
 * Form Validation
 */
function validateForm() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    let isValid = true;
    let message = '';

    if (username.length < 2) {
        message = 'Username must be at least 2 characters long.';
        isValid = false;
    } else if (username.length > 20) {
        message = 'Username must not exceed 20 characters.';
        isValid = false;
    }

    if (password.length < 6) {
        message = 'Password must be at least 6 characters long.';
        isValid = false;
    }

    if (!isValid) {
        showError(message);
    }

    return isValid;
}

/**
 * Handle Login Response - FIXED for pending accounts
 */
function handleLoginResponse(data) {
    const errorDiv = document.getElementById('login-error');
    
    if (data.success) {
        clearSecurityState();
        showSuccess('Login successful! Redirecting...');
        
        setTimeout(() => {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = '../php/home.php';
            }
        }, 500);
        
    } else {
        // CHECK FOR PENDING STATUS FIRST - THIS IS THE FIX
        if (data.error_type === 'pending') {
            // Show ONLY the pending message
            showPendingMessage(data.error);
            // DO NOT increment error count
            // DO NOT show lockout
            // DO NOT show attempts alert
            return; // Exit immediately
        }
        
        // Handle other errors normally
        showError(data.error);
        handleLoginFailure(data.attempts);
    }
}

/**
 * Show Pending Message Only - NEW FUNCTION
 */
function showPendingMessage(message) {
    // Hide any existing attempts alert
    const attemptsAlert = document.getElementById('attemptsAlert');
    if (attemptsAlert) {
        attemptsAlert.style.display = 'none';
    }
    
    // Hide the countdown timer if visible
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        countdownEl.classList.remove('active');
    }
    
    // Show only the pending message
    const errorDiv = document.getElementById('login-error');
    if (errorDiv) {
        errorDiv.style.backgroundColor = '#FFF3E0';
        errorDiv.style.color = '#E65100';
        errorDiv.style.borderLeft = '4px solid #FF9800';
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `
            <i class="fas fa-clock" style="margin-right: 10px;"></i>
            ${message}
        `;
    }
}

/**
 * Handle Login Failure (Security) - Only for REAL failures
 */
function handleLoginFailure(serverAttempts) {
    if (serverAttempts) {
        AppState.errorCount = serverAttempts;
    } else {
        AppState.errorCount++;
    }
    
    saveSecurityState();

    // Show alert and forgot link after 2 attempts (but NOT on 3rd when locking)
    if (AppState.errorCount >= 2 && AppState.errorCount < SECURITY_CONFIG.MAX_ATTEMPTS) {
        // Hide the specific error message when showing attempts alert
        const errorDiv = document.getElementById('login-error');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
        
        const forgotLink = document.getElementById('forgot-password');
        if (forgotLink) {
            forgotLink.style.display = 'block';
            forgotLink.style.animation = 'slideDown 0.3s ease';
        }
        
        injectAttemptsAlert();
    }

    // Lockout on 3rd attempt - hide attempts alert and show lockout
    if (AppState.errorCount >= SECURITY_CONFIG.MAX_ATTEMPTS) {
        // Hide the attempts alert before starting lockout
        const attemptsAlert = document.getElementById('attemptsAlert');
        if (attemptsAlert) {
            attemptsAlert.style.display = 'none';
        }
        initiateLockout();
    }
}

/**
 * Inject Multiple Attempts Alert
 */
function injectAttemptsAlert() {
    let existingAlert = document.getElementById('attemptsAlert');
    if (existingAlert) {
        existingAlert.style.display = 'flex';
        return;
    }
    
    const form = document.querySelector('.LoginForm');
    if (!form) return;
    
    const firstFormGroup = form.querySelector('.form-group');
    if (!firstFormGroup) return;
    
    const alertHTML = `
        <div class="attempts-alert" id="attemptsAlert">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="alert-content">
                <span class="alert-title">Multiple failed attempts</span>
                <p class="alert-text">Check your credentials or reset your password.</p>
            </div>
        </div>
    `;
    
    firstFormGroup.insertAdjacentHTML('beforebegin', alertHTML);
}

/**
 * Initiate Account Lockout
 */
function initiateLockout() {
    AppState.lockoutTime = AppState.currentLockoutDuration;
    saveSecurityState();
    activateLockout();
}

/**
 * Activate Lockout UI
 */
function activateLockout() {
    setControlsDisabled(true);
    disableShowPasswordToggle(true);
    
    // Show the countdown timer above username
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        countdownEl.classList.add('active');
        
        // Reset animation for progress bar
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.animation = 'none';
            setTimeout(() => {
                progressBar.style.animation = `countdown-progress ${AppState.lockoutTime}s linear`;
            }, 10);
        }
    }
    
    disableNavigation(true);
    startLockoutTimer();
}

/**
 * Lockout Timer Logic
 */
function startLockoutTimer() {
    updateTimerDisplay();
    
    if (AppState.timerInterval) clearInterval(AppState.timerInterval);
    
    AppState.timerInterval = setInterval(() => {
        if (AppState.lockoutTime > 0) {
            AppState.lockoutTime--;
            saveSecurityState();
            updateTimerDisplay();
        } else {
            endLockout();
        }
    }, 1000);
}

/**
 * Update Timer Display
 */
function updateTimerDisplay() {
    const timeLeftEl = document.getElementById('time-left');
    if (timeLeftEl) {
        timeLeftEl.textContent = AppState.lockoutTime;
    }
}

/**
 * End Lockout
 */
function endLockout() {
    clearInterval(AppState.timerInterval);
    
    // Hide countdown timer
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        countdownEl.classList.remove('active');
    }
    
    setControlsDisabled(false);
    disableNavigation(false);
    disableShowPasswordToggle(false);
    
    AppState.errorCount = 0;
    AppState.lockoutTime = 0;
    AppState.currentLockoutDuration = Math.min(
        AppState.currentLockoutDuration * 2, 
        SECURITY_CONFIG.MAX_LOCKOUT
    );
    
    saveSecurityState();
    
    // Keep attempts alert hidden after lockout ends
    const attemptsAlert = document.getElementById('attemptsAlert');
    if (attemptsAlert) {
        attemptsAlert.style.display = 'none';
    }
}

/**
 * Disable/Enable Form Controls
 */
function setControlsDisabled(isDisabled) {
    const elements = [
        document.getElementById('username'),
        document.getElementById('password'),
        document.getElementById('login-btn')
    ];
    
    elements.forEach(el => {
        if (el) {
            el.disabled = isDisabled;
            if (isDisabled) {
                el.style.opacity = '0.6';
                el.style.cursor = 'not-allowed';
            } else {
                el.style.opacity = '1';
                el.style.cursor = '';
            }
        }
    });
}

/**
 * Disable/Enable Navigation Links
 */
function disableNavigation(isDisabled) {
    const navLinks = [
        document.getElementById('register-link'),
        document.getElementById('register-link3')
    ];
    
    navLinks.forEach(link => {
        if (link) {
            link.style.pointerEvents = isDisabled ? 'none' : 'auto';
            link.style.opacity = isDisabled ? '0.5' : '1';
        }
    });
}

/**
 * Disable/Enable Show Password Checkbox
 */
function disableShowPasswordToggle(isDisabled) {
    const showPassCheckbox = document.getElementById('showPassword');
    const showPassLabel = document.querySelector('.checkbox-wrapper');
    const showPassText = document.querySelector('.label-text');
    const passwordField = document.getElementById('password');
    
    if (showPassCheckbox) {
        showPassCheckbox.disabled = isDisabled;
        
        if (showPassLabel) {
            showPassLabel.style.opacity = isDisabled ? '0.5' : '1';
            showPassLabel.style.cursor = isDisabled ? 'not-allowed' : 'pointer';
            showPassLabel.style.pointerEvents = isDisabled ? 'none' : 'auto';
        }
        
        if (showPassText) {
            showPassText.style.color = isDisabled ? '#999' : '';
        }
        
        if (isDisabled && showPassCheckbox.checked && passwordField) {
            passwordField.type = 'password';
            showPassCheckbox.checked = false;
        }
    }
}

/**
 * Loading State
 */
function setLoadingState(isLoading) {
    const btn = document.getElementById('login-btn');
    if (btn) {
        btn.disabled = isLoading;
        if (isLoading) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        } else {
            btn.innerHTML = '<span>Sign In</span><i class="fas fa-arrow-right"></i>';
        }
    }
}

/**
 * UI Helpers
 */
function showError(message) {
    const errorDiv = document.getElementById('login-error');
    if (errorDiv) {
        errorDiv.style.backgroundColor = '#FFEBEE';
        errorDiv.style.color = '#C62828';
        errorDiv.style.borderLeft = '4px solid #F44336';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>${message}`;
        errorDiv.style.display = 'flex';
        
        // Don't auto-hide if we're about to show lockout
        if (AppState.errorCount < SECURITY_CONFIG.MAX_ATTEMPTS - 1) {
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    }
}

function showSuccess(message) {
    const errorDiv = document.getElementById('login-error');
    if (errorDiv) {
        errorDiv.style.backgroundColor = '#E8F5E9';
        errorDiv.style.color = '#2E7D32';
        errorDiv.style.borderLeftColor = '#4CAF50';
        errorDiv.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 10px;"></i>${message}`;
        errorDiv.style.display = 'flex';
    }
}

/**
 * Clear Security State
 */
function clearSecurityState() {
    AppState.errorCount = 0;
    AppState.lockoutTime = 0;
    AppState.currentLockoutDuration = SECURITY_CONFIG.INITIAL_LOCKOUT;
    
    localStorage.removeItem(SECURITY_CONFIG.STORAGE_KEYS.ERROR_COUNT);
    localStorage.removeItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_TIME);
    localStorage.removeItem(SECURITY_CONFIG.STORAGE_KEYS.LOCKOUT_DURATION);
    
    // Hide any visible alerts
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        countdownEl.classList.remove('active');
    }
    
    const attemptsAlert = document.getElementById('attemptsAlert');
    if (attemptsAlert) {
        attemptsAlert.style.display = 'none';
    }
}

/**
 * Password Visibility Toggle
 */
function togglePassword() {
    const passwordField = document.getElementById('password');
    const checkbox = document.getElementById('showPassword');
    
    if (passwordField && checkbox) {
        passwordField.type = checkbox.checked ? 'text' : 'password';
    }
}

/**
 * Shake Animation for locked out users trying to submit
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);   