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
 * Prints an instance of mod_findpartner.
 *
 * @package     mod_findpartner
 * @copyright   2020 Rodrigo Aguirregabiria Herrero
 * @copyright   2020 Manuel Alfredo Collado Centeno
 * @copyright   2020 GIETA Universidad Politécnica de Madrid (http://gieta.etsisi.upm.es/)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once('locallib.php');



// Course_module ID, or.
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$f  = optional_param('f', 0, PARAM_INT);

// Id of the request that is accepted or denied.
$requestid  = optional_param('requestid', 0, PARAM_INT);

// Identifier of the button type (accept or deny). Accept = 1|Deny = 0.
$buttonvalue  = optional_param('buttonvalue', 0, PARAM_INT);


if ($id) {
    $cm             = get_coursemodule_from_id('findpartner', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('findpartner', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($f) {
    $moduleinstance = $DB->get_record('findpartner', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('findpartner', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_findpartner'));
}

require_login($course, true, $cm);


$modulecontext = context_module::instance($cm->id);


global $USER;
global $DB;

$PAGE->set_url('/mod/findpartner/requests.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);


echo $OUTPUT->header();

// Style.
echo "<style>table,td{border: 1px solid black;}td{padding: 10px;}</style>";
$project = $DB->get_record('findpartner_projectgroup', array('groupadmin' => $USER->id, 'findpartner' => $moduleinstance->id));

// This is used to know if somebody has pressed a button of accept or deny.

if ($requestid > 0) {
    // Check if the group is not full. The admin can accept more request if they uses the return page button.
    if (maxmembers($moduleinstance->id) > nummembers($project->id)) {
        $updaterecord = $DB->get_record('findpartner_request', array('id' => $requestid, 'status' => 'P'));
        $thereis = $DB->get_record('findpartner_student', array('studentid' => $updaterecord->student,
        'findpartnerid' => $moduleinstance->id));

        // The student must be in the activity.
        // This scenario is possible when the student exit and the admin accept a previus
        // request because the page hasn't been refreshed.
        if ($thereis != null) {
            if ($buttonvalue == 1) {

                $updaterecord->status = 'A';
                // If a student is accepted, the groupid has to be updated in the student table.
                $ins = $DB->get_record('findpartner_student', array('studentid' => $updaterecord->student,
                    'findpartnerid' => $moduleinstance->id));
                $ins->studentgroup = $updaterecord->groupid;
                $DB->update_record('findpartner_student', $ins);
                // If a student is accepted in one group, all his request mush be denied.
                denyrequests($moduleinstance->id, $ins->studentid);
            } else {
                $updaterecord->status = 'D';
            }
            $DB->update_record('findpartner_request', $updaterecord);
        }

        $morerequestsleft = $DB->get_records('findpartner_request', array('groupid' => $project->id, 'status' => 'P'));
        if ($morerequestsleft == null) {
            // If the are no more requests, you go to view.php.
            redirect(new moodle_url('/mod/findpartner/view.php',
                array('id' => $cm->id)));
        } else if (maxmembers($moduleinstance->id) == nummembers($project->id)) {
            // If the group is full, redirect to view
            // Note that the rest of the request are not denied, just in case a student exists the group.
            redirect(new moodle_url('/mod/findpartner/view.php',
                array('id' => $cm->id)));
        } else {
            // If there are more and the group is not full, you stay in the request page.
            // But it has to be refreshed so the accepted or denied request doesn't appear.
            redirect(new moodle_url('/mod/findpartner/requests.php',
                array('id' => $cm->id, 'requestid' => -1)));
        }
    }
}

// Show the requests.

$requests = $DB->get_records('findpartner_request', array('groupid' => $project->id, 'status' => 'P'));
// If there are more request.
if ($requests != null) {

    // If the group is not full.
    if (maxmembers($moduleinstance->id) > nummembers($project->id)) {
        // Show requests.
        echo '<table>';
        echo "<tr><td>". get_string('requestmessage', 'mod_findpartner') .
        "</td><td>". get_string('accept', 'mod_findpartner') .
        "</td><td>". get_string('deny', 'mod_findpartner') ."</td></tr>";
        foreach ($requests as $request) {
            echo "<tr><td>" . $request->message . "</td>";
            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/requests.php',
                array('id' => $cm->id, 'requestid' => $request->id, 'buttonvalue' => 1)),
                    get_string('accept', 'mod_findpartner')) . "</td>";
            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/requests.php',
                array('id' => $cm->id, 'requestid' => $request->id, 'buttonvalue' => 0)),
                    get_string('deny', 'mod_findpartner')) . "</td>";
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo get_string('groupfull', 'mod_findpartner');
    }
} else {
    echo get_string('norequest', 'mod_findpartner');
}


echo $OUTPUT->footer();