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
require_once('group_form.php');
require_once('locallib.php');
require_once('group_form_request.php');

// Course_module ID, or.
$id = optional_param('id', 0, PARAM_INT);

// ... module instance id.
$f  = optional_param('f', 0, PARAM_INT);

// Value equals to 1 if student joins the activity, 0 if he/seh refuses.
$join = optional_param('join', 0, PARAM_INT);

// Value equals to 1 if the student wants to exit the activity.
$exitactivity = optional_param('exitactivity', 0, PARAM_INT);

// Value equals to 1 if the student wants to exit the group.
$exitgroup = optional_param('exitgroup', 0, PARAM_INT);

// Value equals to 1 if the student wants to make contract, 2 if not.
$contract = optional_param('contract', 0, PARAM_INT);

// Value equals to 1 if the student agrees with the block, 2 if not.
$workblockvote = optional_param('workblockvote', 0, PARAM_INT);

// Id of the workblock the user has vote.
$workblockid = optional_param('workblockid', 0, PARAM_INT);

// Id of the workblock that is set as done by an user.
$workblockdone = optional_param('workblockdone', 0, PARAM_INT);

// Value equals to 1 if the student agrees that the block is complete, 2 if not.
$validationvote = optional_param('validationvote', 0, PARAM_INT);

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

