<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_harpiasurvey;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for interacting with LLM APIs.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llm_service {

    /**
     * @var object Model record from database
     */
    protected $model;

    /**
     * Constructor.
     *
     * @param object $model Model record from harpiasurvey_models table
     */
    public function __construct($model) {
        $this->model = $model;
    }

    /**
     * Send a message to the LLM API.
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @return array Response array with 'success', 'content' (if success), or 'error' (if failure)
     */
    public function send_message(array $messages): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        if (empty($this->model->endpoint)) {
            return [
                'success' => false,
                'error' => 'Model endpoint not configured'
            ];
        }

        // Prepare request payload.
        $payload = [
            'model' => $this->model->model,
            'messages' => $messages
        ];

        // Add extra fields if configured.
        if (!empty($this->model->extrafields)) {
            $extrafields = json_decode($this->model->extrafields, true);
            if (is_array($extrafields)) {
                $payload = array_merge($payload, $extrafields);
            }
        }

        // Prepare headers.
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Add API key if configured.
        if (!empty($this->model->apikey)) {
            $headers[] = 'Authorization: Bearer ' . trim($this->model->apikey);
        }

        // Make HTTP request.
        $ch = curl_init($this->model->endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        $curlerrno = curl_errno($ch);

        curl_close($ch);

        if ($curlerrno) {
            return [
                'success' => false,
                'error' => 'CURL error: ' . $curlerror
            ];
        }

        if ($httpcode >= 200 && $httpcode < 300) {
            $responsedata = json_decode($response, true);
            
            // Extract content from response (supports OpenAI-compatible format).
            $content = '';
            if (isset($responsedata['choices'][0]['message']['content'])) {
                $content = $responsedata['choices'][0]['message']['content'];
            } else if (isset($responsedata['content'])) {
                $content = $responsedata['content'];
            } else if (isset($responsedata['message'])) {
                $content = $responsedata['message'];
            }

            if (empty($content)) {
                return [
                    'success' => false,
                    'error' => 'No content in API response'
                ];
            }

            return [
                'success' => true,
                'content' => $content
            ];
        } else {
            $errordata = json_decode($response, true);
            $errormessage = $errordata['error']['message'] ?? 'HTTP ' . $httpcode;
            
            return [
                'success' => false,
                'error' => $errormessage
            ];
        }
    }
}

