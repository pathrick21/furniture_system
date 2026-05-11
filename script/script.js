document.querySelectorAll('#id_main, #user, #email').forEach(field => {
    field.addEventListener('blur', function () {
        const fieldName = this.name;
        const fieldValue = this.value.trim();

        if (fieldValue === '') return; // Skip empty fields

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '../php/check_existing.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onload = function () {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                const errorField = document.getElementById(`${fieldName}-error`);
                if (response.exists) {
                    errorField.textContent = `${fieldName} already exists.`;
                } else {
                    errorField.textContent = '';
                }
            }
        };

        xhr.send(`field=${fieldName}&value=${fieldValue}`);
    });
});
// Toggle security answer visibility
function toggleSecurityAnswer(inputId) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    // Toggle eye icon
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    if (type === 'text') {
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}



function validateForm() {
    // Regular expressions
    const capitalAndLowercaseRegex = /^[A-Z][a-z]*(\s+[A-Z][a-z]*)*$/;
    const lettersOnly = /^[A-Za-z\s]+$/;
    const capital = /^(M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})(\s*(Sr\.|Jr\.))?)$/;
    const capitalOnlyRegex = /^[A-Z\s]*$/;
    const LetterBeforeNum = /^[a-zA-Z0-9._]+$/;
    const noNumbers = /^[A-Za-z]*$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const doubleSpaceRegex = /\s{2,}/;
    const containsSpaceRegex = /\s/;
    const noSymbolsExceptHyphenAndSpaceRegex = /^[A-Za-z0-9-\s]+$/;
    const numbers = /.*\d.*/;
    const lettersAndNumbersRegex = /^[A-Za-z0-9\s]+$/; // Allows letters, numbers, and spaces

    



    // Retrieve values from form fields
    const id_main = document.getElementById('id_main').value;
    const fname = document.getElementById('fname').value;
    const lname = document.getElementById('lname').value;
    const pass = document.getElementById('pass').value;
    const repass = document.getElementById('repass').value;
    const email = document.getElementById('email').value;
    const zip = document.getElementById('zip').value;
    const user = document.getElementById('user').value;
    const Purok = document.getElementById('Purok').value;
    const barangay = document.getElementById('Barranggay').value;
    const CM = document.getElementById('CM').value;
    const province = document.getElementById('Province').value;
    const country = document.getElementById('Country').value;
    const ename = document.getElementById('Ename').value;
    const mi = document.getElementById('mi').value;
    const bd = document.getElementById('bd').value;

    if(id_main.length < 1 ){
        alert("Input ID");
        return false;
    }
    if(fname.length < 1 ){
        alert("Input First Name");
        return false;
    }
    if(lname.length < 1 ){
        alert("Input Last Name");
        return false;
    }
    if(user.length < 1 ){
        alert("Input Username");
        return false;
    }

    if(email.length < 1 ){
        alert("Input Email");
        return false;
    }

    if(pass.length < 1 ){
        alert("Input Password");
        return false;
    }

    if(bd.length < 1 ){
        alert("Input Birthdate");
        return false;
    }
    
    if(Purok.length < 1 ){
        alert("Purok must be filled");
        return false;
    }

    if(barangay.length < 1 ){
        alert("Barangay code must be filled");
        return false;
    }

    if(CM.length < 1 ){
        alert("City/Municipality code must be filled");
        return false;
    }

    if(province.length < 1 ){
        alert("Province code must be filled");
        return false;
    }
    if( country.length < 1 ){
        alert("Country code must be filled");
        return false;
    }


    if(fname.match(numbers)){
        alert("First Name contains number");
        return false;
    }
    if(lname.match(numbers)){
        alert("Last Name contains number");
        return false;
    }
    const symbols = /[^a-zA-Z\s]/;  // Matches any character that is NOT a letter or space

    if (lname.match(symbols)) {
        alert("Last name do not contain symbol");
        return false;

    }
    if (fname.match(symbols)) {
        alert("First name do not contain symbol");
        return false;
    }


    if(ename.length  > 5 ){
        alert("Extension Name must not exceed to 4 character.");
        return false;
    }
    if (pass !== repass) {
        alert("Password must match");
        return false;
    }
    if(containsDoubleSpaces(mi)){
        alert("Middle initial contains multiple spaces.");
        return false;
    }
    if (fname.startsWith(" ")) {
        alert("First Name do not start with space.");
        return false;
    }
    if (lname.startsWith(" ")) {
        alert("Last Name do not start with space.");
        return false;
    }
    if (mi[0] === ' ') {
        alert("Middle Initial do not start with space.");
        return false;
    }

    if(!mi.match(capitalOnlyRegex)){
        alert("Middle Initial Must be only 1 letter only, no special character or numbers and must be capitalized.");
        return false;
    }

    if(!ename.match(capital)){
        alert("Name Extension only allowed roman numerals but Jr. Sr. is included and must be capitalized.");
        return false;
    }

    if(!mi.match(noNumbers)){
        alert("Middle Initial contains numbers");
        return false;
    }
    if(mi.length  > 2 ){
        alert("Middle Initial must be a letter only.");
        return false;
    }
    if(fname.length < 2 ){
        alert("First Name minimum is 2 character.");
        return false;
    }

    if(fname.length > 20 ){
        alert("First Name maximum is 20 character");
        return false;
    }
    if(lname.length < 2 ){
        alert("Last Name minimum is 2 character.");
        return false;
    }

    if(lname.length > 20 ){
        alert("last Name maximum is 20 character.");
        return false;
    }

    if(user.length < 2 ){
        alert("Username Minimum is 2 Character.");
        return false;
    }



    // Check for repeated letters or multiple spaces in first and last names
    if (hasThreeRepeatedLetters(fname)) {
        alert("First Name contains repeated letters.");
        return false;
    }

    if(containsDoubleSpaces(fname)){
        alert("First Name contains multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(lname)) {
        alert("Last Name contains repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(lname)){
        alert("Last Name contains multiple spaces.");
        return false;
    }
    


    // // Check if ID already exists
    console.log('ID Main:', id_main);  // Debugging line
    if (id_main.length !== 9) {
        alert("ID must be exactly 9 characters, including hyphen(-).");
        return false;
    }

    if (user.length < 3 || user.length > 20) {
        alert("Username only accepts between 3 and 20 characters.");
        return false;

    }if (/^\d/.test(user)) {
        alert("Username must not start with a number.");
        return false;
    } if(containsDoubleSpaces(user)){
        alert("Middle initial contains multiple spaces.");
        return false;
    }


    if (zip.length !== 4) {
        alert("ZIP code must be exactly 4 digits.");
        return false;
    }
    
    // Check if ZIP code contains only numbers
    if (isNaN(zip)) {
        alert("ZIP code do not accept letters or any special characters.");
        return false;
    }
    
    // Check if ZIP code contains any spaces
    if (/\s/.test(zip)) {
        alert("ZIP code contains spaces.");
        return false;
    }

    if (!id_main.match(noSymbolsExceptHyphenAndSpaceRegex)){
        alert("ID must not contain symbols, except for hyphen.");
         return false;
    }

    if (id_main.match(containsSpaceRegex)){
        alert("ID contains spaces.");
        return false;
    }

    if (!id_main.match(/^\d{4}-\d{4}$/)) {
        alert("ID must be in the format xxxx-xxxx.");
        return false;
    }
    if(!CM.match(lettersOnly)){
        alert("City/Municiplity only accepts letters.");
        return false;  
    }
    if(!country.match(lettersOnly)){
        alert("Country only accepts letters.");
        return false;  
    }

        // Check for repeated letters or multiple spaces in first and last names
    if (hasThreeRepeatedLetters(fname)) {
        alert("First Name should not contain repeated letters.");
        return false;
    }

    if(containsDoubleSpaces(fname)){
        alert("First Name should not contain multiple spaces.");
        return false;
    }
    
    if (hasThreeRepeatedLetters(lname)) {
        alert("Last Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(lname)){
        alert("Last Name should not contain multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(Purok)) {
        alert("Purok Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(Purok)){
        alert("Purok Name should not contain multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(barangay)) {
        alert("barangay Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(barangay)){
        alert("barangay Name should not contain multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(CM)) {
        alert("city/municipality Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(CM)){
        alert("city/municipality Name should not contain multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(province)) {
        alert("province Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(province)){
        alert("province Name should not contain multiple spaces.");
        return false;
    }

    if (hasThreeRepeatedLetters(country)) {
        alert("country Name should not contain repeated letters.");
        return false;
    }
    if(containsDoubleSpaces(country)){
        alert("country Name should not contain multiple spaces.");
        return false;
    }if (!barangay.match(lettersAndNumbersRegex)) {
        alert("Barangay must not contain special characters (letters, numbers, and spaces are allowed).");
        return false;
    }if (!CM.match(lettersAndNumbersRegex)) {
        alert("City/Municipality must not contain special characters.");
        return false;
    }if (!Purok.match(lettersAndNumbersRegex)) {
        alert("Purok must not contain special characters (letters, numbers, and spaces are allowed).");
        return false;
    }if (!province.match(lettersAndNumbersRegex)) {
        alert("Province must not contain special characters.");
        return false;
    }


    


    function validateName(fname, lname, Purok, barangay, CM, province, country) {
        // Regex for checking if a word starts with a capital letter followed by lowercase letters
        const capitalAndLowercaseRegex = /^[A-Z][a-z]+$/;
        const capitalAndLowercaseRegex2 = /^[A-Z][a-z]*(?:-[a-zA-Z0-9]+)*(\s\d+)?$/;
        const validPurokRegex = /^[A-Za-z0-9\s]+$/; // Allows letters, numbers, and spaces only

    
        // Split names into parts (words)
        const fnameParts = fname.trim().split(" ");
        const lnameParts = lname.trim().split(" ");
        const Purokpart = Purok.trim().split(" ");
        const barangaypart = barangay.trim().split(" ");
        const CMpart = CM.trim().split(" ");
        const provincepart = province.trim().split(" ");
        const countrypart = country.trim().split(" ");
    
    
        // === First Name Validation ===
        // Validate each part of the first name
        

        for (let i = 0; i < fnameParts.length; i++) {

            if (/\d/.test(fnameParts[i][0])) {
                alert(`${fnameParts[i]} in the First Name must not start with number.`);
                return false;
            }



            if (!fnameParts[i].match(capitalAndLowercaseRegex2)) {
                alert(`${fnameParts[i]} in the First Name must always start with capital then followed with lower cases.`);
                return false;
            }
        }
    
        // === Last Name Validation ===
        // Validate each part of the last name
        for (let i = 0; i < lnameParts.length; i++) {
            if (/\d/.test(lnameParts[i][0])) {
                alert(`${lnameParts[i]} in the Last Name must not start with a number.`);
                return false;
            }

            if (!lnameParts[i].match(capitalAndLowercaseRegex2)) {
                alert(`The word "${lnameParts[i]}" in the Last Name must always start with capital and followed with lower cases.`);
                return false;
            }
        }

        for (let i = 0; i < Purokpart.length; i++) {
            // If the first character is a number (0-9)
            if (/\d/.test(Purokpart[i][0])) {
                // If the string has more than 1 character, check that it's all numbers
                if (Purokpart[i].length > 1 && /[a-zA-Z]/.test(Purokpart[i][1])) {
                    alert(`${Purokpart[i]} in the Purok must start with letter then number.`);
                    return false;
                }
            }
            // If the first character is a letter
            else if (/[a-zA-Z]/.test(Purokpart[i][0])) {
                // Check if the first letter is uppercase and the rest of the string is lowercase
                if (!Purokpart[i].match(capitalAndLowercaseRegex2)) {
                    alert(`The ${Purokpart[i]} in the Purok must always start with capital followed by lower cases letters.`);
                    return false;
                }
            }
        }

        for (let i = 0; i < barangaypart.length; i++) {
            // If the first character is a number (0-9)
            if (/\d/.test(barangaypart[i][0])) {
                // If the string has more than 1 character, check that it's all numbers
                if (barangaypart[i].length > 1 && /[a-zA-Z]/.test(barangaypart[i][1])) {
                    alert(`${barangaypart[i]}" in the Baranggay must start with letter then number.`);
                    return false;
                }
            }
            // If the first character is a letter
            else if (/[a-zA-Z]/.test(barangaypart[i][0])) {
                // Check if the first letter is uppercase and the rest of the string is lowercase
                if (!barangaypart[i].match(capitalAndLowercaseRegex2)) {
                    alert(`${barangaypart[i]} in the Baranggay must always start with capital letter followed by lowercase letters.`);
                    return false;
                }
            }

            
        }

        for (let i = 0; i < CMpart.length; i++) {
            if (/\d/.test(CMpart[i][0])) {
                alert(`${CMpart[i]} in the Citymunicipality must not start with a number.`);
                return false;
            }

            if (!CMpart[i].match(capitalAndLowercaseRegex2)) {
                alert(`${CMpart[i]} in the City/Municipalty should always start with  capital letter followed by lowercase letters.`);
                return false;
            }
        }

        for (let i = 0; i < provincepart.length; i++) {

            if (/\d/.test(provincepart[i][0])) {
                alert(`${provincepart[i]} in the Province must not start with a number.`);
                return false;
            }
            if (!provincepart[i].match(capitalAndLowercaseRegex2)) {
                alert(`${provincepart[i]} in the Province should always start with capital letters followed by lowercase letters.`);
                return false;
            }
        }


        for (let i = 0; i < countrypart.length; i++) {

            if (/\d/.test(countrypart[i][0])) {
                alert(`${countrypart[i]} in the Country should not start with a number.`);
                return false;
            }
            if (!countrypart[i].match(capitalAndLowercaseRegex2)) {
                alert(`${countrypart[i]} in the Country should always starts with capital letters followed by lowercase letters.`);
                return false;
            }
        }

        
    
        return true; // All validations passed
    }
    

    if (!validateName(fname, lname, Purok, barangay, CM, province, country )) {
        return false;
    }


    if (email && !email.match(emailRegex)) {
        alert("Please enter a valid email address.");
        return false;
    }


    // Validate Purok
    if (Purok.length < 1 || Purok.length > 25) {
        alert("Purok must be between 1 and 25 characters.");
        return false;
    }

    // Validate Barangay
    if (barangay.length < 1 || barangay.length > 25) {
        alert("Barangay must be between 1 and 25 characters.");
        return false;
    }

    // Validate City/Municipality (CM)
    if (CM.length < 2 || CM.length > 25) {
        alert("City/Municipality must be between 2 and 25 characters.");
        return false;
    }

    // Validate Province
    if (province.length < 2 || province.length > 25) {
        alert("Province must be between 2 and 25 characters.");
        return false;
    }

    // Validate Country
    if (country.length < 2 || country.length > 25) {
        alert("Country must be between 2 and 25 characters");
        return false;
    }
    if(!country.match(lettersOnly)){
        alert("Country do not accept numbers.");
        return false;  
    }

    // Check if the username starts with a letter and contains only letters, numbers, underscores, or periods
if (!/^[A-Za-z][A-Za-z0-9._]*$/.test(user)) {
    alert("Username only contains limited special characters like underscores, or periods.");
    return false;
}

    if (/^[A-Z]/.test(user)) {
        alert("Username should be all lowered cases followed by numbers.");
        return false;
    }
    


        // Check password strength
   

            
        // Check password strength
    const passwordStrength = checkPasswordStrength2(pass);
    if (passwordStrength === "weak") {
        alert("Password is too weak. It must be at least 6 characters long.");
        return false;
    } else if (passwordStrength === "medium") {
        alert("Password is medium. It must contain both letters and numbers.");
        return false;
    } else if (passwordStrength === "strong") {
        return true;
    }

    if (pass !== repass) {
        alert("Password must match");
        return false;
    }

    if(!ename.match(capital)){
        alert("Name Extension only allowed roman numerals but Jr. Sr. is included and must be capitalized.");
        return false;
    }
    if (!validateName(fname, lname)) {
        return false;
    }


    alert("Validating User: " + user);
    return true;  // Proceed with form submission if all checks pass
}


function checkPasswordStrength2(password) {
    if (password.length < 6) {
        return "weak";
    } else if (password.length >= 6 && /\d/.test(password) && /[a-zA-Z]/.test(password) && /[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        return "strong"; // This now checks for symbols
    } else if (password.length >= 6 && /\d/.test(password) && /[a-zA-Z]/.test(password)) {
        return "medium";
    } else {
        return "weak"; // This case is redundant but ensures weak password handling
    }
}





function togglePassword() {
    const passwordField = document.getElementById('pass');
    const repasswordField = document.getElementById('repass');
    const showPasswordCheckbox = document.getElementById('showPassword');

    if (showPasswordCheckbox.checked) {
        passwordField.type = "text";
        repasswordField.type = "text";
    } else {
        passwordField.type = "password";
        repasswordField.type = "password";
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('pass').value;
    const strengthText = document.getElementById('password-strength');
    
    // Regular expressions to check for weak, medium, and strong passwords
    const weakRegex = /^[a-zA-Z]+$/;
    const mediumRegex = /^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]+$/;
    const strongRegex = /^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d!@#$%^&*(),.?":{}|<>]*$/;

    // Check for password strength
    if (weakRegex.test(password)) {
        strengthText.textContent = "Weak: Password is weak.";
        strengthText.style.color = "red";
    } else if (mediumRegex.test(password)) {
        strengthText.textContent = "Medium: Password is medium.";
        strengthText.style.color = "orange";
    } else if (strongRegex.test(password)) {
        strengthText.textContent = "Strong: Password is strong.";
        strengthText.style.color = "green";
    } else {
        strengthText.textContent = "Password is too weak.";
        strengthText.style.color = "red";
    }
}


function containsDoubleSpaces(input) {
    const doubleSpacePattern = /[\s]{2,}/;
    return doubleSpacePattern.test(input);
}

function hasThreeRepeatedLetters(str) {
    // Remove spaces before checking
    str = str.replace(/\s+/g, '');  // This will remove all spaces from the string
    
    // Now check for three consecutive repeated letters
    for (var i = 0; i <= str.length - 3; i++) {
        if (str[i] === str[i+1] && str[i] === str[i+2]) {
            return true;
        }
    }
    return false;
}
// Function to calculate age and check if the user is 18 or older
function validateAge() {
    var birthDate = document.getElementById('bd').value;
    if (birthDate) {
        var birthDateObj = new Date(birthDate);
        var today = new Date();
        var age = today.getFullYear() - birthDateObj.getFullYear();
        var m = today.getMonth() - birthDateObj.getMonth();

        if (m < 0 || (m === 0 && today.getDate() < birthDateObj.getDate())) {
            age--;
        }

        if (age < 18) {
            document.getElementById('age-error').innerHTML = ""; alert("Your are: " + age + " year's old, must be legal age to register");
            return false;
        } else {
            document.getElementById('age-error').innerHTML = "";
            return true;
        }
    }
    return false;

    
}
// Toggle security answer visibility
function toggleSecurityAnswer(inputId) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    // Toggle eye icon
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    if (type === 'text') {
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}