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
$questionid = required_param('question', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'harpiasurvey');
$harpiasurvey = $DB->get_record('harpiasurvey', ['id' => $cm->instance], '*', MUST_EXIST);
$experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $experimentid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);
$question = $DB->get_record('harpiasurvey_questions', ['id' => $questionid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/harpiasurvey:manageexperiments', $cm->context);

$context = $cm->context;

// Handle deletion.
if ($confirm && confirm_sesskey()) {
    // Delete all question options first.
    $DB->delete_records('harpiasurvey_question_options', ['questionid' => $questionid]);
    
    // Delete all page-question associations.
    $DB->delete_records('harpiasurvey_page_questions', ['questionid' => $questionid]);
    
    // Delete all responses for this question.
    $DB->delete_records('harpiasurvey_responses', ['questionid' => $questionid]);
    
    // Delete all conversations for this question.
    $DB->delete_records('harpiasurvey_conversations', ['questionid' => $questionid]);
    
    // Delete page-question associations where this question is referenced as evaluates_conversation_id.
    $DB->set_field('harpiasurvey_page_questions', 'evaluates_conversation_id', null, ['evaluates_conversation_id' => $questionid]);
    
    // Delete the question.
    $DB->delete_records('harpiasurvey_questions', ['id' => $questionid]);
    
    // Redirect back to question bank.
    redirect(new moodle_url('/mod/harpiasurvey/question_bank.php', [
        'id' => $cm->id,
        'experiment' => $experiment->id
    ]), get_string('questiondeleted', 'mod_harpiasurvey'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Display confirmation page.
$url = new moodle_url('/mod/harpiasurvey/delete_question.php', [
    'id' => $cm->id,
    'experiment' => $experiment->id,
    'question' => $questionid
]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('deletequestion', 'mod_harpiasurvey'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Add breadcrumb.
$PAGE->navbar->add(format_string($harpiasurvey->name), new moodle_url('/mod/harpiasurvey/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(format_string($experiment->name), new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
$PAGE->navbar->add(get_string('questionbank', 'mod_harpiasurvey'), new moodle_url('/mod/harpiasurvey/question_bank.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
$PAGE->navbar->add(get_string('deletequestion', 'mod_harpiasurvey'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deletequestion', 'mod_harpiasurvey'));

// Count related records.
$pagequestioncount = $DB->count_records('harpiasurvey_page_questions', ['questionid' => $questionid]);
$optioncount = $DB->count_records('harpiasurvey_question_options', ['questionid' => $questionid]);
$responsecount = $DB->count_records('harpiasurvey_responses', ['questionid' => $questionid]);
$conversationcount = $DB->count_records('harpiasurvey_conversations', ['questionid' => $questionid]);
$evaluatescount = $DB->count_records('harpiasurvey_page_questions', ['evaluates_conversation_id' => $questionid]);

echo $OUTPUT->confirm(
    get_string('deletequestionconfirm', 'mod_harpiasurvey', [
        'name' => format_string($question->name),
        'pages' => $pagequestioncount,
        'options' => $optioncount,
        'responses' => $responsecount,
        'conversations' => $conversationcount,
        'evaluates' => $evaluatescount
    ]),
    new moodle_url('/mod/harpiasurvey/delete_question.php', [
        'id' => $cm->id,
        'experiment' => $experiment->id,
        'question' => $questionid,
        'confirm' => 1,
        'sesskey' => sesskey()
    ]),
    new moodle_url('/mod/harpiasurvey/question_bank.php', [
        'id' => $cm->id,
        'experiment' => $experiment->id
    ])
);

echo $OUTPUT->footer();

