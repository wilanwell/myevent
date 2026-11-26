<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;

/** Demo mode (APP_DEMO=true, switched on by tests/bootstrap.php) and the nightly reset. */
final class DemoTest extends DatabaseTestCase
{
    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myevent-demo-' . bin2hex(random_bytes(4));
        foreach ([POSTER_DIR, AVATAR_DIR] as $dir) {
            mkdir($this->uploads . '/' . $dir, 0777, true);
            file_put_contents($this->uploads . '/' . $dir . '/.gitkeep', '');
            file_put_contents($this->uploads . '/' . $dir . '/visitor_upload.jpg', 'x');
            file_put_contents($this->uploads . '/' . $dir . '/other_install.jpg', 'x');
        }
    }

    protected function tearDown(): void
    {
        foreach ([POSTER_DIR, AVATAR_DIR] as $dir) {
            array_map('unlink', array_filter(glob($this->uploads . '/' . $dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
            @rmdir($this->uploads . '/' . $dir);
        }
        @rmdir($this->uploads . '/uploads');
        @rmdir($this->uploads);
        parent::tearDown();
    }

    public function testDemoAccountsAreRecognisedByEmail(): void
    {
        $this->assertTrue(DEMO_MODE);
        foreach (DEMO_ACCOUNTS as $email) {
            $this->assertTrue(is_demo_account($email));
        }
        $this->assertTrue(is_demo_account('  Admin@MyEvent.test '));
        $this->assertFalse(is_demo_account('someone@example.test'));
        $this->assertFalse(is_demo_account(null));
    }

    public function testDemoUserIsLookedUpById(): void
    {
        $demo  = $this->makeUser('Demo', ['email' => DEMO_ACCOUNTS['participant']]);
        $other = $this->makeUser('Other');

        $this->assertTrue(is_demo_user($this->db, $demo));
        $this->assertFalse(is_demo_user($this->db, $other));
        $this->assertFalse(is_demo_user($this->db, null));
    }

    public function testResetReplacesEverythingWithTheDemoData(): void
    {
        $visitor = $this->makeUser('Visitor who signed up', ['email' => 'visitor@example.test', 'profile_pic' => 'visitor_upload.jpg']);
        $this->makeEvent($visitor, ['event_name' => 'Visitor event', 'poster_path' => 'visitor_upload.jpg']);
        $this->makeEvent($visitor, ['event_name' => 'Sneaky', 'poster_path' => '../profiles/.gitkeep']);

        $result = demo_reset($this->db, $this->uploads);

        $this->assertSame(0, $this->countRows("SELECT COUNT(*) FROM users WHERE email = 'visitor@example.test'"));
        $this->assertSame(0, (int) db_value($this->db, "SELECT COUNT(*) FROM events WHERE event_name = 'Visitor event'"));
        $this->assertSame(11, $result['events']);
        $this->assertSame(15, $result['users']);
        $this->assertSame($this->countRows('SELECT COUNT(*) FROM registrations'), $result['registrations']);
        $this->assertGreaterThan(20, $this->countRows('SELECT COUNT(*) FROM feedback'));
        $this->assertSame(2, $this->countRows('SELECT COUNT(*) FROM waitlist'));

        // Every demo button has an account to sign in to.
        foreach (DEMO_ACCOUNTS as $email) {
            $this->assertSame(1, (int) db_value($this->db, 'SELECT COUNT(*) FROM users WHERE email = ?', 's', [$email]));
        }
        $this->assertSame('Admin', db_value($this->db, 'SELECT role FROM users WHERE email = ?', 's', [DEMO_ACCOUNTS['admin']]));

        // The extra showcase participants cannot be signed in to.
        foreach (db_all($this->db, "SELECT password FROM users WHERE email NOT IN ('" . implode("','", DEMO_ACCOUNTS) . "')") as $row) {
            $this->assertFalse(password_verify('', $row['password']));
            $this->assertSame('!', $row['password']);
        }

        // The uploads this database used are deleted; files it never pointed at and the folder guards stay.
        $this->assertSame(2, $result['uploads']);
        foreach ([POSTER_DIR, AVATAR_DIR] as $dir) {
            $this->assertFileDoesNotExist($this->uploads . '/' . $dir . '/visitor_upload.jpg');
            $this->assertFileExists($this->uploads . '/' . $dir . '/.gitkeep');
            $this->assertFileExists($this->uploads . '/' . $dir . '/other_install.jpg');
        }
    }

    public function testResetIsRepeatable(): void
    {
        demo_reset($this->db, $this->uploads);
        $result = demo_reset($this->db, $this->uploads);

        $this->assertSame(11, $result['events']);
        $this->assertSame(0, $result['uploads']);
    }

    public function testDemoDataFillsTheReports(): void
    {
        demo_reset($this->db, $this->uploads);

        $summary = report_summary($this->db, report_filter([]));
        $this->assertGreaterThan(0, (int) $summary['attended']);
        $this->assertSame(25, array_sum(report_rating_distribution($this->db, report_filter([]))));
    }
}
