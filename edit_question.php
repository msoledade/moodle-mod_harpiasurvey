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

// Course module id - can come from URL or form submission.
$id = optional_param('id', 0, PARAM_INT);
$experimentid = required_param('experiment', PARAM_INT);
$questionid = optional_param('question', 0, PARAM_INT);

// If id is not in URL, try to get it from form data.
if (empty($id)) {
    if (isset($_POST['cmid'])) {
        $id = (int)$_POST['cmid'];
    } else if (isset($_POST['form_id'])) {
        $id = (int)$_POST['form_id'];
    } else if ($experimentid) {
        // If we have experiment ID but no course module ID, get it from the experiment.
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} md ON md.id = cm.module
                  JOIN {harpiasurvey} h ON h.id = cm.instance
                  JOIN {harpiasurvey_experiments} he ON he.harpiasurveyid = h.id
                 WHERE he.id = :experimentid
                   AND md.name = 'harpiasurvey'";
        $cmid = $DB->get_field_sql($sql, ['experimentid' => $experimentid], MUST_EXIST);
        $id = $cmid;
    }
}

// Get course and course module.
if (empty($id)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

// Validate that the course module is actually a harpiasurvey module before proceeding.
$moduleid = $DB->get_field('modules', 'id', ['name' => 'harpiasurvey'], MUST_EXIST);
$cmcheck = $DB->get_record('course_modules', [
    'id' => $id,
    'module' => $moduleid
], 'id, instance', IGNORE_MISSING);

if (!$cmcheck) {
    // If the course module ID doesn't match harpiasurvey, try to get it from experiment.
    if ($experimentid) {
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} md ON md.id = cm.module
                  JOIN {harpiasurvey} h ON h.id = cm.instance
                  JOIN {harpiasurvey_experiments} he ON he.harpiasurveyid = h.id
                 WHERE he.id = :experimentid
                   AND md.name = 'harpiasurvey'";
        $cmid = $DB->get_field_sql($sql, ['experimentid' => $experimentid], IGNORE_MISSING);
        if ($cmid) {
            $id = $cmid;
        } else {
            throw new moodle_exception('invalidcoursemoduleid', 'error', '', $id);
        }
    } else {
        throw new moodle_exception('invalidcoursemoduleid', 'error', '', $id);
    }
}

list($course, $cm) = get_course_and_cm_from_cmid($id, 'harpiasurvey');
$harpiasurvey = $DB->get_record('harpiasurvey', ['id' => $cm->instance], '*', MUST_EXIST);
$experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $experimentid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/harpiasurvey:manageexperiments', $cm->context);

$context = $cm->context;

// Prepare form data.
$formdata = new stdClass();
$formdata->cmid = $cm->id;
$formdata->experimentid = $experiment->id;
$formdata->harpiasurveyid = $harpiasurvey->id;

