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
 * Upgrade code for the harpiasurvey module.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade harpiasurvey module.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_harpiasurvey_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v5.1.0 release upgrade line. Do not remove.
    if ($oldversion < 2025010710) {
        // Create harpiasurvey_conversations table.
        $installfile = $CFG->dirroot . '/mod/harpiasurvey/db/install.xml';
        if (!$dbman->table_exists('harpiasurvey_conversations')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_conversations');
        }
        upgrade_mod_savepoint(true, 2025010710, 'harpiasurvey');
    }
    if ($oldversion < 2025010709) {
        // Create harpiasurvey_responses table.
        $installfile = $CFG->dirroot . '/mod/harpiasurvey/db/install.xml';
        if (!$dbman->table_exists('harpiasurvey_responses')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_responses');
        }
        upgrade_mod_savepoint(true, 2025010709, 'harpiasurvey');
    }
    
    if ($oldversion < 2025010708) {
        // Add settings field to harpiasurvey_questions table.
        $table = new xmldb_table('harpiasurvey_questions');
        $field = new xmldb_field('settings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'type');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_mod_savepoint(true, 2025010708, 'harpiasurvey');
    }
    
    if ($oldversion < 2025010700) {
        // Define tables to be created.
        $installfile = $CFG->dirroot . '/mod/harpiasurvey/db/install.xml';

        // Create harpiasurvey_models table.
        if (!$dbman->table_exists('harpiasurvey_models')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_models');
        }

        // Create harpiasurvey_experiments table.
        if (!$dbman->table_exists('harpiasurvey_experiments')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_experiments');
        }

        // Create harpiasurvey_experiment_models table.
        if (!$dbman->table_exists('harpiasurvey_experiment_models')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_experiment_models');
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010700, 'harpiasurvey');
    }

    if ($oldversion < 2025010702) {
        // Add new fields to harpiasurvey_models table.
        $table = new xmldb_table('harpiasurvey_models');

        // Add 'model' field (model identifier).
        $field = new xmldb_field('model', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'extrafields' field (JSON).
        $field = new xmldb_field('extrafields', XMLDB_TYPE_TEXT, null, null, null, null, null, 'endpoint');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'enabled' field.
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'extrafields');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010702, 'harpiasurvey');
    }

    if ($oldversion < 2025010703) {
        // Add maxparticipants field to harpiasurvey_experiments table.
        $table = new xmldb_table('harpiasurvey_experiments');

        // Add 'maxparticipants' field.
        $field = new xmldb_field('maxparticipants', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'participants');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010703, 'harpiasurvey');
    }

    if ($oldversion < 2025010704) {
        // Create harpiasurvey_pages table.
        $installfile = $CFG->dirroot . '/mod/harpiasurvey/db/install.xml';
        if (!$dbman->table_exists('harpiasurvey_pages')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_pages');
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010704, 'harpiasurvey');
    }

    if ($oldversion < 2025010705) {
        // Create question-related tables.
        $installfile = $CFG->dirroot . '/mod/harpiasurvey/db/install.xml';
        if (!$dbman->table_exists('harpiasurvey_questions')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_questions');
        }
        if (!$dbman->table_exists('harpiasurvey_question_options')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_question_options');
        }
        if (!$dbman->table_exists('harpiasurvey_page_questions')) {
            $dbman->install_one_table_from_xmldb_file($installfile, 'harpiasurvey_page_questions');
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010705, 'harpiasurvey');
    }

    if ($oldversion < 2025010706) {
        // Add enabled field to harpiasurvey_page_questions table.
        $table = new xmldb_table('harpiasurvey_page_questions');

        // Add 'enabled' field.
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'questionid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Harpiasurvey savepoint reached.
        upgrade_mod_savepoint(true, 2025010706, 'harpiasurvey');
    }

    if ($oldversion < 202501280011) {
        // Added new page type 'aichat' for AI chat evaluation pages.
        // AI conversation questions are now restricted to aichat pages only.
        // No database schema changes needed - the type field already supports any value.
        
        upgrade_mod_savepoint(true, 202501280011, 'harpiasurvey');
    }

    if ($oldversion < 202501280012) {
        // Add evaluates_conversation_id field to harpiasurvey_page_questions table.
        $table = new xmldb_table('harpiasurvey_page_questions');
        
        // Add 'evaluates_conversation_id' field.
        $field = new xmldb_field('evaluates_conversation_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add foreign key constraint.
        $key = new xmldb_key('fk_evaluates_conversation', XMLDB_KEY_FOREIGN, ['evaluates_conversation_id'], 'harpiasurvey_questions', ['id']);
        $dbman->add_key($table, $key);
        
        upgrade_mod_savepoint(true, 202501280012, 'harpiasurvey');
    }

    return true;
}

