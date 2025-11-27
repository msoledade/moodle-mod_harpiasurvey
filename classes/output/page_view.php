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

namespace mod_harpiasurvey\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;

/**
 * Page view renderable class.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_view implements renderable, templatable {

    /**
     * @var object Page object
     */
    public $page;

    /**
     * @var object Context object
     */
    public $context;

    /**
     * @var string Edit URL
     */
    public $editurl;

    /**
     * @var array Questions array
     */
    public $questions;

    /**
     * @var int Course module ID
     */
    public $cmid;

    /**
     * @var int Page ID
     */
    public $pageid;

    /**
     * @var array All pages for the experiment
     */
    public $allpages;

    /**
     * @var int Experiment ID
     */
    public $experimentid;

    /**
     * @var bool Whether user can manage experiments (is admin)
     */
    public $canmanage;

    /**
     * Class constructor.
     *
     * @param object $page
     * @param object $context
     * @param string $editurl
     * @param array $questions
     * @param int $cmid
     * @param int $pageid
     * @param array $allpages
     * @param int $experimentid
     * @param bool $canmanage
     */
    public function __construct($page, $context, $editurl, $questions = [], $cmid = 0, $pageid = 0, $allpages = [], $experimentid = 0, $canmanage = true) {
        $this->page = $page;
        $this->context = $context;
        $this->editurl = $editurl;
        $this->questions = $questions;
        $this->cmid = $cmid;
        $this->pageid = $pageid;
        $this->allpages = $allpages;
        $this->experimentid = $experimentid;
        $this->canmanage = $canmanage;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $DB, $USER;

        // Format the page description.
        $description = format_text($this->page->description, $this->page->descriptionformat, [
            'context' => $this->context,
            'noclean' => false,
            'overflowdiv' => true
        ]);
        
        // Check if description has actual content (strip HTML tags and whitespace).
        $hasdescription = !empty(trim(strip_tags($description)));

        // Load existing responses for this user and page.
        $responses = [];
        $responsetimestamps = [];
        if ($this->pageid && $USER->id) {
            $userresponses = $DB->get_records('harpiasurvey_responses', [
                'pageid' => $this->pageid,
                'userid' => $USER->id
            ]);
            foreach ($userresponses as $response) {
                $responses[$response->questionid] = $response->response;
                $responsetimestamps[$response->questionid] = $response->timemodified;
            }
        }

        // Prepare questions for rendering.
        $questionslist = [];
        $turnevaluationquestions = []; // Questions that evaluate turns (for aichat pages with turns mode).
        $hasnonaiconversationquestions = false;
        
        foreach ($this->questions as $question) {
            if (!$question->enabled) {
                continue; // Skip disabled questions.
            }

            // Note: For aichat pages with turns mode, questions are also processed as turn evaluation questions
            // below, but they should still appear in the regular questions list so users can see them from the start.
            // They will appear both in the regular list and dynamically when turns are created.

            global $DB;
            
            // Load question settings from JSON.
            $settings = [];
            if (!empty($question->settings)) {
                $settings = json_decode($question->settings, true) ?? [];
            }
            
            // Load saved response for this question (used for all question types).
            $savedresponse = $responses[$question->questionid] ?? null;
            
            // Get question options if it's a choice/select question.
            $options = [];
            $inputtype = 'radio'; // Default for single choice.
            $ismultiplechoice = false;
            if (in_array($question->type, ['singlechoice', 'multiplechoice', 'select', 'likert'])) {
                $inputtype = ($question->type === 'singlechoice' || $question->type === 'select' || $question->type === 'likert') ? 'radio' : 'checkbox';
                $ismultiplechoice = ($question->type === 'multiplechoice');
                
                // For Likert, generate 1-5 options.
                if ($question->type === 'likert') {
                    for ($i = 1; $i <= 5; $i++) {
                        $isselected = ($savedresponse && (string)$i === $savedresponse);
                        $options[] = [
                            'id' => $i,
                            'value' => (string)$i,
                            'isdefault' => $isselected,
                            'inputtype' => 'radio',
                            'questionid' => $question->questionid,
                            'nameattr' => 'question_' . $question->questionid,
                        ];
                    }
                } else {
                    // For other types, load from database.
                    $questionoptions = $DB->get_records('harpiasurvey_question_options', [
                        'questionid' => $question->questionid
                    ], 'sortorder ASC');
                    // Format the name attribute: use array notation for checkboxes, simple name for radio buttons
                    $nameattr = 'question_' . $question->questionid;
                    if ($ismultiplechoice) {
                        $nameattr .= '[]';
                    }
                    foreach ($questionoptions as $opt) {
                        // Check if this option is in the saved response.
                        $isselected = false;
                        if ($savedresponse !== null) {
                            if ($ismultiplechoice) {
                                // Multiple choice: response is JSON array.
                                $savedvalues = json_decode($savedresponse, true);
                                if (is_array($savedvalues)) {
                                    $isselected = in_array((string)$opt->id, array_map('strval', $savedvalues));
                                }
                            } else {
                                // Single choice or select: response is the option ID.
                                $isselected = ((string)$opt->id === (string)$savedresponse);
                            }
                        } else {
                            $isselected = (bool)$opt->isdefault;
                        }
                        
                        $options[] = [
                            'id' => $opt->id,
                            'value' => format_string($opt->value),
                            'isdefault' => $isselected,
                            'inputtype' => $inputtype,
                            'questionid' => $question->questionid,
                            'nameattr' => $nameattr,
                        ];
                    }
                }
            }
            
            // Number field settings.
            $numbersettings = [];
            if ($question->type === 'number') {
                $min = $settings['min'] ?? null;
                $max = $settings['max'] ?? null;
                $allownegatives = !empty($settings['allownegatives']);
                
                // If negatives not allowed and no min set, default to 0.
                if (!$allownegatives && $min === null) {
                    $min = 0;
                }
                
                $numbersettings = [
                    'has_min' => $min !== null,
                    'min_value' => $min,
                    'has_max' => $max !== null,
                    'max_value' => $max,
                    'default' => $settings['default'] ?? null,
                    'step' => ($settings['numbertype'] ?? 'integer') === 'integer' ? 1 : 0.01,
                ];
            }

            // Get saved response value (already loaded above as $savedresponse).
            $savedresponsevalue = $savedresponse;
            $savedtimestamp = $responsetimestamps[$question->questionid] ?? null;
            
            // Track if we have questions (all questions are now regular questions, no aiconversation type).
            $hasnonaiconversationquestions = true;
            
            $questionslist[] = [
                'id' => $question->questionid,
                'name' => format_string($question->name),
                'type' => $question->type,
                'description' => format_text($question->description, $question->descriptionformat, [
                    'context' => $this->context,
                    'noclean' => false,
                    'overflowdiv' => true
                ]),
                'options' => $options,
                'has_options' => !empty($options),
                'is_multiplechoice' => $ismultiplechoice,
                'is_select' => ($question->type === 'select'),
                'is_likert' => ($question->type === 'likert'),
                'is_number' => ($question->type === 'number'),
                'numbersettings' => $numbersettings,
                'is_shorttext' => ($question->type === 'shorttext'),
                'is_longtext' => ($question->type === 'longtext'),
                'saved_response' => $savedresponsevalue,
                'has_saved_response' => !empty($savedresponsevalue),
                'saved_timestamp' => $savedtimestamp,
                'saved_datetime' => $savedtimestamp ? userdate($savedtimestamp, get_string('strftimedatetimeshort', 'langconfig')) : null,
                'cmid' => $this->cmid,
                'pageid' => $this->pageid,
            ];
        }

        // Now process turn evaluation questions (for aichat pages with turns mode).
        // In aichat pages, all questions evaluate the page's chat conversation.
        if ($this->page->type === 'aichat' && ($this->page->behavior ?? 'continuous') === 'turns') {
            foreach ($this->questions as $question) {
                if (!$question->enabled) {
                    continue;
                }

                // For turns mode pages, if min_turn is not set, default to 1 (appears from first turn).
                // This allows questions added before min_turn was implemented to still work.
                $minturn = isset($question->min_turn) && $question->min_turn !== null ? $question->min_turn : 1;

                // Load question data (similar to regular questions).
                $settings = [];
                if (!empty($question->settings)) {
                    $settings = json_decode($question->settings, true) ?? [];
                }

                // Get question options if it's a choice/select question.
                $options = [];
                $inputtype = 'radio';
                $ismultiplechoice = false;
                if (in_array($question->type, ['singlechoice', 'multiplechoice', 'select', 'likert'])) {
                    $inputtype = ($question->type === 'singlechoice' || $question->type === 'select' || $question->type === 'likert') ? 'radio' : 'checkbox';
                    $ismultiplechoice = ($question->type === 'multiplechoice');
                    
                    if ($question->type === 'likert') {
                        for ($i = 1; $i <= 5; $i++) {
                            $options[] = [
                                'id' => $i,
                                'value' => (string)$i,
                                'isdefault' => false,
                                'inputtype' => 'radio',
                                'questionid' => $question->questionid,
                                'nameattr' => 'question_' . $question->questionid . '_turn',
                            ];
                        }
                    } else {
                        $questionoptions = $DB->get_records('harpiasurvey_question_options', [
                            'questionid' => $question->questionid
                        ], 'sortorder ASC');
                        $nameattr = 'question_' . $question->questionid . '_turn';
                        if ($ismultiplechoice) {
                            $nameattr .= '[]';
                        }
                        foreach ($questionoptions as $opt) {
                            $options[] = [
                                'id' => $opt->id,
                                'value' => format_string($opt->value),
                                'isdefault' => (bool)$opt->isdefault,
                                'inputtype' => $inputtype,
                                'questionid' => $question->questionid,
                                'nameattr' => $nameattr,
                            ];
                        }
                    }
                }

                // Number field settings.
                $numbersettings = [];
                if ($question->type === 'number') {
                    $min = $settings['min'] ?? null;
                    $max = $settings['max'] ?? null;
                    $allownegatives = !empty($settings['allownegatives']);
                    if (!$allownegatives && $min === null) {
                        $min = 0;
                    }
                    $numbersettings = [
                        'has_min' => $min !== null,
                        'min_value' => $min,
                        'has_max' => $max !== null,
                        'max_value' => $max,
                        'default' => $settings['default'] ?? null,
                        'step' => ($settings['numbertype'] ?? 'integer') === 'integer' ? 1 : 0.01,
                    ];
                }

                $turnevaluationquestions[] = [
                    'id' => $question->questionid,
                    'name' => format_string($question->name),
                    'type' => $question->type,
                    'description' => format_text($question->description, $question->descriptionformat, [
                        'context' => $this->context,
                        'noclean' => false,
                        'overflowdiv' => true
                    ]),
                    'options' => $options,
                    'has_options' => !empty($options),
                    'is_multiplechoice' => $ismultiplechoice,
                    'is_select' => ($question->type === 'select'),
                    'is_likert' => ($question->type === 'likert'),
                    'is_number' => ($question->type === 'number'),
                    'numbersettings' => $numbersettings,
                    'is_shorttext' => ($question->type === 'shorttext'),
                    'is_longtext' => ($question->type === 'longtext'),
                    'min_turn' => $minturn, // Use calculated min_turn (defaults to 1 if not set).
                    'cmid' => $this->cmid,
                    'pageid' => $this->pageid,
                ];
            }
        }

        // Calculate pagination.
        $pagination = [];
        if (!empty($this->allpages) && count($this->allpages) > 1) {
            // Convert associative array to numerically indexed array.
            $pagesarray = array_values($this->allpages);
            $totalpages = count($pagesarray);
            $currentindex = -1;
            
            // Find current page index.
            foreach ($pagesarray as $index => $p) {
                if ($p->id == $this->pageid) {
                    $currentindex = $index;
                    break;
                }
            }

            if ($currentindex >= 0) {
                $currentpagenum = $currentindex + 1;

                // Previous page.
                if ($currentindex > 0) {
                    $prevpage = $pagesarray[$currentindex - 1];
                    $prevurl = new \moodle_url('/mod/harpiasurvey/view_experiment.php', [
                        'id' => $this->cmid,
                        'experiment' => $this->experimentid,
                        'page' => $prevpage->id
                    ]);
                    $pagination['prev_url'] = $prevurl->out(false);
                    $pagination['has_prev'] = true;
                } else {
                    $pagination['has_prev'] = false;
                }

                // Next page.
                if ($currentindex < $totalpages - 1) {
                    $nextpage = $pagesarray[$currentindex + 1];
                    $nexturl = new \moodle_url('/mod/harpiasurvey/view_experiment.php', [
                        'id' => $this->cmid,
                        'experiment' => $this->experimentid,
                        'page' => $nextpage->id
                    ]);
                    $pagination['next_url'] = $nexturl->out(false);
                    $pagination['has_next'] = true;
                } else {
                    $pagination['has_next'] = false;
                }

                // Page numbers.
                $pagenumbers = [];
                foreach ($pagesarray as $index => $p) {
                    $pageurl = new \moodle_url('/mod/harpiasurvey/view_experiment.php', [
                        'id' => $this->cmid,
                        'experiment' => $this->experimentid,
                        'page' => $p->id
                    ]);
                    $pagenumbers[] = [
                        'number' => $index + 1,
                        'url' => $pageurl->out(false),
                        'is_current' => ($p->id == $this->pageid)
                    ];
                }
                $pagination['pages'] = $pagenumbers;
                $pagination['current_page'] = $currentpagenum;
                $pagination['total_pages'] = $totalpages;
            }
        }

        // Load AI chat data if this is an aichat page (chat is part of the page, not a question).
        $aichatdata = [];
        $hasaichat = false;
        $pagebehavior = $this->page->behavior ?? 'continuous';
        $isturnsmode = ($pagebehavior === 'turns');
        
        // Check if page type is aichat and behavior is not multi_model.
        if ($this->page->type === 'aichat' && $pagebehavior !== 'multi_model') {
            $hasaichat = true;
            
            // Load models associated with this page.
            $pagemodels = $DB->get_records('harpiasurvey_page_models', ['pageid' => $this->pageid], '', 'modelid');
            $modelids = array_keys($pagemodels);
            $models = [];
            if (!empty($modelids)) {
                $modelsrecords = $DB->get_records_list('harpiasurvey_models', 'id', $modelids, 'name ASC');
                foreach ($modelsrecords as $model) {
                    if ($model->enabled) {
                        $models[] = [
                            'id' => $model->id,
                            'name' => format_string($model->name),
                            'model' => $model->model // Full model identifier (e.g., gpt-4o-2024-08-06)
                        ];
                    }
                }
            }
            // Note: Chat will appear even without models (showing a warning message)
            
            // Load conversation history for this page (not tied to a question anymore).
            $conversationhistory = [];
            if ($this->pageid && $USER->id) {
                $messages = $DB->get_records('harpiasurvey_conversations', [
                    'pageid' => $this->pageid,
                    'userid' => $USER->id
                ], 'timecreated ASC');
                
                foreach ($messages as $msg) {
                    $roleobj = new \stdClass();
                    $roleobj->{$msg->role} = true;
                    
                    $msgdata = [
                        'id' => $msg->id,
                        'role' => $roleobj,
                        'content' => format_text($msg->content, FORMAT_MARKDOWN, [
                            'context' => $this->context,
                            'noclean' => false,
                            'overflowdiv' => true
                        ]),
                        'parentid' => $msg->parentid,
                        'turn_id' => $msg->turn_id,
                        'timecreated' => $msg->timecreated
                    ];
                    
                    
                    $conversationhistory[] = $msgdata;
                }
            }
            
            // Prepare JSON for turn evaluation questions (for JavaScript).
            $turnevaluationquestionsjson = '';
            if (!empty($turnevaluationquestions)) {
                $turnevaluationquestionsjson = json_encode($turnevaluationquestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $aichatdata = [
                'models' => $models,
                'has_models' => !empty($models),
                'behavior' => $pagebehavior,
                'is_turns_mode' => $isturnsmode, // Explicit boolean.
                'conversation_history' => $conversationhistory,
                'has_history' => !empty($conversationhistory),
                'turn_evaluation_questions' => $turnevaluationquestions,
                'has_turn_evaluation_questions' => !empty($turnevaluationquestions),
                'turn_evaluation_questions_json' => $turnevaluationquestionsjson
            ];
        } else {
            // Ensure aichatdata is always an array, even if not an aichat page
            $aichatdata = [
                'models' => [],
                'has_models' => false,
                'behavior' => 'continuous',
                'is_turns_mode' => false,
                'conversation_history' => [],
                'has_history' => false,
                'turn_evaluation_questions' => [],
                'has_turn_evaluation_questions' => false,
                'turn_evaluation_questions_json' => ''
            ];
        }


        return [
            'title' => format_string($this->page->title),
            'description' => $description,
            'has_description' => $hasdescription,
            'editurl' => $this->editurl,
            'has_editurl' => !empty($this->editurl),
            'canmanage' => $this->canmanage,
            'questions' => $questionslist,
            'has_questions' => !empty($questionslist),
            'has_non_aiconversation_questions' => $hasnonaiconversationquestions,
            'turn_evaluation_questions' => $turnevaluationquestions,
            'has_turn_evaluation_questions' => !empty($turnevaluationquestions),
            'pagination' => $pagination,
            'has_pagination' => !empty($pagination),
            'wwwroot' => $CFG->wwwroot,
            'cmid' => $this->cmid,
            'pageid' => $this->pageid,
            'is_aichat_page' => $hasaichat,
            'aichat' => $aichatdata,
        ];
    }
}

