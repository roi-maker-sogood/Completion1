<!DOCTYPE html>
<html>

<head>

    <title>Sign In - PulseReport</title>

    <link rel="stylesheet" href="Styling/auth.css">

</head>

<body>

    <div class="auth-page">

        <!-- LEFT SIDE -->

        <div class="brand-section">

            <h1>
                PulseReport for JT Digital
            </h1>

            <p class="brand-description">
                Streamlined completion reports and analytics
                for modern agencies
            </p>

            <div class="report-preview">

                <div class="report-header">
                    JT DIGITAL<br>
                    MARKETING SERVICES
                </div>

                <div class="report-body">

                    <h2>
                        Completion Report
                    </h2>

                    <div class="report-month">
                        August 2026
                    </div>

                    <p>
                        <strong>Prepared For</strong><br>
                        The Outlets @ LIMA Estate
                    </p>

                    <p>
                        <strong>Prepared By</strong><br>
                        Jeah Tradio · JT Digital Marketing Services
                    </p>

                </div>

            </div>

            <div class="benefits">

                <div class="benefit">

                    <span class="check">✓</span>

                    <span>
                        Monthly content calendar & grid layouts
                        for Facebook, IG, and TikTok
                    </span>

                </div>

                <div class="benefit">

                    <span class="check">✓</span>

                    <span>
                        Performance analytics and content shoot logs
                    </span>

                </div>

                <div class="benefit">

                    <span class="check">✓</span>

                    <span>
                        Scope of work and approval tracker
                    </span>

                </div>

            </div>

        </div>


        <!-- RIGHT SIDE -->

        <div class="auth-card">

            <!-- SIGN UP / SIGN IN SWITCH -->

            <div class="auth-switch">

                <a href="signup.php">
                    Sign Up
                </a>

                <a href="login.php" class="active">
                    Sign In
                </a>

            </div>


            <h2>
                Welcome back
            </h2>


            <p class="auth-description">
                Sign in to continue managing your workspace
                and reports.
            </p>


            <!-- LOGIN FORM -->

            <form action="check_login.php" method="POST">


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Work email
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your password"
                        required
                    >

                    <button
                        type="button"
                        id="showPassword"
                    >
                        Show
                    </button>

                </div>


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="auth-button"
                >
                    Sign In
                </button>


            </form>


            <!-- FOOTER -->

            <div class="auth-footer">

                Don't have an account?

                <a href="signup.php">
                    Sign Up
                </a>

            </div>


        </div>

    </div>


    <!-- JAVASCRIPT -->

    <script src="Styling/auth.js"></script>

</body>

</html>