// If editing, load question data.
if ($questionid) {
    $question = $DB->get_record('harpiasurvey_questions', ['id' => $questionid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);
    $formdata->id = $question->id;
    $formdata->name = $question->name;
    $formdata->shortname = $question->shortname;
    $formdata->description = $question->description;
    $formdata->descriptionformat = $question->descriptionformat;
    $formdata->type = $question->type;
    $formdata->enabled = $question->enabled;
    
    // Load settings from JSON.
    if (!empty($question->settings)) {
        $settings = json_decode($question->settings, true);
        if ($settings) {
            // Number settings.
            if ($question->type === 'number') {
                $formdata->numbertype = $settings['numbertype'] ?? 'integer';
                $formdata->numbermin = $settings['min'] ?? '';
                $formdata->numbermax = $settings['max'] ?? '';
                $formdata->numberdefault = $settings['default'] ?? '';
                $formdata->numberallownegatives = $settings['allownegatives'] ?? 0;
            }
            
            // AI Conversation settings.
            if ($question->type === 'aiconversation') {
                $formdata->aimodels = $settings['models'] ?? [];
                $formdata->aibehavior = $settings['behavior'] ?? 'chat';
                $formdata->aitemplate = $settings['template'] ?? '';
                $formdata->aitemplateformat = $settings['templateformat'] ?? FORMAT_HTML;
            }
        }
    }
} else {
    $formdata->id = 0;
}

// Set up page.
$url = new moodle_url('/mod/harpiasurvey/edit_question.php', ['id' => $cm->id, 'experiment' => $experiment->id]);
if ($questionid) {
    $url->param('question', $questionid);
    $pagetitle = get_string('editingquestion', 'mod_harpiasurvey', format_string($formdata->name ?? ''));
} else {
    $pagetitle = get_string('creatingquestion', 'mod_harpiasurvey');
}

$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// No need for custom JavaScript - Moodle's hideIf handles conditional fields.

// Add breadcrumb.
$PAGE->navbar->add(format_string($harpiasurvey->name), new moodle_url('/mod/harpiasurvey/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(format_string($experiment->name), new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
$PAGE->navbar->add(get_string('questionbank', 'mod_harpiasurvey'), new moodle_url('/mod/harpiasurvey/question_bank.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
$PAGE->navbar->add($pagetitle);

// Create form - pass the moodle_url object directly so it preserves parameters.
$form = new \mod_harpiasurvey\forms\question($url, $formdata, $context);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/harpiasurvey/question_bank.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
} else if ($data = $form->get_data()) {
    global $DB;

    // Ensure we have a valid session key to prevent resubmission.
    require_sesskey();

    // Verify we have the required course module ID.
    if (empty($data->cmid) || $data->cmid != $cm->id) {
        throw new moodle_exception('invalidcoursemodule', 'error');
    }

    // Prepare question data.
    $questiondata = new stdClass();
    $questiondata->harpiasurveyid = $harpiasurvey->id;
    $questiondata->name = $data->name;
    $questiondata->shortname = !empty($data->shortname) ? $data->shortname : null;
    $questiondata->type = $data->type;
    $questiondata->enabled = isset($data->enabled) ? (int)$data->enabled : 1;
    $questiondata->timemodified = time();

    // Handle description editor.
    $descriptiondata = file_postupdate_standard_editor(
        $data,
        'description',
        $form->get_editor_options(),
        $context,
        'mod_harpiasurvey',
        'question',
        $data->id ?? null
    );
    $questiondata->description = $descriptiondata->description;
    $questiondata->descriptionformat = $descriptiondata->descriptionformat;

    // Handle type-specific settings (store as JSON).
    $settings = [];
    
    // Number settings.
    if ($data->type === 'number') {
        $settings['numbertype'] = $data->numbertype ?? 'integer';
        $settings['min'] = !empty($data->numbermin) ? (float)$data->numbermin : null;
        $settings['max'] = !empty($data->numbermax) ? (float)$data->numbermax : null;
        $settings['default'] = !empty($data->numberdefault) ? (float)$data->numberdefault : null;
        $settings['allownegatives'] = !empty($data->numberallownegatives) ? 1 : 0;
    }
    
    // AI Conversation settings.
    if ($data->type === 'aiconversation') {
        $settings['models'] = !empty($data->aimodels) ? $data->aimodels : [];
        $settings['behavior'] = $data->aibehavior ?? 'chat';
        
        // Handle AI template editor - need to do this before saving question.
        $templatedata = file_postupdate_standard_editor(
            $data,
            'aitemplate',
            $form->get_editor_options(),
            $context,
            'mod_harpiasurvey',
            'question_ai_template',
            $data->id ?? null
        );
        $settings['template'] = $templatedata->aitemplate ?? '';
        $settings['templateformat'] = $templatedata->aitemplateformat ?? FORMAT_HTML;
    }
    
    // Store settings as JSON.
    $questiondata->settings = !empty($settings) ? json_encode($settings) : null;

    if ($data->id) {
        // Update existing question.
        $questiondata->id = $data->id;
        $DB->update_record('harpiasurvey_questions', $questiondata);
        $questionid = $data->id;
    } else {
        // Create new question.
        $questiondata->timecreated = time();
        $questionid = $DB->insert_record('harpiasurvey_questions', $questiondata);
    }

    // Handle options for single/multiple choice/select questions.
    if (in_array($data->type, ['singlechoice', 'multiplechoice', 'select']) && !empty($data->options)) {
        // Delete existing options.
        $DB->delete_records('harpiasurvey_question_options', ['questionid' => $questionid]);
        
        // Parse options (one per line).
        $options = explode("\n", $data->options);
        $sortorder = 0;
        foreach ($options as $option) {
            $option = trim($option);
            if (empty($option)) {
                continue;
            }
            
            $optiondata = new stdClass();
            $optiondata->questionid = $questionid;
            $optiondata->value = $option;
            $optiondata->isdefault = ($option === $data->defaultvalue) ? 1 : 0;
            $optiondata->sortorder = $sortorder++;
            $optiondata->timecreated = time();
            $DB->insert_record('harpiasurvey_question_options', $optiondata);
        }
    } else {
        // For non-choice questions, delete any existing options.
        $DB->delete_records('harpiasurvey_question_options', ['questionid' => $questionid]);
    }


    // Purge course cache to refresh the table display.
    rebuild_course_cache($course->id, true);

    // Redirect back to question bank.
    redirect(new moodle_url('/mod/harpiasurvey/question_bank.php', ['id' => $cm->id, 'experiment' => $experiment->id]), get_string('questionsaved', 'mod_harpiasurvey'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Display form.
echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();

