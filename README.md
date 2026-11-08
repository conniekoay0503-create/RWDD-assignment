# E-Waste & Repair Café

RWDD assignment, Group 52, Asia Pacific University.

Students bring broken devices to the Green Hub (Block E). A student volunteer tries to repair them. Devices that cannot be repaired are either returned to the owner or recycled by a Recycling Officer. Students earn points for every device and can swap the points for APCard cashback.

| Role | Member | Folder |
|---|---|---|
| Admin | Koay Shin Shee (TP090995) | `admin/` |
| Student | Genieve Ki Yi Nee (TP087791) | `student/` |
| Recycling Officer | Jerald Long Jitson (TP087766) | `officer/` |
| Repair Volunteer | Lim Wen Ying (TP091586) | `volunteer/` |

Built with plain PHP 8 (mysqli), MySQL / MariaDB, HTML, CSS and JavaScript. No frameworks or libraries.

---

## 1. Run it on XAMPP

1. Copy the project folder into `C:\xampp\htdocs\`, for example `C:\xampp\htdocs\RWDD-assignment\`.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`:
   1. Click **New**, create a database named `ewaste_repair_cafe` (collation `utf8mb4_general_ci`).
   2. Select the new database, open the **Import** tab, choose `database/ewaste_repair_cafe.sql`, and click **Import**.
4. Open `http://localhost/RWDD-assignment/` (use your own folder name). You will see the homepage. Click **Log in** or **Sign up**.

If your MySQL has a password or does not use port 3306, change `config/db.php`.

**Importing again:** the SQL file does not delete old tables. To reset the demo data, drop all tables in `ewaste_repair_cafe` first (select all tables, then **Drop**), then import again.

## 2. Demo accounts

Every password is `Password123`.

| Role | Email |
|---|---|
| Admin | koay@mail.apu.edu.my, aisyah@mail.apu.edu.my |
| Recycling Officer | jerald@mail.apu.edu.my |
| Repair Volunteer | lim@mail.apu.edu.my |
| Student | genieve@mail.apu.edu.my, daniel@mail.apu.edu.my, priya@mail.apu.edu.my, meiling@mail.apu.edu.my, arjun@mail.apu.edu.my, siti@mail.apu.edu.my |
| Deactivated (cannot log in) | ken@mail.apu.edu.my |

After logging in, each role goes to its own dashboard (`home_for()` in `includes/functions.php`).

## 3. Folder structure

```
index.php                 Homepage (one screen: hero, live numbers, Log in / Sign up)
login.php  register.php  logout.php

config/db.php             Database connection (mysqli) and time zone (Asia/Kuala_Lumpur)

includes/
  functions.php           Shared functions (see section 6)
  icons.php               icon('name') draws an SVG icon
  public_top.php          Top of the login / register pages
  admin_top.php    bottom.php      Top bar + menu / closing tags for admin pages
  student_top.php  bottom.php    Same for student pages
  officer_top.php  bottom.php    Same for officer pages
  volunteer_top.php  bottom.php   Same for volunteer pages
  volunteer_logic.php                    Database functions used by the volunteer pages
  collection_deadlines.php               check_collection_deadlines(): sends "Overdue" notifications

admin/      Dashboard, Categories (+ points calculator), Challenges, Cashback (requests, history, rewards),
            Users (all users, volunteer requests, assign officer), Announcements, Feedback,
            Reports (print / save as PDF), Profile, Notifications
student/    Dashboard, Submit a Device, My Devices, Device Details, Points & Cashback, Redeem,
            Challenges & Leaderboard, Feedback, Become a Volunteer, My Report, Notifications
officer/    Dashboard, Recycling Queue, Process / Confirm Recycling, Abandoned Devices,
            Recycling History, Recycling Statistics, Notifications
volunteer/  Dashboard, Repair Queue (claim a device), My Jobs (notes, fixed / can't be fixed,
            ready at the counter), Repair History, Points & Cashback, Challenge & Rank,
            Repair Report, Notifications, Profile

assets/css/style.css      All styles for the whole website (no Bootstrap)
assets/js/main.js         All JavaScript for the whole website
assets/img/               SVG pictures for the homepage
uploads/devices/          Device photos uploaded by students
database/ewaste_repair_cafe.sql   Tables + demo data
```

Every page inside `admin/`, `student/`, `officer/` and `volunteer/` starts with the same 3 lines. Keep them:

```php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');   // 'admin' / 'student' / 'officer' / 'volunteer'
```

