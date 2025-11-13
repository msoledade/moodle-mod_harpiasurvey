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
 * Page form class.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page extends \moodleform {
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

        // General section header.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Title field.
        $mform->addElement('text', 'title', get_string('title', 'mod_harpiasurvey'), ['size' => '64']);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->setType('title', PARAM_TEXT);
        if (isset($this->_customdata->title)) {
            $mform->setDefault('title', $this->_customdata->title);
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
            'page',
            $customdata->id
        );

        $mform->addElement('editor', 'description_editor', get_string('description', 'mod_harpiasurvey'), null, $this->get_editor_options());
        $mform->setType('description_editor', PARAM_CLEANHTML);
        if (isset($editordata->description_editor)) {
            $mform->setDefault('description_editor', $editordata->description_editor);
        }

        // Type dropdown.
        $typeoptions = [
            'opening' => get_string('typeopening', 'mod_harpiasurvey'),
            'demographic' => get_string('typedemographic', 'mod_harpiasurvey'),
            'interaction' => get_string('typeinteraction', 'mod_harpiasurvey'),
            'feedback' => get_string('typefeedback', 'mod_harpiasurvey'),
            'aichat' => get_string('typeaichat', 'mod_harpiasurvey'),
        ];
        $mform->addElement('select', 'type', get_string('type', 'mod_harpiasurvey'), $typeoptions);
        $mform->setType('type', PARAM_ALPHANUMEXT);
        if (isset($this->_customdata->type)) {
            $mform->setDefault('type', $this->_customdata->type);
        } else {
            $mform->setDefault('type', 'opening');
        }

        // Available field (checkbox/toggle).
        $mform->addElement('advcheckbox', 'available', get_string('available', 'mod_harpiasurvey'));
        $mform->setType('available', PARAM_BOOL);
        $mform->setDefault('available', 1);
        if (isset($this->_customdata->available)) {
            $mform->setDefault('available', $this->_customdata->available ? 1 : 0);
        }

        // Questions section header.
        $mform->addElement('header', 'questions', get_string('questions', 'mod_harpiasurvey'));
        
        // Questions table will be added via HTML element.
        $pageid = isset($this->_customdata->id) ? $this->_customdata->id : 0;
        $experimentid = isset($this->_customdata->experimentid) ? $this->_customdata->experimentid : 0;
        $cmid = isset($this->_customdata->cmid) ? $this->_customdata->cmid : 0;
        
        if ($pageid) {
            // Load questions for this page.
            global $DB, $OUTPUT;
            $page_type = isset($this->_customdata->type) ? $this->_customdata->type : '';
            $is_aichat = ($page_type === 'aichat');
            
            // Get conversation questions for dropdown (only for aichat pages).
            $conversationquestions = [];
            if ($is_aichat) {
                $conversationquestions_sql = "SELECT pq.questionid, q.name
                                                FROM {harpiasurvey_page_questions} pq
                                                JOIN {harpiasurvey_questions} q ON q.id = pq.questionid
                                               WHERE pq.pageid = :pageid AND q.type = 'aiconversation' AND pq.enabled = 1
                                            ORDER BY pq.sortorder ASC";
                $conversationrecords = $DB->get_records_sql($conversationquestions_sql, ['pageid' => $pageid]);
                foreach ($conversationrecords as $conv) {
                    $conversationquestions[$conv->questionid] = format_string($conv->name);
                }
            }
            
            $pagequestions = $DB->get_records_sql(
                "SELECT pq.id, pq.pageid, pq.questionid, pq.enabled, pq.sortorder, 
                        pq.evaluates_conversation_id, pq.timecreated,
                        q.name, q.type,
                        cq.name AS evaluates_conversation_name
                   FROM {harpiasurvey_page_questions} pq
                   JOIN {harpiasurvey_questions} q ON q.id = pq.questionid
              LEFT JOIN {harpiasurvey_questions} cq ON cq.id = pq.evaluates_conversation_id
                  WHERE pq.pageid = :pageid
                  ORDER BY pq.sortorder ASC",
                ['pageid' => $pageid]
            );
            
            // Add spacing after the "Questions" header.
            $questionstable = '<div class="mb-3"></div>';
            
            if (!empty($pagequestions)) {
                $questionstable .= '<table class="table table-hover table-bordered" style="width: 100%;">';
                $questionstable .= '<thead class="table-dark">';
                $questionstable .= '<tr>';
                $questionstable .= '<th scope="col">' . get_string('question', 'mod_harpiasurvey') . '</th>';
                $questionstable .= '<th scope="col">' . get_string('type', 'mod_harpiasurvey') . '</th>';
                if ($is_aichat) {
                    $questionstable .= '<th scope="col">' . get_string('evaluatesconversation', 'mod_harpiasurvey') . '</th>';
                }
                $questionstable .= '<th scope="col">' . get_string('enabled', 'mod_harpiasurvey') . '</th>';
                $questionstable .= '<th scope="col">' . get_string('actions', 'mod_harpiasurvey') . '</th>';
                $questionstable .= '</tr>';
                $questionstable .= '</thead>';
                $questionstable .= '<tbody>';
                
                foreach ($pagequestions as $pq) {
                    $questionstable .= '<tr>';
                    $questionstable .= '<td>' . format_string($pq->name) . '</td>';
                    $questionstable .= '<td>' . get_string('type' . $pq->type, 'mod_harpiasurvey') . '</td>';
                    
                    // Evaluates conversation column (only for aichat pages).
                    if ($is_aichat) {
                        if ($pq->type === 'aiconversation') {
                            $questionstable .= '<td><span class="text-muted">—</span></td>';
                        } else {
                            // Create dropdown for selecting conversation.
                            $selectid = 'evaluates_conversation_' . $pq->id;
                            // Get the current value, ensuring it's an integer (NULL becomes 0).
                            $currentvalue = isset($pq->evaluates_conversation_id) && $pq->evaluates_conversation_id !== null 
                                ? (int)$pq->evaluates_conversation_id 
                                : 0;
                            $questionstable .= '<td>';
                            $questionstable .= '<select class="form-select form-select-sm evaluates-conversation-select" id="' . $selectid . '" data-pagequestionid="' . $pq->id . '" data-cmid="' . $cmid . '">';
                            $questionstable .= '<option value="0">' . get_string('none', 'mod_harpiasurvey') . '</option>';
                            foreach ($conversationquestions as $conv_id => $conv_name) {
                                // Use strict comparison with both values cast to int.
                                $selected = ((int)$currentvalue === (int)$conv_id) ? ' selected' : '';
                                $questionstable .= '<option value="' . (int)$conv_id . '"' . $selected . '>' . htmlspecialchars($conv_name) . '</option>';
                            }
                            $questionstable .= '</select>';
                            $questionstable .= '</td>';
                        }
                    }
                    
                    // Enabled checkbox.
                    $enabledid = 'question_enabled_' . $pq->id;
                    $enabledchecked = $pq->enabled ? 'checked' : '';
                    $questionstable .= '<td>';
                    $questionstable .= '<input type="checkbox" id="' . $enabledid . '" name="question_enabled[' . $pq->id . ']" value="1" ' . $enabledchecked . '>';
                    $questionstable .= '</td>';
                    
                    // Actions: remove from page.
                    $removeurl = new \moodle_url('/mod/harpiasurvey/remove_page_question.php', [
                        'id' => $cmid,
                        'experiment' => $experimentid,
                        'page' => $pageid,
                        'question' => $pq->questionid,
                        'sesskey' => sesskey()
                    ]);
                    $removeicon = $OUTPUT->pix_icon('t/delete', get_string('remove'), 'moodle', ['class' => 'icon']);
                    $questionstable .= '<td>';
                    $questionstable .= '<a href="' . $removeurl->out(false) . '" title="' . get_string('remove', 'mod_harpiasurvey') . '">' . $removeicon . '</a>';
                    $questionstable .= '</td>';
                    $questionstable .= '</tr>';
                }
                
                $questionstable .= '</tbody>';
                $questionstable .= '</table>';
            } else {
                $questionstable .= '<p class="text-muted">' . get_string('noquestionsonpage', 'mod_harpiasurvey') . '</p>';
            }
            
            // Action button - Question bank (opens modal).
            // JavaScript initialization is handled in edit_page.php via js_call_amd.
            $questionstable .= '<div class="mt-2 mb-4">';
            $questionstable .= '<button type="button" class="btn btn-secondary mr-2 question-bank-modal-trigger" data-cmid="' . $cmid . '" data-pageid="' . $pageid . '">' . get_string('questionbank', 'mod_harpiasurvey') . '</button>';
            $questionstable .= '</div>';
            
            $mform->addElement('html', $questionstable);
        } else {
            // New page - show message.
            $mform->addElement('static', 'questions_info', '', '<p class="text-muted">' . get_string('savepagetoaddquestions', 'mod_harpiasurvey') . '</p>');
        }

        // Add action buttons.
        $this->add_action_buttons(true, get_string('save', 'mod_harpiasurvey'));
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

