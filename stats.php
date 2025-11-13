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

// Course module id.
$id = required_param('id', PARAM_INT);
$experimentid = required_param('experiment', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'harpiasurvey');
$harpiasurvey = $DB->get_record('harpiasurvey', ['id' => $cm->instance], '*', MUST_EXIST);
$experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $experimentid, 'harpiasurveyid' => $harpiasurvey->id], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/harpiasurvey:manageexperiments', $cm->context);

$context = $cm->context;

// Check for CSV download.
$download = optional_param('download', '', PARAM_ALPHA);

// Set up page (only if not downloading).
if ($download !== 'csv') {
    $url = new moodle_url('/mod/harpiasurvey/stats.php', ['id' => $cm->id, 'experiment' => $experiment->id]);
    $PAGE->set_url($url);
    $PAGE->set_title(format_string($experiment->name) . ': ' . get_string('stats', 'mod_harpiasurvey'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    // Load module stylesheet.
    $PAGE->requires->css('/mod/harpiasurvey/styles.css');

    // Add breadcrumb.
    $PAGE->navbar->add(format_string($harpiasurvey->name), new moodle_url('/mod/harpiasurvey/view.php', ['id' => $cm->id]));
    $PAGE->navbar->add(format_string($experiment->name), new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]));
    $PAGE->navbar->add(get_string('stats', 'mod_harpiasurvey'));

    echo $OUTPUT->header();
    
    // Display page heading.
    echo $OUTPUT->heading(format_string($experiment->name) . ': ' . get_string('stats', 'mod_harpiasurvey'), 2);
}

// Get all pages for this experiment.
$pages = $DB->get_records('harpiasurvey_pages', ['experimentid' => $experiment->id], 'sortorder ASC, timecreated ASC');
$pageids = array_keys($pages);

