const password = document.getElementById("password");
const toggleButton = document.getElementById("showPassword");

toggleButton.addEventListener("click", function () {

    if (password.type === "password") {

        password.type = "text";
        toggleButton.textContent = "Hide";

    } else {

        password.type = "password";
        toggleButton.textContent = "Show";

    }

});