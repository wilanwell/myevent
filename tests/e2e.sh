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

sql()  { "$MYSQL" -uroot "$DB" -N -e "$1" | tr -d '\r'; }   # strip CR from Windows mysql output
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
         DELETE FROM audit_log WHERE actor_name LIKE 'QA %' OR details LIKE '%QA %' OR details LIKE '%qa\_%@example.test%';"
    rm -rf "${WORK:?}"
}
trap cleanup EXIT
cleanup >/dev/null 2>&1; WORK=$(mktemp -d)

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