`require_role()` does six things on every page, in this order:
1. Is the user logged in? If not, go to the login page.
2. Is the account still active? If an admin deactivated it, log the user out.
3. Read the role again from the database (`find_role()`), so a student who was just approved as a volunteer moves to the volunteer pages straight away.
4. Wrong role? Go to that user's own dashboard.
5. `check_csrf()`: every POST form must carry the CSRF token (section 6).
6. `check_collection_deadlines()`: the 7-day checks (section 4).

## 4. Device status flow (15 statuses)

```
Submitted ──> Dropped Off ──> Claimed ──> In Repair
   │               │                         │
   └─> Cancelled   └─> Returned Unclaimed    ├─> Ready for Collection ──> Completed
                       (no volunteer          │     (repaired)
                        claimed it in         │
                        7 days)               └─> Pending Consent   (cannot be repaired)
                                                    ├─> Awaiting Recycle ──> Recycled         (student allows recycling)
                                                    └─> Return Requested ──> Returned Unrepaired ──> Collected Unrepaired
                                                                                                    (student wants it back)
Not collected within 7 days (Ready for Collection / Returned Unrepaired / Returned Unclaimed)
  ──(officer, Abandoned Devices page)──> Disposed
```

Who changes what:
| New status | Who | Page |
|---|---|---|
| Submitted | Student | `student/submit-device.php` |
| Dropped Off / Cancelled | Student | `student/device-details.php` ("Mark as dropped off" / "Cancel") |
| Claimed | Volunteer | `volunteer/repair_queue.php` |
| In Repair | Volunteer | `volunteer/my_jobs.php` ("Confirm receipt": the volunteer **weighs the device** here) |
| Ready for Collection / Pending Consent | Volunteer | `volunteer/my_jobs.php` ("Mark as fixed" gives the points / "Can't be fixed") |
| Awaiting Recycle / Return Requested | Student | `student/device-details.php` (the choice after Pending Consent) |
| Returned Unrepaired | Volunteer | `volunteer/my_jobs.php` ("It is ready at the counter") |
| Returned Unclaimed | System | `vol_return_expired()`: dropped off but not claimed within 7 days |
| Completed / Collected Unrepaired | Student | `student/device-details.php` ("Confirm Collection") |
| Recycled | Officer | `officer/confirm_recycling.php` (gives the points) |
| Disposed | Officer | `officer/abandoned_devices.php` |

Every status change also INSERTs one row into `device_status_history` (the timeline), and **whoever changes the status calls `notify()` at that moment** to tell the owner.

**7-day rules.** XAMPP has no scheduler, so `require_role()` checks them when a page opens (at most once every 5 minutes per user, a *throttle*, so the pages stay fast). The rule is written **once**, in `functions.php` (`DEADLINE_DAYS`, `WAITING_FOR_OWNER`, `WAITING_FOR_ACTION`, `overdue_sql()`), and every page uses it:
- **WAITING_FOR_OWNER** (Ready for Collection, Returned Unrepaired, Returned Unclaimed) for more than 7 days: overdue. `check_collection_deadlines()` notifies the owner, the admins and the officers (once), and the device appears on the officer's Abandoned Devices page. There the officer first presses **Remind owner**; only **24 hours** after a reminder (`DISPOSE_WAIT_HOURS`) can the device be disposed of (checked in SQL, and the button is disabled until then). The disposal methods are one shared list, `DISPOSAL_METHODS`.
- **WAITING_FOR_ACTION** (Pending Consent, Return Requested, In Repair) for more than 7 days: also overdue on the admin dashboard. `check_collection_deadlines()` reminds the person who must act (once): the owner for Pending Consent (recycle or return?), the volunteer for Return Requested (bring it to the counter) and for In Repair (finish it, or mark it as "Can't be fixed"; the device is not taken away, because a repair can take long). These are never on the Abandoned Devices page, so a Return Requested device can **never** be disposed of: the student has asked for it back.
- Dropped off but not claimed within 7 days: becomes Returned Unclaimed (`vol_return_expired()`).
- Claimed but not picked up within 7 days (the device is still at the counter): goes back to the repair queue for another volunteer, and the volunteer is told (`vol_release_stale_claims()`).

Every status that waits for a person has a deadline or a reminder, so no device can wait forever.

## 5. Points and cashback

**Points** (each category has a multiplier and a max):

```
points = MIN( FLOOR(weight_kg × multiplier), max_points )
repaired device: the student gets × 2 (still capped at max_points)
the volunteer who repaired it gets the normal amount
```

