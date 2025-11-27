<?php
// Temporary debug script - remove after debugging
define('CLI_SCRIPT', false);
require(__DIR__.'/../../config.php');
require_login();

$pageid = required_param('pageid', PARAM_INT);

global $DB;

$page = $DB->get_record('harpiasurvey_pages', ['id' => $pageid], '*', MUST_EXIST);
echo "<h2>Page Data:</h2>";
echo "<pre>";
var_dump([
    'id' => $page->id,
    'title' => $page->title,
    'type' => $page->type,
    'behavior' => $page->behavior ?? 'NULL',
]);
echo "</pre>";

$pagemodels = $DB->get_records('harpiasurvey_page_models', ['pageid' => $pageid]);
echo "<h2>Page Models (count: " . count($pagemodels) . "):</h2>";
echo "<pre>";
var_dump($pagemodels);
echo "</pre>";

// Get experiment to get harpiasurveyid
$experiment = $DB->get_record('harpiasurvey_experiments', ['id' => $page->experimentid], '*', MUST_EXIST);
$models = $DB->get_records('harpiasurvey_models', ['harpiasurveyid' => $experiment->harpiasurveyid, 'enabled' => 1]);
echo "<h2>Available Models (count: " . count($models) . "):</h2>";
echo "<pre>";
var_dump($models);
echo "</pre>";

// Check if chat should appear
$pagebehavior = $page->behavior ?? 'continuous';
$shouldappear = ($page->type === 'aichat' && $pagebehavior !== 'multi_model');
echo "<h2>Chat Should Appear:</h2>";
echo "<pre>";
var_dump([
    'type === aichat' => ($page->type === 'aichat'),
    'behavior !== multi_model' => ($pagebehavior !== 'multi_model'),
    'should_appear' => $shouldappear,
    'has_models' => (count($pagemodels) > 0)
]);
echo "</pre>";

