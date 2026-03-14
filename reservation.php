<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Reservation</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#003A6B;--white:#ffffff;--radius:8px;--shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 16px rgba(0,58,107,0.10);}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
nav{background:var(--blue-dk);border-bottom:1px solid rgba(255,255,255,0.1);height:60px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-title{font-size:18px;font-weight:700;color:#ffffff;}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.8);text-decoration:none;padding:6px 12px;border-radius:var(--radius);transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;}
.nav-dropdown{position:relative;}
.nav-dropdown > a{display:flex;align-items:center;gap:4px;}
.nav-dropdown > a .chevron{font-size:10px;color:rgba(255,255,255,0.5);transition:transform .2s;}
.nav-dropdown:hover > a .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 6px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;text-decoration:none;border-radius:0 !important;background:transparent !important;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
.btn-logout{background:#e53e3e;color:#fff !important;font-weight:600 !important;border-radius:var(--radius);padding:6px 16px !important;margin-left:6px;}
.btn-logout:hover{background:#c53030 !important;}

.page-body{max-width:820px;margin:0 auto;padding:36px 20px 60px;}
.page-title{font-size:22px;font-weight:700;color:var(--blue-dk);margin-bottom:24px;text-align:center;}

.res-card{background:var(--white);border-radius:var(--radius);border:1px solid var(--gray-200);box-shadow:var(--shadow-md);overflow:hidden;}
.res-card-head{background:var(--blue);padding:14px 24px;}
.res-card-head h2{color:#fff;font-size:14px;font-weight:600;}

.res-body{padding:28px 28px 32px;}

.section-divider{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--blue);padding-bottom:8px;border-bottom:1px solid var(--gray-100);margin-bottom:16px;margin-top:24px;}
.section-divider:first-of-type{margin-top:0;}

.field{margin-bottom:16px;}
.field label{display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:6px;letter-spacing:0.03em;}
.field input,.field select,.field textarea{
  width:100%;padding:10px 12px;
  border:1px solid var(--gray-200);border-radius:var(--radius);
  font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-800);
  background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;
}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.12);}
.field input[readonly]{background:var(--gray-50);color:var(--gray-400);cursor:not-allowed;}
.field input::placeholder{color:var(--gray-400);}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

.btn-submit{
  padding:10px 28px;border:none;border-radius:var(--radius);
  background:var(--blue);color:#fff;
  font-size:13.5px;font-weight:600;font-family:'Inter',sans-serif;
  cursor:pointer;transition:background .15s;
}
.btn-submit:hover{background:var(--blue-dk);}

.btn-reserve{
  padding:10px 28px;border:none;border-radius:var(--radius);
  background:var(--blue);color:#fff;
  font-size:13.5px;font-weight:600;font-family:'Inter',sans-serif;
  cursor:pointer;transition:background .15s;margin-top:8px;
}
.btn-reserve:hover{background:var(--blue-dk);}

.session-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--blue-lt);border:1px solid var(--blue-bd);
  color:var(--blue);border-radius:6px;padding:6px 12px;
  font-size:13px;font-weight:600;margin-top:4px;
}

@media(max-width:600px){
  .field-row{grid-template-columns:1fr;}
  .res-body{padding:20px 18px 24px;}
  nav{padding:0 16px;}
}
</style>
</head>
<body>
<nav>
  <div class="nav-title">Dashboard</div>
  <div class="nav-links">
    <div class="nav-dropdown">
      <a href="#">Notification <span class="chevron">▾</span></a>
      <div class="dropdown-menu">
        <a href="#">All Notifications</a>
        <a href="#">Unread</a>
        <a href="#">Mark all as read</a>
      </div>
    </div>
    <a href="Homepage.php">Home</a>
    <a href="Profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="Reservation.php" class="active">Reservation</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-title">Reservation</div>

  <div class="res-card">
    <div class="res-card-head"><h2>Lab Reservation Form</h2></div>
    <div class="res-body">

      <!-- STUDENT DETAILS (read-only) -->
      <div class="section-divider">Student Details</div>
      <div class="field-row">
        <div class="field">
          <label>ID Number</label>
          <input type="text" value="<?php echo htmlspecialchars($_SESSION['id_number'] ?? ''); ?>" readonly/>
        </div>
        <div class="field">
          <label>Student Name</label>
          <input type="text" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?>" readonly/>
        </div>
      </div>

      <!-- RESERVATION DETAILS -->
      <div class="section-divider">Reservation Details</div>
      <div class="field-row">
        <div class="field">
          <label>Purpose</label>
          <input type="text" name="purpose" placeholder="e.g. C Programming"/>
        </div>
        <div class="field">
          <label>Laboratory</label>
          <input type="text" name="lab" placeholder="e.g. 524"/>
        </div>
      </div>

      <button class="btn-submit" type="button">Submit</button>

      <!-- TIME & DATE -->
      <div class="section-divider" style="margin-top:24px;">Schedule</div>
      <div class="field-row">
        <div class="field">
          <label>Time In</label>
          <input type="time" name="time_in"/>
        </div>
        <div class="field">
          <label>Date</label>
          <input type="date" name="date"/>
        </div>
      </div>

      <!-- SESSION -->
      <div class="section-divider">Session</div>
      <div class="field">
        <label>Remaining Session</label>
        <div class="session-badge">
          🖥️ <?php echo htmlspecialchars($_SESSION['session'] ?? '0'); ?> sessions remaining
        </div>
      </div>

      <button class="btn-reserve" type="button">Reserve</button>

    </div>
  </div>
</div>
</body>
</html>