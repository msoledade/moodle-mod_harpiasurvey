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
 * Plugin strings are defined here.
 *
 * @package     mod_harpiasurvey
 * @category    string
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Harpia Survey';
$string['modulename'] = 'Harpia Survey';
$string['modulenameplural'] = 'Harpia Surveys';
$string['modulename_help'] = 'The Harpia Survey module allows researchers to create experiments for studying LLMs, interact with them, and collect quality survey responses from students.';
$string['harpiasurveyname'] = 'Survey name';
$string['harpiasurveyname_help'] = 'The name of this Harpia Survey activity.';
$string['harpiasurvey:addinstance'] = 'Add a new Harpia Survey';
$string['harpiasurvey:view'] = 'View Harpia Survey';
$string['harpiasurvey:manageexperiments'] = 'Manage experiments';
$string['noharpiasurveys'] = 'No Harpia Survey instances';
$string['pluginadministration'] = 'Harpia Survey administration';
$string['eventcourse_module_viewed'] = 'Course module viewed';
$string['experimentname'] = 'Experiment';
$string['participants'] = 'Participants';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['view'] = 'View';
$string['noexperiments'] = 'No experiments available yet.';
$string['createexperiment'] = 'Create experiment';
$string['registermodel'] = 'Register model';
$string['viewstats'] = 'View statistics';
$string['statusrunning'] = 'Running';
$string['statusfinished'] = 'Finished';
$string['statusdraft'] = 'Draft';
$string['newexperiment'] = 'New experiment';
$string['editexperiment'] = 'Edit experiment';
$string['type'] = 'Type';
$string['typequestionsanswers'] = 'Questions & answers';
$string['typedialogue'] = 'Dialogue';
$string['models'] = 'Models';
$string['searchmodels'] = 'Search models...';
$string['validation'] = 'Validation';
$string['validationnone'] = 'No validation';
$string['validationcrossvalidation'] = 'Cross-validation';
$string['available'] = 'Available';
$string['continue'] = 'Continue';
$string['experimentsaved'] = 'Experiment saved successfully';
$string['errormodelsrequired'] = 'At least one model must be selected';
$string['description'] = 'Description';
$string['newmodel'] = 'New model';
$string['editmodel'] = 'Edit model';
$string['modelname'] = 'Name';
$string['modelidentifier'] = 'Model';
$string['modelidentifier_help'] = 'The model identifier used by the API (e.g., gpt-4o-2025712).';
$string['baseurl'] = 'Base URL';
$string['baseurl_help'] = 'The base URL for the API endpoint.';
$string['apikey'] = 'API Key';
$string['apikey_help'] = 'The API key for authenticating with the service.';
$string['extrafields'] = 'Extra fields';
$string['extrafields_help'] = 'Additional parameters as JSON (e.g., {"temperature": 0}). Formatting and spacing will be preserved.';
$string['enabled'] = 'Enabled';
$string['addmodel'] = 'Add model';
$string['modelsaved'] = 'Model saved successfully';
$string['invalidurl'] = 'Invalid URL format';
$string['invalidjson'] = 'Invalid JSON format';
$string['maxparticipants'] = 'Max participants';
$string['startdate'] = 'Start date';
$string['enddate'] = 'End date';
$string['invalidnumber'] = 'Invalid number';
$string['unlimited'] = 'Unlimited';
$string['pages'] = 'Pages';
$string['addpage'] = 'Add page';
$string['editingpage'] = 'Editing page: {$a}';
$string['addingpage'] = 'Adding a new page';
$string['nopages'] = 'No pages yet. Click "Add page" to create the first page.';
$string['backtocourse'] = 'Back to course';
$string['title'] = 'Title';
$string['typeopening'] = 'Opening';
$string['typedemographic'] = 'Demographic data collection';
$string['typeinteraction'] = 'Model interaction';
$string['typefeedback'] = 'Feedback';
$string['typeaichat'] = 'AI Chat';
$string['save'] = 'Save';
$string['pagesaved'] = 'Page saved successfully';
$string['selectpagetoadd'] = 'Select a page from the list to edit, or click "Add page" to create a new one.';
$string['questionbank'] = 'Question bank';
$string['questions'] = 'Questions';
$string['question'] = 'Question';
$string['noquestions'] = 'No questions yet. Click "Create question" to add the first one.';
$string['createquestion'] = 'Create question';
$string['editingquestion'] = 'Editing question: {$a}';
$string['creatingquestion'] = 'Creating question';
$string['questionname'] = 'Name';
$string['questionshortname'] = 'Short name';
$string['questionsaved'] = 'Question saved successfully';
$string['back'] = 'Back';
$string['savechanges'] = 'Save changes';
$string['typesinglechoice'] = 'Single choice';
$string['typemultiplechoice'] = 'Multiple choice';
$string['typeselect'] = 'Select';
$string['typelikert'] = 'Likert (1-5 scale)';
$string['typenumber'] = 'Number';
$string['typeshorttext'] = 'Short text';
$string['typelongtext'] = 'Long text';
$string['typeaichat'] = 'AI Model Evaluation';
$string['typegeneral'] = 'General';
$string['pagebehavior'] = 'Evaluation Mode';
$string['pagebehavior_help'] = 'Choose how the AI model evaluation works: Continuous (ongoing conversation), Turns (turn-based navigation with conversation tree), Multi-model (future).';
$string['pagebehaviorcontinuous'] = 'Continuous';
$string['pagebehaviorturns'] = 'Turns';
$string['pagebehaviormultimodel'] = 'Multi-model';
$string['type_help'] = 'Depending on the question type, the fields below will change to specific settings.';
$string['selectionfieldsettings'] = 'Selection field settings';
$string['optionsoneline'] = 'Options (one per line)';
$string['optionsoneline_help'] = 'Enter each option on a separate line.';
$string['defaultvalue'] = 'Default value';
$string['numberfieldsettings'] = 'Number field settings';
$string['numbertype'] = 'Number type';
$string['numbertypeinteger'] = 'Integer';
$string['numbertypedecimal'] = 'Decimal';
$string['numbermin'] = 'Minimum value';
$string['numbermin_help'] = 'Minimum allowed value for this number field.';
$string['numbermax'] = 'Maximum value';
$string['numbermax_help'] = 'Maximum allowed value for this number field.';
$string['numberdefault'] = 'Default value';
$string['numberallownegatives'] = 'Allow negative values';
$string['aiconversationsettings'] = 'AI Conversation settings';
$string['aimodels'] = 'Models';
$string['aimodels_help'] = 'Select one or more AI models to use for this conversation question.';
$string['aibehavior'] = 'Behavior';
$string['aibehavior_help'] = 'Choose how the AI should interact: Q&A for single question-answer, Chat for ongoing conversation.';
$string['aibehaviorqa'] = 'Question and answer';
$string['aibehaviorchat'] = 'Chat';
$string['aitemplate'] = 'Template';
$string['aitemplate_help'] = 'System prompt or template to guide the AI\'s behavior in this conversation.';
$string['noselection'] = 'No selection';
$string['aiconversationplaceholder'] = 'AI Conversation interface will be displayed here.';
$string['save'] = 'Save';
$string['saved'] = 'Saved';
$string['responsesaved'] = 'Response saved successfully';
$string['saving'] = 'Saving...';
$string['on'] = 'on';
$string['typeyourmessage'] = 'Type your message here...';
$string['waitingforresponse'] = 'Waiting for AI response...';
$string['nomodelsavailable'] = 'No models are available for this question. Please contact the administrator.';
$string['noquestionsonpage'] = 'No questions added to this page yet.';
$string['savepagetoaddquestions'] = 'Save the page first to add questions.';
$string['addquestiontopage'] = 'Add question to page';
$string['questionaddedtopage'] = 'Question added to page successfully';
$string['questionremovedfrompage'] = 'Question removed from page successfully';
$string['noquestionsavailable'] = 'No questions available. All questions have been added to this page.';
$string['questionalreadyonpage'] = 'This question is already on the page.';
$string['remove'] = 'Remove';
$string['add'] = 'Add';
$string['stats'] = 'Statistics';
$string['noresponses'] = 'No responses yet.';
$string['answer'] = 'Answer';
$string['time'] = 'Time';
$string['responses'] = 'Responses';
$string['conversations'] = 'Conversations';
$string['noconversations'] = 'No conversations yet.';
$string['conversation'] = 'Conversation';
$string['messages'] = 'messages';
$string['viewconversation'] = 'View conversation';
$string['hideconversation'] = 'Hide conversation';
$string['role'] = 'Role';
$string['messageid'] = 'Message ID';
$string['parentid'] = 'Parent ID';
$string['downloadcsv'] = 'Download CSV';
$string['evaluatesconversation'] = 'Evaluates conversation';
$string['evaluatesconversation_help'] = 'Select which AI conversation this question evaluates. Leave empty if this is a general question not tied to a specific conversation.';
$string['pagechatevaluation'] = 'Page chat evaluation';
$string['none'] = 'None';
$string['deletepage'] = 'Delete page';
$string['deletepageconfirm'] = 'Are you sure you want to delete the page "{$a->title}"? This will also delete {$a->questions} question association(s), {$a->responses} response(s), and {$a->conversations} conversation(s). This action cannot be undone.';
$string['pagedeleted'] = 'Page deleted successfully';
$string['deletequestion'] = 'Delete question';
$string['deletequestionconfirm'] = 'Are you sure you want to delete the question "{$a->name}"? This will also delete {$a->pages} page association(s), {$a->options} option(s), {$a->responses} response(s), {$a->conversations} conversation(s), and {$a->evaluates} evaluation relationship(s). This action cannot be undone.';
$string['questiondeleted'] = 'Question deleted successfully';
$string['evaluateturn'] = 'Evaluate this turn';
$string['addcomment'] = 'Add a comment (optional)';
$string['saveevaluation'] = 'Save evaluation';
$string['turn'] = 'Turn';
$string['nextturn'] = 'Next turn';
$string['previousturn'] = 'Previous turn';
$string['turnlocked'] = 'This turn is locked. Navigate to the current turn to send messages.';
$string['gotocurrentturn'] = 'Go to current turn';
$string['createnextturn'] = 'Create next turn';
$string['showpreviousmessages'] = 'Show previous messages';
$string['hidepreviousmessages'] = 'Hide previous messages';

