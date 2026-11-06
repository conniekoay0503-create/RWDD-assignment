# Student module update — 2 November 2026

## What changed
- Updated all Student pages to use the prefixed VARCHAR IDs in the latest SQL (D, C, H, F, N, R, RW, T, VR, CH).
- Added `Return Requested` as the student choice when a repair is unsuccessful. The volunteer must set `Returned Unrepaired` after returning the item to Green Hub, before the student can mark it collected.
- Shared Student navigation, dashboard, page cards and responsive CSS matching the existing Admin design system.
- Added `includes/student_top.php`, `includes/student_bottom.php`. (The student styles were in `assets/css/student.css`; they are now section 14 at the end of `assets/css/style.css`.)
- Fixed Admin cashback integration: student redemption reserves one stock item; approval does not deduct again; rejection restores reserved stock. The Admin rejection still deletes the original redeem transaction as in the team's existing code.

## Installation
1. Back up your local project and database first.
2. Copy the updated project files over your working `C:\xampp\htdocs\RWDD-assignment` directory. **Do not overwrite or delete your `.git` folder**. The ZIP deliberately excludes `.git`.
3. Keep your existing `config/db.php` credentials if they differ from this copy.
4. Ensure phpMyAdmin has the latest prefixed-ID SQL imported.
5. Start Apache and MySQL; open `http://localhost/RWDD-assignment/student/index.php` after logging in as a student.
6. Test: submit device, open details, cancel a newly submitted device, request return for a Pending Consent device, mark notifications read, submit feedback and volunteer request, redeem cashback with a test account, and Admin approve/reject.
7. Check `git status` before staging, and coordinate the Admin cashback change with your teammate before pushing.

## Known limitations
- The team's shared `next_id()` uses MAX+1 and can collide on simultaneous inserts. A shared database sequence/locking strategy is recommended before production.
- PHP lint checks pass; no running XAMPP/MySQL integration test was available here. Test each role's workflows locally.
- Cashback rejection follows the existing team's practice of deleting the redeem transaction (rather than keeping a separate refund audit entry).
- Automatic challenge bonus awards are not added; the existing challenge page shows progress and history.
