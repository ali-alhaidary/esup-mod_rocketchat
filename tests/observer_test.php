<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod_rocketchat event observers test.
 * recycle bin tests included into observer_testcase
 * @package    local_digital_training_account_services
 * @copyright   2020 ESUP-Portail {@link https://www.esup-portail.org/}
 * @author Céline Pervès<cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__.'/../locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

require_once($CFG->dirroot.'/mod/rocketchat/vendor/autoload.php');
use \mod_rocketchat\api\manager\rocket_chat_api_manager;

class observer_testcase extends advanced_testcase{
    private $course;
    private $rocketchat;
    private $userstudent;
    private $usereditingteacher;

    protected function setUp() {
        global $CFG, $DB;
        parent::setUp();
        set_config('recyclebin_patch', 1, 'mod_rocketchat');
        // Enable rocketchat module.
        $modulerecord = $DB->get_record('modules', ['name' => 'rocketchat']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        require($CFG->dirroot.'/mod/rocketchat/config-test.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        // Disable recyclebin.
        set_config('coursebinenable', 0, 'tool_recyclebin');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $studentusername = 'moodleusertest'.time();
        $this->userstudent = $generator->create_user(array('username' => $studentusername,
            'firstname' => $studentusername, 'lastname' => $studentusername));
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->userstudent->id, $this->course->id, $student->id);
        $edititingteacherusername = 'moodleusertest'.(time() + 1);
        $this->usereditingteacher = $generator->create_user(array('username' => $edititingteacherusername,
            'firstname' => $edititingteacherusername, 'lastname' => $edititingteacherusername));
        $editingteacher = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($this->usereditingteacher->id, $this->course->id, $editingteacher->id);
        // Set a groupname for tests.
        set_config('groupnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_rocketchat');
        $groupname = mod_rocketchat_tools::rocketchat_group_name(0, $this->course);
        $this->rocketchat = $generator->create_module('rocketchat',
            array('course' => $this->course->id, 'groupname' => $groupname));
    }
    protected function tearDown() {
        ob_start();
        if (!empty($this->rocketchat)) {
            course_delete_module($this->rocketchat->cmid, true);
        }
        $rocketchatmanager = new rocket_chat_api_manager();
        $rocketchatmanager->delete_user($this->userstudent->username);
        $rocketchatmanager->delete_user($this->usereditingteacher->username);
        $rocketchatmanager->delete_rocketchat_group($this->rocketchat->rocketchatid);
        ob_get_contents();
        ob_end_clean();
        parent::tearDown();
    }

    public function test_user_delete() {
        // Structure created in setUp.
        $rocketchatmanager = new rocket_chat_api_manager();
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $members = $rocketchatgroup->members();
        $this->assertCount(3, $members);
        delete_user($this->userstudent);
        $rocketchatuser = $rocketchatmanager->get_rocketchat_user_object($this->userstudent->username);
        $this->assertNotEmpty($rocketchatuser);
        $this->assertNotEmpty($rocketchatuser->info());
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $members = $rocketchatgroup->members();
        $this->assertCount(2, $members);
    }

    public function test_module_delete() {
        // Structure created in setUp.
        $rocketchatmanager = new rocket_chat_api_manager();
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $members = $rocketchatgroup->members();
        $this->assertCount(3, $members);
        course_delete_module($this->rocketchat->cmid);
        $rocketchatuser = $rocketchatmanager->get_rocketchat_user_object($this->userstudent->username);
        $this->assertNotEmpty($rocketchatuser);
        $this->assertNotEmpty($rocketchatuser->info());
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $this->assertNotEmpty($rocketchatgroup);
        $this->assertEmpty($rocketchatgroup->info());

    }

    public function test_module_visibility() {
        // Structure created in setUp.
        $rocketchatmanager = new rocket_chat_api_manager();
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $groupinfo = $rocketchatgroup->info()->group;
        list($course, $cm) = get_course_and_cm_from_cmid($this->rocketchat->cmid);
        $this->assertFalse(property_exists($groupinfo, 'archived'));
        set_coursemodule_visible($this->rocketchat->cmid, 0, 1);
        // Need to trigger event manually.
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $groupinfo = $rocketchatgroup->info()->group;
        $this->assertTrue($groupinfo->archived);
        set_coursemodule_visible($this->rocketchat->cmid, 1, 1);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $groupinfo = $rocketchatgroup->info()->group;
        $this->assertFalse($groupinfo->archived);
        set_coursemodule_visible($this->rocketchat->cmid, 0, 0);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $groupinfo = $rocketchatgroup->info()->group;
        $this->assertTrue($groupinfo->archived);
        set_coursemodule_visible($this->rocketchat->cmid, 1, 1);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        set_coursemodule_visible($this->rocketchat->cmid, 1, 0);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $groupinfo = $rocketchatgroup->info()->group;
        $this->assertTrue($groupinfo->archived);
    }

    public function test_user_role_changes() {
        global $DB;
        $rocketchatmanager = new rocket_chat_api_manager();
        $rocketchatgroup = $rocketchatmanager->get_rocketchat_group_object($this->rocketchat->rocketchatid);
        $members = $rocketchatgroup->members();
        $this->assertCount(3, $members);
        $moderators = $rocketchatgroup->moderators();
        $this->assertCount(1, $moderators);
        $moderator = $moderators[0];
        $this->assertEquals($this->usereditingteacher->username, $moderator->username);
        // Change role for usereditingteacher.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($this->course->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance = $courseenrolinstance;
                break;
            }
        }
        $enrol->unenrol_user($instance, $this->usereditingteacher->id);
        // Enrol as student.
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($this->usereditingteacher->id, $this->course->id, $student->id);
        $moderators = $rocketchatgroup->moderators();
        $members = $rocketchatgroup->members();
        $this->assertCount(3, $members);
        $this->assertCount(0, $moderators);
        $enrol->unenrol_user($instance, $this->usereditingteacher->id);
        $editingteacher = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->usereditingteacher->id, $this->course->id, $editingteacher->id);
        $moderators = $rocketchatgroup->moderators();
        $members = $rocketchatgroup->members();
        $this->assertCount(3, $members);
        $this->assertCount(1, $moderators);
        $enrol->unenrol_user($instance, $this->usereditingteacher->id);
    }
}