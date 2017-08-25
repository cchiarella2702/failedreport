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
 * Definition of the grade_failed_report class
 *
 * @package gradereport_overview
 * @copyright 2007 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->libdir.'/tablelib.php');

/**
 * Class providing an API for the overview report building and displaying.
 * @uses grade_report
 * @package gradereport_overview
 */
class grade_report_failed extends grade_report {

    /**
     * The user.
     * @var object $user
     */
    public $users;

    /**
     * The user's courses
     * @var array $courses
     */
    public $courses;

    /**
     * A flexitable to hold the data.
     * @var object $table
     */
    public $table;

    /**
     * An array of course ids that the user is a student in.
     * @var array $studentcourseids
     */
    public $courseid;


    /**
     * Constructor. Sets local copies of user preferences and initialises grade_tree.
     * @param int $userid
     * @param object $gpr grade plugin return tracking object
     * @param string $context
     */
    public function __construct($courseid, $gpr, $context) {
        global $CFG, $COURSE, $DB;
        parent::__construct($COURSE->id, $gpr, $context);

        $this->courses = $this->load_failed_grades();

        $this->studentcourseids = array();
        $this->teachercourses = array();

		$this->courseid = $courseid;
        // base url for sorting by first/last name
        $this->baseurl = $CFG->wwwroot.'/grade/failed/index.php?id='. $courseid;
        $this->pbarurl = $this->baseurl;

        $this->setup_table();
    }

    /**
     * Prepares the headers and attributes of the flexitable.
     */
    public function setup_table() {
        /*
         * Table has 3 columns
         *| course  | final grade | rank (optional) |
         */

        // setting up table headers
        $tablecolumns = array('studentname', 'activityname', 'failedgrade', 'gradetopass');
        $tableheaders = array($this->get_lang_string('studentname', 'gradereport_failed'),
        $this->get_lang_string('activityname','gradereport_failed'),
        $this->get_lang_string('finalgrade',  'gradereport_failed'),
		$this->get_lang_string('gradetopass',  'gradereport_failed'));
		
        $this->table = new flexible_table('grade-report-failed-10');

        $this->table->define_columns($tablecolumns);
        $this->table->define_headers($tableheaders);
        $this->table->define_baseurl($this->baseurl);

        $this->table->set_attribute('cellspacing', '0');
        $this->table->set_attribute('id', 'failed-grade');
        $this->table->set_attribute('class', 'boxaligncenter generaltable');

        $this->table->setup();
    }

    

    /**
     * Fill the table for displaying.
     *
     * @param bool $activitylink If this report link to the activity report or the user report.
     * @param bool $studentcoursesonly Only show courses that the user is a student of.
     */
    public function fill_table($activitylink = false, $studentcoursesonly = false) {
        global $CFG, $DB, $OUTPUT, $USER;

        // Only show user's courses instead of all courses.
        if ($this->courses) {
			// Get course grade_item.
			$courseitem = grade_item::fetch_course_item($this->courseid);
			$wuserid = '';
            //    $data = array($courselink, grade_format_gradevalue($finalgrade, $courseitem, true));
			foreach ($this->courses as $course) {
				if ($wuserid <> $course->userid) {
					$studentname = $course->firstname . " " . $course->lastname;
					$activityname = $course->itemname;
					$failedgrade = grade_format_gradevalue($course->finalgrade,$courseitem, true);
					$gradetopass = grade_format_gradevalue($course->gradepass, $courseitem, true);
					$data = array($studentname, $activityname, $failedgrade, $gradetopass);
					$this->table->add_data($data);
					$wuserid = $course->userid;
				}
            }

            return true;

        } else {
			//print_error('nothingtoshow','gradereport_failed');
			             // nothingtoshow
			//print_error('nomodifyacl','mnet');
			echo "<p>" . get_string('nothingtoshow', 'gradereport_failed') . "</p>";
		}
    }

