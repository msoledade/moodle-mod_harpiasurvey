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

/**
 * Lists all harpiasurvey instances in a given course.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/harpiasurvey/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

// Trigger course_module_instance_list_viewed event.
$event = \mod_harpiasurvey\event\course_module_instance_list_viewed::create(['context' => $coursecontext]);
$event->trigger();

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'mod_harpiasurvey');
echo $OUTPUT->heading($modulenameplural);

$harpiasurveys = get_all_instances_in_course('harpiasurvey', $course);

if (empty($harpiasurveys)) {
    notice(get_string('noharpiasurveys', 'mod_harpiasurvey'), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format == 'weeks') {
    $table->head  = [get_string('week'), get_string('name')];
    $table->align = ['center', 'left'];
} else if ($course->format == 'topics') {
    $table->head  = [get_string('topic'), get_string('name')];
    $table->align = ['center', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left'];
}

foreach ($harpiasurveys as $harpiasurvey) {
    if (!$harpiasurvey->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/harpiasurvey/view.php', ['id' => $harpiasurvey->coursemodule]),
            format_string($harpiasurvey->name, true),
            ['class' => 'dimmed']
        );
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/harpiasurvey/view.php', ['id' => $harpiasurvey->coursemodule]),
            format_string($harpiasurvey->name, true)
        );
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = [$harpiasurvey->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();

