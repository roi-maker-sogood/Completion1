<?php

require_once "config/database.php";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Check if fields are empty
    if (empty($name) || empty($email) || empty($password)) {

        $message = "Please fill in all fields.";

    } else {

        // Check if email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $message = "Email is already registered.";
        } else {
            // Hash the password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
            // Insert the new user
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "sss",
                $name,  
                $email,
                $hashedPassword
            );

            if ($stmt->execute()) {

                $message = "Account created successfully!";

            } else {

                $message = "Something went wrong.";

            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Sign Up Result</title>
</head>

<body>

    <h1>
        <?php echo htmlspecialchars($message); ?>
    </h1>

    <a href="signup.php">Back to Sign Up</a>

</body>

</html>