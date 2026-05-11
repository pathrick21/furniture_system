// ===== SELECT CONTROL FUNCTION WITH DEBUGGING =====
function selectControl(level) {
    console.log('selectControl called with level:', level);
    
    // Check if the radio button exists
    const radio = document.getElementById('control_' + level);
    console.log('Radio element for control_' + level + ':', radio);
    
    if (!radio) {
        console.error('Radio button not found for level:', level);
        alert('Error: Radio button not found for ' + level);
        return;
    }
    
    // Find the parent control-option
    const selectedOption = radio.closest('.control-option');
    console.log('Parent control-option:', selectedOption);
    
    if (!selectedOption) {
        console.error('Parent .control-option not found for radio:', radio);
        return;
    }
    
    // Remove selected class from all options
    document.querySelectorAll('.control-option').forEach(opt => {
        opt.classList.remove('selected');
        opt.style.borderColor = '#e0d5c7';
        opt.style.background = '#faf8f5';
        
        // Reset icon colors
        const icon = opt.querySelector('i');
        if (icon) icon.style.color = '#8B5A2B';
    });
    
    // Add selected class to clicked option
    selectedOption.classList.add('selected');
    selectedOption.style.borderColor = '#D4AF37';
    selectedOption.style.background = 'white';
    
    // Change icon color to gold for selected
    const selectedIcon = selectedOption.querySelector('i');
    if (selectedIcon) selectedIcon.style.color = '#D4AF37';
    
    // Check the radio button
    radio.checked = true;
    console.log('Radio checked:', radio.checked);
    
    // Show/hide manual permissions and description
    const manualSection = document.getElementById('manualPermissions');
    const descriptionBox = document.getElementById('controlDescription');
    
    console.log('manualSection:', manualSection);
    console.log('descriptionBox:', descriptionBox);
    
    if (level === 'manual') {
        if (manualSection) manualSection.style.display = 'block';
        if (descriptionBox) descriptionBox.style.display = 'none';
    } else {
        if (manualSection) manualSection.style.display = 'none';
        if (descriptionBox) descriptionBox.style.display = 'block';
        
        // Update description content based on level
         if (level === 'full') {
    descriptionBox.innerHTML = `
        <div class="description-box full" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(139, 90, 43, 0.05)); border: 2px solid #D4AF37; border-radius: 12px; padding: 1.25rem; margin-top: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <i class="fas fa-crown" style="color: #D4AF37; font-size: 1.25rem;"></i>
                <h4 style="margin: 0; color: #1a1a2e; font-family: 'Playfair Display', serif; font-size: 1.1rem;">Full Access (God Mode)</h4>
            </div>
            <p style="margin: 0 0 0.75rem 0; color: #5d4e37; font-size: 0.875rem; line-height: 1.5;">
                This administrator has <strong style="color: #D4AF37;">complete unrestricted access</strong> to all system features and management functions.
            </p>
            
            <!-- ✓ ALL PRIVILEGES -->
            <div style="margin-bottom: 1rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> Products:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Add Products
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Products
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Products
                    </div>
                </div>
                
                <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> Users:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Approve Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset User Passwords
                    </div>
                </div>
                
                <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> Admins:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Admins
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Admins
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Admins
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Admins
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset Admin Passwords
                    </div>
                </div>
                
                <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> Logs & Activity:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Online Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Login History
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Activity
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View User Lists
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Logs
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Session Data
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Activity Logs
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Force Logout Users
                    </div>
                </div>
            </div>
            
            <!-- ✗ NO PRIVILEGES (BOTTOM) - EMPTY SINCE FULL HAS ALL PERMISSIONS -->
            <div style="margin-bottom: 1rem; opacity: 0.7;">
                <h5 style="margin: 0 0 0.5rem 0; color: #95a5a6; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-minus-circle"></i> Cannot Do:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #95a5a6; font-style: italic;">
                    <div style="display: flex; align-items: center; gap: 0.375rem; grid-column: span 2;">
                        <i class="fas fa-check-circle" style="color: #95a5a6;"></i> Nothing - has all permissions
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(212, 175, 55, 0.3);">
                <span style="font-size: 0.75rem; color: #8b7355;">
                    <i class="fas fa-info-circle" style="color: #D4AF37; margin-right: 0.25rem;"></i>
                    Recommended for: Super Administrators and System Managers
                </span>
            </div>
        </div>
    `;
} else if (level === 'limited') {
    descriptionBox.innerHTML = `
        <div class="description-box limited" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.05), rgba(33, 150, 243, 0.1)); border: 2px solid #2196F3; border-radius: 12px; padding: 1.25rem; margin-top: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <i class="fas fa-pen" style="color: #2196F3; font-size: 1.25rem;"></i>
                <h4 style="margin: 0; color: #1a1a2e; font-family: 'Playfair Display', serif; font-size: 1.1rem;">Limited Access (Edit Capabilities)</h4>
            </div>
            <p style="margin: 0 0 0.75rem 0; color: #5d4e37; font-size: 0.875rem; line-height: 1.5;">
                This administrator can <strong style="color: #2E7D32;">create and edit</strong> content but <strong style="color: #C62828;">cannot delete</strong> anything.
            </p>
            
            <!-- ✓ YES PRIVILEGES (TOP) -->
            <div style="margin-bottom: 1rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: #2E7D32; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> Can Do:
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                    <!-- Products -->
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Add Products
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Products
                    </div>
                    
                    <!-- Users -->
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Approve Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset User Passwords
                    </div>
                    
                    <!-- View Permissions -->
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Online Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Login History
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Activity
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View User Lists
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Logs
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Session Data
                    </div>
                </div>
            </div>
            
            <!-- ✗ NO PRIVILEGES (BOTTOM) - ONLY DELETE ACTIONS -->
            <div>
                <h5 style="margin: 0 0 0.5rem 0; color: #C62828; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-times-circle"></i> Cannot Do (Delete Actions Only):
                </h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                    <!-- Delete restrictions only -->
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Products
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Users
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Admins
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(33, 150, 243, 0.2);">
                <span style="font-size: 0.75rem; color: #8b7355;">
                    <i class="fas fa-info-circle" style="color: #2196F3; margin-right: 0.25rem;"></i>
                    Can create and edit but cannot delete. Recommended for: Content Managers and Moderators
                </span>
            </div>
        </div>
    `;
}
    }
    
    // Auto-check permissions based on level (for backend processing)
    const checkboxes = document.querySelectorAll('#manualPermissions input[type="checkbox"]');
    if (level === 'full') {
        checkboxes.forEach(cb => cb.checked = true);
    } else if (level === 'limited') {
        checkboxes.forEach(cb => cb.checked = false);
    }
}

