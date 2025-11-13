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
 * Save response module for harpiasurvey.
 *
 * @module     mod_harpiasurvey/save_response
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import Config from 'core/config';
import $ from 'jquery';

let initialized = false;

/**
 * Initialize the save response functionality.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 */
export const init = (cmid, pageid) => {
    if (initialized) {
        return;
    }

    // Pre-populate select dropdowns with saved values.
    $('select[data-saved-value]').each(function() {
        const select = $(this);
        const savedValue = select.data('saved-value');
        if (savedValue) {
            select.val(savedValue);
        }
    });

    // Handle save all button click.
    $(document).on('click', '.save-all-responses-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const cmid = parseInt(button.data('cmid'), 10);
        const pageid = parseInt(button.data('pageid'), 10);
        const statusDiv = $('.save-all-status');

        // Collect all questions and their responses.
        const questionsToSave = [];
        $('.question-item').each(function() {
            const questionItem = $(this);
            // Skip AI conversation questions.
            if (questionItem.data('questiontype') === 'aiconversation') {
                return;
            }

            const questionid = parseInt(questionItem.data('questionid'), 10);
            const questiontype = questionItem.data('questiontype');

            if (!questionid || !questiontype) {
                return; // Skip if we can't find question ID or type.
            }

            // Get the response value based on question type.
            let response = '';

            if (questiontype === 'singlechoice' || questiontype === 'likert') {
                const selected = $(`input[name="question_${questionid}"]:checked`);
                if (selected.length > 0) {
                    response = selected.val();
                }
            } else if (questiontype === 'multiplechoice') {
                const selected = $(`input[name="question_${questionid}[]"]:checked`);
                const values = [];
                selected.each(function() {
                    values.push($(this).val());
                });
                response = JSON.stringify(values);
            } else if (questiontype === 'select') {
                const selected = $(`#question_${questionid}`).val();
                if (selected) {
                    response = selected;
                }
            } else if (questiontype === 'number' || questiontype === 'shorttext') {
                response = $(`#question_${questionid}`).val();
            } else if (questiontype === 'longtext') {
                response = $(`#question_${questionid}`).val();
            }

            questionsToSave.push({
                questionid: questionid,
                questiontype: questiontype,
                response: response
            });
        });

        if (questionsToSave.length === 0) {
            Notification.addNotification({
                message: 'No questions to save.',
                type: 'info'
            });
            return;
        }

        // Save all responses sequentially.
        saveAllResponses(cmid, pageid, questionsToSave, button, statusDiv);
    });

    initialized = true;
};

/**
 * Save all responses sequentially.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 * @param {Array} questionsToSave Array of {questionid, questiontype, response} objects
 * @param {jQuery} button Save button element
 * @param {jQuery} statusDiv Status div element
 */
