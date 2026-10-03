<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Template Copier</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
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


        .container {
            width: 100%;
            max-width: 850px;
        }


        /* =========================
           HEADER
           ========================= */

        .header {
            text-align: center;
            margin-bottom: 30px;
        }


        .header h1 {
            font-size: 42px;
            margin-bottom: 10px;
        }


        .header p {
            color: #cbd5e1;
            font-size: 16px;
        }


        /* =========================
           UPLOAD CARD
           ========================= */

        .upload-card {
            background: rgba(255, 255, 255, 0.08);

            border: 1px solid rgba(255, 255, 255, 0.15);

            border-radius: 20px;

            padding: 35px;

            backdrop-filter: blur(15px);

            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.35);
        }


        /* =========================
           DROP AREA
           ========================= */

        .drop-area {

            border: 2px dashed #64748b;

            border-radius: 16px;

            padding: 60px 30px;

            text-align: center;

            cursor: pointer;

            transition: 0.25s ease;
        }


        .drop-area:hover {

            border-color: #818cf8;

            background:
                rgba(129, 140, 248, 0.08);

        }


        .drop-area.dragover {

            border-color: #a5b4fc;

            background:
                rgba(129, 140, 248, 0.15);

        }


        .upload-icon {

            font-size: 55px;

            margin-bottom: 15px;
        }


        .drop-area h2 {

            font-size: 22px;

            margin-bottom: 10px;
        }


        .drop-area p {

            color: #cbd5e1;

            margin-bottom: 20px;
        }


        .browse-button {

            display: inline-block;

            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #8b5cf6
                );

            color: white;

            padding: 12px 22px;

            border-radius: 10px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s ease;
        }


        .browse-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(99, 102, 241, 0.4);

        }


        #templateFile {

            display: none;

        }


        /* =========================
           FILE INFORMATION
           ========================= */

        .file-info {

            display: none;

            margin-top: 20px;

            padding: 15px;

            background: rgba(255, 255, 255, 0.07);

            border-radius: 10px;
        }


        .file-info.active {

            display: block;

        }


        .file-name {

            font-weight: bold;

            margin-bottom: 5px;

            word-break: break-word;

        }


        .file-type {

            color: #cbd5e1;

            font-size: 13px;

        }


        /* =========================
           COPY BUTTON
           ========================= */

        .copy-button {

            width: 100%;

            margin-top: 25px;

            padding: 15px;

            border: none;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    #4f46e5,
                    #7c3aed
                );

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s ease;
        }


        .copy-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(79, 70, 229, 0.4);

        }


        .copy-button:disabled {

            opacity: 0.5;

            cursor: not-allowed;

            transform: none;

            box-shadow: none;
        }


        /* =========================
           SUPPORTED FILES
           ========================= */

        .supported {

            text-align: center;

            margin-top: 20px;

            color: #94a3b8;

            font-size: 13px;
        }

    </style>

</head>


<body>


<div class="container">


    <!-- =========================
         HEADER
         ========================= -->

    <header class="header">

        <h1>
            Template Copier
        </h1>

        <p>
            Turn a PDF into an editable design template.
        </p>

    </header>



    <!-- =========================
         UPLOAD CARD
         ========================= -->

    <main class="upload-card">


        <form
            action="analyze.php"
            method="POST"
            enctype="multipart/form-data"
            id="uploadForm"
        >


            <div
                class="drop-area"
                id="dropArea"
            >


                <div class="upload-icon">
                    📄
                </div>


                <h2>
                    Upload your template
                </h2>


                <p>
                    Drag and drop your file here
                    or choose one from your computer.
                </p>


                <label
                    for="templateFile"
                    class="browse-button"
                >
                    Choose Template
                </label>


                <input
                    type="file"
                    name="template"
                    id="templateFile"
                    accept=".pdf,application/pdf"
                    required
                >

            </div>



            <!-- FILE INFORMATION -->

            <div
                class="file-info"
                id="fileInfo"
            >

                <div
                    class="file-name"
                    id="fileName"
                >
                </div>

                <div
                    class="file-type"
                    id="fileType"
                >
                </div>

            </div>



            <!-- COPY BUTTON -->

            <button
                type="submit"
                class="copy-button"
                id="copyButton"
                disabled
            >
                Analyze PDF
            </button>


        </form>


        <div class="supported">

            PDF files up to 10 MB

        </div>


    </main>


</div>



<script>

    const fileInput =
        document.getElementById("templateFile");

    const dropArea =
        document.getElementById("dropArea");

    const fileInfo =
        document.getElementById("fileInfo");

    const fileName =
        document.getElementById("fileName");

    const fileType =
        document.getElementById("fileType");

    const copyButton =
        document.getElementById("copyButton");

    document.getElementById("uploadForm").addEventListener(
        "submit",
        function () {
            copyButton.disabled = true;
            copyButton.textContent = "Analyzing PDF...";
        }
    );


    /*
     * When the user selects a file
     */

    fileInput.addEventListener(
        "change",
        function () {

            if (fileInput.files.length > 0) {

                showFile(fileInput.files[0]);

            }

        }
    );


    /*
     * Show selected file
     */

    function showFile(file) {

        const isPdf =
            file.type === "application/pdf" ||
            file.name.toLowerCase().endsWith(".pdf");

        if (!isPdf || file.size > 10 * 1024 * 1024) {

            fileInput.value = "";
            fileName.textContent = "";
            fileType.textContent = !isPdf
                ? "Only PDF files are supported."
                : "PDF files must be 10 MB or smaller.";
            fileInfo.classList.add("active");
            copyButton.disabled = true;
            return;

        }

        fileName.textContent =
            file.name;

        fileType.textContent =
            file.type || "Unknown file type";

        fileInfo.classList.add("active");

        copyButton.disabled = false;

    }


    /*
     * Drag over
     */

    dropArea.addEventListener(
        "dragover",
        function (event) {

            event.preventDefault();

            dropArea.classList.add(
                "dragover"
            );

        }
    );


    /*
     * Drag leave
     */

    dropArea.addEventListener(
        "dragleave",
        function () {

            dropArea.classList.remove(
                "dragover"
            );

        }
    );


    /*
     * Drop file
     */

    dropArea.addEventListener(
        "drop",
        function (event) {

            event.preventDefault();

            dropArea.classList.remove(
                "dragover"
            );


            const files =
                event.dataTransfer.files;


            if (files.length > 0) {

                fileInput.files = files;

                showFile(files[0]);

            }

        }
    );

</script>


</body>

</html>