// ===== MODAL FUNCTIONS =====
function openModal() {
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal() {
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// ===== PASSWORD FUNCTIONS FOR CREATE ADMIN =====
function toggleAdminPassword() {
    const passInput = document.getElementById('adminPass');
    const repassInput = document.getElementById('adminRepass');
    const showCheckbox = document.getElementById('showAdminPassword');
    
    if (!passInput || !repassInput || !showCheckbox) return;
    
    const type = showCheckbox.checked ? 'text' : 'password';
    passInput.type = type;
    repassInput.type = type;
}

function checkAdminPasswordStrength() {
    const password = document.getElementById('adminPass')?.value || '';
    const strengthDiv = document.getElementById('admin-password-strength');
    if (!strengthDiv) return;
    
    let strength = 0;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    if (password.length >= 8) strength++;
    
    let strengthText = '';
    let strengthColor = '';
    
    switch(strength) {
        case 0:
        case 1:
            strengthText = 'Weak';
            strengthColor = '#C62828';
            break;
        case 2:
        case 3:
            strengthText = 'Medium';
            strengthColor = '#F57C00';
            break;
        case 4:
        case 5:
            strengthText = 'Strong';
            strengthColor = '#2E7D32';
            break;
    }
    
    strengthDiv.innerHTML = password ? `Password Strength: <span style="color: ${strengthColor}; font-weight: 600;">${strengthText}</span>` : '';
}

function checkAdminPasswordMatch() {
    const password = document.getElementById('adminPass')?.value || '';
    const repass = document.getElementById('adminRepass')?.value || '';
    const matchDiv = document.getElementById('admin-password-match');
    if (!matchDiv) return;
    
    if (repass.length === 0) {
        matchDiv.innerHTML = '';
    } else if (password === repass) {
        matchDiv.innerHTML = '<span style="color: #2E7D32;"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        matchDiv.innerHTML = '<span style="color: #C62828;"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
    }
}

function updateControlOptions() {
    // Optional: Dynamic control level options based on account type
}

// ===== PASSWORD FUNCTIONS FOR EDIT ADMIN (CHANGE PASSWORD) =====
function togglePasswordSection() {
    const content = document.getElementById('passwordContent');
    const icon = document.getElementById('passwordToggleIcon');
    
    if (!content || !icon) return;
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function togglePasswordVisibility() {
    const newPass = document.getElementById('newPassword');
    const confirmPass = document.getElementById('confirmPassword');
    const showCheckbox = document.getElementById('showPassword');
    
    if (!newPass || !confirmPass || !showCheckbox) return;
    
    const type = showCheckbox.checked ? 'text' : 'password';
    newPass.type = type;
    confirmPass.type = type;
}

function changeAdminPassword(adminId) {
    const newPass = document.getElementById('newPassword')?.value;
    const confirmPass = document.getElementById('confirmPassword')?.value;
    
    if (!newPass) {
        alert('Please enter a new password');
        return;
    }
    
    if (newPass.length < 8) {
        alert('Password must be at least 8 characters long');
        return;
    }
    
    if (newPass !== confirmPass) {
        alert('Passwords do not match');
        return;
    }
    
    if (confirm('Are you sure you want to change this administrator\'s password?')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_management.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'admin_id';
        idInput.value = adminId;
        
        const passInput = document.createElement('input');
        passInput.type = 'hidden';
        passInput.name = 'new_password';
        passInput.value = newPass;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'change_admin_password';
        actionInput.value = '1';
        
        form.appendChild(idInput);
        form.appendChild(passInput);
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Completely replace the viewPrivileges function
window.viewPrivileges = function(username, fullname) {
    console.log('viewPrivileges called with:', username, fullname);
    
    // Remove any existing debug modal
    const existingModal = document.getElementById('debug-privilege-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create new modal
    const modal = document.createElement('div');
    modal.id = 'debug-privilege-modal';
    modal.style.cssText = `
        display: flex !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: rgba(0, 0, 0, 0.9) !important;
        z-index: 9999999999 !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 !important;
        padding: 0 !important;
        font-family: 'Poppins', sans-serif !important;
    `;
    
    // Create modal content
    const content = document.createElement('div');
    content.style.cssText = `
        background: #f8f5f0 !important;
        width: 600px !important;
        max-width: 90% !important;
        max-height: 80vh !important;
        overflow-y: auto !important;
        border-radius: 24px !important;
        padding: 0 !important;
        box-shadow: 0 30px 70px rgba(0,0,0,0.5) !important;
        border: 4px solid #D4AF37 !important;
        position: relative !important;
    `;
    
    // Add header
    const header = document.createElement('div');
    header.style.cssText = `
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%) !important;
        color: white !important;
        padding: 1.5rem 2rem !important;
        border-bottom: 4px solid #D4AF37 !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    `;
    header.innerHTML = `
        <h3 style="margin:0; font-family: 'Playfair Display', serif; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-shield-alt" style="color: #D4AF37;"></i>
            <span id="modal-privilege-name">${fullname}'s Privileges</span>
        </h3>
        <button onclick="document.getElementById('debug-privilege-modal').remove()" style="background: rgba(255,255,255,0.1); border: 2px solid rgba(212,175,55,0.3); color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.3rem; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add body with loading spinner
    const body = document.createElement('div');
    body.id = 'modal-privilege-body';
    body.style.cssText = `
        padding: 2rem !important;
        background: #f8f5f0 !important;
        color: #1a1a2e !important;
    `;
    body.innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-pulse" style="font-size: 3rem; color: #D4AF37;"></i>
            <p style="margin-top: 1rem; color: #1a1a2e;">Loading privileges for ${fullname}...</p>
            <p style="font-size: 0.8rem; color: #8b7355;">Username: ${username}</p>
        </div>
    `;
    
    content.appendChild(header);
    content.appendChild(body);
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
    
    // Fetch privileges - with correct filename (privileges, not permissions)
fetch('/ITE107/php/get_admin_privileges.php?username=' + encodeURIComponent(username))
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Data received:', data);
        if (data.success) {
            const html = buildPrivilegesHTML(data);
            document.getElementById('modal-privilege-body').innerHTML = html;
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        body.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #C62828;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p><strong>Error:</strong> ${error.message}</p>
                <button onclick="document.getElementById('debug-privilege-modal').remove()" style="margin-top: 1rem; padding: 0.5rem 1.5rem; background: #8B5A2B; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    Close
                </button>
            </div>
        `;
    });
};

// Function to build HTML from JSON data
function buildPrivilegesHTML(data) {
    const permissions = data.perms || {};
    const controlLevel = data.level || 'limited';
    const accountType = data.type || 'admin';
    
    let controlBadge = '';
    let badgeColor = '';
    let badgeIcon = '';
    
    if (controlLevel === 'full') {
        controlBadge = 'FULL ACCESS';
        badgeColor = '#D4AF37';
        badgeIcon = 'fa-crown';
    } else if (controlLevel === 'limited') {
        controlBadge = 'LIMITED ACCESS';
        badgeColor = '#2196F3';
        badgeIcon = 'fa-pen';
    } else {
        controlBadge = 'MANUAL ACCESS';
        badgeColor = '#8B5A2B';
        badgeIcon = 'fa-sliders-h';
    }
    
    let html = `
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <span style="display: inline-block; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 700; font-size: 0.9rem; background: ${badgeColor}; color: ${controlLevel === 'full' ? '#1a1a2e' : 'white'}; margin-bottom: 1rem;">
                <i class="fas ${badgeIcon}"></i> ${controlBadge}
            </span>
            <p style="color: #8b7355; margin-top: 0.5rem;">Account Type: <strong>${accountType.replace('_', ' ').toUpperCase()}</strong></p>
        </div>
    `;
    
    // Product Management
    html += `
        <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0ebe3;">
            <h4 style="font-family: 'Playfair Display', serif; color: #1a1a2e; margin: 0 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 2px solid #f0ebe3; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-box" style="color: #D4AF37;"></i> Product Management
            </h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                ${createPermItem(permissions.can_add_product, 'Add Products')}
                ${createPermItem(permissions.can_edit_product, 'Edit Products')}
                ${createPermItem(permissions.can_delete_product, 'Delete Products')}
                ${createPermItem(permissions.can_view_product_activity, 'View Product Activity')}
                ${createPermItem(permissions.can_view_product_logs, 'View Product Logs')}
                ${createPermItem(permissions.can_delete_product_logs, 'Delete Product Logs')}
            </div>
        </div>
    `;
    
    // User Management
    html += `
        <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0ebe3;">
            <h4 style="font-family: 'Playfair Display', serif; color: #1a1a2e; margin: 0 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 2px solid #f0ebe3; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-users" style="color: #D4AF37;"></i> User Management
            </h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                ${createPermItem(permissions.can_create_user, 'Create Users')}
                ${createPermItem(permissions.can_edit_user, 'Edit Users')}
                ${createPermItem(permissions.can_ban_user, 'Ban Users')}
                ${createPermItem(permissions.can_delete_user, 'Delete Users')}
                ${createPermItem(permissions.can_approve_reject_user, 'Approve/Reject Users')}
                ${createPermItem(permissions.can_reset_user_password, 'Reset User Passwords')}
            </div>
        </div>
    `;
    
    // Admin Management
    html += `
        <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0ebe3;">
            <h4 style="font-family: 'Playfair Display', serif; color: #1a1a2e; margin: 0 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 2px solid #f0ebe3; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-user-shield" style="color: #D4AF37;"></i> Admin Management
            </h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                ${createPermItem(permissions.can_create_admin, 'Create Admins')}
                ${createPermItem(permissions.can_edit_admin, 'Edit Admins')}
                ${createPermItem(permissions.can_ban_admin, 'Ban Admins')}
                ${createPermItem(permissions.can_delete_admin, 'Delete Admins')}
                ${createPermItem(permissions.can_reset_admin_password, 'Reset Admin Passwords')}
            </div>
        </div>
    `;
    
    
    return html;
}

// Helper function to create permission items
function createPermItem(value, label) {
    const isYes = value == 1 || value === true;
    return `
        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-radius: 8px; background: #faf8f5; color: ${isYes ? '#2E7D32' : '#C62828'};">
            <i class="fas ${isYes ? 'fa-check-circle' : 'fa-times-circle'}" style="color: ${isYes ? '#2E7D32' : '#C62828'}; width: 20px;"></i>
            <span style="font-size: 0.85rem; font-weight: 500;">${label}</span>
        </div>
    `;
}

// Privilege modal functions
function viewPrivileges(username, fullname) {
    console.log('viewPrivileges called with:', username, fullname);
    
    // Show the modal
    document.getElementById('privilegeAdminName').textContent = fullname + "'s Privileges";
    document.getElementById('privilegeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Show loading spinner
    document.getElementById('privilegeModalBody').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-pulse" style="font-size: 2rem; color: #D4AF37;"></i><p style="margin-top: 1rem; color: #666;">Loading privileges...</p></div>';
    
    // Fetch the data
    fetch('/ITE107/php/get_admin_privileges.php?username=' + encodeURIComponent(username))
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.success) {
                const html = buildPrivilegesHTML(data);
                document.getElementById('privilegeModalBody').innerHTML = html;
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('privilegeModalBody').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #C62828;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p style="font-size: 0.8rem; margin-top: 0.5rem;">Attempted URL: /ITE107/php/get_admin_privileges.php?username=${username}</p>
                    <button onclick="closePrivilegeModal()" style="margin-top: 1rem; padding: 0.5rem 1.5rem; background: #8B5A2B; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Close
                    </button>
                </div>
            `;
        });
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Management JS loaded');
    
    // Set initial control level state (limited is checked by default)
    const limitedRadio = document.getElementById('control_limited');
    if (limitedRadio && limitedRadio.checked) {
        selectControl('limited');
    }
    
    // Close modal on outside click
    const createModal = document.getElementById('createModal');
    if (createModal) {
        createModal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
    
    // Close privilege modal on outside click
    const privilegeModal = document.getElementById('privilegeModal');
    if (privilegeModal) {
        privilegeModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePrivilegeModal();
            }
        });
    }
    
    // Password strength checker for edit modal
    const newPass = document.getElementById('newPassword');
    if (newPass) {
        newPass.addEventListener('keyup', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('newPasswordStrength');
            if (!strengthDiv) return;
            
            let strength = 0;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            if (password.length >= 8) strength++;
            
            let strengthText = '';
            let strengthColor = '';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthText = 'Weak';
                    strengthColor = '#C62828';
                    break;
                case 2:
                case 3:
                    strengthText = 'Medium';
                    strengthColor = '#F57C00';
                    break;
                case 4:
                case 5:
                    strengthText = 'Strong';
                    strengthColor = '#2E7D32';
                    break;
            }
            
            strengthDiv.innerHTML = password ? `Password Strength: <span style="color: ${strengthColor}; font-weight: 600;">${strengthText}</span>` : '';
        });
    }
    
    const confirmPass = document.getElementById('confirmPassword');
    if (confirmPass) {
        confirmPass.addEventListener('keyup', function() {
            const password = document.getElementById('newPassword')?.value || '';
            const confirm = this.value;
            const matchDiv = document.getElementById('confirmPasswordMatch');
            if (!matchDiv) return;
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
            } else if (password === confirm) {
                matchDiv.innerHTML = '<span style="color: #2E7D32;"><i class="fas fa-check-circle"></i> Passwords match</span>';
            } else {
                matchDiv.innerHTML = '<span style="color: #C62828;"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
            }
        });
    }
    
    // Ping to stay online
    fetch('ping.php');
    setInterval(() => fetch('ping.php'), 30000);
});