if (empty($pageids)) {
    echo $OUTPUT->notification(get_string('nopages', 'mod_harpiasurvey'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Get all responses for this experiment (all pages).
$responses = [];
if (!empty($pageids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
    $sql = "SELECT r.id, r.userid, r.questionid, r.pageid, r.response, r.timecreated, r.timemodified,
                   q.name AS questionname, q.type AS questiontype, q.settings AS questionsettings,
                   pq.evaluates_conversation_id,
                   cq.name AS evaluates_conversation_name
              FROM {harpiasurvey_responses} r
              JOIN {harpiasurvey_questions} q ON q.id = r.questionid
              JOIN {harpiasurvey_page_questions} pq ON pq.pageid = r.pageid AND pq.questionid = r.questionid
         LEFT JOIN {harpiasurvey_questions} cq ON cq.id = pq.evaluates_conversation_id
             WHERE r.pageid $insql
          ORDER BY r.userid ASC, q.name ASC, r.timecreated ASC";

    $responses = $DB->get_records_sql($sql, $inparams);
}

// Get all conversations for this experiment (grouped by user and question).
$conversations = [];
if (!empty($pageids)) {
    list($convinsql, $convparams) = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
    $convsql = "SELECT c.id, c.userid, c.questionid, c.role, c.content, c.timecreated, c.parentid,
                       q.name AS questionname, q.type AS questiontype
                  FROM {harpiasurvey_conversations} c
                  JOIN {harpiasurvey_questions} q ON q.id = c.questionid
                 WHERE c.pageid $convinsql
              ORDER BY c.userid ASC, c.questionid ASC, c.timecreated ASC";
    
    $allmessages = $DB->get_records_sql($convsql, $convparams);
    
    // Group messages by (userid, questionid) - each group is one conversation
    foreach ($allmessages as $msg) {
        $key = $msg->userid . '_' . $msg->questionid;
        if (!isset($conversations[$key])) {
            $conversations[$key] = [
                'userid' => $msg->userid,
                'questionid' => $msg->questionid,
                'questionname' => $msg->questionname,
                'questiontype' => $msg->questiontype,
                'messages' => []
            ];
        }
        $conversations[$key]['messages'][] = $msg;
    }
}

// Get all unique user IDs from both responses and conversations, then fetch full user records.
$userids = [];
if (!empty($responses)) {
    $userids = array_merge($userids, array_column($responses, 'userid'));
}
if (!empty($conversations)) {
    foreach ($conversations as $conv) {
        $userids[] = $conv['userid'];
    }
}
$userids = array_unique($userids);
if (!empty($userids)) {
    list($userinsql, $userinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $users = $DB->get_records_sql("SELECT * FROM {user} WHERE id $userinsql", $userinparams);
} else {
    $users = [];
}

// Get all question options for questions that have options.
$questionids = array_unique(array_column($responses, 'questionid'));
$alloptions = [];
if (!empty($questionids)) {
    list($qinsql, $qinparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
    $optionrecords = $DB->get_records_sql(
        "SELECT id, questionid, value, sortorder FROM {harpiasurvey_question_options} WHERE questionid $qinsql ORDER BY questionid, sortorder ASC",
        $qinparams
    );
    // Organize options by question ID and option ID, including sortorder for numbering.
    foreach ($optionrecords as $opt) {
        if (!isset($alloptions[$opt->questionid])) {
            $alloptions[$opt->questionid] = [];
        }
        // Store both value and option number (sortorder + 1, since sortorder is 0-indexed).
        $alloptions[$opt->questionid][$opt->id] = [
            'value' => $opt->value,
            'number' => $opt->sortorder + 1
        ];
    }
}

// Format conversations for display.
$conversationslist = [];
foreach ($conversations as $conv) {
    $user = isset($users[$conv['userid']]) ? $users[$conv['userid']] : null;
    if ($user) {
        $fullname = fullname($user);
    } else {
        $fullname = get_string('unknownuser', 'moodle');
    }
    
    $messages = $conv['messages'];
    $messagecount = count($messages);
    $firstmessage = $messages[0];
    $lastmessage = $messages[$messagecount - 1];
    
    // Format messages for display
    $formattedmessages = [];
    foreach ($messages as $msg) {
        $roleobj = new \stdClass();
        $roleobj->{$msg->role} = true;
        
        $formattedmessages[] = [
            'id' => $msg->id,
            'role' => $roleobj,
            'content' => format_text($msg->content, FORMAT_MARKDOWN, [
                'context' => $context,
                'noclean' => false,
                'overflowdiv' => true
            ]),
            'rawcontent' => $msg->content, // For preview and CSV
            'timecreated' => userdate($msg->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            'timestamp' => $msg->timecreated,
            'parentid' => $msg->parentid ?? '' // Include parent_id for CSV export
        ];
    }
    
    // Create preview (first user message or first message)
    $preview = '';
    foreach ($messages as $msg) {
        if ($msg->role === 'user') {
            $preview = $msg->content;
            break;
        }
    }
    if (empty($preview) && !empty($messages)) {
        $preview = $messages[0]->content;
    }
    $preview = strip_tags($preview);
    $preview = strlen($preview) > 100 ? substr($preview, 0, 100) . '...' : $preview;
    
    // Get localized question type string
    $questiontypestring = get_string('type' . $conv['questiontype'], 'mod_harpiasurvey');
    
    $conversationslist[] = [
        'id' => 'conv_' . $conv['userid'] . '_' . $conv['questionid'],
        'userid' => $conv['userid'], // Keep userid for CSV export
        'user' => $fullname,
        'question' => format_string($conv['questionname']),
        'questiontype' => $questiontypestring,
        'messagecount' => $messagecount,
        'preview' => htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'),
        'messages' => $formattedmessages,
        'messagesjson' => htmlspecialchars(json_encode($formattedmessages), ENT_QUOTES, 'UTF-8'),
        'timecreated' => userdate($firstmessage->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'timelast' => userdate($lastmessage->timecreated, get_string('strftimedatetimeshort', 'langconfig'))
    ];
}

// Format responses for display.
$responseslist = [];
foreach ($responses as $response) {
    // Get user object and format name.
    $user = isset($users[$response->userid]) ? $users[$response->userid] : null;
    if ($user) {
        $fullname = fullname($user);
    } else {
        $fullname = get_string('unknownuser', 'moodle');
    }
    
    // Format answer based on question type.
    $answer = $response->response;
    $answeritems = []; // Array of answer items with text and number.
    $islong = false;
    $hasoptions = isset($alloptions[$response->questionid]);
    
    if (!empty($answer)) {
        // Handle different question types.
        if ($response->questiontype === 'likert') {
            // Likert: response is a number (1-5), display as-is with number.
            $answeritems[] = [
                'text' => $answer,
                'number' => (int)$answer,
                'isoption' => true
            ];
        } else if ($hasoptions) {
            // Questions with options: map IDs to option text and numbers.
            // Try to decode JSON (for multiple choice questions).
            $decoded = json_decode($answer, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Multiple choice - map IDs to option text and numbers.
                foreach ($decoded as $optionid) {
                    if (isset($alloptions[$response->questionid][$optionid])) {
                        $opt = $alloptions[$response->questionid][$optionid];
                        $answeritems[] = [
                            'text' => $opt['value'],
                            'number' => $opt['number'],
                            'isoption' => true
                        ];
                    } else {
                        $answeritems[] = [
                            'text' => '[' . $optionid . ']',
                            'number' => null,
                            'isoption' => false
                        ];
                    }
                }
            } else {
                // Single choice or select - map ID to option text and number.
                $optionid = (int)$answer;
                if (isset($alloptions[$response->questionid][$optionid])) {
                    $opt = $alloptions[$response->questionid][$optionid];
                    $answeritems[] = [
                        'text' => $opt['value'],
                        'number' => $opt['number'],
                        'isoption' => true
                    ];
                } else {
                    $answeritems[] = [
                        'text' => '[' . $answer . ']',
                        'number' => null,
                        'isoption' => false
                    ];
                }
            }
        } else {
            // For other question types (shorttext, longtext, number), answer is already text.
            $answeritems[] = [
                'text' => $answer,
                'number' => null,
                'isoption' => false
            ];
        }
        
        // Check if answer is long (more than 100 characters).
        $fulltext = implode(', ', array_column($answeritems, 'text'));
        $islong = strlen($fulltext) > 100;
    } else {
        $answeritems[] = [
            'text' => '-',
            'number' => null,
            'isoption' => false
        ];
    }
    
    // Prepare display answer (truncated if long).
    $fulltext = implode(', ', array_column($answeritems, 'text'));
    $displaytext = $islong ? substr($fulltext, 0, 100) . '...' : $fulltext;
    
    // For expand/collapse, we need to store the full answer items as JSON.
    $answeritemsjson = json_encode($answeritems);
    
    // Get localized question type string
    $questiontypestring = get_string('type' . $response->questiontype, 'mod_harpiasurvey');
    
    // Get evaluates conversation name if available
    $evaluatesconversation = '';
    if (!empty($response->evaluates_conversation_id) && !empty($response->evaluates_conversation_name)) {
        $evaluatesconversation = format_string($response->evaluates_conversation_name);
    }
    
    $responseslist[] = [
        'id' => $response->id,
        'user' => $fullname,
        'question' => format_string($response->questionname),
        'questiontype' => $questiontypestring,
        'evaluatesconversation' => $evaluatesconversation,
        'answeritems' => $answeritems,
        'answeritemsjson' => htmlspecialchars($answeritemsjson, ENT_QUOTES, 'UTF-8'), // For JavaScript reconstruction.
        'answer' => htmlspecialchars($fulltext, ENT_QUOTES, 'UTF-8'), // Escape for data attribute.
        'displayanswer' => htmlspecialchars($displaytext, ENT_QUOTES, 'UTF-8'), // Escape for display.
        'islong' => $islong,
        'timecreated' => userdate($response->timecreated, get_string('strftimedatetimeshort', 'langconfig'))
    ];
}

// Merge conversations into responses list as special entries.
// Conversations are treated as a special type of response.
foreach ($conversationslist as $conv) {
    $responseslist[] = [
        'id' => $conv['id'],
        'user' => $conv['user'],
        'question' => $conv['question'],
        'questiontype' => $conv['questiontype'],
        'evaluatesconversation' => '', // Conversations don't evaluate other conversations
        'isconversation' => true,
        'messagecount' => $conv['messagecount'],
        'preview' => $conv['preview'],
        'messages' => $conv['messages'],
        'messagesjson' => $conv['messagesjson'],
        'timecreated' => $conv['timecreated'],
        'timelast' => $conv['timelast'],
        // Empty fields for compatibility
        'answeritems' => [],
        'answeritemsjson' => '[]',
        'answer' => '',
        'displayanswer' => '',
        'islong' => false
    ];
}

// Sort by user, then by question, then by time
usort($responseslist, function($a, $b) {
    $userCmp = strcmp($a['user'], $b['user']);
    if ($userCmp !== 0) {
        return $userCmp;
    }
    $questionCmp = strcmp($a['question'], $b['question']);
    if ($questionCmp !== 0) {
        return $questionCmp;
    }
    return strcmp($a['timecreated'], $b['timecreated']);
});

// Handle CSV download.
if ($download === 'csv') {
    require_once($CFG->libdir . '/csvlib.class.php');
    
    $filename = clean_filename(format_string($experiment->name) . '_stats');
    $csvexport = new \csv_export_writer('comma');
    $csvexport->set_filename($filename);
    
    // CSV headers
    $headers = [
        get_string('user'),
        get_string('question', 'mod_harpiasurvey'),
        get_string('type', 'mod_harpiasurvey'),
        get_string('evaluatesconversation', 'mod_harpiasurvey'),
        get_string('answer', 'mod_harpiasurvey'),
        get_string('role', 'mod_harpiasurvey'),
        get_string('time', 'mod_harpiasurvey'),
        get_string('messageid', 'mod_harpiasurvey'),
        get_string('parentid', 'mod_harpiasurvey')
    ];
    $csvexport->add_data($headers);
    
    // Export all data
    foreach ($responseslist as $item) {
        if (isset($item['isconversation']) && $item['isconversation']) {
            // For conversations, export each message as a separate row
            foreach ($item['messages'] as $msg) {
                // Get user name
                $userid = $item['userid'] ?? 0;
                $user = isset($users[$userid]) ? $users[$userid] : null;
                $username = $user ? fullname($user) : get_string('unknownuser', 'moodle');
                
                // Determine role string
                $rolestr = '';
                if (isset($msg['role'])) {
                    if (is_object($msg['role'])) {
                        $rolestr = isset($msg['role']->user) ? 'user' : (isset($msg['role']->assistant) ? 'assistant' : '');
                    } else {
                        $rolestr = $msg['role'];
                    }
                }
                
                $row = [
                    $username,
                    $item['question'],
                    $item['questiontype'],
                    '', // No evaluates conversation for conversation entries themselves
                    $msg['rawcontent'] ?? $msg['content'] ?? '', // Use raw content for CSV
                    $rolestr,
                    userdate($msg['timestamp'], get_string('strftimedatetimeshort', 'langconfig')),
                    $msg['id'] ?? '', // Message ID only for conversations
                    $msg['parentid'] ?? '' // Parent ID only for conversations
                ];
                $csvexport->add_data($row);
            }
        } else {
            // For regular responses, export as a single row (no Message ID or Parent ID)
            $fulltext = implode(', ', array_column($item['answeritems'], 'text'));
            $row = [
                $item['user'],
                $item['question'],
                $item['questiontype'],
                $item['evaluatesconversation'] ?? '', // Evaluates conversation name
                $fulltext,
                '', // No role for regular responses
                $item['timecreated'],
                '', // No Message ID for regular responses (to avoid ID conflicts)
                '' // No Parent ID for regular responses
            ];
            $csvexport->add_data($row);
        }
    }
    
    $csvexport->download_file();
    exit;
}

// Create and render stats table.
require_once(__DIR__.'/classes/output/stats_table.php');
$statstable = new \mod_harpiasurvey\output\stats_table($responseslist, $context);
$renderer = $PAGE->get_renderer('mod_harpiasurvey');
$tablehtml = $renderer->render_stats_table($statstable);

// Display the stats table.
echo $tablehtml;

// Load JavaScript for expand/collapse functionality.
$PAGE->requires->js_call_amd('mod_harpiasurvey/stats_table', 'init');

// Back button.
$backurl = new moodle_url('/mod/harpiasurvey/view_experiment.php', ['id' => $cm->id, 'experiment' => $experiment->id]);
echo html_writer::div(
    html_writer::link($backurl, get_string('back', 'moodle'), ['class' => 'btn btn-secondary']),
    'mt-3'
);

echo $OUTPUT->footer();