$PAGE->set_url('/mod/findpartner/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Execute the autogroup algorithm when groups are not closed and the date comes.

$findpartner = $DB->get_record('findpartner',
        array('id' => $moduleinstance->id));

if ($findpartner->autogroupstatus == 'N') {
    if (time() > $findpartner->dateclosuregroups) {
        $findpartner->autogroupstatus = 'F';
        $DB->update_record('findpartner', $findpartner);
        matchgroups ($moduleinstance->id);
    }
}


echo $OUTPUT->header();
if (has_capability('mod/findpartner:update', $modulecontext)) {

    // Teacher view.
    // TODO Teacher can edit groups.
    // Teacher can only enrol when the groups are not closed.
    if (time() < $findpartner->dateclosuregroups) {
        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/enrolstudents.php',
            array('id' => $cm->id, 'studenttoenrol' => 0)),
                    get_string('enrolstudents', 'mod_findpartner'));

        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/deenrolstudents.php',
            array('id' => $cm->id, 'studenttoenrol' => 0)),
                get_string('deenrolstudents', 'mod_findpartner'));
    }
    // Button to complete groups before dateclosuregroups comes.
    // Will only be shown if the date of closure groups has not come.
    if ($findpartner->autogroupstatus != 'F') {
        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/completegroups.php',
            array('id' => $cm->id)), get_string('completegroups', 'mod_findpartner'));
    }
    // Style.
    echo "<style>table,td{border: 1px solid black;}td{padding: 10px;}</style>";
    echo '<table><tr><td>'. get_string('group_name', 'mod_findpartner').'</td><td>'.
        get_string('description', 'mod_findpartner') .'</td><td>'.
            get_string('members', 'mod_findpartner') . ' (' . get_string('minimum', 'mod_findpartner') .
                ' ' . $findpartner->minmembers . ')</td></tr>';
    $newrecords = $DB->get_records('findpartner_projectgroup', ['findpartner' => $moduleinstance->id]);
    $student = $DB->get_record('findpartner_student',
        array('studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id));
    foreach ($newrecords as $newrecord) {
        $maxmembers = $DB->get_record('findpartner', ['id' => $newrecord->findpartner]);
        $nummembers = $DB->count_records('findpartner_student', array('studentgroup' => $newrecord->id));

        // Group name.

        echo "<tr><td>".$newrecord->name."</td>";

        // Group description.

        echo "<td>$newrecord->description</td><td>";

        // Group number of members.

        echo $nummembers . "/" . $maxmembers->maxmembers . "</td>";

        // Button to view the group.

        echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/viewgroup.php',
            array('id' => $cm->id, 'groupid' => $newrecord->id)),
                    get_string('viewgroup', 'mod_findpartner')) . "</td>";
        if ($newrecord->contractstatus == 'Y') {
            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/viewcontracts.php',
                array('id' => $cm->id, 'groupid' => $newrecord->id)),
                    get_string('viewcontracts', 'mod_findpartner')) . "</td>";
        }
        echo "</tr>";
    }

    echo '</table>';

} else {

    // Student view.

    // If the date of closure groups has not come, the students can create and join groups.
    $time = $findpartner;


    if (time() < $time->dateclosuregroups) {
        // If the student decided to exit this activity.
        if ($exitactivity == 1) {
            $DB->delete_records('findpartner_student', array('studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id));
            denyrequests($moduleinstance->id, $USER->id);
        }

        $record = $DB->get_record('findpartner_student', ['studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id]);
        // If the student isn't in the activity.
        if ($record == null && $join == 0) {
            echo "<center>" . get_string('joinmessage', 'mod_findpartner') . "</center>";
            // A button ask the student if them want to join it.
            echo "<center>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                array('id' => $cm->id, 'join' => 1)),
                    get_string('accept', 'mod_findpartner')) . "</center>";
        } else {
            // If the student wants to join gets in the database.
            if ($record == null && $join == 1) {
                $ins = (object)array('studentgroup' => null, 'studentid' => $USER->id,
                    'findpartnerid' => $moduleinstance->id);
                $DB->insert_record('findpartner_student', $ins, $returnid = true. $bulk = false);
            }
            if ($record->contactmethod == null) {
                // Students must have a contact method so they can community with each other.
                redirect(new moodle_url('/mod/findpartner/addcontactmethod.php',
                            array('id' => $cm->id)));
            }
            // If the student wants to exit a group.
            if ($exitgroup == 1) {
                if (isadmin($record->studentgroup, $USER->id)) {
                    // We need to check again if the student is alone in the group.
                    // This could happend in some scerarios.
                    $nummembers = $DB->count_records('findpartner_student', array('studentgroup' => $record->studentgroup));
                    if ($nummembers == 1) {
                        // Delete group.
                        $DB->delete_records('findpartner_projectgroup', array('id' => $record->studentgroup));
                        // Update the group of the student.
                        $record->studentgroup = null;
                        $DB->update_record('findpartner_student', $record);
                    }
                } else {
                    $record->studentgroup = null;
                    $DB->update_record('findpartner_student', $record);
                }
            }
            // Students can edit their contact info.
            echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/addcontactmethod.php',
                            array('id' => $cm->id)), get_string('editcontact', 'mod_findpartner'));

            $admin = $DB->get_record('findpartner_projectgroup',
                array('groupadmin' => $USER->id, 'findpartner' => $moduleinstance->id));

            // If a student is admin of a group.

            if ($admin != null) {

                // If there are pending.
                $request = $DB->get_record('findpartner_request', array('groupid' => $admin->id, 'status' => 'P'));
                if ($request != null) {
                    // If the group is not full.
                    if (maxmembers($moduleinstance->id) > nummembers($admin->id)) {
                        // Show the button of request.
                        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/requests.php',
                            array('id' => $cm->id, 'requestid' => -1)), get_string('viewrequest', 'mod_findpartner'));
                    }
                }
            }

            // If a student has no group, can create one.

            $newrecords = $DB->get_record('findpartner_student',
                array('studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id));

            if ($newrecords->studentgroup == null) {
                echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/creategroup.php',
                    array('id' => $cm->id)), get_string('creategroup', 'mod_findpartner'));

                echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                    array('id' => $cm->id, 'exitactivity' => 1)), get_string('exitactivity', 'mod_findpartner'));
            }

            // This prints the table with the groups.
            // style.
            echo "<style>table,td{border: 1px solid black;}td{padding: 10px;}</style>";

            $minmembers = $DB->get_record('findpartner',
                array('id' => $moduleinstance->id));
            echo '<table><tr><td>'. get_string('group_name', 'mod_findpartner').'</td><td>'.
                get_string('description', 'mod_findpartner').'</td><td>'.
                    get_string('members', 'mod_findpartner') . ' (' . get_string('minimum', 'mod_findpartner') .
                        ' ' . $minmembers->minmembers . ')</td></tr>';

            $newrecords = $DB->get_records('findpartner_projectgroup', ['findpartner' => $moduleinstance->id]);
            $student = $DB->get_record('findpartner_student',
                array('studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id));
            foreach ($newrecords as $newrecord) {
                $maxmembers = $DB->get_record('findpartner', ['id' => $newrecord->findpartner]);
                $nummembers = $DB->count_records('findpartner_student', array('studentgroup' => $newrecord->id));

                // Group name.

                echo "<tr><td>".$newrecord->name."</td>";

                // Group description.

                echo "<td>$newrecord->description</td><td>";

                // Group number of members.

                echo $nummembers . "/" . $maxmembers->maxmembers . "</td>";

                // If the student has no group.

                if ($student->studentgroup == null) {
                    // If there is enough space in the group.
                    if ($nummembers < $maxmembers->maxmembers) {

                        // If the student hasn't got more request pending for this group.

                        $requestmade = $DB->count_records('findpartner_request', array('student' => $student->studentid,
                            'groupid' => $newrecord->id, 'status' => 'P'));
                        if ($requestmade == 0) {
                            // The student can make a request to the group.
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/makerequest.php',
                                array('id' => $cm->id, 'groupid' => $newrecord->id)),
                                    get_string('send_request', 'mod_findpartner')) . "</td>";
                        } else {
                            echo "<td><center>" . get_string('alreadysent', 'mod_findpartner') . "</center></td>";
                        }
                    }
                    // If this is the group of the student.
                } else if ($newrecord->id == $student->studentgroup) {
                    // If the student is the admin.
                    if ($USER->id == $newrecord->groupadmin) {
                        // If the admin is the only member.
                        if ($nummembers == 1) {
                            // Then can leave.
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                                array('id' => $cm->id, 'exitgroup' => 1)),
                                    get_string('exitgroup', 'mod_findpartner')) . "</td>";
                        }
                    } else {
                        // If its not the admin, can leave.
                        echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                                array('id' => $cm->id, 'exitgroup' => 1)),
                                    get_string('exitgroup', 'mod_findpartner')) . "</td>";
                    }
                    // If this is the group of the students can see members and contact info.
                    echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/viewgroup.php',
                        array('id' => $cm->id, 'groupid' => $newrecord->id)),
                                get_string('viewmembers', 'mod_findpartner')) . "</td>";
                }
                echo "</tr>";
            }

            echo '</table>';
        }
        // The groups are close.
    } else {
        // Contracts view.
        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/helpcontracts.php',
        array('id' => $cm->id)), get_string('whatcontracts', 'mod_findpartner'));

        $student = $DB->get_record('findpartner_student', array('studentid' => $USER->id, 'findpartnerid' => $moduleinstance->id));
        $group = $DB->get_record('findpartner_projectgroup', array('id' => $student->studentgroup));
        // Show button to see members and contact info.
        echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/viewgroup.php',
            array('id' => $cm->id, 'groupid' => $group->id)),
                get_string('viewmembers', 'mod_findpartner')) . "</td>";


        // If the student has vote to make the contract.
        if ($contract == 1) {
            $ins = (object)array('groupid' => $group->id, 'studentid' => $USER->id, 'vote' => 'Y');
            $DB->insert_record('findpartner_votes', $ins, $returnid = true. $bulk = false);
        } else if ($contract == 2) {
            $ins = (object)array('groupid' => $group->id, 'studentid' => $USER->id, 'vote' => 'N');
            $DB->insert_record('findpartner_votes', $ins, $returnid = true. $bulk = false);
        }
        if ($group->contractstatus == 'P') {
            // The student has 24 hours (86400 seconds) to decide if they want to make contracts.
            if (time() < ($time->dateclosuregroups + 86400)) {
                // TODO Put button that says what is a contract.
                $numvotes = $DB->count_records('findpartner_votes', array('groupid' => $group->id));
                $numstudents = nummembers($group->id);
                if ($numvotes < $numstudents) {

                    echo "<center>" . get_string('alertcontract', 'mod_findpartner') . "</center>";

                    $vote = $DB->get_record('findpartner_votes', array('studentid' => $USER->id, 'groupid' => $group->id));

                    // If the student hasn't voted.

                    if ($vote == null) {
                        echo "<center>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                            array('id' => $cm->id, 'contract' => 1)), get_string('contractyes', 'mod_findpartner'));
                        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                            array('id' => $cm->id, 'contract' => 2)), get_string('contractno', 'mod_findpartner')) . "<center>";
                    }
                } else {
                    updatestatus($group);
                    redirect(new moodle_url('/mod/findpartner/view.php',
                        array('id' => $cm->id)));
                }
            } else {
                updatestatus($group);
                redirect(new moodle_url('/mod/findpartner/view.php',
                    array('id' => $cm->id)));
            }
            // If the contract is made.
        } else if ($group->contractstatus == 'Y') {
            // If the student is admin they can create work blocks.
            $istheadmin = $group->groupadmin == $USER->id;
            // This is used to know if the activity's end date has passed.
            $thereistime = time() < $time->enddate;
            if ($istheadmin && $thereistime) {
                echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/makeworkblock.php',
                    array('id' => $cm->id, 'groupid' => $group->id)),
                        get_string('create_block', 'mod_findpartner')) . "</td>";
            }
            // Insert votes.
            if ($workblockvote != 0) {
                if ($workblockvote == 1) {
                    $ins = (object)array('workblockid' => $workblockid, 'studentid' => $USER->id,
                        'vote' => 'A');
                } else if ($workblockvote == 2) {
                    $ins = (object)array('workblockid' => $workblockid, 'studentid' => $USER->id,
                        'vote' => 'D');
                }
                $DB->insert_record('findpartner_workblockvotes', $ins, $returnid = true. $bulk = false);

                // Here we check if workblock status need to be changed to accept or denied.
                $votes = $DB->count_records('findpartner_workblockvotes', array('workblockid' => $workblockid));
                if ($votes == nummembers($group->id)) {
                    $record = $DB->get_record('findpartner_workblock', ['id' => $workblockid]);
                    if (workblockapproved($workblockid)) {
                        $record->status = 'A';
                        $record->datemodified = time();
                        $DB->update_record('findpartner_workblock', $record);
                    } else {
                        $record->status = 'D';
                        $record->datemodified = time();
                        $DB->update_record('findpartner_workblock', $record);
                    }
                }
            }
            // Set a workblock as completed (the users will have to decide if everythng is ok).

            if ($workblockdone != 0) {
                $record = $DB->get_record('findpartner_workblock', ['id' => $workblockdone]);
                $record->status = 'C';
                $record->datemodified = time();
                $DB->update_record('findpartner_workblock', $record);
            }
            // Insert verification votes.
            if ($validationvote != 0) {
                if ($validationvote == 1) {
                    $ins = (object)array('workblockid' => $workblockid, 'studentid' => $USER->id,
                        'vote' => 'A');
                } else if ($validationvote == 2) {
                    $ins = (object)array('workblockid' => $workblockid, 'studentid' => $USER->id,
                        'vote' => 'D');
                }
                $DB->insert_record('findpartner_donevotes', $ins, $returnid = true. $bulk = false);

                // Here we check if workblock status need to be changed to verified (task done) or accept (not finished).
                $votes = $DB->count_records('findpartner_donevotes', array('workblockid' => $workblockid));
                if ($votes == nummembers($group->id)) {
                    $record = $DB->get_record('findpartner_workblock', ['id' => $workblockid]);
                    if (workblockverified($workblockid)) {
                        $record->status = 'V';
                        $record->datemodified = time();
                        $DB->update_record('findpartner_workblock', $record);
                    } else {
                        $record->status = 'A';
                        $record->datemodified = time();
                        $DB->update_record('findpartner_workblock', $record);
                        // Now we have to delete the votes of this workblock so next time it doesn't affect the votatation.
                        $DB->delete_records('findpartner_donevotes', array('workblockid' => $workblockid));
                    }
                }

            }

            // Students can edit their contact info.
            echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/addcontactmethod.php',
                            array('id' => $cm->id)), get_string('editcontact', 'mod_findpartner'));


            echo "<style>table,td{border: 1px solid black;}td{padding: 10px;}</style>";

            // Show workblocks.
            echo '<table><tr><td>'. get_string('workblock', 'mod_findpartner').'</td><td>'.
                get_string('memberstable', 'mod_findpartner').'</td><td>' .
                    get_string('workblockstatus', 'mod_findpartner').'</td><td>';

            if ($thereistime) {
                echo get_string('sendcomplain', 'mod_findpartner') . '</td><td>';
            }
            echo get_string('complains', 'mod_findpartner') . '</td></tr>';

            $workblocks = $DB->get_records('findpartner_workblock', ['groupid' => $group->id]);
            foreach ($workblocks as $workblock) {

                // Edited blocks are not shown.
                if ($workblock->status != 'E') {
                    echo '<tr><td>' . $workblock->task . '</td><td>';
                    $studentsname = $DB->get_records('findpartner_incharge', ['workblockid' => $workblock->id]);
                    foreach ($studentsname as $studentname) {
                        $studentinfo = $DB->get_record('user', ['id' => $studentname->studentid]);
                        echo $studentinfo->firstname . ' ' . $studentinfo->lastname .'<br>';
                    }
                    echo "</td>";

                    if ($workblock->status == 'A') {
                        echo "<td>" . get_string('accepted', 'mod_findpartner') ."</td>";
                    } else if ($workblock->status == 'D') {
                        echo "<td>" . get_string('dennied', 'mod_findpartner') ."</td>";
                    } else if ($workblock->status == 'P') {
                        echo "<td>" . get_string('pending', 'mod_findpartner') ."</td>";
                    } else if ($workblock->status == 'V') {
                        echo "<td>" . get_string('verified', 'mod_findpartner') ."</td>";
                    } else if ($workblock->status == 'C') {
                        echo "<td>" . get_string('complete', 'mod_findpartner') ."</td>";
                    }


                    $hasworkblockvote = $DB->get_record('findpartner_workblockvotes',
                        array('studentid' => $USER->id, 'workblockid' => $workblock->id));


                    // If the workblock is approved the student can complain in case they don't agree anymore.
                    // With the person/s in charge.
                    if ($thereistime) {
                        if (($workblock->status == 'A' || $workblock->status == 'C')) {
                            $query = $DB->get_record('findpartner_complain',
                                ['workblockid' => $workblock->id, 'studentid' => $USER->id]);
                            if ($query == null) {
                                echo '<td>' . $OUTPUT->single_button(new moodle_url('/mod/findpartner/makecomplain.php',
                                array('id' => $cm->id, 'workblockid' => $workblock->id)),
                                    get_string('complain', 'mod_findpartner')) . '</td>';
                            } else {
                                echo '<td>' . get_string('alreadysent', 'mod_findpartner') . '</td>';
                            }
                        } else {
                            echo '<td></td>';
                        }
                    }
                    $complains = $DB->get_records('findpartner_complain', ['workblockid' => $workblock->id]);
                    if ($complains != null) {
                        echo '<td>';
                        foreach ($complains as $complain) {
                            echo $complain->complain . '<br>';
                        }
                        echo '</td>';
                    } else {
                        echo '<td></td>';
                    }

                    // If the student has voted, can't vote again.
                    if ($hasworkblockvote == null && $thereistime) {
                        echo '<td>';
                        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                            array('id' => $cm->id,  'workblockvote' => 1, 'workblockid' => $workblock->id)),
                                get_string('accept', 'mod_findpartner'));
                        echo "</td><td>";

                        echo $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                            array('id' => $cm->id,  'workblockvote' => 2, 'workblockid' => $workblock->id)),
                                get_string('deny', 'mod_findpartner'));
                        echo '</td>';
                    }
                    // If the workblock has been denied the admin can edit it.
                    if ($istheadmin && $thereistime) {
                        if ($workblock->status == 'D') {
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/makeworkblock.php',
                            array('id' => $cm->id, 'groupid' => $group->id, 'editworkblock' => $workblock->id)),
                                get_string('edit', 'mod_findpartner')) . "</td>";
                        } else {
                            $complains = $DB->get_records('findpartner_complain', ['workblockid' => $workblock->id]);
                            if ($complains != null) {
                                echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/makeworkblock.php',
                                array('id' => $cm->id, 'groupid' => $group->id, 'editworkblock' => $workblock->id)),
                                    get_string('edit', 'mod_findpartner')) . "</td>";
                            }
                        }
                    }
                    // If the user is assigned to the task, can set it as done. Only if the workblock has A as status.
                    if ($workblock->status == 'A') {
                        $incharge = $DB->get_record('findpartner_incharge',
                            ['workblockid' => $workblock->id, 'studentid' => $USER->id]);
                        if ($incharge != null && $thereistime) {
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                                    array('id' => $cm->id, 'workblockdone' => $workblock->id)),
                                        get_string('done', 'mod_findpartner')) . "</td>";
                        }
                    }
                    if ($workblock->status == 'C') {
                        // If the student has already vote, can't vote again.
                        $record = $DB->get_record('findpartner_donevotes',
                            ['workblockid' => $workblock->id, 'studentid' => $USER->id]);
                        if ($record == null && $thereistime) {
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                                    array('id' => $cm->id, 'workblockid' => $workblock->id, 'validationvote' => 1)),
                                        get_string('verify', 'mod_findpartner')) . "</td>";
                            echo "<td>" . $OUTPUT->single_button(new moodle_url('/mod/findpartner/view.php',
                                array('id' => $cm->id, 'workblockid' => $workblock->id, 'validationvote' => 2)),
                                    get_string('noverify', 'mod_findpartner')) . "</td>";

                        }
                    }


                    echo '</tr>';
                }
            }
            echo '</table>';


        } // TODO poner el chat.
    }
}
echo $OUTPUT->footer();