// ===== CREATE MODAL CONTROL LEVEL FUNCTIONS =====
function selectCreateControl(level) {
    // Update visual selection
    document.querySelectorAll('#createModal .control-option').forEach(opt => {
    opt.classList.remove('selected');
    opt.style.borderColor = 'var(--border-subtle)';
    opt.style.background = 'var(--dark-5)';
    opt.style.boxShadow = 'none';
        
        // Reset icon colors
        const icon = opt.querySelector('i');
        if (icon) icon.style.color = '#8B5A2B';
    });
    
    const selectedOpt = document.querySelector(`#createModal [onclick="selectCreateControl('${level}')"]`);
    if (selectedOpt) {
        selectedOpt.classList.add('selected');
selectedOpt.style.borderColor = 'var(--gold)';
selectedOpt.style.background = 'rgba(201,168,76,0.06)';
selectedOpt.style.boxShadow = '0 4px 20px rgba(201,168,76,0.15)';
        
        // Change icon color to gold for selected
        const selectedIcon = selectedOpt.querySelector('i');
        if (selectedIcon) selectedIcon.style.color = '#D4AF37';
    }
    
    // Update radio button
    const radio = document.getElementById(`create_control_${level}`);
    if (radio) radio.checked = true;
    
    // Show/hide manual permissions
    const manualPerms = document.getElementById('createManualPermissions');
    const description = document.getElementById('createControlDescription');
    
    if (level === 'manual') {
        if (manualPerms) manualPerms.style.display = 'block';
        if (description) description.style.display = 'none';
    } else {
        if (manualPerms) manualPerms.style.display = 'none';
        if (description) description.style.display = 'block';
        
        // Update description content
        if (level === 'full') {
            description.innerHTML = `
                <div class="description-box full" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(139, 90, 43, 0.05)); border: 2px solid #D4AF37; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-crown" style="color: #D4AF37; font-size: 1.25rem;"></i>
                        <h4 style="margin: 0; color: #1a1a2e; font-family: 'Playfair Display', serif; font-size: 1.1rem;">Full Access (God Mode)</h4>
                    </div>
                    <p style="margin: 0 0 0.75rem 0; color: #5d4e37; font-size: 0.875rem; line-height: 1.5;">
                        This administrator has <strong style="color: #D4AF37;">complete unrestricted access</strong> to all system features and management functions.
                    </p>
                    
                    <!-- Products -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-box"></i> Products:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Add Products
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Products
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Products
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-users"></i> Users:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Approve Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset User Passwords
                            </div>
                        </div>
                    </div>
                    
                    <!-- Admins -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user-shield"></i> Admins:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Admins
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Admins
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Admins
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Admins
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset Admin Passwords
                            </div>
                        </div>
                    </div>
                    
                    <!-- Logs & Activity -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #D4AF37; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-clipboard-list"></i> Logs & Activity:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Online Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Login History
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Activity
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View User Lists
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Logs
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Session Data
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Delete Activity Logs
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Force Logout Users
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cannot Do section -->
                    <div style="opacity: 0.6; margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #95a5a6; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-minus-circle"></i> Cannot Do:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #95a5a6; font-style: italic;">
                            <div style="display: flex; align-items: center; gap: 0.375rem; grid-column: span 2;">
                                <i class="fas fa-check-circle" style="color: #95a5a6;"></i> Nothing - has all permissions
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(212, 175, 55, 0.3);">
                        <span style="font-size: 0.75rem; color: #8b7355;">
                            <i class="fas fa-info-circle" style="color: #D4AF37; margin-right: 0.25rem;"></i>
                            Recommended for: Super Administrators and System Managers
                        </span>
                    </div>
                </div>
            `;
        } else if (level === 'limited') {
            description.innerHTML = `
                <div class="description-box limited" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.05), rgba(33, 150, 243, 0.1)); border: 2px solid #2196F3; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-pen" style="color: #2196F3; font-size: 1.25rem;"></i>
                        <h4 style="margin: 0; color: #1a1a2e; font-family: 'Playfair Display', serif; font-size: 1.1rem;">Limited Access (Edit Capabilities)</h4>
                    </div>
                    <p style="margin: 0 0 0.75rem 0; color: #5d4e37; font-size: 0.875rem; line-height: 1.5;">
                        This administrator can <strong style="color: #2E7D32;">create and edit</strong> content but <strong style="color: #C62828;">cannot delete</strong> anything.
                    </p>
                    
                    <!-- Can Do section -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #2E7D32; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-check-circle"></i> Can Do:
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <!-- Products -->
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Add Products
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Products
                            </div>
                            
                            <!-- Users -->
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Create Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Edit Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Ban Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Approve Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> Reset User Passwords
                            </div>
                            
                            <!-- View Only -->
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Online Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Login History
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Activity
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View User Lists
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Product Logs
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-check-circle" style="color: #2E7D32;"></i> View Session Data
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cannot Do section -->
                    <div style="margin-bottom: 0.75rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #C62828; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times-circle"></i> Cannot Do (Delete Actions Only):
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: #5d4e37;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Products
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Users
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <i class="fas fa-times-circle" style="color: #C62828;"></i> Delete Admins
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(33, 150, 243, 0.2);">
                        <span style="font-size: 0.75rem; color: #8b7355;">
                            <i class="fas fa-info-circle" style="color: #2196F3; margin-right: 0.25rem;"></i>
                            Can create and edit but cannot delete. Recommended for: Content Managers and Moderators
                        </span>
                    </div>
                </div>
            `;
        }
    }
    
    // Auto-check permissions based on level (for backend processing)
    const checkboxes = document.querySelectorAll('#createManualPermissions input[type="checkbox"]');
    if (level === 'full') {
        checkboxes.forEach(cb => cb.checked = true);
    } else if (level === 'limited') {
        checkboxes.forEach(cb => cb.checked = false);
    }
}

