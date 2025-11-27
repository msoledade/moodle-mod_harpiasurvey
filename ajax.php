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
} else if (in_array($action, ['save_response', 'send_ai_message', 'get_conversation_history', 'get_turn_responses'])) {
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
        
        // Filter out questions already on the page.
        $availablequestions = [];
        foreach ($questions as $question) {
            if (!in_array($question->id, $pagequestionids)) {
                
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
            'is_aichat_page' => ($page_type === 'aichat')
        ]);
        break;
        
    case 'add_question_to_page':
        require_sesskey();
        
        $questionid = required_param('questionid', PARAM_INT);
        
        // Check if question belongs to this harpiasurvey instance.
        $question = $DB->get_record('harpiasurvey_questions', [
            'id' => $questionid,
            'harpiasurveyid' => $harpiasurvey->id
        ], '*', MUST_EXIST);
        
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
        
        // Get page to check if it's a turns mode page.
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
        
        // Add question to page.
        $pagequestion = new stdClass();
        $pagequestion->pageid = $pageid;
        $pagequestion->questionid = $questionid;
        $pagequestion->sortorder = ($maxsort !== false) ? $maxsort + 1 : 0;
        $pagequestion->timecreated = time();
        $pagequestion->enabled = 1;
        
        // For aichat pages with turns mode, set min_turn = 1 by default.
        // All questions on aichat pages evaluate the page's chat conversation.
        if ($page->type === 'aichat' && ($page->behavior ?? 'continuous') === 'turns') {
            $pagequestion->min_turn = 1; // Default to appearing from turn 1.
        }
        
        $DB->insert_record('harpiasurvey_page_questions', $pagequestion);
        
        echo json_encode([
            'success' => true,
            'message' => get_string('questionaddedtopage', 'mod_harpiasurvey')
        ]);
        break;
        
    case 'save_response':
        require_sesskey();
        
        $questionid = required_param('questionid', PARAM_INT);
        $response = optional_param('response', '', PARAM_RAW);
        // Get turn_id - can be 0 or positive integer, or null for regular questions.
        $turn_id_param = optional_param('turn_id', null, PARAM_RAW);
        $turn_id = null;
        if ($turn_id_param !== null && $turn_id_param !== '' && $turn_id_param !== 'null') {
            $turn_id = (int)$turn_id_param;
            // Only accept positive integers (turn IDs start at 1).
            if ($turn_id <= 0) {
                $turn_id = null;
            }
        }
        
        // Debug: Log turn_id for troubleshooting.
        debugging("save_response: turn_id_param = " . var_export($turn_id_param, true) . ", turn_id = " . var_export($turn_id, true) . " for questionid = {$questionid}, pageid = {$pageid}", DEBUG_NORMAL);
        
        // Verify page belongs to this harpiasurvey instance.
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
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
        // Use SQL to properly handle NULL values for turn_id.
        if ($turn_id !== null) {
            // For turn evaluation questions, search with specific turn_id.
            $existing = $DB->get_record('harpiasurvey_responses', [
                'pageid' => $pageid,
                'questionid' => $questionid,
                'userid' => $USER->id,
                'turn_id' => $turn_id
            ]);
        } else {
            // For regular questions, turn_id must be NULL.
            $existing = $DB->get_record_sql(
                "SELECT * FROM {harpiasurvey_responses} 
                 WHERE pageid = ? AND questionid = ? AND userid = ? AND turn_id IS NULL",
                [$pageid, $questionid, $USER->id]
            );
        }
        
        if ($existing) {
            // Update existing response.
            $existing->response = $response;
            $existing->timemodified = time();
            // Ensure turn_id is preserved on update.
            if ($turn_id !== null) {
                $existing->turn_id = $turn_id;
            }
            $DB->update_record('harpiasurvey_responses', $existing);
        } else {
            // Create new response.
            $newresponse = new stdClass();
            $newresponse->pageid = $pageid;
            $newresponse->questionid = $questionid;
            $newresponse->userid = $USER->id;
            $newresponse->response = $response;
            // Explicitly set turn_id - use null for regular questions, integer for turn evaluations.
            if ($turn_id !== null) {
                $newresponse->turn_id = $turn_id;
            } else {
                $newresponse->turn_id = null;
            }
            $newresponse->timecreated = time();
            $newresponse->timemodified = time();
            $insertedid = $DB->insert_record('harpiasurvey_responses', $newresponse);
            
            // Debug: Log if turn_id is being saved correctly.
            $verify = $DB->get_record('harpiasurvey_responses', ['id' => $insertedid], 'id, turn_id, pageid, questionid, userid');
            debugging("save_response: After insert - id = {$insertedid}, turn_id = " . var_export($verify->turn_id ?? null, true) . ", expected = " . var_export($turn_id, true), DEBUG_NORMAL);
            if ($turn_id !== null && $verify && $verify->turn_id != $turn_id) {
                // Log error but don't fail - this is just for debugging.
                debugging("Warning: turn_id mismatch after insert. Expected: {$turn_id}, Got: " . var_export($verify->turn_id, true), DEBUG_NORMAL);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => get_string('responsesaved', 'mod_harpiasurvey'),
            'turn_id' => $turn_id // Return turn_id for confirmation.
        ]);
        break;

    case 'get_turn_responses':
        require_sesskey();

        $turn_id = required_param('turn_id', PARAM_INT);

        // Verify page belongs to this harpiasurvey instance.
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page'
            ]);
            break;
        }

        // Get all responses for this turn.
        $responses = $DB->get_records('harpiasurvey_responses', [
            'pageid' => $pageid,
            'userid' => $USER->id,
            'turn_id' => $turn_id
        ], 'questionid ASC', 'questionid, response, timemodified');
        
        $responsesArray = [];
        foreach ($responses as $response) {
            $responsesArray[$response->questionid] = [
                'response' => $response->response,
                'timemodified' => $response->timemodified
            ];
        }
        
        echo json_encode([
            'success' => true,
            'responses' => $responsesArray
        ]);
        break;
        
    case 'send_ai_message':
        require_sesskey();
        
        $message = required_param('message', PARAM_RAW);
        $modelid = required_param('modelid', PARAM_INT);
        $parentid = optional_param('parentid', 0, PARAM_INT);
        $requested_turn_id = optional_param('turn_id', null, PARAM_INT); // Optional: requested turn ID from frontend.
        
        // Verify page belongs to this harpiasurvey instance and is aichat type.
        // First get the page (already loaded at line 43, but we need to verify experiment).
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
        
        // Get the experiment for this page.
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page'
            ]);
            break;
        }
        
        if ($page->type !== 'aichat') {
            echo json_encode([
                'success' => false,
                'message' => 'This page is not an AI chat page'
            ]);
            break;
        }
        
        // Verify model belongs to this harpiasurvey instance and is enabled.
        $model = $DB->get_record('harpiasurvey_models', [
            'id' => $modelid,
            'harpiasurveyid' => $harpiasurvey->id,
            'enabled' => 1
        ], '*', MUST_EXIST);
        
        // Verify model is associated with this page.
        $pagemodel = $DB->get_record('harpiasurvey_page_models', [
            'pageid' => $pageid,
            'modelid' => $modelid
        ]);
        if (!$pagemodel) {
            echo json_encode([
                'success' => false,
                'message' => 'Model is not associated with this page'
            ]);
            break;
        }
        
        // Get behavior from page (continuous, turns, multi_model).
        $behavior = $page->behavior ?? 'continuous';
        
        // For turns mode, use requested turn_id if provided, otherwise calculate it.
        $turn_id = null;
        if ($behavior === 'turns') {
            if ($requested_turn_id !== null) {
                // Use the turn_id requested by the frontend (the viewing turn).
                $turn_id = $requested_turn_id;
            } else {
                // Fallback: Get the last turn_id for this user and page.
                $lastturn = $DB->get_record_sql(
                    "SELECT MAX(turn_id) as max_turn FROM {harpiasurvey_conversations} 
                     WHERE pageid = ? AND userid = ? AND turn_id IS NOT NULL",
                    [$pageid, $USER->id]
                );
                $turn_id = ($lastturn && $lastturn->max_turn) ? ($lastturn->max_turn + 1) : 1;
            }
        }
        
        // Save user message to database (questionid is NULL for page-level conversations).
        $usermessage = new stdClass();
        $usermessage->pageid = $pageid;
        $usermessage->questionid = null; // No longer tied to a question.
        $usermessage->userid = $USER->id;
        $usermessage->modelid = $modelid;
        $usermessage->role = 'user';
        $usermessage->content = $message;
        $usermessage->parentid = $parentid > 0 ? $parentid : null;
        $usermessage->turn_id = $turn_id;
        $usermessage->timecreated = time();
        $usermessageid = $DB->insert_record('harpiasurvey_conversations', $usermessage);
        
        // Get conversation history for context (if chat mode).
        $history = [];
        if ($behavior === 'chat' || $behavior === 'continuous') {
            $historyrecords = $DB->get_records('harpiasurvey_conversations', [
                'pageid' => $pageid,
                'userid' => $USER->id
            ], 'timecreated ASC');
            
            foreach ($historyrecords as $record) {
                $history[] = [
                    'role' => $record->role,
                    'content' => $record->content
                ];
            }
        } else if ($behavior === 'turns') {
            // For turns mode, get only messages from the current turn.
            // For now, we'll get all messages (can be refined later).
            $historyrecords = $DB->get_records('harpiasurvey_conversations', [
                'pageid' => $pageid,
                'userid' => $USER->id
            ], 'timecreated ASC');
            
            foreach ($historyrecords as $record) {
                $history[] = [
                    'role' => $record->role,
                    'content' => $record->content
                ];
            }
        } else {
            // Q&A mode: only include current message.
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
            $aimessage->questionid = null; // No longer tied to a question.
            $aimessage->userid = $USER->id;
            $aimessage->modelid = $modelid;
            $aimessage->role = 'assistant';
            $aimessage->content = $response['content'];
            $aimessage->parentid = $usermessageid;
            $aimessage->turn_id = $turn_id; // Same turn_id as user message.
            $aimessage->timecreated = time();
            $aimessageid = $DB->insert_record('harpiasurvey_conversations', $aimessage);
            
            // Format content as markdown for display.
            $content = $response['content'] ?? '';
            if (empty($content)) {
                $content = 'Empty response from AI';
            }
            
            $formattedcontent = format_text($content, FORMAT_MARKDOWN, [
                'context' => $cm->context,
                'noclean' => false,
                'overflowdiv' => true
            ]);
            
            // Ensure content is a string (format_text can return empty string but we want to be safe).
            if (!is_string($formattedcontent)) {
                $formattedcontent = (string)$formattedcontent;
            }
            
            echo json_encode([
                'success' => true,
                'messageid' => $aimessageid,
                'content' => $formattedcontent,
                'parentid' => $usermessageid,
                'user_message_turn_id' => $turn_id, // Turn ID for the user message.
                'turn_id' => $turn_id // Turn ID for the AI message (same turn).
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $response['error'] ?? 'Error communicating with AI service'
            ]);
        }
        break;
        
    case 'get_conversation_history':
        // Verify page belongs to this harpiasurvey instance.
        // First get the page (already loaded at line 43, but we need to verify experiment).
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
        
        // Get the experiment for this page.
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page'
            ]);
            break;
        }
        
        if ($page->type !== 'aichat') {
            echo json_encode([
                'success' => false,
                'message' => 'This page is not an AI chat page'
            ]);
            break;
        }
        
        // Get conversation history for this user and page.
        $messages = $DB->get_records('harpiasurvey_conversations', [
            'pageid' => $pageid,
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
        
    case 'save_turn_evaluation':
        require_sesskey();
        
        $turn_id = required_param('turn_id', PARAM_INT);
        $rating = optional_param('rating', null, PARAM_INT);
        $comment = optional_param('comment', '', PARAM_TEXT);
        
        // Verify page belongs to this harpiasurvey instance.
        if (!$page || $page->id != $pageid) {
            $page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
        }
        
        // Get the experiment for this page.
        $experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
        if ($experiment->harpiasurveyid != $harpiasurvey->id) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid page'
            ]);
            break;
        }
        
        // Verify turn exists and belongs to this user.
        $turnmessage = $DB->get_record('harpiasurvey_conversations', [
            'pageid' => $pageid,
            'userid' => $USER->id,
            'turn_id' => $turn_id,
            'role' => 'assistant'
        ]);
        if (!$turnmessage) {
            echo json_encode([
                'success' => false,
                'message' => 'Turn not found'
            ]);
            break;
        }
        
        // Check if evaluation already exists.
        $existing = $DB->get_record('harpiasurvey_turn_evaluations', [
            'pageid' => $pageid,
            'userid' => $USER->id,
            'turn_id' => $turn_id
        ]);
        
        if ($existing) {
            // Update existing evaluation.
            $existing->rating = $rating;
            $existing->comment = $comment;
            $existing->timemodified = time();
            $DB->update_record('harpiasurvey_turn_evaluations', $existing);
        } else {
            // Create new evaluation.
            $evaluation = new stdClass();
            $evaluation->pageid = $pageid;
            $evaluation->turn_id = $turn_id;
            $evaluation->userid = $USER->id;
            $evaluation->rating = $rating;
            $evaluation->comment = $comment;
            $evaluation->timecreated = time();
            $evaluation->timemodified = time();
            $DB->insert_record('harpiasurvey_turn_evaluations', $evaluation);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Evaluation saved'
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

