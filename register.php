<?php
session_start();
require_once 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize inputs
    $id_number  = trim($_POST['id_number']  ?? '');
    $lastname   = trim($_POST['lastname']   ?? '');
    $firstname  = trim($_POST['firstname']  ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $email      = trim($_POST['email']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $course     = trim($_POST['course']     ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($id_number) || empty($lastname) || empty($firstname) || empty($email) || empty($course) || empty($year_level) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_pw) {
        $error = 'Passwords do not match.';
    } else {
        // Check if ID number already exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id_number = ? LIMIT 1");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = 'ID Number is already registered.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email address is already registered.';
            } else {
                // Insert new student
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO students (id_number, lastname, firstname, middlename, course, year_level, email, password, address, session)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 30)
                ");
                $stmt->execute([$id_number, $lastname, $firstname, $middlename, $course, $year_level, $email, $hashed_pw, $address]);
                $success = 'Account created successfully! You can now log in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Register</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root {
  --blue:    #1B5886;
  --blue-dk: #003A6B;
  --blue-lt: #e8f4fb;
  --blue-bd: #89CFF1;
  --gray-50: #f4f8fc;
  --gray-100:#e8f0f7;
  --gray-200:#cddaec;
  --gray-400:#8aaac8;
  --gray-600:#3d607f;
  --gray-800:#003A6B;
  --white:   #ffffff;
  --radius:  8px;
  --shadow-md: 0 4px 16px rgba(0,58,107,0.10);
  --red:     #dc2626;
  --red-lt:  #fef2f2;
  --green:   #16a34a;
  --green-lt:#f0fdf4;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}

nav{
  background:var(--blue-dk);border-bottom:1px solid rgba(255,255,255,0.1);
  height:60px;padding:0 32px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;
}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-brand img{width:36px;height:36px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.nav-brand-text{font-size:14px;font-weight:600;color:#fff;line-height:1.3;}
.nav-brand-sub{font-size:11px;font-weight:400;color:rgba(255,255,255,0.5);}
.nav-links{display:flex;align-items:center;gap:4px;}
.nav-links a{font-size:13.5px;font-weight:500;color:rgba(255,255,255,0.8);text-decoration:none;padding:6px 12px;border-radius:var(--radius);transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links .btn-login{border:1px solid rgba(255,255,255,0.35);color:rgba(255,255,255,0.85);}
.nav-links .btn-register{background:var(--blue);color:#fff;font-weight:600;margin-left:2px;}
.nav-links .btn-register:hover{background:var(--blue-dk);color:#fff;}

/* Dropdown */
.nav-dropdown{position:relative;}
.nav-dropdown > a{display:flex;align-items:center;gap:4px;}
.nav-dropdown > a .chevron{font-size:10px;color:rgba(255,255,255,0.5);transition:transform .2s;}
.nav-dropdown:hover > a .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 6px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;font-weight:500;color:var(--gray-600) !important;text-decoration:none;border-radius:0 !important;background:transparent !important;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}

/* Alert */
.alert{padding:12px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:18px;font-weight:500;}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.alert-success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}

/* Auth page */
.auth-page{min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;background:var(--gray-50);padding:40px 16px;}

.reg-box{background:var(--white);border:1px solid var(--gray-200);border-radius:12px;box-shadow:var(--shadow-md);width:100%;max-width:640px;overflow:hidden;}
.reg-header{background:var(--blue);padding:20px 28px;display:flex;align-items:center;gap:14px;}
.reg-header img{width:44px;height:44px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.reg-header h2{color:#fff;font-size:17px;font-weight:700;}
.reg-header p{color:rgba(255,255,255,0.65);font-size:12px;margin-top:2px;}

.reg-body{padding:28px 28px 32px;}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--blue);padding-bottom:8px;border-bottom:1px solid var(--gray-200);margin-bottom:16px;margin-top:24px;}
.section-title:first-of-type{margin-top:0;}
.reg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
.reg-grid .full{grid-column:1/-1;}

.field{display:flex;flex-direction:column;gap:6px;}
.field label{font-size:12px;font-weight:600;color:var(--gray-600);}
.field label .req{color:var(--red);margin-left:2px;}
.field input,.field select{
  padding:10px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);
  font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-800);
  background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;
}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.12);}
.field input::placeholder{color:var(--gray-400);}

.reg-footer{display:flex;gap:10px;margin-top:24px;}
.btn-outline{padding:10px 18px;border-radius:var(--radius);background:transparent;border:1px solid var(--gray-200);color:var(--gray-600);font-size:13.5px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;display:flex;align-items:center;}
.btn-outline:hover{border-color:var(--blue);color:var(--blue);}
.btn{flex:1;padding:10px;border:none;border-radius:var(--radius);background:var(--blue);color:#fff;font-size:14px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:background .15s;}
.btn:hover{background:var(--blue-dk);}

.alt-line{text-align:center;margin-top:14px;font-size:13px;color:var(--gray-400);}
.alt-line a{color:var(--blue);font-weight:600;text-decoration:none;}
.alt-line a:hover{text-decoration:underline;}

@media(max-width:640px){
  nav{padding:0 16px;}
  .reg-grid{grid-template-columns:1fr;}
  .reg-grid .full{grid-column:1;}
  .reg-body{padding:24px 20px 28px;}
  .reg-header{padding:18px 20px;}
}
</style>
</head>
<body>

<nav>
  <a class="nav-brand" href="index.php">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <div>
      <div class="nav-brand-text">College of Computer Studies</div>
      <div class="nav-brand-sub">Sit-in Monitoring System</div>
    </div>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <div class="nav-dropdown">
      <a href="#">Community <span class="chevron">▾</span></a>
    </div>
    <a href="#">About</a>
    <a href="login.php" class="btn-login">Login</a>
    <a href="register.php" class="btn-register">Register</a>
  </div>
</nav>

<div class="auth-page">
  <div class="reg-box">
    <div class="reg-header">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
      <div>
        <h2>Create Account</h2>
        <p>CCS Sit-in Monitoring System</p>
      </div>
    </div>

    <div class="reg-body">

      <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?> <a href="login.php" style="color:var(--green);font-weight:700;">Click here to login →</a></div>
      <?php endif; ?>

      <form method="POST" action="register.php">

        <div class="section-title">Personal Information</div>
        <div class="reg-grid">
          <div class="field full">
            <label>ID Number <span class="req">*</span></label>
            <input type="text" name="id_number" value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>" required/>
          </div>
          <div class="field">
            <label>Last Name <span class="req">*</span></label>
            <input type="text" name="lastname" value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>" required/>
          </div>
          <div class="field">
            <label>First Name <span class="req">*</span></label>
            <input type="text" name="firstname" value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>" required/>
          </div>
          <div class="field">
            <label>Middle Name</label>
            <input type="text" name="middlename" value="<?php echo htmlspecialchars($_POST['middlename'] ?? ''); ?>"/>
          </div>
          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required/>
          </div>
          <div class="field full">
            <label>Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"/>
          </div>
        </div>

        <div class="section-title">Academic Information</div>
        <div class="reg-grid">
          <div class="field">
            <label>Course <span class="req">*</span></label>
            <select name="course" required>
              <option value="">Select course</option>
              <?php foreach(['BSIT','BSCS'] as $c): ?>
                <option value="<?php echo $c; ?>" <?php echo (($_POST['course'] ?? '') === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Year Level <span class="req">*</span></label>
            <select name="year_level" required>
              <option value="">Select year</option>
              <?php foreach(['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4] as $label=>$val): ?>
                <option value="<?php echo $val; ?>" <?php echo (($_POST['year_level'] ?? '') == $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="section-title">Account Security</div>
        <div class="reg-grid">
          <div class="field">
            <label>Password <span class="req">*</span></label>
            <input type="password" name="password" placeholder="At least 6 characters" required/>
          </div>
          <div class="field">
            <label>Confirm Password <span class="req">*</span></label>
            <input type="password" name="confirm_password" placeholder="Confirm password" required/>
          </div>
        </div>

        <div class="reg-footer">
          <a href="login.php" class="btn-outline">← Back</a>
          <button type="submit" class="btn">Create Account</button>
        </div>
        <p class="alt-line">Already have an account? <a href="login.php">Sign in</a></p>

      </form>
    </div>
  </div>
</div>

</body>
</html>