// ===== CREATE MODAL PASSWORD FUNCTIONS =====
function toggleCreatePasswordSection() {
    const content = document.getElementById('createPasswordContent');
    const icon = document.getElementById('createPasswordToggleIcon');
    
    if (!content || !icon) return;
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function toggleCreatePasswordVisibility() {
    const newPass = document.getElementById('createNewPassword');
    const confirmPass = document.getElementById('createConfirmPassword');
    const checkbox = document.getElementById('showCreatePassword');
    
    if (!newPass || !confirmPass || !checkbox) return;
    
    const type = checkbox.checked ? 'text' : 'password';
    newPass.type = type;
    confirmPass.type = type;
}

function checkCreatePasswordStrength() {
    const password = document.getElementById('createNewPassword')?.value || '';
    const strengthDiv = document.getElementById('createPasswordStrength');
    if (!strengthDiv) return;
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    const strengthText = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const strengthColor = ['#C62828', '#F57C00', '#FBC02D', '#388E3C', '#2E7D32'];
    
    strengthDiv.innerHTML = `<span style="color: ${strengthColor[strength]}; font-weight: 600;">${strengthText[strength]}</span>`;
}

function checkCreatePasswordMatch() {
    const newPass = document.getElementById('createNewPassword')?.value || '';
    const confirmPass = document.getElementById('createConfirmPassword')?.value || '';
    const matchDiv = document.getElementById('createPasswordMatch');
    if (!matchDiv) return;
    
    if (confirmPass.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPass === confirmPass) {
        matchDiv.innerHTML = '<span style="color: #2E7D32;"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        matchDiv.innerHTML = '<span style="color: #C62828;"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
    }
}

// Make sure functions are available globally
window.viewPrivileges = window.viewPrivileges;
window.closePrivilegeModal = closePrivilegeModal;
window.selectControl = selectControl;
window.openModal = openModal;
window.closeModal = closeModal;
window.togglePasswordSection = togglePasswordSection;
window.togglePasswordVisibility = togglePasswordVisibility;
window.changeAdminPassword = changeAdminPassword;
window.selectCreateControl = selectCreateControl;
window.toggleCreatePasswordSection = toggleCreatePasswordSection;
window.toggleCreatePasswordVisibility = toggleCreatePasswordVisibility;
window.checkCreatePasswordStrength = checkCreatePasswordStrength;
window.checkCreatePasswordMatch = checkCreatePasswordMatch;

// Also make sure buildPrivilegesHTML and createPermItem are available
window.buildPrivilegesHTML = buildPrivilegesHTML;
window.createPermItem = createPermItem;