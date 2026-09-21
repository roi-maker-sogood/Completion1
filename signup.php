<!DOCTYPE html>
<html>

<head>

    <title>Sign Up - PulseReport</title>

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

            <div class="auth-switch">

                <a href="signup.php" class="active">
                    Sign Up
                </a>

                <a href="login.php">
                    Sign In
                </a>

            </div>


            <h2>
                Create your account
            </h2>

            <p class="auth-description">
                Set up your workspace or join a team that's already
                reporting on PulseReport.
            </p>


            <form action="save.php" method="POST">

        <div class="form-group">

            <label>
                Full name
            </label>

            <input
                type="text"
                name="name"
                placeholder="e.g Roi Gwapo"
                required>

        </div>


    <div class="form-group">

        <label>
            Work email
        </label>

        <input
            type="email"
            name="email"
            placeholder="jeah@jtdigitalmarketingservices.com"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Password
        </label>

        <input
            type="password"
            name="password"
            id="password"
            placeholder="At least 8 characters"
            required>

              <button
                  type="button"
                  id="showPassword">Show
              </button>

    </div>


    <!-- CREATE ACCOUNT BUTTON -->

    <button
        type="submit"
        class="auth-button"
    >
        Create Account
    </button>

</form>


<div class="auth-footer">

    Already have an account?

    <a href="login.php">
        Sign In
    </a>

</div>
        </div>

    </div>
<script src="Styling/auth.js"></script>
</body>
</html>
