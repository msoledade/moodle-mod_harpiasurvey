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
$pageid = optional_param('page', 0, PARAM_INT);

// If id is not in URL, try to get it from form data (for form submissions).
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

// If editing, load page data.
if ($pageid) {
    $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid, 'experimentid' => $experiment->id], '*', MUST_EXIST);
    $formdata->id = $page->id;
    $formdata->title = $page->title;
    $formdata->description = $page->description;
    $formdata->descriptionformat = $page->descriptionformat;
    $formdata->type = $page->type;
    $formdata->available = $page->available;
} else {
    $formdata->id = 0;
}

// Set up page.
$url = new moodle_url('/mod/harpiasurvey/edit_page.php', ['id' => $cm->id, 'experiment' => $experiment->id]);
if ($pageid) {
    $url->param('page', $pageid);
    $pagetitle = get_string('editingpage', 'mod_harpiasurvey', format_string($formdata->title ?? ''));
} else {
    $pagetitle = get_string('addingpage', 'mod_harpiasurvey');
}

$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Load JavaScript for question bank modal - must be called before header().
// Always load it, but it will only work when a page exists (button only shows for existing pages).
$PAGE->requires->js_call_amd('mod_harpiasurvey/question_bank_modal', 'init', [
    $cm->id,
    $pageid ? $pageid : 0
]);

// Load JavaScript for edit page functionality (evaluates conversation dropdown).
if ($pageid) {
    $PAGE->requires->js_call_amd('mod_harpiasurvey/edit_page', 'init');
}

// Add breadcrumb.
$PAGE->navbar->add(format_string($harpiasurvey->name), new moodle_url('/mod/harpiasurvey/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(format_string($experiment->name), new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
$PAGE->navbar->add($pagetitle);

// Create form - pass the moodle_url object directly so it preserves parameters.
$form = new \mod_harpiasurvey\forms\page($url, $formdata, $context);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
} else if ($data = $form->get_data()) {
    global $DB;

    // Ensure we have a valid session key to prevent resubmission.
    require_sesskey();

    // Verify we have the required course module ID.
    if (empty($data->cmid) || $data->cmid != $cm->id) {
        throw new moodle_exception('invalidcoursemodule', 'error');
    }

    // Prepare page data.
    $pagedata = new stdClass();
    $pagedata->experimentid = $experiment->id;
    $pagedata->title = $data->title;
    $pagedata->type = $data->type;
    $pagedata->available = isset($data->available) ? (int)$data->available : 1;
    $pagedata->timemodified = time();

    // Handle description editor.
    $descriptiondata = file_postupdate_standard_editor(
        $data,
        'description',
        $form->get_editor_options(),
        $context,
        'mod_harpiasurvey',
        'page',
        $data->id ?? null
    );
    $pagedata->description = $descriptiondata->description;
    $pagedata->descriptionformat = $descriptiondata->descriptionformat;

    if ($data->id) {
        // Update existing page.
        $pagedata->id = $data->id;
        $DB->update_record('harpiasurvey_pages', $pagedata);
        
        // Handle question enabled states.
        if (isset($data->question_enabled) && is_array($data->question_enabled)) {
            // Get all page questions.
            $pagequestions = $DB->get_records('harpiasurvey_page_questions', ['pageid' => $data->id]);
            
            foreach ($pagequestions as $pq) {
                $enabled = isset($data->question_enabled[$pq->id]) ? 1 : 0;
                if ($pq->enabled != $enabled) {
                    $pq->enabled = $enabled;
                    $DB->update_record('harpiasurvey_page_questions', $pq);
                }
            }
        }
    } else {
        // Create new page.
        // Get the next sort order.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {harpiasurvey_pages} WHERE experimentid = ?",
            [$experiment->id]
        );
        $pagedata->sortorder = ($maxsort !== false) ? $maxsort + 1 : 0;
        $pagedata->timecreated = time();
        $DB->insert_record('harpiasurvey_pages', $pagedata);
    }

    // Purge course cache to refresh the table display.
    rebuild_course_cache($course->id, true);

    // Redirect back to experiment view.
    redirect(new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]), get_string('pagesaved', 'mod_harpiasurvey'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Display form.
echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();

