<?php
session_start();

// Include the connection file
include('connection.php'); // Ensure this path is correct

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user inputs and trim any extra spaces
    $username = trim($_POST['user']);
    $secret_key = trim($_POST['sk']);

    // Check if both fields are not empty
    if (empty($username) || empty($secret_key)) {
        echo "Both fields are required.";
    } else {
        // Prepare the query to check if the username and secret key match
        $query = "SELECT * FROM signinfo WHERE user = ? AND sk = ?";
        
        // Prepare the statement
        if ($stmt = $conn->prepare($query)) {
            // Bind the parameters (s = string, s = string)
            $stmt->bind_param("ss", $username, $secret_key);

            // Execute the query
            if ($stmt->execute()) {
                $result = $stmt->get_result();

                // Check if a matching row was found
                if ($result->num_rows > 0) {
                    // If they match, proceed to the reset password page
                    $_SESSION['username'] = $username; // Store the username in session
                    header('Location: update-password.php'); // Redirect to password update page
                    exit();
                } else {
                    // If no match, show error message
                    echo "Username or Secret Key is incorrect. Please try again.";
                }
            } else {
                // Error executing the query
                echo "Error executing query: " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        } else {
            // Error preparing the query
            echo "Error preparing query: " . $conn->error;
        }
    }
}
?>