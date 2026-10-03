<?php

/*
|--------------------------------------------------------------------------
| Template Copier - Upload Handler
|--------------------------------------------------------------------------
*/


// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: index.php");
    exit;

}


// Check whether a file was uploaded
if (!isset($_FILES["template"])) {

    die("No template was uploaded.");

}


$file = $_FILES["template"];


// Check for upload errors
if ($file["error"] !== UPLOAD_ERR_OK) {

    die("There was a problem uploading the template.");

}


// Allowed file extensions
$allowedExtensions = [
    "pdf",
    "png",
    "jpg",
    "jpeg"
];


// Get the original filename
$originalName = $file["name"];


// Get the extension
$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);


// Check extension
if (!in_array($extension, $allowedExtensions, true)) {

    die("Invalid file type. Please upload PDF, PNG, JPG, or JPEG.");

}


// Maximum file size: 10 MB
$maxFileSize = 10 * 1024 * 1024;

if ($file["size"] > $maxFileSize) {

    die("File is too large. Maximum size is 10 MB.");

}


// Create folders if they don't exist
$uploadDirectory = __DIR__ . "/uploads/";
$copyDirectory = __DIR__ . "/copies/";


if (!is_dir($uploadDirectory)) {

    mkdir($uploadDirectory, 0777, true);

}


if (!is_dir($copyDirectory)) {

    mkdir($copyDirectory, 0777, true);

}


// Create a unique filename
$uniqueName =
    uniqid("template_", true)
    . "."
    . $extension;


// Full path for original upload
$uploadPath =
    $uploadDirectory
    . $uniqueName;


// Move uploaded file
if (!move_uploaded_file(
    $file["tmp_name"],
    $uploadPath
)) {

    die("Failed to save the uploaded template.");

}


// Create filename for the copy
$copyName =
    "copy_"
    . uniqid("", true)
    . "."
    . $extension;


// Full path for copy
$copyPath =
    $copyDirectory
    . $copyName;


// Create an exact copy
if (!copy($uploadPath, $copyPath)) {

    die("The template was uploaded, but the copy could not be created.");

}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Template Copied</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e1b4b,
                    #312e81
                );

            color: white;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 30px;

        }


        .card {

            width: 100%;

            max-width: 600px;

            padding: 40px;

            text-align: center;

            background:
                rgba(255, 255, 255, 0.08);

            border:
                1px solid
                rgba(255, 255, 255, 0.15);

            border-radius: 20px;

            backdrop-filter: blur(15px);

            box-shadow:
                0 20px 60px
                rgba(0, 0, 0, 0.35);

        }


        .success-icon {

            width: 70px;

            height: 70px;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #22c55e;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 35px;

        }


        h1 {

            margin-bottom: 10px;

        }


        p {

            color: #cbd5e1;

            line-height: 1.6;

        }


        .file-name {

            margin: 25px 0;

            padding: 15px;

            background:
                rgba(255, 255, 255, 0.07);

            border-radius: 10px;

            word-break: break-word;

        }


        .buttons {

            display: flex;

            gap: 10px;

            justify-content: center;

            margin-top: 25px;

        }


        .button {

            display: inline-block;

            padding: 12px 20px;

            border-radius: 10px;

            text-decoration: none;

            font-weight: bold;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #8b5cf6
                );

        }


        .button.secondary {

            background:
                rgba(255, 255, 255, 0.1);

        }

    </style>

</head>


<body>


<div class="card">


    <div class="success-icon">

        ✓

    </div>

    <h1>
        Template Copied!
    </h1>
    
    <p>
        Your template was successfully uploaded
        and an exact copy was created.
    </p>


    <div class="file-name">

        <strong>
            Original:
        </strong>

        <?php echo htmlspecialchars($originalName); ?>

        <br><br>

        <strong>
            Copy:
        </strong>

        <?php echo htmlspecialchars($copyName); ?>

    </div>


    <div class="buttons">

        <a
            href="index.php"
            class="button"
        >
            Copy Another
        </a>


        <a
            href="<?php echo "copies/" . rawurlencode($copyName); ?>"
            class="button secondary"
            download
        >
            Download Copy
        </a>

    </div>


</div>


</body>

</html>