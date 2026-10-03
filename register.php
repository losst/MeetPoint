
<?php // Nancy

session_start();

// session exists
if (isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: appointments.php"); // to be modified
    exit();
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Register</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login_style.css">
    <link rel="stylesheet" href="css/register_style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>

<body>
    <div class="container">
        <h1 class="header">REGISTER</h1>

        <form id="form" method="post" action="register.php">
            <div class="input-container">
                <div class="email-container">
                    <div class="email-label">McGill Email *</div>
                    <input class="email-input" id="email" type="email" name="email"
                        placeholder="@mail.mcgill.ca or @mcgill.ca" required>
                </div>

                <div class="password-container">
                    <div class="password-input-box">
                        <div class="password-label">Password *</div>
                        <input id="password1" type="password" name="password" placeholder="Enter your password"
                            required>
                    </div>

                    <div class="password-input-box">
                        <div class="password-label">Confirm Password *</div>
                        <input id="password2" type="password" placeholder="Confirm your password" required>
                    </div>
                </div>

            </div>

            <input class="submit-btn" id="register" type="submit" value="REGISTER">
        </form>

        <div id="message" style="color: white; visibility: hidden">...</div>
        <a href="login.php">Return to login page</a>

    </div>


    <script>
        // asynchronous register request
        document.getElementById("form").addEventListener("submit", (event) => {
            event.preventDefault();

            const email = document.getElementById("email").value;
            const affiliation = email.split('@')[1];

            // check email
            if (affiliation === 'mcgill.ca' || affiliation === 'mail.mcgill.ca') {
                const password1 = document.getElementById("password1").value;
                const password2 = document.getElementById("password2").value;

                // check password
                if (password1 === password2) {

                    const xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function () {
                        if (this.readyState == 4 && this.status == 200) {
                            const response = JSON.parse(this.responseText);

                            if (response.redirect) {
                                window.location.href = response.redirect;

                            } else if (response.message) {
                                const msg = document.getElementById("message");
                                msg.innerHTML = response.message;

                                if (response.message.includes("Successfully")) {
                                    msg.style.textShadow = "0 0 8px #00ffcc";
                                }

                                msg.style.visibility = "visible";
                                setTimeout(() => { 
                                    msg.innerHTML = "..."; 
                                    msg.style.visibility = "hidden"; 
                                    msg.style.textShadow = "0 0 8px #ff4d6d";
                                }, 3000);
                            }
                        }
                    };

                    xhttp.open("POST", "register_action.php", true);
                    xhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhttp.send(new URLSearchParams(new FormData(event.target)));

                } else {
                    alert("The two passwords do not match!");
                    return false;
                }

            } else {
                alert("Please use your McGill email.");
                return false;
            }

        })
    </script>
</body>

</html>