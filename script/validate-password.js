function validatePassword() {
    // Get the password and confirm password values from the form
    const pass = document.getElementById('new-pass').value;
    const repass = document.getElementById('confirm-pass').value;
    
    console.log("Password:", pass);  // Log password value
    console.log("Confirm Password:", repass);  // Log confirm password value

    // Check if passwords match
    if (pass !== repass) {
        alert("Passwords do not match. Please try again.");
        return false;  // Prevent form submission
    }

    // Check password strength
    const passwordStrength = checkPasswordStrength(pass);
    console.log("Password Strength:", passwordStrength);  // Log password strength
    
    if (passwordStrength === "weak") {
        alert("Password is too weak. It must be at least 8 characters long and contain at least one number.");
        return false;  // Prevent form submission
    } else if (passwordStrength === "medium") {
        alert("Password is medium. Consider adding special characters and capital letters for a stronger password next time.");
    }

    // If password is strong, return true to allow form submission
    return true;
}

function checkPasswordStrength(password) {
    // Weak password: less than 8 characters or doesn't contain at least one number
    if (password.length < 8 || !/\d/.test(password)) {
        return "weak";
    }

    // Medium password: contains letters and numbers, but no special symbols
    if (/\d/.test(password) && /[a-zA-Z]/.test(password) && !/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        return "medium";
    }

    // Strong password: contains letters, numbers, special symbols, and at least one capital letter
    if (/\d/.test(password) && /[a-zA-Z]/.test(password) && /[!@#$%^&*(),.?":{}|<>]/.test(password) && /[A-Z]/.test(password)) {
        return "strong";
    }

    // Default fallback (should not reach this point if above checks are correct)
    return "medium";
}
