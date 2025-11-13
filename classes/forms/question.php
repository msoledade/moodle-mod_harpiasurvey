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

namespace mod_harpiasurvey\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Question form class.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends \moodleform {
    /**
     * The context for this form.
     *
     * @var \context
     */
    protected $context;

    /**
     * Class constructor
     *
     * @param moodle_url|string $url
     * @param $formdata
     * @param $context
     */
    public function __construct($url, $formdata, $context) {
        $this->context = $context;
        // Ensure URL is a moodle_url object to preserve parameters.
        if (is_string($url)) {
            $url = new \moodle_url($url);
        }
        parent::__construct($url, $formdata);
    }

    /**
     * The form definition.
     *
     * @throws \coding_exception
     */
    public function definition() {
        global $DB;
        
        $mform = $this->_form;

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        if (isset($this->_customdata->id)) {
            $mform->setDefault('id', $this->_customdata->id);
        }

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $this->_customdata->cmid);
        $mform->setConstant('cmid', $this->_customdata->cmid);

        $mform->addElement('hidden', 'form_id');
        $mform->setType('form_id', PARAM_INT);
        $mform->setDefault('form_id', $this->_customdata->cmid);
        $mform->setConstant('form_id', $this->_customdata->cmid);

        $mform->addElement('hidden', 'experimentid');
        $mform->setType('experimentid', PARAM_INT);
        $mform->setDefault('experimentid', $this->_customdata->experimentid);
        $mform->setConstant('experimentid', $this->_customdata->experimentid);

        $mform->addElement('hidden', 'harpiasurveyid');
        $mform->setType('harpiasurveyid', PARAM_INT);
        $mform->setDefault('harpiasurveyid', $this->_customdata->harpiasurveyid);
        $mform->setConstant('harpiasurveyid', $this->_customdata->harpiasurveyid);

        // General section header.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Type dropdown.
        $typeoptions = [
            'singlechoice' => get_string('typesinglechoice', 'mod_harpiasurvey'),
            'multiplechoice' => get_string('typemultiplechoice', 'mod_harpiasurvey'),
            'select' => get_string('typeselect', 'mod_harpiasurvey'),
            'likert' => get_string('typelikert', 'mod_harpiasurvey'),
            'number' => get_string('typenumber', 'mod_harpiasurvey'),
            'shorttext' => get_string('typeshorttext', 'mod_harpiasurvey'),
            'longtext' => get_string('typelongtext', 'mod_harpiasurvey'),
            'aiconversation' => get_string('typeaiconversation', 'mod_harpiasurvey'),
        ];
        $mform->addElement('select', 'type', get_string('type', 'mod_harpiasurvey'), $typeoptions);
        $mform->setType('type', PARAM_ALPHANUMEXT);
        if (isset($this->_customdata->type)) {
            $mform->setDefault('type', $this->_customdata->type);
        } else {
            $mform->setDefault('type', 'singlechoice');
        }
        // Add JavaScript to show/hide fields based on type.
        $mform->addElement('static', 'type_help', '', get_string('type_help', 'mod_harpiasurvey'));

        // Name field.
        $mform->addElement('text', 'name', get_string('questionname', 'mod_harpiasurvey'), ['size' => '64']);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->setType('name', PARAM_TEXT);
        if (isset($this->_customdata->name)) {
            $mform->setDefault('name', $this->_customdata->name);
        }

        // Short name field.
        $mform->addElement('text', 'shortname', get_string('questionshortname', 'mod_harpiasurvey'), ['size' => '20', 'maxlength' => '50']);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        if (isset($this->_customdata->shortname)) {
            $mform->setDefault('shortname', $this->_customdata->shortname);
        }

        // Description field (editor).
        $customdata = $this->_customdata ?? new \stdClass();
        
        // Ensure description and descriptionformat properties exist.
        if (!isset($customdata->description)) {
            $customdata->description = '';
        }
        if (!isset($customdata->descriptionformat)) {
            $customdata->descriptionformat = FORMAT_HTML;
        }
        if (!isset($customdata->id)) {
            $customdata->id = null;
        }

        $editordata = file_prepare_standard_editor(
            $customdata,
            'description',
            $this->get_editor_options(),
            $this->context,
            'mod_harpiasurvey',
            'question',
            $customdata->id
        );

