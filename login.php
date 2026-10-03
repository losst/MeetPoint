
<?php // Nancy

session_start();

$redirect = $_GET['redirect'] ?? 'appointments.php'; // (Emily)

// session exists
if (isset($_SESSION['user_id'], $_SESSION['role'])) { 
    header("Location: " .$redirect); 
    exit();
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login_style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>

<body>
    <div class="container">
        <h1 class="header">LOGIN</h1>

        <form id="form" autocomplete="on">
            <div class="input-container">
                <div class="email-container">
                    <div class="email-label">McGill Email *</div>
                    <input class="email-input" type="email" name="email" placeholder="name@example.com" required> 
                </div>

                <div class="password-container">
                    <div class="password-label">Password *</div>
                    <div class="password-input-box">
                        <input class="password-input" id="password" type="password" name="password" placeholder="Enter your password" required> 
                        <button class="password-btn" type="button" onclick="showPassword()">
                            <!--bootstrap svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="rgb(91, 18, 112)" class="bi bi-eye-fill" viewBox="0 0 16 16">
                                <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                                <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <input class="submit-btn" type="submit" value="SIGN IN">
        </form>

        <div id="message" style="color: white; visibility: hidden">...</div>

        <div class="register-container">
            <span>New to MeetPoint?</span>
            <a href="register.php">Create an account.</a>
        </div>

    </div>
    

    <script>
        const redirect = new URLSearchParams(window.location.search).get("redirect") || "appointments.php"; // (Emily)
        
        // toggle visibility of password
        function showPassword() {
            const pass = document.getElementById("password");
            if (pass.type === "password") {
                pass.type = "text";
            } else {
                pass.type = "password";
            }
        }

        // create asynchronous request for form submission
        document.getElementById("form").addEventListener("submit", (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            const xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    const response = JSON.parse(this.responseText);

                    if (response.redirect) {
                        window.location.href = response.redirect;

                    } else if (response.message) {
                        // display message
                        const msg = document.getElementById("message");
                        msg.innerHTML = response.message;
                        msg.style.visibility = "visible";
                    }
                }
            }

            xhttp.open("POST", "login_action.php", true);
            xhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            formData.append("redirect", redirect); // (Emily)
            xhttp.send(new URLSearchParams(formData));
        })
    </script>
</body>

</html>