<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = required_param('id', PARAM_INT);
$experimentid = required_param('experiment', PARAM_INT);
$pageid = required_param('page', PARAM_INT);
$questionid = required_param('question', PARAM_INT);

require_sesskey();

list($course, $cm) = get_course_and_cm_from_cmid($id, 'harpiasurvey');
$harpiasurvey = $DB->get_record('harpiasurvey', ['id' => $cm->instance], '*', MUST_EXIST);
$experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $experimentid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);
$page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid, 'experimentid' => $experiment->id], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/harpiasurvey:manageexperiments', $cm->context);

// Remove question from page.
$DB->delete_records('harpiasurvey_page_questions', [
    'pageid' => $pageid,
    'questionid' => $questionid
]);

// Redirect back to page edit.
redirect(new moodle_url('/mod/harpiasurvey/view_experiment.php', [
    'id' => $cm->id,
    'experiment' => $experiment->id,
    'page' => $pageid,
    'edit' => 1
]), get_string('questionremovedfrompage', 'mod_harpiasurvey'), null, \core\output\notification::NOTIFY_SUCCESS);

