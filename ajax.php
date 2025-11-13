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

define('AJAX_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);
$pageid = required_param('pageid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'harpiasurvey');
require_login($course, true, $cm);

// Different capabilities for different actions.
if (in_array($action, ['add_question_to_page', 'get_available_questions'])) {
    require_capability('mod/harpiasurvey:manageexperiments', $cm->context);
} else if (in_array($action, ['save_response', 'send_ai_message', 'get_conversation_history'])) {
    // Students can save their own responses and interact with AI.
    require_capability('mod/harpiasurvey:view', $cm->context);
}

// Verify sesskey for write operations.
if (in_array($action, ['add_question_to_page', 'save_response', 'send_ai_message'])) {
    require_sesskey();
}

$harpiasurvey = $DB->get_record('harpiasurvey', ['id' => $cm->instance], '*', MUST_EXIST);
$page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);

header('Content-Type: application/json');

switch ($action) {
    case 'get_available_questions':
        // Get page type to filter questions appropriately
        $page_type = $page->type ?? '';
        
        // Get all questions for this harpiasurvey instance.
        $questions = $DB->get_records('harpiasurvey_questions', ['harpiasurveyid' => $harpiasurvey->id], 'name ASC');
        
        // Get questions already on this page.
        $pagequestionids = $DB->get_records('harpiasurvey_page_questions', ['pageid' => $pageid], '', 'questionid');
        $pagequestionids = array_keys($pagequestionids);
        
        // Get conversation questions on this page (for aichat pages, to show in dropdown).
        $conversationquestions = [];
        if ($page_type === 'aichat') {
            $conversationquestions_sql = "SELECT pq.questionid, q.name
                                            FROM {harpiasurvey_page_questions} pq
                                            JOIN {harpiasurvey_questions} q ON q.id = pq.questionid
                                           WHERE pq.pageid = :pageid AND q.type = 'aiconversation' AND pq.enabled = 1
                                        ORDER BY pq.sortorder ASC";
            $conversationrecords = $DB->get_records_sql($conversationquestions_sql, ['pageid' => $pageid]);
            foreach ($conversationrecords as $conv) {
                $conversationquestions[] = [
                    'id' => $conv->questionid,
                    'name' => format_string($conv->name)
                ];
            }
        }
        
        // Filter out questions already on the page and filter by page type.
        $availablequestions = [];
        foreach ($questions as $question) {
            if (!in_array($question->id, $pagequestionids)) {
                // Filter: aiconversation questions only allowed on aichat pages
                // Non-aiconversation questions are allowed on all pages
                if ($question->type === 'aiconversation' && $page_type !== 'aichat') {
                    continue; // Skip aiconversation questions on non-aichat pages
                }
                
                $description = format_text($question->description, $question->descriptionformat, [
                    'context' => $cm->context,
                    'noclean' => false
                ]);
                $description = strip_tags($description);
                if (strlen($description) > 100) {
                    $description = substr($description, 0, 100) . '...';
                }
                
                $availablequestions[] = [
                    'id' => $question->id,
                    'name' => format_string($question->name),
                    'type' => $question->type,
                    'typedisplay' => get_string('type' . $question->type, 'mod_harpiasurvey'),
                    'description' => $description,
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'questions' => $availablequestions,
            'is_aichat_page' => ($page_type === 'aichat'),
            'conversation_questions' => $conversationquestions
        ]);
        break;
        
    case 'add_question_to_page':
        require_sesskey();
        
        $questionid = required_param('questionid', PARAM_INT);
        $evaluates_conversation_id = optional_param('evaluates_conversation_id', 0, PARAM_INT);
        
        // Check if question belongs to this harpiasurvey instance.
        $question = $DB->get_record('harpiasurvey_questions', [
            'id' => $questionid,
            'harpiasurveyid' => $harpiasurvey->id
        ], '*', MUST_EXIST);
        
        // Validate: aiconversation questions can only be added to aichat pages
        if ($question->type === 'aiconversation' && $page->type !== 'aichat') {
            echo json_encode([
                'success' => false,
                'message' => 'AI conversation questions can only be added to AI Chat pages.'
            ]);
            break;
        }
        
        // Validate: if evaluates_conversation_id is provided, it must be a valid conversation question on this page
        if ($evaluates_conversation_id > 0) {
            $conversationquestion = $DB->get_record_sql(
                "SELECT pq.questionid, q.type
                   FROM {harpiasurvey_page_questions} pq
                   JOIN {harpiasurvey_questions} q ON q.id = pq.questionid
                  WHERE pq.pageid = :pageid AND pq.questionid = :convquestionid AND q.type = 'aiconversation'",
                ['pageid' => $pageid, 'convquestionid' => $evaluates_conversation_id]
            );
            if (!$conversationquestion) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid conversation question selected.'
                ]);
                break;
            }
        }
        
        // Check if question is already on this page.
        $existing = $DB->get_record('harpiasurvey_page_questions', [
            'pageid' => $pageid,
            'questionid' => $questionid
        ]);
        
        if ($existing) {
            echo json_encode([
                'success' => false,
                'message' => get_string('questionalreadyonpage', 'mod_harpiasurvey')
            ]);
            break;
        }
        
        // Get max sort order.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {harpiasurvey_page_questions} WHERE pageid = ?",
            [$pageid]
        );
        
        // Add question to page.
        $pagequestion = new stdClass();
        $pagequestion->pageid = $pageid;
        $pagequestion->questionid = $questionid;
        $pagequestion->sortorder = ($maxsort !== false) ? $maxsort + 1 : 0;
        $pagequestion->timecreated = time();
        $pagequestion->enabled = 1;
        if ($evaluates_conversation_id > 0) {
            $pagequestion->evaluates_conversation_id = $evaluates_conversation_id;
        }
        $DB->insert_record('harpiasurvey_page_questions', $pagequestion);
        
        echo json_encode([
            'success' => true,
            'message' => get_string('questionaddedtopage', 'mod_harpiasurvey')
        ]);
        break;
        
    case 'update_evaluates_conversation':
        require_sesskey();
        
        $pagequestionid = required_param('pagequestionid', PARAM_INT);
        $evaluates_conversation_id = optional_param('evaluates_conversation_id', 0, PARAM_INT);
        
        // Get the page question record.
        $pagequestion = $DB->get_record('harpiasurvey_page_questions', ['id' => $pagequestionid], '*', MUST_EXIST);
        
        // Verify the page belongs to this harpiasurvey instance.
        $page = $DB->get_record('harpiasurvey_pages', ['id' => $pagequestion->pageid], '*', MUST_EXIST);
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page question'
            ]);
            break;
        }
        
        // Validate: if evaluates_conversation_id is provided, it must be a valid conversation question on this page
        if ($evaluates_conversation_id > 0) {
            $conversationquestion = $DB->get_record_sql(
                "SELECT pq.questionid, q.type
                   FROM {harpiasurvey_page_questions} pq
                   JOIN {harpiasurvey_questions} q ON q.id = pq.questionid
                  WHERE pq.pageid = :pageid AND pq.questionid = :convquestionid AND q.type = 'aiconversation'",
                ['pageid' => $page->id, 'convquestionid' => $evaluates_conversation_id]
            );
            if (!$conversationquestion) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid conversation question selected.'
                ]);
                break;
            }
        }
        
        // Update the evaluates_conversation_id.
        $pagequestion->evaluates_conversation_id = ($evaluates_conversation_id > 0) ? $evaluates_conversation_id : null;
        $DB->update_record('harpiasurvey_page_questions', $pagequestion);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conversation relationship updated'
        ]);
        break;
        
    case 'save_response':
        require_sesskey();
        
        $questionid = required_param('questionid', PARAM_INT);
        $response = optional_param('response', '', PARAM_RAW);
        
        // Verify page belongs to this harpiasurvey instance.
        $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page'
            ]);
            break;
        }
        
        // Verify question belongs to this harpiasurvey instance.
        $question = $DB->get_record('harpiasurvey_questions', [
            'id' => $questionid,
            'harpiasurveyid' => $harpiasurvey->id
        ], '*', MUST_EXIST);
        
        // Check if response already exists.
        $existing = $DB->get_record('harpiasurvey_responses', [
            'pageid' => $pageid,
            'questionid' => $questionid,
            'userid' => $USER->id
        ]);
        
        if ($existing) {
            // Update existing response.
            $existing->response = $response;
            $existing->timemodified = time();
            $DB->update_record('harpiasurvey_responses', $existing);
        } else {
            // Create new response.
            $newresponse = new stdClass();
            $newresponse->pageid = $pageid;
            $newresponse->questionid = $questionid;
            $newresponse->userid = $USER->id;
            $newresponse->response = $response;
            $newresponse->timecreated = time();
            $newresponse->timemodified = time();
            $DB->insert_record('harpiasurvey_responses', $newresponse);
        }
        
        echo json_encode([
            'success' => true,
            'message' => get_string('responsesaved', 'mod_harpiasurvey')
        ]);
        break;
        
    case 'send_ai_message':
        require_sesskey();
        
        $questionid = required_param('questionid', PARAM_INT);
        $message = required_param('message', PARAM_RAW);
        $modelid = required_param('modelid', PARAM_INT);
        $parentid = optional_param('parentid', 0, PARAM_INT);
        
        // Verify question belongs to this harpiasurvey instance and is AI conversation type.
        $question = $DB->get_record('harpiasurvey_questions', [
            'id' => $questionid,
            'harpiasurveyid' => $harpiasurvey->id,
            'type' => 'aiconversation'
        ], '*', MUST_EXIST);
        
        // Verify model belongs to this harpiasurvey instance and is enabled.
        $model = $DB->get_record('harpiasurvey_models', [
            'id' => $modelid,
            'harpiasurveyid' => $harpiasurvey->id,
            'enabled' => 1
        ], '*', MUST_EXIST);
        
        // Load question settings to get behavior and template.
        $settings = json_decode($question->settings, true) ?? [];
        $behavior = $settings['behavior'] ?? 'chat';
        $template = $settings['template'] ?? '';
        
        // Save user message to database.
        $usermessage = new stdClass();
        $usermessage->pageid = $pageid;
        $usermessage->questionid = $questionid;
        $usermessage->userid = $USER->id;
        $usermessage->modelid = $modelid;
        $usermessage->role = 'user';
        $usermessage->content = $message;
        $usermessage->parentid = $parentid > 0 ? $parentid : null;
        $usermessage->timecreated = time();
        $usermessageid = $DB->insert_record('harpiasurvey_conversations', $usermessage);
        
        // Get conversation history for context (if chat mode).
        $history = [];
        if ($behavior === 'chat') {
            $historyrecords = $DB->get_records('harpiasurvey_conversations', [
                'questionid' => $questionid,
                'userid' => $USER->id
            ], 'timecreated ASC');
            
            foreach ($historyrecords as $record) {
                $history[] = [
                    'role' => $record->role,
                    'content' => $record->content
                ];
            }
        } else {
            // Q&A mode: only include system template and current message.
            if (!empty($template)) {
                $history[] = [
                    'role' => 'system',
                    'content' => $template
                ];
            }
            $history[] = [
                'role' => 'user',
                'content' => $message
            ];
        }
        
        // Call LLM API.
        require_once(__DIR__ . '/classes/llm_service.php');
        $llmservice = new \mod_harpiasurvey\llm_service($model);
        $response = $llmservice->send_message($history);
        
        if ($response['success']) {
            // Save AI response to database (store raw markdown).
            $aimessage = new stdClass();
            $aimessage->pageid = $pageid;
            $aimessage->questionid = $questionid;
            $aimessage->userid = $USER->id;
            $aimessage->modelid = $modelid;
            $aimessage->role = 'assistant';
            $aimessage->content = $response['content'];
            $aimessage->parentid = $usermessageid;
            $aimessage->timecreated = time();
            $aimessageid = $DB->insert_record('harpiasurvey_conversations', $aimessage);
            
            // Format content as markdown for display.
            $formattedcontent = format_text($response['content'], FORMAT_MARKDOWN, [
                'context' => $cm->context,
                'noclean' => false,
                'overflowdiv' => true
            ]);
            
            echo json_encode([
                'success' => true,
                'messageid' => $aimessageid,
                'content' => $formattedcontent,
                'parentid' => $usermessageid
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $response['error'] ?? 'Error communicating with AI service'
            ]);
        }
        break;
        
    case 'get_conversation_history':
        $questionid = required_param('questionid', PARAM_INT);
        
        // Verify question belongs to this harpiasurvey instance.
        $question = $DB->get_record('harpiasurvey_questions', [
            'id' => $questionid,
            'harpiasurveyid' => $harpiasurvey->id
        ], '*', MUST_EXIST);
        
        // Get conversation history for this user and question.
        $messages = $DB->get_records('harpiasurvey_conversations', [
            'questionid' => $questionid,
            'userid' => $USER->id
        ], 'timecreated ASC');
        
        $history = [];
        foreach ($messages as $msg) {
            $history[] = [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'parentid' => $msg->parentid,
                'timecreated' => $msg->timecreated
            ];
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $history
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