One function does this for the whole website: `calc_points($kg, $multiplier, $max, $repaired)` in `functions.php` (`main.js` copies the same rule for the admin calculator). It rounds to 2 decimals before rounding down, because a computer stores 0.29 × 100 as 28.999…, and `floor()` alone would give 28 instead of 29.

When the points are given:
- **Repaired**: the volunteer × 1 when they press "Mark as fixed" (`vol_change_status()`), and the owner × 2 when they press "Confirm Collection" (`student/device-details.php`), each in the same transaction as its status change. Points are given at the moment the work is really finished: a repaired device that is never collected (and is disposed of) earns the owner nothing, the same as the challenge rule (`Completed` only).
- **Recycled**: when the officer confirms recycling: the owner × 1.
- The UNIQUE key `(deviceID, tp_number)` in `points_transactions` stops anyone being paid twice for one device.

**Challenges:** a device counts when it is repaired and collected, or recycled, during the challenge (`CHALLENGE_STATUSES`, `challenge_kg()` in `functions.php`; the student page uses the same rule). After a challenge ends, the admin presses **Award bonus** on the Challenges page. Every student who reached the target gets the bonus once (the UNIQUE key `(challengeID, tp_number)` stops a second one). The target is **per student** and the bonus is **for students only**; the volunteer Challenge page just shows the campus numbers from `challenge_summary()` (same rule).

**Changing role.** A student becomes a volunteer (approved request) or an officer (Assign officer) only when none of their devices is still in progress (`open_devices_count()`, `FINISHED_STATUSES`). Otherwise the device would get stuck: only the student pages let the owner choose recycle / return or confirm collection. The same check is on the student's Become a Volunteer page (they cannot apply yet) and on the admin's request card (Approve is disabled).

**Deactivating an account** (admin Users page) is also "leaving", so it has the same check: not while the person still has devices in progress (`open_devices_count()`) or repair jobs (`open_jobs_count()`: Claimed, In Repair, Return Requested).

Example: Phone / Tablet, multiplier 500, max 300. A 0.2 kg phone is worth 0.2 × 500 = 100 points, or 200 if it is repaired.

Balance = `SUM(points)` from `points_transactions` (earn and bonus rows are positive, redeem rows are negative).

**Cashback:**
1. The student redeems a reward. A `pending` redemption is created, a negative `redeem` transaction is added, and **1 is taken from the reward's stock straight away**, so two students cannot get the last one.
2. The admin approves it and pays the amount to the APCard linked to the student's email. The stock is not reduced again.
3. Or the admin rejects it. The `redeem` transaction is deleted (the points come back) and the stock goes back up by 1.

Rules on the admin Rewards page:
- A reward that has already been redeemed cannot be deleted, because that would break the cashback history. Set the stock to 0 to stop offering it.
- A new reward cannot have the same cash value as a reward that is still in stock.

## 6. Notes for the team

### IDs are prefixed strings
All IDs are VARCHAR(10), "prefix + 3 digits", **not** AUTO_INCREMENT. Give the ID yourself when you INSERT:

| Table | Prefix | Example |
|---|---|---|
| announcements | A | A001 |
| categories | C | C001 |
| challenges | CH | CH001 |
| devices | D | D001 |
| device_status_history | H | H001 |
| feedback | F | F001 |
| notifications | N | N001 |
| points_transactions | T | T001 |
| redemptions | R | R001 |
| rewards | RW | RW001 |
| volunteer_request | VR | VR001 |

```php
$deviceID = next_id($conn, 'devices', 'deviceID', 'D');   // e.g. 'D017'
db_exec($conn, "INSERT INTO devices (deviceID, submitted_by, ...) VALUES (?, ?, ...)", 'ss...', [$deviceID, $tp, ...]);
```
In `bind_param` the type of an ID is `'s'` (string), not `'i'`.

### Shared functions (`includes/functions.php`)
| Function | What it does |
|---|---|
| `e($text)` | `htmlspecialchars()`: use it every time you print user input |
| `db_all` / `db_one` / `db_value` / `db_exec` | Run a prepared statement and return all rows / one row / one value / affected rows |
| `next_id($conn, $table, $column, $prefix)` | Next ID such as `D017` |
| `notify($conn, $tp, $title, $message)` | Send a notification to one user (it makes the N-ID itself) |
| `notify_admins($conn, $title, $message)` | Send a notification to every active admin |
| `set_flash($type, $msg)` + `redirect($url)` | Show a message on the next page (Post → Redirect → Get) |
| `require_role($conn, $role, $base)` | Page guard (see section 3) |
| `csrf_field()` / `check_csrf()` | CSRF token for forms (see below) |
| `calc_points(...)`, `overdue_sql(...)`, `challenge_kg(...)` | The shared rules (sections 4 and 5) |
| `rm($amount)`, `nice_date($date)` | Format money (`RM 5.00`) and dates |

