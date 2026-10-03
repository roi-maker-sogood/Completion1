<?php

session_start();

require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Check if fields are empty
    if (empty($email) || empty($password)) {

        $message = "Please enter your email and password.";

    } else {

        // Find the user by email
        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();

        // Check if the user exists
        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            // Check the password
            if (password_verify($password, $user["password"])) {

                // Save user information in the session
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];

                // Go to your existing dashboard
                header("Location:Dashboard/index.php");
                exit;

            } else {

                $message = "Incorrect email or password.";

            }

        } else {

            $message = "Incorrect email or password.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Login Result</title>
</head>

<body>

    <h1>
        <?php echo htmlspecialchars($message); ?>
    </h1>

    <a href="login.php">Back to Login</a>

</body>

</html>