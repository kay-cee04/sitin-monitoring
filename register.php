<?php
session_start();
require_once 'db.php';
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number=trim($_POST['id_number']??''); $id_number=trim($_POST['id_number']??'');
    $lastname=trim($_POST['lastname']??''); $firstname=trim($_POST['firstname']??'');
    $middlename=trim($_POST['middlename']??''); $email=trim($_POST['email']??'');
    $address=trim($_POST['address']??''); $course=trim($_POST['course']??'');
    $year_level=trim($_POST['year_level']??''); $password=$_POST['password']??'';
    $confirm_pw=$_POST['confirm_password']??'';
    if (!$id_number||!$lastname||!$firstname||!$email||!$course||!$year_level||!$password) $error='Please fill in all required fields.';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Please enter a valid email address.';
    elseif (strlen($password)<6) $error='Password must be at least 6 characters.';
    elseif ($password!==$confirm_pw) $error='Passwords do not match.';
    else {
        $s=$pdo->prepare("SELECT id FROM students WHERE id_number=? LIMIT 1"); $s->execute([$id_number]);
        if ($s->fetch()) $error='ID Number is already registered.';
        else {
            $s=$pdo->prepare("SELECT id FROM students WHERE email=? LIMIT 1"); $s->execute([$email]);
            if ($s->fetch()) $error='Email address is already registered.';
            else {
                $pdo->prepare("INSERT INTO students (id_number,lastname,firstname,middlename,course,year_level,email,password,address,session) VALUES (?,?,?,?,?,?,?,?,?,30)")
                    ->execute([$id_number,$lastname,$firstname,$middlename,$course,$year_level,$email,password_hash($password,PASSWORD_DEFAULT),$address]);
                $success='Account created! You can now log in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Register</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow-md:0 4px 20px rgba(0,58,107,0.11);--red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-brand img{width:34px;height:34px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.nav-brand-text{font-size:14px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-brand-sub{font-size:10.5px;color:rgba(255,255,255,0.5);}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.btn-login-nav{border:1px solid rgba(255,255,255,0.3);}
.btn-register-nav{background:var(--blue);color:#fff !important;font-weight:700 !important;opacity:.5;pointer-events:none;}
.nav-dropdown{position:relative;}
.nav-dropdown>a{display:flex;align-items:center;gap:4px;}
.chevron{font-size:10px;color:rgba(255,255,255,0.4);transition:transform .2s;}
.nav-dropdown:hover .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 8px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;background:transparent !important;border-radius:0 !important;font-weight:500;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
.auth-page{min-height:calc(100vh - 58px);display:flex;align-items:center;justify-content:center;padding:36px 16px;}
.reg-box{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);width:100%;max-width:640px;overflow:hidden;}
.reg-header{background:linear-gradient(135deg,#002855 0%,#1B5886 100%);padding:20px 28px;display:flex;align-items:center;gap:14px;}
.reg-header img{width:44px;height:44px;border-radius:50%;border:2px solid rgba(255,255,255,0.28);}
.reg-header h2{color:#fff;font-size:17px;font-weight:800;letter-spacing:-0.02em;}
.reg-header p{color:rgba(255,255,255,0.6);font-size:12px;margin-top:2px;}
.reg-body{padding:28px 28px 32px;}
.alert{padding:12px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:18px;font-weight:600;}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.alert-success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.section-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--blue);padding-bottom:8px;border-bottom:1px solid var(--gray-100);margin-bottom:14px;margin-top:22px;}
.section-title:first-of-type{margin-top:0;}
.reg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
.reg-grid .full{grid-column:1/-1;}
.field{display:flex;flex-direction:column;gap:5px;}
.field label{font-size:11.5px;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.03em;}
.field label .req{color:var(--red);margin-left:2px;}
.field input,.field select{padding:10px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field input::placeholder{color:var(--gray-400);}
.reg-footer{display:flex;gap:10px;margin-top:24px;}
.btn-back{padding:10px 18px;border-radius:var(--radius);background:transparent;border:1.5px solid var(--gray-200);color:var(--gray-600);font-size:13.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:all .15s;text-decoration:none;display:flex;align-items:center;}
.btn-back:hover{border-color:var(--blue);color:var(--blue);}
.btn{flex:1;padding:11px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;}
.btn:hover{background:#002255;}
.alt-line{text-align:center;margin-top:14px;font-size:13px;color:var(--gray-400);}
.alt-line a{color:var(--blue);font-weight:700;text-decoration:none;}
@media(max-width:640px){nav{padding:0 16px;}.reg-grid{grid-template-columns:1fr;}.reg-grid .full{grid-column:1;}.reg-body{padding:22px 18px 26px;}}
</style>
</head>
<body>
<nav>
  <a class="nav-brand" href="index.php">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <div><div class="nav-brand-text">College of Computer Studies</div><div class="nav-brand-sub">Sit-in Monitoring System</div></div>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <div class="nav-dropdown">
      <a href="#">Community <span class="chevron">▾</span></a>
    </div>
    <a href="#">About</a>
    <a href="login.php" class="btn-login-nav">Login</a>
    <a href="register.php" class="btn-register-nav">Register</a>
  </div>
</nav>
<div class="auth-page">
  <div class="reg-box">
    <div class="reg-header">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
      <div><h2>Create Account</h2><p>CCS Sit-in Monitoring System</p></div>
    </div>
    <div class="reg-body">
      <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?> <a href="login.php" style="color:var(--green);font-weight:700;">Sign in →</a></div><?php endif; ?>
      <form method="POST" action="register.php">
        <div class="section-title">Personal Information</div>
        <div class="reg-grid">
          <div class="field full"><label>ID Number <span class="req">*</span></label><input type="text" name="id_number" value="<?= htmlspecialchars($_POST['id_number']??'') ?>" required/></div>
          <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="lastname" value="<?= htmlspecialchars($_POST['lastname']??'') ?>" required/></div>
          <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="firstname" value="<?= htmlspecialchars($_POST['firstname']??'') ?>" required/></div>
          <div class="field"><label>Middle Name</label><input type="text" name="middlename" value="<?= htmlspecialchars($_POST['middlename']??'') ?>"/></div>
          <div class="field"><label>Email Address <span class="req">*</span></label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>" required/></div>
          <div class="field full"><label>Address</label><input type="text" name="address" value="<?= htmlspecialchars($_POST['address']??'') ?>"/></div>
        </div>
        <div class="section-title">Academic Information</div>
        <div class="reg-grid">
          <div class="field"><label>Course <span class="req">*</span></label>
            <select name="course" required><option value="">Select course</option>
              <?php foreach(['BSIT','BSCS'] as $c): ?><option value="<?=$c?>" <?=($_POST['course']??'')===$c?'selected':''?>><?=$c?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Year Level <span class="req">*</span></label>
            <select name="year_level" required><option value="">Select year</option>
              <?php foreach(['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4] as $l=>$v): ?><option value="<?=$v?>" <?=($_POST['year_level']??'')==$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="section-title">Account Security</div>
        <div class="reg-grid">
          <div class="field"><label>Password <span class="req">*</span></label><input type="password" name="password" placeholder="At least 6 characters" required/></div>
          <div class="field"><label>Confirm Password <span class="req">*</span></label><input type="password" name="confirm_password" placeholder="Repeat password" required/></div>
        </div>
        <div class="reg-footer">
          <a href="login.php" class="btn-back">← Back</a>
          <button type="submit" class="btn">Create Account</button>
        </div>
        <p class="alt-line">Already have an account? <a href="login.php">Sign in</a></p>
      </form>
    </div>
  </div>
</div>
</body>
</html>