When a student does something an admin must handle (redeems cashback, sends feedback, asks to be a volunteer), call `notify_admins()` after the INSERT. The admin's bell then shows a red number.

### CSRF: put `<?= csrf_field() ?>` in every POST form
CSRF = a bad website makes a logged-in user's browser send a form to our site (the browser adds our cookie by itself). So every `<form method="post">` must carry a secret token:
```php
<form method="post">
    <?= csrf_field() ?>
    ...
</form>
```
You do **not** need to check it yourself: `require_role()` calls `check_csrf()`, and a form without the right token is refused with "Your session has expired". `login.php` and `register.php` call `check_csrf()` themselves. The token is the same for the whole session, so two open tabs both work.

### Error pages
Never use `exit('Device not found.')`: the user sees a blank page with no menu. Use a message and go back:
```php
set_flash('error', 'Device not found.');
redirect('my-devices.php');
```

### Text length
Use `mb_strlen()` / `mb_substr()` for anything a user types. `strlen()` / `substr()` count bytes, so they can cut a Chinese character or an emoji in half.

### Pop-up messages and confirmations (handled by `main.js`)
- **Confirm before saving or deleting:** add `data-confirm="Delete this device?"` to a `<form>`, a submit button or a link. The browser's `confirm()` pop-up appears, and the form is only sent if the user presses OK.
- **Check a form before it is sent:** `<form novalidate data-validate>`. It checks `required`, `email`, `min` / `max`, `maxlength` and `pattern`. Each error appears under its box, and `alert()` lists all of them.
- **Number boxes:** `<input type="number" step="1">` only accepts whole numbers. Typing a letter shows a message straight away. Use `step="0.01"` to allow a decimal point.
- **Messages from PHP:** `set_flash('error', '...')` shows a red box on the next page and also pops up with `alert()`. `set_flash('success', '...')` shows a green box only.

## 7. Points to explain in the presentation

- **Prepared statements**: every SQL statement uses `?` and `bind_param`, so user input can never become part of the SQL command. This prevents SQL injection.
- **password_hash / password_verify**: the database only stores a hash, never the real password.
- **htmlspecialchars (`e()`)**: user input is escaped before it is printed. This prevents XSS.
- **CSRF token**: every POST form carries a secret token (`csrf_field()`), checked in one place (`require_role()` → `check_csrf()`). A bad website cannot read the token, so it cannot send a form as our user.
- **Single source of truth**: each rule is written once in `functions.php` (points `calc_points()`, overdue `overdue_sql()`, challenges `challenge_kg()`, IDs `next_id()`) and every role calls it. Changing a rule means changing one place, and two pages can never give different answers.
- **Session + require_role()**: every page checks login, active account and role on its first lines.
- **Transactions**: when one action changes several tables (for example, approving a volunteer: update the request, remove the student row, add the volunteer row, send a notification), it runs inside `begin_transaction()`. If any step fails, `rollback()` undoes all of them, so nothing is half done.
- **"Affected rows = 1" check**: `UPDATE ... WHERE status = 'pending'` changes 1 row only while the request is still pending. If two admins press Approve at the same time, the second one changes 0 rows and sees "already reviewed".
- **PRG (Post → Redirect → Get) + flash messages**: after a form is sent the page redirects, so pressing F5 does not send it twice.
- **Client + server validation**: JavaScript checks first (fast feedback), and PHP always checks again (safe, because JavaScript can be turned off).
- **Time zone**: PHP and MySQL both use Malaysia time (UTC+8), set in `config/db.php`. XAMPP's default is Europe/Berlin, which would make every time 7 hours behind.
- **rem units**: sizes in `style.css` use `rem`, so the page follows the user's browser font-size setting.

## 8. Before you push to GitHub

1. Run `git status` and check that only your own files changed.
2. Do not commit `config/db.php` if you changed the password in it for your own computer.
3. Tell the team when you change a shared file (`includes/`, `assets/css/style.css`, `assets/js/main.js`, `admin/cashback.php`), because it affects every role.

## 9. Known limitations

- `next_id()` uses MAX + 1. If two people insert into the same table at exactly the same moment, they could get the same ID and the second INSERT fails. This is acceptable for the assignment.
- The demo photos in `uploads/devices/` (`demo_1.jpg` … `demo_16.jpg`) are simple drawings. Replace them with your own photos (same file names), because the brief asks for graphics created by the team.