        $mform->addElement('editor', 'description_editor', get_string('description', 'mod_harpiasurvey'), null, $this->get_editor_options());
        $mform->setType('description_editor', PARAM_CLEANHTML);
        if (isset($editordata->description_editor)) {
            $mform->setDefault('description_editor', $editordata->description_editor);
        }

        // Selection field settings (for single/multiple choice/select).
        $mform->addElement('header', 'selection_settings', get_string('selectionfieldsettings', 'mod_harpiasurvey'));
        $mform->setExpanded('selection_settings', true);
        // Hide selection settings for types that don't need it.
        $mform->hideIf('selection_settings', 'type', 'eq', 'number');
        $mform->hideIf('selection_settings', 'type', 'eq', 'likert');
        $mform->hideIf('selection_settings', 'type', 'eq', 'shorttext');
        $mform->hideIf('selection_settings', 'type', 'eq', 'longtext');
        $mform->hideIf('selection_settings', 'type', 'eq', 'aiconversation');
        
        // Options field (one per line) - for singlechoice, multiplechoice, select.
        $mform->addElement('textarea', 'options', get_string('optionsoneline', 'mod_harpiasurvey'), [
            'rows' => 10,
            'cols' => 80,
            'wrap' => 'off'
        ]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'optionsoneline', 'mod_harpiasurvey');
        
        // Load existing options if editing.
        if (isset($this->_customdata->id) && $this->_customdata->id) {
            $options = $DB->get_records('harpiasurvey_question_options', ['questionid' => $this->_customdata->id], 'sortorder ASC');
            $optionstext = '';
            foreach ($options as $option) {
                $optionstext .= $option->value . "\n";
            }
            $mform->setDefault('options', trim($optionstext));
        }

        // Default value field - for singlechoice, multiplechoice, select.
        $mform->addElement('text', 'defaultvalue', get_string('defaultvalue', 'mod_harpiasurvey'), ['size' => '64']);
        $mform->setType('defaultvalue', PARAM_TEXT);
        if (isset($this->_customdata->id) && $this->_customdata->id) {
            $defaultoption = $DB->get_record('harpiasurvey_question_options', ['questionid' => $this->_customdata->id, 'isdefault' => 1]);
            if ($defaultoption) {
                $mform->setDefault('defaultvalue', $defaultoption->value);
            }
        }

        // Number field settings.
        $mform->addElement('header', 'number_settings', get_string('numberfieldsettings', 'mod_harpiasurvey'));
        $mform->setExpanded('number_settings', false);
        // Hide number settings for all types except number.
        $mform->hideIf('number_settings', 'type', 'neq', 'number');
        
        // Number type (integer/decimal).
        $numbertypeoptions = [
            'integer' => get_string('numbertypeinteger', 'mod_harpiasurvey'),
            'decimal' => get_string('numbertypedecimal', 'mod_harpiasurvey'),
        ];
        $mform->addElement('select', 'numbertype', get_string('numbertype', 'mod_harpiasurvey'), $numbertypeoptions);
        $mform->setType('numbertype', PARAM_ALPHANUMEXT);
        $mform->setDefault('numbertype', isset($this->_customdata->numbertype) ? $this->_customdata->numbertype : 'integer');
        
        // Minimum value.
        $mform->addElement('text', 'numbermin', get_string('numbermin', 'mod_harpiasurvey'), ['size' => '20']);
        $mform->setType('numbermin', PARAM_FLOAT);
        $mform->addHelpButton('numbermin', 'numbermin', 'mod_harpiasurvey');
        if (isset($this->_customdata->numbermin)) {
            $mform->setDefault('numbermin', $this->_customdata->numbermin);
        }
        
        // Maximum value.
        $mform->addElement('text', 'numbermax', get_string('numbermax', 'mod_harpiasurvey'), ['size' => '20']);
        $mform->setType('numbermax', PARAM_FLOAT);
        $mform->addHelpButton('numbermax', 'numbermax', 'mod_harpiasurvey');
        if (isset($this->_customdata->numbermax)) {
            $mform->setDefault('numbermax', $this->_customdata->numbermax);
        }
        
        // Default value for number.
        $mform->addElement('text', 'numberdefault', get_string('numberdefault', 'mod_harpiasurvey'), ['size' => '20']);
        $mform->setType('numberdefault', PARAM_FLOAT);
        if (isset($this->_customdata->numberdefault)) {
            $mform->setDefault('numberdefault', $this->_customdata->numberdefault);
        }
        