const saveAllResponses = (cmid, pageid, questionsToSave, button, statusDiv) => {
    const originalText = button.text();
    button.prop('disabled', true);
    statusDiv.show().html('<div class="text-info"><i class="fa fa-spinner fa-spin"></i> Saving responses...</div>');

    let savedCount = 0;
    let failedCount = 0;
    const total = questionsToSave.length;

    // Save responses sequentially using Promise chain.
    let promiseChain = Promise.resolve();

    questionsToSave.forEach((questionData, index) => {
        promiseChain = promiseChain.then(() => {
            return new Promise((resolve) => {
                // Update status.
                statusDiv.html(`<div class="text-info"><i class="fa fa-spinner fa-spin"></i> Saving ${index + 1} of ${total}...</div>`);

                const params = new URLSearchParams({
                    action: 'save_response',
                    cmid: cmid,
                    pageid: pageid,
                    questionid: questionData.questionid,
                    response: questionData.response,
                    sesskey: Config.sesskey
                });

                fetch(Config.wwwroot + '/mod/harpiasurvey/ajax.php?' + params.toString(), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        savedCount++;
                        // Update saved message for this question.
                        const now = new Date();
                        const datetimeStr = now.toLocaleString();
                        getString('saved', 'mod_harpiasurvey').then((savedText) => {
                            getString('on', 'mod_harpiasurvey').then((onText) => {
                                const questionItem = $(`.question-item[data-questionid="${questionData.questionid}"]`);
                                const savedMessage = questionItem.find(`.saved-response-message[data-questionid="${questionData.questionid}"]`);
                                const icon = '<i class="fa fa-check-circle" aria-hidden="true"></i>';
                                if (savedMessage.length === 0) {
                                    // Create new message element.
                                    const messageHtml = '<div class="mt-2 small text-muted saved-response-message" ' +
                                        `data-questionid="${questionData.questionid}">` +
                                        `${icon} ${savedText} ${onText} ${datetimeStr}</div>`;
                                    questionItem.append(messageHtml);
                                } else {
                                    // Update existing message timestamp.
                                    savedMessage.html(`${icon} ${savedText} ${onText} ${datetimeStr}`);
                                }
                            });
                        });
                    } else {
                        failedCount++;
                    }
                    resolve();
                })
                .catch(error => {
                    failedCount++;
                    resolve(); // Continue with next question even on error.
                });
            });
        });
    });

    // After all saves complete.
    promiseChain.then(() => {
        button.prop('disabled', false);
        button.text(originalText);

        if (failedCount === 0) {
            // All saved successfully.
            button.removeClass('btn-primary').addClass('btn-success');
            statusDiv.html(`<div class="text-success"><i class="fa fa-check-circle"></i> All ${savedCount} response(s) saved successfully!</div>`);
            setTimeout(() => {
                button.removeClass('btn-success').addClass('btn-primary');
                statusDiv.fadeOut();
            }, 3000);
        } else {
            // Some failed.
            button.removeClass('btn-primary').addClass('btn-warning');
            statusDiv.html(`<div class="text-warning"><i class="fa fa-exclamation-triangle"></i> ${savedCount} saved, ${failedCount} failed. Please try again.</div>`);
            setTimeout(() => {
                button.removeClass('btn-warning').addClass('btn-primary');
            }, 5000);
        }
    });
};

/**
 * Save response via AJAX.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 * @param {number} questionid Question ID
 * @param {string} response Response value
 * @param {jQuery} button Save button element
 */
const saveResponse = (cmid, pageid, questionid, response, button) => {
    const originalText = button.text();
    button.prop('disabled', true);
    getString('saving', 'mod_harpiasurvey').then((savingText) => {
        button.text(savingText);

        const params = new URLSearchParams({
            action: 'save_response',
            cmid: cmid,
            pageid: pageid,
            questionid: questionid,
            response: response,
            sesskey: Config.sesskey
        });

        fetch(Config.wwwroot + '/mod/harpiasurvey/ajax.php?' + params.toString(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message.
                getString('saved', 'mod_harpiasurvey').then((savedText) => {
                    button.text(savedText);
                    button.removeClass('btn-primary').addClass('btn-success');

                    // Update or show saved message with timestamp.
                    const savedMessage = $(`.saved-response-message[data-questionid="${questionid}"]`);
                    if (savedMessage.length === 0) {
                        // Create new message element.
                        const now = new Date();
                        const datetimeStr = now.toLocaleString();
                        getString('on', 'mod_harpiasurvey').then((onText) => {
                            const messageHtml = '<div class="mt-1 small text-muted saved-response-message" ' +
                                `data-questionid="${questionid}">` +
                                '<i class="fa fa-check-circle" aria-hidden="true"></i> ' +
                                `${savedText} ${onText} ${datetimeStr}</div>`;
                            button.after(messageHtml);
                        });
                    } else {
                        // Update existing message timestamp.
                        const now = new Date();
                        const datetimeStr = now.toLocaleString();
                        getString('on', 'mod_harpiasurvey').then((onText) => {
                            const icon = '<i class="fa fa-check-circle" aria-hidden="true"></i>';
                            savedMessage.html(`${icon} ${savedText} ${onText} ${datetimeStr}`);
                        });
                    }

                    // Reset button after 2 seconds.
                    setTimeout(() => {
                        button.prop('disabled', false);
                        button.text(originalText);
                        button.removeClass('btn-success').addClass('btn-primary');
                    }, 2000);
                });
            } else {
                Notification.addNotification({
                    message: data.message || 'Error saving response',
                    type: 'error'
                });
                button.prop('disabled', false);
                button.text(originalText);
            }
        })
        .catch(error => {
            Notification.addNotification({
                message: 'Error saving response: ' + error.message,
                type: 'error'
            });
            button.prop('disabled', false);
            button.text(originalText);
        });
    });
};

