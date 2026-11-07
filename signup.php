<?php
session_start();

/*
|--------------------------------------------------------------------------
| AAVARTAN - LOCAL MYSQL SIGNUP
|--------------------------------------------------------------------------
| Requirements:
| 1. XAMPP Apache + MySQL must be running.
| 2. Create database/table using the SQL supplied with this project.
| 3. Put this file inside:
|      C:\xampp\htdocs\aavartan\signup.php
|--------------------------------------------------------------------------
*/

$host = "localhost";
$dbUser = "root";
$dbPassword = "";          // Change this if your MySQL root user has a password.
$dbName = "aavartan_db";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["fullName"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $college = trim($_POST["college"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";
    $terms = isset($_POST["terms"]);

    // -----------------------------
    // VALIDATION
    // -----------------------------
    if (
        $fullName === "" ||
        $email === "" ||
        $college === "" ||
        $course === "" ||
        $year === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {
        $message = "Password must contain at least 6 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } elseif (!$terms) {
        $message = "Please accept the Terms of Service.";
        $messageType = "error";

    } else {

        // -----------------------------
        // MYSQL CONNECTION
        // -----------------------------
        $conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

        if ($conn->connect_error) {
            $message = "Database connection failed: " . $conn->connect_error;
            $messageType = "error";
        } else {

            $conn->set_charset("utf8mb4");

            // -----------------------------
            // CHECK EXISTING EMAIL
            // -----------------------------
            $check = $conn->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $message = "This email is already registered. Please sign in.";
                $messageType = "error";

                $check->close();

            } else {

                $check->close();

                // -----------------------------
                // HASH PASSWORD
                // -----------------------------
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // -----------------------------
                // INSERT USER
                // -----------------------------
                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (full_name, email, college, course, year, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, ?, 'student')"
                );

                if (!$stmt) {

                    $message = "Database error: " . $conn->error;
                    $messageType = "error";

                } else {

                    $stmt->bind_param(
                        "ssssss",
                        $fullName,
                        $email,
                        $college,
                        $course,
                        $year,
                        $passwordHash
                    );

                    if ($stmt->execute()) {

                        $userId = $stmt->insert_id;

                        // -----------------------------
                        // CREATE PHP LOGIN SESSION
                        // -----------------------------
                        $_SESSION["user_id"] = $userId;
                        $_SESSION["full_name"] = $fullName;
                        $_SESSION["email"] = $email;
                        $_SESSION["college"] = $college;
                        $_SESSION["course"] = $course;
                        $_SESSION["year"] = $year;
                        $_SESSION["role"] = "student";

                        $stmt->close();
                        $conn->close();

                        // Redirect to your PHP dashboard.
                        header("Location: dashboard.php");
                        exit;

                    } else {

                        if ($conn->errno == 1062) {
                            $message = "This email is already registered. Please sign in.";
                        } else {
                            $message = "Unable to create account. Please try again.";
                        }

                        $messageType = "error";
                        $stmt->close();
                    }
                }
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Aavartan | Create Account</title>

<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html,body{
    width:100%;
    height:100%;
    margin:0;
    overflow:hidden;
}

body{
    font-family:"DM Sans",sans-serif;
    background:#f5f8f6;
    min-height:100vh;
    height:100vh;
    display:flex;
    color:#122019;
}

/* LEFT */

.left{
    width:45%;
    height:100vh;
    min-height:0;
    background:#122019;
    color:white;
    padding:38px 6%;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    position:relative;
    overflow:hidden;
}

.left::before{
    content:"";
    position:absolute;
    width:500px;
    height:500px;
    border:1px solid rgba(134,239,172,.15);
    border-radius:50%;
    right:-230px;
    top:-180px;
}

.logo{
    display:flex;
    align-items:center;
    gap:11px;
    font-family:"Plus Jakarta Sans";
    font-weight:800;
    font-size:22px;
    position:relative;
    z-index:2;
}

.logo-icon{
    width:42px;
    height:42px;
    border-radius:13px;
    background:#dcfce7;
    color:#16a34a;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
}

.logo span{
    color:#86efac;
}

.left-content{
    position:relative;
    z-index:2;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 12px;
    border:1px solid rgba(134,239,172,.2);
    background:rgba(134,239,172,.08);
    border-radius:30px;
    color:#a7f3d0;
    font-size:12px;
    font-weight:600;
    margin-bottom:16px;
}

.dot{
    width:7px;
    height:7px;
    background:#4ade80;
    border-radius:50%;
}

.left h1{
    font-family:"Plus Jakarta Sans";
    font-size:40px;
    line-height:1.08;
    letter-spacing:-2px;
}

.left h1 span{
    color:#86efac;
}

.left p{
    margin-top:14px;
    color:#aebbb4;
    line-height:1.7;
    font-size:15px;
    max-width:470px;
    max-height:calc(100vh - 48px);
}

.features{
    margin-top:24px;
    display:grid;
    gap:12px;
}

.feature{
    display:flex;
    align-items:center;
    gap:12px;
    color:#c2cec7;
    font-size:13px;
}

.feature-icon{
    width:32px;
    height:32px;
    border-radius:9px;
    background:rgba(134,239,172,.1);
    display:flex;
    align-items:center;
    justify-content:center;
}

.footer{
    color:#728078;
    font-size:12px;
}


/* RIGHT */

.right{
    width:55%;
    height:100vh;
    min-height:0;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px 5%;
}

.container{
    width:100%;
    max-width:470px;
}

.header{
    margin-bottom:16px;
}

.header h2{
    font-family:"Plus Jakarta Sans";
    font-size:30px;
    letter-spacing:-1px;
}

.header p{
    color:#718078;
    margin-top:7px;
    font-size:14px;
}


/* FORM */

.form-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

.group{
    margin-bottom:10px;
}

label{
    display:block;
    font-size:12px;
    font-weight:700;
    margin-bottom:7px;
}

input,
select{
    width:100%;
    height:42px;
    border:1px solid #dfe7e2;
    border-radius:10px;
    background:white;
    padding:0 13px;
    outline:none;
    font-family:inherit;
    font-size:13px;
}

input:focus,
select:focus{
    border-color:#4ade80;
    box-shadow:0 0 0 3px rgba(74,222,128,.1);
}

.password-wrapper{
    position:relative;
}

.password-wrapper input{
    padding-right:45px;
}

.eye{
    position:absolute;
    right:13px;
    top:50%;
    transform:translateY(-50%);
    border:none;
    background:none;
    cursor:pointer;
}


/* TERMS */

.terms{
    display:flex;
    gap:8px;
    align-items:flex-start;
    color:#718078;
    font-size:11px;
    line-height:1.5;
    margin:3px 0 10px;
}

.terms input{
    width:15px;
    height:15px;
    margin-top:1px;
    accent-color:#16a34a;
}

.terms a{
    color:#16a34a;
    font-weight:700;
}


/* BUTTON */

.signup-btn{
    width:100%;
    height:44px;
    border:none;
    border-radius:11px;
    background:#16a34a;
    color:white;
    font-family:inherit;
    font-weight:700;
    font-size:14px;
    cursor:pointer;
    transition:.2s;
}

.signup-btn:hover{
    background:#15803d;
}

.signup-btn:disabled{
    opacity:.6;
    cursor:not-allowed;
}


/* MESSAGE */

.message{
    display:none;
    padding:11px 13px;
    border-radius:9px;
    margin-bottom:15px;
    font-size:12px;
}

.error{
    display:block;
    background:#fef2f2;
    color:#b91c1c;
}

.success{
    display:block;
    background:#f0fdf4;
    color:#15803d;
}


/* LOGIN */

.login{
    text-align:center;
    margin-top:12px;
    color:#718078;
    font-size:13px;
}

.login a{
    color:#16a34a;
    font-weight:800;
    text-decoration:none;
}


/* SECURITY */

.security{
    margin-top:10px;
    padding:8px 12px;
    border-radius:10px;
    background:#f0fdf4;
    color:#4b6354;
    font-size:11px;
    text-align:center;
}


@media(max-width:850px){

    html,body{
        height:auto;
        min-height:100%;
        overflow:auto;
    }

    body{
        display:block;
    }

    .left{
        width:100%;
        height:auto;
        min-height:auto;
        padding:30px 7%;
    }

    .left-content{
        margin:45px 0;
    }

    .right{
        width:100%;
        height:auto;
        min-height:auto;
        padding:35px 7%;
    }

    .container{
        max-height:none;
    }

}

@media(max-width:500px){

    .form-row{
        grid-template-columns:1fr;
        gap:0;
    }

    .left h1{
        font-size:36px;
    }

}

</style>

</head>


<body>


<!-- LEFT -->

<section class="left">

    <div class="logo">

        <div class="logo-icon">♻</div>

        Aav<span>artan</span>

    </div>


    <div class="left-content">

        <div class="badge">

            <div class="dot"></div>

            Sustainable Student Marketplace

        </div>


        <h1>

            Give your project
            materials a
            <span>second life.</span>

        </h1>


        <p>

            Join Aavartan and connect with students
            who want to buy, exchange and donate
            college project materials.

        </p>


        <div class="features">

            <div class="feature">

                <div class="feature-icon">♻</div>

                Reuse leftover project materials

            </div>


            <div class="feature">

                <div class="feature-icon">₹</div>

                Save money on your next project

            </div>


            <div class="feature">

                <div class="feature-icon">🎓</div>

                Connect with students near your college

            </div>

        </div>

    </div>


    <div class="footer">

        © 2026 Aavartan · Built for students, by students.

    </div>

</section>



<!-- RIGHT -->

<section class="right">

<div class="container">


    <div class="header">

        <h2>Create your account</h2>

        <p>
            Join Aavartan and start reusing smarter.
        </p>

    </div>


    <?php if ($message !== ""): ?>
        <div id="message" class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>


    <form id="signupForm" method="POST" action="signup.php">


        <!-- NAME -->

        <div class="group">

            <label>Full Name</label>

            <input
                name="fullName"
                id="fullName"
                type="text"
                placeholder="Enter your full name"
                required
            >

        </div>


        <!-- EMAIL -->

        <div class="group">

            <label>Email Address</label>

            <input
                name="email"
                id="email"
                type="email"
                placeholder="you@college.edu"
                required
            >

        </div>


        <!-- COLLEGE + COURSE -->

        <div class="form-row">

            <div class="group">

                <label>College</label>

                <input
                    name="college"
                    id="college"
                    type="text"
                    placeholder="Your college"
                    required
                >

            </div>


            <div class="group">

                <label>Course</label>

                <select name="course" id="course" required>

                    <option value="">Select</option>

                    <option>B.Tech</option>

                    <option>B.E.</option>

                    <option>BCA</option>

                    <option>MCA</option>

                    <option>Diploma</option>

                    <option>Other</option>

                </select>

            </div>

        </div>


        <!-- YEAR -->

        <div class="group">

            <label>Year of Study</label>

            <select name="year" id="year" required>

                <option value="">Select your year</option>

                <option>First Year</option>

                <option>Second Year</option>

                <option>Third Year</option>

                <option>Final Year</option>

            </select>

        </div>


        <!-- PASSWORD -->

        <div class="group">

            <label>Password</label>

            <div class="password-wrapper">

                <input
                    name="password"
                    id="password"
                    type="password"
                    placeholder="Create a password"
                    minlength="6"
                    required
                >

                <button
                    type="button"
                    class="eye"
                    onclick="togglePassword('password')"
                >
                    👁
                </button>

            </div>

        </div>


        <!-- CONFIRM -->

        <div class="group">

            <label>Confirm Password</label>

            <div class="password-wrapper">

                <input
                    name="confirmPassword"
                    id="confirmPassword"
                    type="password"
                    placeholder="Confirm your password"
                    required
                >

                <button
                    type="button"
                    class="eye"
                    onclick="togglePassword('confirmPassword')"
                >
                    👁
                </button>

            </div>

        </div>


        <!-- TERMS -->

        <label class="terms">

            <input name="terms" id="terms" type="checkbox" required>

            <span>

                I agree to the
                <a href="#">Terms of Service</a>
                and
                <a href="#">Privacy Policy</a>.

            </span>

        </label>


        <button
            id="signupButton"
            class="signup-btn"
            type="submit"
        >

            Create Account →

        </button>


    </form>


    <div class="login">

        Already have an account?

        <a href="login.php">
            Sign in
        </a>

    </div>


    <div class="security">

        🔒 Your password is securely encrypted before being stored.

    </div>


</div>

</section>
<script>
window.togglePassword = function(id){
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
};
</script>
</body>

</html>