        // Allow negatives.
        $mform->addElement('advcheckbox', 'numberallownegatives', get_string('numberallownegatives', 'mod_harpiasurvey'));
        $mform->setType('numberallownegatives', PARAM_BOOL);
        $mform->setDefault('numberallownegatives', isset($this->_customdata->numberallownegatives) ? $this->_customdata->numberallownegatives : 0);

        // AI Conversation settings.
        $mform->addElement('header', 'ai_settings', get_string('aiconversationsettings', 'mod_harpiasurvey'));
        $mform->setExpanded('ai_settings', false);
        // Hide AI settings for all types except aiconversation.
        $mform->hideIf('ai_settings', 'type', 'neq', 'aiconversation');
        
        // Load available models.
        $models = $DB->get_records('harpiasurvey_models', ['harpiasurveyid' => $this->_customdata->harpiasurveyid, 'enabled' => 1], 'name ASC');
        $modeloptions = [];
        foreach ($models as $model) {
            $modeloptions[$model->id] = $model->name . ' (' . $model->model . ')';
        }
        
        // Model selection (multi-select).
        $mform->addElement('autocomplete', 'aimodels', get_string('aimodels', 'mod_harpiasurvey'), $modeloptions, [
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'mod_harpiasurvey'),
        ]);
        $mform->setType('aimodels', PARAM_INT);
        $mform->addHelpButton('aimodels', 'aimodels', 'mod_harpiasurvey');
        if (isset($this->_customdata->aimodels) && is_array($this->_customdata->aimodels)) {
            $mform->getElement('aimodels')->setSelected($this->_customdata->aimodels);
        }
        
        // Behavior (Q&A or Chat).
        $behavioroptions = [
            'qa' => get_string('aibehaviorqa', 'mod_harpiasurvey'),
            'chat' => get_string('aibehaviorchat', 'mod_harpiasurvey'),
        ];
        $mform->addElement('select', 'aibehavior', get_string('aibehavior', 'mod_harpiasurvey'), $behavioroptions);
        $mform->setType('aibehavior', PARAM_ALPHANUMEXT);
        $mform->setDefault('aibehavior', isset($this->_customdata->aibehavior) ? $this->_customdata->aibehavior : 'chat');
        $mform->addHelpButton('aibehavior', 'aibehavior', 'mod_harpiasurvey');
        
        // Template (rich text editor).
        $aitemplatedata = new \stdClass();
        $aitemplatedata->aitemplate = isset($this->_customdata->aitemplate) ? $this->_customdata->aitemplate : '';
        $aitemplatedata->aitemplateformat = isset($this->_customdata->aitemplateformat) ? $this->_customdata->aitemplateformat : FORMAT_HTML;
        $aitemplatedata->id = isset($this->_customdata->id) ? $this->_customdata->id : null;
        
        $templatedata = file_prepare_standard_editor(
            $aitemplatedata,
            'aitemplate',
            $this->get_editor_options(),
            $this->context,
            'mod_harpiasurvey',
            'question_ai_template',
            $aitemplatedata->id
        );
        
        $mform->addElement('editor', 'aitemplate_editor', get_string('aitemplate', 'mod_harpiasurvey'), null, $this->get_editor_options());
        $mform->setType('aitemplate_editor', PARAM_CLEANHTML);
        if (isset($templatedata->aitemplate_editor)) {
            $mform->setDefault('aitemplate_editor', $templatedata->aitemplate_editor);
        }
        $mform->addHelpButton('aitemplate_editor', 'aitemplate', 'mod_harpiasurvey');

        // Enabled field (checkbox/toggle).
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'mod_harpiasurvey'));
        $mform->setType('enabled', PARAM_BOOL);
        $mform->setDefault('enabled', 1);
        if (isset($this->_customdata->enabled)) {
            $mform->setDefault('enabled', $this->_customdata->enabled ? 1 : 0);
        }

        // Add action buttons.
        $this->add_action_buttons(true, get_string('savechanges', 'mod_harpiasurvey'));
    }

    /**
     * Get editor options for description field.
     *
     * @return array
     */
    public function get_editor_options() {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => 0,
            'context' => $this->context,
            'noclean' => true,
            'autosave' => true,
            'overflowdiv' => true,
            'enable_filemanagement' => true,
        ];
    }
}

