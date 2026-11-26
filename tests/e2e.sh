#!/usr/bin/env bash
# =====================================================================
# MyEvent - end-to-end security & workflow checks (HTTP level).
#
# Usage (Git Bash on Windows, or any bash):
#   bash tests/e2e.sh
#
# Settings (override with environment variables):
#   BASE   application URL               default http://localhost/myevent
#   MYSQL  mysql client                  default /c/xampp/mysql/bin/mysql
#   DB     database name                 default myevent
#   MYSQL_OPTS  client options           default -uroot   (CI: "-h127.0.0.1 -uroot -proot")
#   PHP    php binary for tools/         default /c/xampp/php/php.exe
#
# Requires MAIL_DRIVER=log in .env (the reset test reads storage/mail).
# Creates throwaway accounts qa_*@example.test and removes them at the end.
# =====================================================================
set -u

BASE=${BASE:-http://localhost/myevent}
MYSQL=${MYSQL:-/c/xampp/mysql/bin/mysql}
DB=${DB:-myevent}
ROOT=$(cd "$(dirname "$0")/.." && pwd)
WORK=$(mktemp -d)
PW='Qa-Test-Pass1'
PASSED=0
FAILED=0

sql()  { "$MYSQL" ${MYSQL_OPTS:--uroot} "$DB" -N -e "$1" | tr -d '\r'; }   # strip CR from Windows mysql output
pass() { echo "PASS  $1"; PASSED=$((PASSED + 1)); }
fail() { echo "FAIL  $1"; FAILED=$((FAILED + 1)); }
check(){ if eval "$2"; then pass "$1"; else fail "$1"; fi; }
jar()  { echo "$WORK/jar_$1.txt"; }
tok()  { curl -s -b "$(jar "$1")" -c "$(jar "$1")" "$BASE/$2" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

cleanup() {
    for p in $(sql "SELECT poster_path FROM events e JOIN users u ON u.user_id = e.created_by WHERE u.email LIKE 'qa\\_%@example.test' AND poster_path IS NOT NULL"); do
        rm -f "$ROOT/uploads/events/${p:?}"
    done
    sql "DELETE n FROM notifications n JOIN users u ON n.user_id = u.user_id WHERE u.email LIKE 'qa\\_%@example.test';
         DELETE FROM events WHERE created_by IN (SELECT user_id FROM users WHERE email LIKE 'qa\\_%@example.test');
         DELETE FROM users WHERE email LIKE 'qa\\_%@example.test';
         DELETE FROM login_attempts WHERE email LIKE 'qa\\_%' OR email = 'x@x.test';
         DELETE FROM audit_log WHERE actor_name LIKE 'QA %' OR details LIKE '%QA %' OR details LIKE '%qa\_%@example.test%';
         DELETE FROM mail_queue WHERE to_email LIKE 'qa\\_%@example.test';"
    # Development mailbox copies addressed to test accounts
    grep -l '^To: qa_[a-z]*@example.test' "$ROOT"/storage/mail/*.txt 2>/dev/null | while read -r f; do
        rm -f "${f%.txt}" "$f" "${f%.txt}.html"
    done
    rm -rf "${WORK:?}"
}
trap cleanup EXIT
cleanup >/dev/null 2>&1; WORK=$(mktemp -d)
PHP=${PHP:-/c/xampp/php/php.exe}
# Count development emails to $1 whose subject starts with $2.
mails() { grep -l "^To: $1" "$ROOT"/storage/mail/*.txt 2>/dev/null | xargs -r grep -l "^Subject: $2" 2>/dev/null | wc -l; }

register() { local t; t=$(tok "$1" auth/register.php)
    curl -s -b "$(jar "$1")" -c "$(jar "$1")" -o /dev/null -w '%{redirect_url}' --data-urlencode "csrf_token=$t" --data-urlencode "name=$2" \
         -d "category=Student&email=$3&phone=0123456789&organization=QA&password=$4&event[]=workshop" "$BASE/auth/register_action.php"; }
login() { local t; rm -f "$(jar "$1")"; t=$(tok "$1" auth/login.php)
    curl -s -b "$(jar "$1")" -c "$(jar "$1")" -o /dev/null -w '%{redirect_url}' --data-urlencode "csrf_token=$t" -d "email=$2&password=$3" "$BASE/auth/login_action.php"; }

echo "== Accounts & CSRF"
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -d "email=x@x.test&password=y" "$BASE/auth/login_action.php")
check "POST without CSRF token is rejected ($code)" '[ "$code" = 403 ]'
r=$(register a "QA Organiser" qa_a@example.test "$PW"); check "register organiser" '[[ "$r" == *auth/login.php ]]'
r=$(register b '<script>alert(1)</script>Bob' qa_b@example.test "$PW"); check "register participant with HTML in name" '[[ "$r" == *auth/login.php ]]'
r=$(register c "QA Weak" qa_weak@example.test short); check "weak password rejected" '[[ "$r" == *register.php ]]'
r=$(login a qa_a@example.test "$PW"); check "organiser logs in" '[[ "$r" == *index.php ]]'
r=$(login b qa_b@example.test "$PW"); check "participant logs in" '[[ "$r" == *index.php ]]'

echo "== Uploads"
echo '<?php system("whoami"); ?>' > "$WORK/evil.png"
create_event() { local t; t=$(tok a users/create_event.php)
    curl -s -b "$(jar a)" -c "$(jar a)" -o /dev/null -w '%{http_code} %{redirect_url}' -F "csrf_token=$t" -F "event_name=$1" -F "description=d" \
         -F "category_id=1" -F "contact_no=0123456789" -F "venue=Hall" -F "event_date=2030-01-01" -F "event_time=10:00" -F "mode=Physical" \
         -F "reg_close_date=2029-12-31" -F "max_participants=1" -F "fee=0" -F "remarks=r" -F "visibility=Public" -F "poster=@$2" "$BASE/users/create_event.php"; }
r=$(create_event "QA Evil Event" "$WORK/evil.png"); check "PHP disguised as PNG is rejected" '[[ "$r" == 200* ]]'
if ls "$ROOT/uploads/events" | grep -q '\.php'; then fail "no PHP file in uploads"; else pass "no PHP file in uploads"; fi
r=$(create_event "QA Real Event" "$ROOT/assets/img/logo.png"); check "event with real PNG is created" '[[ "$r" == *my_events.php ]]'
EV=$(sql "SELECT event_id FROM events WHERE event_name = 'QA Real Event' ORDER BY event_id DESC LIMIT 1")
POSTER=$(sql "SELECT poster_path FROM events WHERE event_id = $EV")
check "poster stored with a random name" '[[ "$POSTER" =~ ^event_[0-9a-f]{24}\.png$ ]]'
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/uploads/events/x.php"); check "scripts in uploads are not executable ($code)" '[ "$code" = 403 ] || [ "$code" = 404 ]'

echo "== Registration rules"
reg_event() { local t; t=$(tok "$1" "event_detail.php?id=$EV")
    curl -s -b "$(jar "$1")" -c "$(jar "$1")" -o /dev/null -w '%{redirect_url}' -d "csrf_token=$t&event_id=$EV" "$BASE/users/register_event.php"; }
r=$(reg_event b); check "participant registers" '[[ "$r" == *my_registrations.php ]]'
reg_event b >/dev/null; n=$(sql "SELECT COUNT(*) FROM registrations WHERE event_id = $EV"); check "duplicate registration blocked" '[ "$n" = 1 ]'
reg_event a >/dev/null; n=$(sql "SELECT COUNT(*) FROM registrations WHERE event_id = $EV"); check "organiser cannot register for own event" '[ "$n" = 1 ]'

echo "== Output escaping"
page=$(curl -s -b "$(jar a)" "$BASE/users/notifications.php")
if echo "$page" | grep -q '<script>alert(1)</script>'; then fail "notification HTML is escaped"; else pass "notification HTML is escaped"; fi

echo "== Access control"
r=$(curl -s -b "$(jar b)" -o /dev/null -w '%{http_code}' "$BASE/users/manage_participants.php?event_id=$EV"); check "participant cannot open organiser's list ($r)" '[ "$r" = 302 ]'
REG=$(sql "SELECT reg_id FROM registrations WHERE event_id = $EV")
t=$(tok b users/my_registrations.php)
curl -s -b "$(jar b)" -o /dev/null -d "csrf_token=$t&action=approve&reg_id=$REG" "$BASE/users/manage_participants.php?event_id=$EV"
st=$(sql "SELECT approval_status FROM registrations WHERE reg_id = $REG"); check "participant cannot approve themselves" '[ "$st" = Pending ]'
t=$(tok a "users/manage_participants.php?event_id=$EV")
curl -s -b "$(jar a)" -o /dev/null -d "csrf_token=$t&action=approve&reg_id=$REG" "$BASE/users/manage_participants.php?event_id=$EV"
st=$(sql "SELECT approval_status FROM registrations WHERE reg_id = $REG"); check "organiser approves" '[ "$st" = Approved ]'
n=$(sql "SELECT COUNT(*) FROM audit_log WHERE action = 'registration.approve' AND target_id = $REG"); check "approval is in the audit log" '[ "$n" = 1 ]'
r=$(curl -s -b "$(jar b)" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/users/delete.php"); check "non-admin cannot use admin actions ($r)" '[ "$r" = 403 ]'
r=$(curl -s -b "$(jar b)" -o /dev/null -w '%{http_code}' "$BASE/admin/audit.php"); check "non-admin cannot read the audit log ($r)" '[ "$r" = 403 ]'

echo "== Ticket QR check-in"
ticket=$(curl -s -b "$(jar b)" "$BASE/users/generate_ticket.php?event_id=$EV")
CODE=$(echo "$ticket" | grep -o 'ME-[0-9]*-[0-9]*-[0-9A-F]\{6\}' | head -1)
check "ticket shows a signed code ($CODE)" '[[ "$CODE" =~ ^ME-[0-9]{4}-[0-9]{5}-[0-9A-F]{6}$ ]]'
echo "$ticket" | grep -q 'data-qr="[^"]*checkin.php?code=' && pass "ticket embeds a check-in QR link" || fail "ticket embeds a check-in QR link"
BAD="${CODE%??????}000000"
curl -s -b "$(jar a)" "$BASE/users/checkin.php?code=$BAD" | grep -q 'not valid' && pass "tampered code is rejected" || fail "tampered code is rejected"
curl -s -b "$(jar b)" "$BASE/users/checkin.php?code=$CODE" | grep -q 'Only the organiser' && pass "participant cannot check themselves in" || fail "participant cannot check themselves in"
page=$(curl -s -b "$(jar a)" -c "$(jar a)" "$BASE/users/checkin.php?code=$CODE")
st=$(sql "SELECT attendance_status FROM registrations WHERE reg_id = $REG"); check "looking up a ticket does not check in (GET is read-only)" '[ "$st" = Absent ]'
t=$(echo "$page" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b "$(jar a)" -o /dev/null --data-urlencode "csrf_token=$t" --data-urlencode "code=$CODE" "$BASE/users/checkin.php"
st=$(sql "SELECT CONCAT(attendance_status, IF(checked_in_at IS NULL, '-no-time', '-timed')) FROM registrations WHERE reg_id = $REG")
check "organiser checks the participant in ($st)" '[ "$st" = Present-timed ]'

echo "== Waitlist (event has 1 place, taken by the participant)"
r=$(register c "QA Waiter" qa_c@example.test "$PW"); r=$(login c qa_c@example.test "$PW")
t=$(tok c "event_detail.php?id=$EV")
curl -s -b "$(jar c)" -c "$(jar c)" "$BASE/event_detail.php?id=$EV" | grep -q 'value="join_waitlist"' && pass "full event offers the waitlist" || fail "full event offers the waitlist"
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null -d "csrf_token=$t&event_id=$EV&action=join_waitlist" "$BASE/users/register_event.php"
n=$(sql "SELECT COUNT(*) FROM waitlist w JOIN users u ON u.user_id = w.user_id WHERE w.event_id = $EV AND u.email = 'qa_c@example.test'")
check "second person joins the waitlist" '[ "$n" = 1 ]'
curl -s -b "$(jar c)" -c "$(jar c)" "$BASE/event_detail.php?id=$EV" | grep -q 'number 1 on the waitlist' && pass "waitlist position is shown" || fail "waitlist position is shown"
t=$(tok c "event_detail.php?id=$EV")
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null -d "csrf_token=$t&event_id=$EV" "$BASE/users/register_event.php"
n=$(sql "SELECT COUNT(*) FROM registrations r JOIN users u ON u.user_id = r.user_id WHERE r.event_id = $EV AND u.email = 'qa_c@example.test'")
check "registering for a full event does not overbook" '[ "$n" = 0 ]'
t=$(tok b users/my_registrations.php)
curl -s -b "$(jar b)" -c "$(jar b)" -o /dev/null -d "csrf_token=$t&event_id=$EV&cancel_registration=1" "$BASE/users/my_registrations.php"
st=$(sql "SELECT r.approval_status FROM registrations r JOIN users u ON u.user_id = r.user_id WHERE r.event_id = $EV AND u.email = 'qa_c@example.test'")
check "cancellation promotes the first person in line ($st)" '[ "$st" = Pending ]'
n=$(sql "SELECT COUNT(*) FROM waitlist WHERE event_id = $EV"); check "promoted person leaves the waitlist" '[ "$n" = 0 ]'
n=$(sql "SELECT COUNT(*) FROM notifications n JOIN users u ON u.user_id = n.user_id WHERE u.email = 'qa_c@example.test' AND n.message LIKE '%place opened up%'")
check "promoted person is notified" '[ "$n" = 1 ]'
n=$(sql "SELECT COUNT(*) FROM registrations WHERE event_id = $EV AND approval_status <> 'Rejected'"); check "capacity still respected after promotion" '[ "$n" = 1 ]'

echo "== Feedback"
CREG=$(sql "SELECT r.reg_id FROM registrations r JOIN users u ON u.user_id = r.user_id WHERE r.event_id = $EV AND u.email = 'qa_c@example.test'")
r=$(curl -s -b "$(jar c)" -o /dev/null -w '%{redirect_url}' "$BASE/users/feedback.php?event_id=$EV"); check "cannot rate before approval" '[[ "$r" == *my_registrations.php* ]]'
sql "UPDATE registrations SET approval_status = 'Approved' WHERE reg_id = $CREG"
r=$(curl -s -b "$(jar c)" -o /dev/null -w '%{redirect_url}' "$BASE/users/feedback.php?event_id=$EV"); check "cannot rate before the event starts" '[[ "$r" == *my_registrations.php* ]]'
sql "UPDATE events SET event_date = CURDATE() - INTERVAL 1 DAY, reg_close_date = CURDATE() - INTERVAL 2 DAY WHERE event_id = $EV"
page=$(curl -s -b "$(jar c)" -c "$(jar c)" "$BASE/users/feedback.php?event_id=$EV"); t=$(echo "$page" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null --data-urlencode "csrf_token=$t" -d "event_id=$EV&rating=9" "$BASE/users/feedback.php"
n=$(sql "SELECT COUNT(*) FROM feedback WHERE event_id = $EV"); check "out-of-range rating rejected" '[ "$n" = 0 ]'
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null --data-urlencode "csrf_token=$t" --data-urlencode "comment=<b>Great</b> session" -d "event_id=$EV&rating=4" "$BASE/users/feedback.php"
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null --data-urlencode "csrf_token=$t" -d "event_id=$EV&rating=5" "$BASE/users/feedback.php"
st=$(sql "SELECT CONCAT(COUNT(*), ':', MAX(rating)) FROM feedback WHERE event_id = $EV"); check "one feedback per person, editable ($st)" '[ "$st" = 1:5 ]'
curl -s "$BASE/event_detail.php?id=$EV" | grep -q 'from 1 rating' && pass "average rating shown publicly" || fail "average rating shown publicly"
curl -s "$BASE/event_detail.php?id=$EV" | grep -q 'session' && fail "comments stay private" || pass "comments stay private"
page=$(curl -s -b "$(jar a)" "$BASE/users/manage_participants.php?event_id=$EV")
echo "$page" | grep -q 'Feedback' && pass "organiser sees feedback" || fail "organiser sees feedback"
r=$(curl -s -b "$(jar a)" -o /dev/null -w '%{redirect_url}' "$BASE/users/feedback.php?event_id=$EV"); check "non-participant cannot rate" '[[ "$r" == *my_registrations.php* ]]'

echo "== Calendar export"
hdr=$(curl -s -D - -o "$WORK/e.ics" "$BASE/event_calendar.php?id=$EV")
echo "$hdr" | grep -qi 'content-type: text/calendar' && pass ".ics served as text/calendar" || fail ".ics served as text/calendar"
grep -q 'BEGIN:VEVENT' "$WORK/e.ics" && grep -q "UID:event-$EV@" "$WORK/e.ics" && pass ".ics contains the event" || fail ".ics contains the event"
ICS_PATH="$WORK/e.ics"; command -v cygpath >/dev/null && ICS_PATH=$(cygpath -w "$ICS_PATH")
crlf=$(python -c "import sys; d=open(sys.argv[1],'rb').read(); print(int(d.count(b'\n') == d.count(b'\r\n') and max(len(l) for l in d.split(b'\r\n')) <= 75))" "$ICS_PATH" 2>/dev/null || echo error)
check ".ics uses CRLF and folds long lines" '[ "$crlf" = 1 ]'

echo "== Reports"
page=$(curl -s -b "$(jar a)" "$BASE/users/reports.php?range=all")
echo "$page" | grep -q 'QA Real Event' && echo "$page" | grep -q 'class="viz viz-columns"' && pass "organiser report lists their event with a chart" || fail "organiser report lists their event with a chart"
curl -s -b "$(jar b)" "$BASE/users/reports.php?range=all" | grep -q 'QA Real Event' && fail "reports only show your own events" || pass "reports only show your own events"
hdr=$(curl -s -b "$(jar a)" -D - -o "$WORK/r.csv" "$BASE/users/reports.php?range=all&export=csv")
echo "$hdr" | grep -qi 'content-type: text/csv' && grep -q 'QA Real Event' "$WORK/r.csv" && pass "report CSV export" || fail "report CSV export"
r=$(curl -s -b "$(jar b)" -o /dev/null -w '%{http_code}' "$BASE/admin/reports.php"); check "non-admin cannot open admin reports ($r)" '[ "$r" = 403 ]'
r=$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE/users/reports.php"); check "reports need a login" '[[ "$r" == *auth/login.php ]]'
r=$(curl -s -b "$(jar a)" -o /dev/null -w '%{http_code}' "$BASE/users/reports.php?range=custom&from=2026-01-01%27%20OR%201%3D1&to=x"); check "bad report dates are ignored ($r)" '[ "$r" = 200 ]'

echo "== Language"
curl -s -H 'Accept-Language: ms-MY,ms;q=0.9' "$BASE/index.php" | grep -q '<html lang="ms"' && pass "browser language picks Malay for visitors" || fail "browser language picks Malay for visitors"
curl -s "$BASE/index.php" | grep -q '<html lang="en"' && pass "English by default" || fail "English by default"
t=$(tok g index.php)
r=$(curl -s -b "$(jar g)" -c "$(jar g)" -o /dev/null -w '%{http_code} %{redirect_url}' -d "csrf_token=$t&locale=ms&return=%2Fmyevent%2Fbrowse_events.php" "$BASE/lang.php")
check "language switch returns to the same page ($r)" '[[ "$r" == "303 "*browse_events.php ]]'
grep -q myevent_lang "$(jar g)" && pass "language remembered in a cookie" || fail "language remembered in a cookie"
page=$(curl -s -b "$(jar g)" "$BASE/browse_events.php")
echo "$page" | grep -q 'Cari acara' && echo "$page" | grep -q '<html lang="ms"' && pass "pages render in Malay after switching" || fail "pages render in Malay after switching"
t=$(tok g index.php)
r=$(curl -s -b "$(jar g)" -c "$(jar g)" -o /dev/null -w '%{redirect_url}' -d "csrf_token=$t&locale=ms&return=%2F%2Fevil.example%2Fx" "$BASE/lang.php")
check "language switch refuses outside return addresses ($r)" '[[ "$r" != *evil.example* ]]'
r=$(curl -s -b "$(jar g)" -o /dev/null -w '%{http_code}' -d "locale=ms" "$BASE/lang.php"); check "language switch needs a CSRF token ($r)" '[ "$r" = 403 ]'
t=$(tok b users/dashboard.php)
curl -s -b "$(jar b)" -c "$(jar b)" -o /dev/null -d "csrf_token=$t&locale=ms&return=%2Fmyevent%2Fusers%2Fdashboard.php" "$BASE/lang.php"
st=$(sql "SELECT locale FROM users WHERE email = 'qa_b@example.test'"); check "signed-in choice is saved on the account ($st)" '[ "$st" = ms ]'
curl -s -b "$(jar b)" "$BASE/users/dashboard.php" | grep -q 'Papan pemuka' && pass "dashboard in Malay" || fail "dashboard in Malay"
t=$(tok b users/dashboard.php)
curl -s -b "$(jar b)" -c "$(jar b)" -o /dev/null -d "csrf_token=$t&locale=en&return=%2Fmyevent%2Fusers%2Fdashboard.php" "$BASE/lang.php"

echo "== Email notifications"
n=$(mails qa_a@example.test "New registration"); check "organiser emailed about a new registration ($n)" '[ "$n" -ge 1 ]'
n=$(mails qa_b@example.test "You're in"); check "participant emailed when approved ($n)" '[ "$n" -ge 1 ]'
f=$(grep -l "^To: qa_b@example.test" "$ROOT"/storage/mail/*.txt | xargs grep -l "^Subject: You're in" | head -1)
grep -q 'View my ticket' "${f%.txt}.html" 2>/dev/null && pass "approval email has an HTML version with the ticket button" || fail "approval email has an HTML version with the ticket button"
grep -q 'Content-Type: multipart/alternative' "${f%.txt}" 2>/dev/null && pass "email is multipart (text + HTML)" || fail "email is multipart (text + HTML)"
n=$(mails qa_c@example.test "A place opened up"); check "promoted person emailed ($n)" '[ "$n" -ge 1 ]'
n=$(mails qa_a@example.test "Waitlist:"); check "organiser emailed about the waitlist move ($n)" '[ "$n" -ge 1 ]'

t=$(tok c users/profile.php)
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null -d "csrf_token=$t&phone=0123456789&email_settings=1&email_organiser=1" "$BASE/users/profile_action.php"
st=$(sql "SELECT CONCAT(email_participant, email_organiser) FROM users WHERE email = 'qa_c@example.test'"); check "participant can switch off registration emails ($st)" '[ "$st" = 01 ]'
sql "UPDATE events SET event_date = DATE(NOW() + INTERVAL 2 HOUR), event_time = TIME(NOW() + INTERVAL 2 HOUR), reg_close_date = DATE(NOW() + INTERVAL 2 HOUR) WHERE event_id = $EV"
"$PHP" "$ROOT/tools/send_mail.php" >/dev/null 2>&1; "$PHP" "$ROOT/tools/send_mail.php" >/dev/null 2>&1
n=$(sql "SELECT COUNT(*) FROM notifications n JOIN users u ON u.user_id = n.user_id WHERE u.email = 'qa_c@example.test' AND n.message LIKE '%Reminder:%'")
check "worker sends one in-app reminder per registration ($n)" '[ "$n" = 1 ]'
n=$(mails qa_c@example.test "Reminder:"); check "no reminder email when switched off ($n)" '[ "$n" = 0 ]'

t=$(tok c users/profile.php)
curl -s -b "$(jar c)" -c "$(jar c)" -o /dev/null -d "csrf_token=$t&phone=0123456789&email_settings=1&email_participant=1&email_organiser=1" "$BASE/users/profile_action.php"
t=$(tok a users/my_events.php)
curl -s -b "$(jar a)" -c "$(jar a)" -o /dev/null -d "csrf_token=$t&event_id=$EV&delete_event=1" "$BASE/users/my_events.php"
n=$(sql "SELECT COUNT(*) FROM events WHERE event_id = $EV"); check "organiser deletes the event" '[ "$n" = 0 ]'
n=$(mails qa_c@example.test "Cancelled:"); check "registered person emailed about the cancellation ($n)" '[ "$n" -ge 1 ]'

sql "INSERT INTO mail_queue (to_email, subject, body_text) VALUES ('qa_queue@example.test', 'Queue test', 'hello'), ('not-an-address', 'Queue failure test', 'x')"
"$PHP" "$ROOT/tools/send_mail.php" >/dev/null 2>&1
st=$(sql "SELECT status FROM mail_queue WHERE to_email = 'qa_queue@example.test'"); check "queued email delivered by the worker ($st)" '[ "$st" = sent ]'
st=$(sql "SELECT CONCAT(status, ':', attempts, ':', IF(last_error IS NULL, 'no-error', 'error')) FROM mail_queue WHERE to_email = 'not-an-address'")
check "failed email kept for retry with the error recorded ($st)" '[ "$st" = pending:1:error ]'
sql "DELETE FROM mail_queue WHERE to_email = 'not-an-address'"

echo "== Profile & password"
t=$(tok b users/profile.php)
curl -s -b "$(jar b)" -o /dev/null -d "csrf_token=$t&phone=0123456789&bio=x&new_password=NewPass123&current_password=wrong" "$BASE/users/profile_action.php"
r=$(login g qa_b@example.test NewPass123); check "password unchanged without the current password" '[[ "$r" == *login.php ]]'

echo "== Password reset"
before=$(ls "$ROOT/storage/mail" 2>/dev/null | wc -l)
t=$(tok r auth/forgot_password.php)
curl -s -b "$(jar r)" -c "$(jar r)" -o /dev/null -d "csrf_token=$t&email=qa_nobody@example.test" "$BASE/auth/forgot_password.php"
after=$(ls "$ROOT/storage/mail" | wc -l); check "unknown email sends nothing" '[ "$before" = "$after" ]'
curl -s -b "$(jar r)" "$BASE/auth/login.php" | grep -q 'If an account exists' && pass "same message for unknown email (no account discovery)" || fail "same message for unknown email"
t=$(tok r auth/forgot_password.php)
curl -s -b "$(jar r)" -c "$(jar r)" -o /dev/null -d "csrf_token=$t&email=qa_b@example.test" "$BASE/auth/forgot_password.php"
MAIL=$(ls -t "$ROOT"/storage/mail/*.txt | head -1)
LINK=$(grep -o "$BASE/auth/reset_password.php?token=[0-9a-f]*" "$MAIL" | head -1)
check "reset email is written with a link" '[ -n "$LINK" ]'
n=$(sql "SELECT COUNT(*) FROM password_resets pr JOIN users u ON u.user_id = pr.user_id WHERE u.email = 'qa_b@example.test' AND token_hash = SHA2('${LINK##*=}', 256)")
check "only the token hash is stored" '[ "$n" = 1 ]'
page=$(curl -s -b "$(jar r)" -c "$(jar r)" "$LINK"); t=$(echo "$page" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b "$(jar r)" -c "$(jar r)" -o /dev/null --data-urlencode "csrf_token=$t" -d "token=${LINK##*=}&password=Reset-Pass-99&password_confirm=Reset-Pass-99" "$BASE/auth/reset_password.php"
r=$(login h qa_b@example.test Reset-Pass-99); check "new password works" '[[ "$r" == *index.php ]]'
r=$(curl -s -o /dev/null -w '%{redirect_url}' "$LINK"); check "reset link works only once" '[[ "$r" == *forgot_password.php ]]'

echo "== Sessions"
t=$(tok f auth/login.php); before=$(grep MYEVENTSESSID "$(jar f)" | awk '{print $7}')
curl -s -b "$(jar f)" -c "$(jar f)" -o /dev/null --data-urlencode "csrf_token=$t" -d "email=qa_a@example.test&password=$PW" "$BASE/auth/login_action.php"
after=$(grep MYEVENTSESSID "$(jar f)" | awk '{print $7}'); check "session id changes at login" '[ -n "$before" ] && [ "$before" != "$after" ]'
curl -s -b "$(jar f)" -c "$(jar f)" -o /dev/null "$BASE/auth/logout.php"
r=$(curl -s -b "$(jar f)" -o /dev/null -w '%{http_code}' "$BASE/users/dashboard.php"); check "GET logout does not log out ($r)" '[ "$r" = 200 ]'
t=$(tok f users/dashboard.php); curl -s -b "$(jar f)" -c "$(jar f)" -o /dev/null -d "csrf_token=$t" "$BASE/auth/logout.php"
r=$(curl -s -b "$(jar f)" -o /dev/null -w '%{http_code}' "$BASE/users/dashboard.php"); check "POST logout logs out ($r)" '[ "$r" = 302 ]'

echo "== Brute force"
for i in 1 2 3 4 5 6; do login d qa_a@example.test "wrong$i" >/dev/null; done
curl -s -b "$(jar d)" "$BASE/auth/login.php" | grep -q "Too many failed" && pass "login locked after 5 failures" || fail "login locked after 5 failures"
r=$(login e qa_a@example.test "$PW"); check "correct password also blocked while locked" '[[ "$r" == *login.php ]]'

echo
echo "Passed: $PASSED  Failed: $FAILED"
[ "$FAILED" -eq 0 ]
