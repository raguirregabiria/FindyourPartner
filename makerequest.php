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
require_once('group_form_request.php');




// Course_module ID, or.
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$f  = optional_param('f', 0, PARAM_INT);

// Groupid.
$groupid = optional_param('groupid', 0, PARAM_INT);


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

$PAGE->set_url('/mod/findpartner/makerequest.php', array('id' => $cm->id, 'groupid' => $groupid));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);


echo $OUTPUT->header();

$data = array (
    'id' => $id,
    'select' => $select,
    'groupid' => $groupid
);
    // Esto queremos tirarlo en el futuro.
$findpartner = $DB->get_record( 'findpartner', array (
'id' => 1
), '*', MUST_EXIST );

$mform = new group_form_request( null, array (
    $data,
    $findpartner
) );

$mform->display();

if ($mform->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form.
    redirect(new moodle_url ('/mod/findpartner/view.php', array('id' => $cm->id)));
} else if ($fromform = $mform->get_data()) {
    // Check that the student has not made any other request to this group.
    // The student can make more request if they uses the return page button.

    $otherrequest = $DB->get_record('findpartner_request',
        array('student' => $USER->id, 'groupid' => $fromform->groupid, 'status' => 'P'));

    if ($otherrequest == null) {
        $ins = (object)array('student' => $USER->id, 'groupid' => $fromform->groupid, 'message' => $fromform->request);
        $DB->insert_record('findpartner_request', $ins, $returnid = true. $bulk = false);
    }
    redirect(new moodle_url ('/mod/findpartner/view.php', array('id' => $cm->id)));
}


echo $OUTPUT->footer();