    /**
     * Prints or returns the HTML from the flexitable.
     * @param bool $return Whether or not to return the data instead of printing it directly.
     * @return string
     */
    public function print_table($return=false) {
        ob_start();
        $this->table->print_html();
        $html = ob_get_clean();
        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    /**
     * Processes the data sent by the form (grades and feedbacks).
     * @param array $data
     * @return bool Success or Failure (array of errors).
     */
    function process_data($data) {
    }
    function process_action($target, $action) {
    }

    /**
     * This report supports being set as the 'grades' report.
     */
    public static function supports_mygrades() {
        return true;
    }

    /**
     * Check if the user can access the report.
     *
     * @param  stdClass $systemcontext   system context
     * @param  stdClass $context         course context
     * @param  stdClass $personalcontext personal context
     * @param  stdClass $course          course object
     * @param  int $userid               userid
     * @return bool true if the user can access the report
     * @since  Moodle 3.2
     */
    public static function check_access($systemcontext, $context, $personalcontext, $course, $userid) {
        global $USER;

        $access = false;
        if (has_capability('moodle/grade:viewall', $systemcontext)) {
            // Ok - can view all course grades.
            $access = true;

        } else if (has_capability('moodle/grade:viewall', $context)) {
            // Ok - can view any grades in context.
            $access = true;

        } else if ($userid == $USER->id and ((has_capability('moodle/grade:view', $context) and $course->showgrades)
                || $course->id == SITEID)) {
            // Ok - can view own course grades.
            $access = true;

        } else if (has_capability('moodle/grade:viewall', $personalcontext) and $course->showgrades) {
            // Ok - can view grades of this user - parent most probably.
            $access = true;
        } else if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext) and $course->showgrades) {
            // Ok - can view grades of this user - parent most probably.
            $access = true;
        }
		//echo 'Cristal ' . $access; die;
        return $access;
    }

	private function load_failed_grades() {
		global $CFG, $DB;

        /* if (!empty($this->grades)) {
            return;
        }

        if (empty($this->users)) {
            return;
        } */

        // please note that we must fetch all grade_grades fields if we want to construct grade_grade object from it!
        $params = array_merge(array('courseid'=>$this->courseid));
        $sql = "SELECT g.*, gi.gradepass, gi.itemname, u.firstname, u.lastname, u.id as iduser 
                FROM {grade_items} gi
				INNER JOIN {grade_grades} g
				ON g.itemid = gi.id
				INNER JOIN {user} u
				ON g.userid = u.id
                WHERE gi.courseid = :courseid 
				AND gi.itemtype <> 'course' AND gi.gradepass > g.finalgrade 
				ORDER BY iduser;";
		
		if ($grades = $DB->get_records_sql($sql, $params)) {
			return $grades;
		} else {
			return false;
		}
	}
    /**
     * Trigger the grade_report_viewed event
     *
     * @param  stdClass $context  course context
     * @param  int $courseid      course id
     * @param  int $userid        user id
     * @since Moodle 3.2
     */
    public static function viewed($context, $courseid, $userid) {
        $event = \gradereport_failed\event\grade_report_viewed::create(
            array(
                'context' => $context,
                'courseid' => $courseid,
                'relateduserid' => $userid,
            )
        );
        $event->trigger();
    }
}

function grade_report_failed_settings_definition(&$mform) {
    global $CFG;

    //show rank
    $options = array(-1 => get_string('default', 'grades'),
                      0 => get_string('hide'),
                      1 => get_string('show'));

    $options[-1] = get_string('defaultprev', 'grades', $options[1]);

    $mform->addElement('select', 'report_overview_showrank', get_string('showrank', 'grades'), $options);
    $mform->addHelpButton('report_overview_showrank', 'showrank', 'grades');

    //showtotalsifcontainhidden
    $options = array(-1 => get_string('default', 'grades'),
                      GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN => get_string('hide'),
                      GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN => get_string('hidetotalshowexhiddenitems', 'grades'),
                      GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN => get_string('hidetotalshowinchiddenitems', 'grades') );

    $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 */
function gradereport_failed_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (empty($course)) {
        // We want to display these reports under the site context.
        $course = get_fast_modinfo(SITEID)->get_course();
    }
    $systemcontext = context_system::instance();
    $usercontext = context_user::instance($user->id);
    $coursecontext = context_course::instance($course->id);
    if (grade_report_overview::check_access($systemcontext, $coursecontext, $usercontext, $course, $user->id)) {
        $url = new moodle_url('/grade/report/failed/index.php', array('userid' => $user->id));
        $node = new core_user\output\myprofile\node('reports', 'grades', get_string('gradesoverview', 'gradereport_overview'),
                null, $url);
        $tree->add_node($node);
    }
}
