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
 * AMD module for AI conversation chat interface.
 *
 * @module     mod_harpiasurvey/ai_conversation
 * @copyright  2025 Your Name
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Notification from 'core/notification';
import Config from 'core/config';
import $ from 'jquery';

let initialized = false;

// Track current turn for each page (for turns mode).
const currentTurns = {};

/**
 * Initialize the AI conversation functionality.
 */
export const init = () => {
    if (initialized) {
        // eslint-disable-next-line no-console
        console.log('ai_conversation: Already initialized');
        return;
    }

    // eslint-disable-next-line no-console
    console.log('ai_conversation: Initializing...');

    // Initialize turn tracking for turns mode.
    $('.ai-conversation-container[data-behavior="turns"]').each(function() {
        const pageid = parseInt($(this).data('pageid'), 10);
        if (pageid && !currentTurns[pageid]) {
            // Get the highest turn number from existing messages.
            const messagesContainer = $(`#chat-messages-page-${pageid}`);
            let maxTurn = 0;
            messagesContainer.find('[data-turn-id]').each(function() {
                const turnId = parseInt($(this).data('turn-id'), 10);
                if (turnId && turnId > maxTurn) {
                    maxTurn = turnId;
                }
            });
            // Current turn is the highest turn, or 1 if no turns exist.
            currentTurns[pageid] = maxTurn > 0 ? maxTurn : 1;
            setViewingTurn(pageid, currentTurns[pageid]);
            updateTurnDisplay(pageid);
            updateChatLockState(pageid);
            // Filter messages to show only current turn initially (hide previous).
            filterMessagesByTurn(pageid, currentTurns[pageid], false);
        }
    });

    // Handle send button clicks.
    $(document).on('click', '.chat-send-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const cmid = parseInt(button.data('cmid'), 10);
        const pageid = parseInt(button.data('pageid'), 10);

        if (!cmid || !pageid) {
            Notification.addNotification({
                message: 'Missing cmid or pageid',
                type: 'error'
            });
            return;
        }

        const input = $(`#chat-input-page-${pageid}`);
        if (input.length === 0) {
            Notification.addNotification({
                message: 'Chat input not found',
                type: 'error'
            });
            return;
        }

        const inputValue = input.val();
        if (!inputValue) {
            return;
        }

        const message = inputValue.trim();

        if (!message) {
            return;
        }

        // Check if chat is locked (viewing a past turn).
        const container = button.closest('.ai-conversation-container');
        const behavior = container.data('behavior');
        if (behavior === 'turns') {
            const viewingTurn = getViewingTurn(pageid);
            const currentTurn = getCurrentTurn(pageid);
            if (viewingTurn !== currentTurn) {
                Notification.addNotification({
                    message: 'Cannot send messages in a locked turn. ' +
                        'Navigate to the current turn first.',
                    type: 'error'
                });
                return;
            }
        }

        // Get model from container data attribute (first available model).
        const modelsdata = container.data('models');
        let modelid = null;

        if (modelsdata) {
            // modelsdata is a comma-separated string, get first one.
            const modelids = modelsdata.split(',');
            if (modelids.length > 0) {
                modelid = parseInt(modelids[0], 10);
            }
        }

        if (!modelid) {
            Notification.addNotification({
                message: 'No model available',
                type: 'error'
            });
            return;
        }

        // Disable input and button.
        input.prop('disabled', true);
        button.prop('disabled', true);

        // Clear input.
        input.val('');

        // Display user message with temporary ID (will be updated with real ID from server).
        const tempId = 'temp-user-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        displayUserMessage(pageid, message, tempId);

        // Show loading indicator.
        const loading = $(`#chat-loading-page-${pageid}`);
        loading.show();

        // Send message to AI.
        sendMessage(cmid, pageid, message, modelid, button, input, loading);
    });

    // Handle Enter key in textarea (Shift+Enter for new line, Enter to send).
    $(document).on('keydown', '.chat-input', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $(this).closest('.ai-conversation-container').find('.chat-send-btn').click();
        }
    });

    // Handle save turn evaluation questions button clicks.
    // Use event delegation on document to catch dynamically added buttons.
    $(document).on('click', '.save-turn-evaluation-questions-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // eslint-disable-next-line no-console
        console.log('=== Save turn evaluation button clicked! ===');

        const button = $(this);
        const turnId = parseInt(button.data('turn-id'), 10);
        const pageid = parseInt(button.data('pageid'), 10);
        const cmid = parseInt(button.data('cmid'), 10);

        // eslint-disable-next-line no-console
        console.log('Button element:', button[0]);
        // eslint-disable-next-line no-console
        console.log('Button data attributes:', {
            'data-turn-id': button.attr('data-turn-id'),
            'data-pageid': button.attr('data-pageid'),
            'data-cmid': button.attr('data-cmid')
        });
        // eslint-disable-next-line no-console
        console.log('Parsed values:', {turnId, pageid, cmid});

        // Validate turnId (must be >= 1), pageid and cmid (must be > 0).
        if (!turnId || turnId < 1 || !pageid || pageid <= 0 || !cmid || cmid <= 0) {
            // eslint-disable-next-line no-console
            console.error('Validation failed:', {turnId, pageid, cmid});
            Notification.addNotification({
                message: 'Missing required data (turnId: ' + turnId + ', pageid: ' + pageid + ', cmid: ' + cmid + ')',
                type: 'error'
            });
            return;
        }

        // Get all question responses from the evaluation container.
        const evaluationContainer = button.closest('.turn-evaluation-questions');
        const responses = {};

        evaluationContainer.find('[data-questionid]').each(function() {
            const questionId = $(this).data('questionid');
            const questionType = $(this).data('questiontype');
            let responseValue = null;

            if (questionType === 'multiplechoice') {
                // Multiple choice: collect all checked values.
                const checked = evaluationContainer.find(`input[name="question_${questionId}_turn[]"]:checked`);
                const values = [];
                checked.each(function() {
                    values.push($(this).val());
                });
                responseValue = values.length > 0 ? JSON.stringify(values) : null;
            } else if (questionType === 'select' || questionType === 'singlechoice' || questionType === 'likert') {
                // Single choice: get selected value.
                const selectSelector = `select[name="question_${questionId}_turn"]`;
                const inputSelector = `input[name="question_${questionId}_turn"]:checked`;
                const selected = evaluationContainer.find(selectSelector + ', ' + inputSelector);
                responseValue = selected.length > 0 ? selected.val() : null;
            } else if (questionType === 'number' || questionType === 'shorttext') {
                // Number or short text: get input value.
                const input = evaluationContainer.find(`input[name="question_${questionId}_turn"]`);
                responseValue = input.length > 0 ? input.val() : null;
            } else if (questionType === 'longtext') {
                // Long text: get textarea value.
                const textarea = evaluationContainer.find(`textarea[name="question_${questionId}_turn"]`);
                responseValue = textarea.length > 0 ? textarea.val() : null;
            }

            if (responseValue !== null && responseValue !== '') {
                responses[questionId] = responseValue;
            }
        });

        // Disable button.
        button.prop('disabled', true);
        const originalText = button.text();
        button.text('Saving...');

        // Save all responses for this turn.
        saveTurnEvaluationQuestions(cmid, pageid, turnId, responses, button, originalText);
    });

    // Handle next turn button clicks.
    $(document).on('click', '.next-turn-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const pageid = parseInt(button.data('pageid'), 10);
        const cmid = parseInt(button.data('cmid'), 10);

        if (!pageid || !cmid) {
            Notification.addNotification({
                message: 'Missing pageid or cmid',
                type: 'error'
            });
            return;
        }

        const viewingTurn = getViewingTurn(pageid);
        const currentTurn = getCurrentTurn(pageid);

        if (viewingTurn < currentTurn) {
            // If viewing a past turn, go to current turn.
            setViewingTurn(pageid, currentTurn);
            updateTurnDisplay(pageid);
            updateChatLockState(pageid);
            filterMessagesByTurn(pageid, currentTurn, false); // Hide previous messages.
            // Render questions and load saved responses for current turn.
            ensureTurnEvaluationQuestionsRendered(pageid, currentTurn).then(() => {
                loadTurnEvaluationResponses(pageid, currentTurn);
            });
        } else {
            // If viewing current turn, create next turn.
            // The next turn becomes the new current turn, so chat should remain unlocked.
            const nextTurn = currentTurn + 1;
            setViewingTurn(pageid, nextTurn);
            currentTurns[pageid] = nextTurn;
            updateTurnDisplay(pageid);
            updateChatLockState(pageid); // This will unlock since nextTurn === currentTurn now.
            filterMessagesByTurn(pageid, nextTurn, false); // Hide previous messages by default.
            // Render questions for new turn and clear form (no saved responses yet).
            ensureTurnEvaluationQuestionsRendered(pageid, nextTurn).then(() => {
                clearTurnEvaluationForm(pageid, nextTurn);
            });
        }
    });

    // Handle previous turn button clicks.
    $(document).on('click', '.prev-turn-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const pageid = parseInt(button.data('pageid'), 10);

        if (!pageid) {
            Notification.addNotification({
                message: 'Missing pageid',
                type: 'error'
            });
            return;
        }

        const viewingTurn = getViewingTurn(pageid);
        if (viewingTurn > 1) {
            const prevTurn = viewingTurn - 1;
            setViewingTurn(pageid, prevTurn);
            updateTurnDisplay(pageid);
            updateChatLockState(pageid);
            filterMessagesByTurn(pageid, prevTurn, false); // Hide previous messages.
            // Render questions and load saved responses for previous turn.
            ensureTurnEvaluationQuestionsRendered(pageid, prevTurn).then(() => {
                loadTurnEvaluationResponses(pageid, prevTurn);
            });
        }
    });

    // Handle show/hide previous messages button clicks.
    $(document).on('click', '.show-previous-messages-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const pageid = parseInt(button.data('pageid'), 10);
        const showing = button.data('showing') || false;
        const viewingTurn = getViewingTurn(pageid);

        if (!pageid) {
            return;
        }

        // Toggle showing previous messages.
        filterMessagesByTurn(pageid, viewingTurn, !showing);
    });

    initialized = true;

    // eslint-disable-next-line no-console
    console.log('ai_conversation: Initialization complete. Event handlers registered.');
};

/**
 * Display user message in chat.
 *
 * @param {number} pageid Page ID
 * @param {string} message Message content
 * @param {number} messageid Optional message ID to prevent duplicates
 */
const displayUserMessage = (pageid, message, messageid = null) => {
    const messagesContainer = $(`#chat-messages-page-${pageid}`);
    const placeholder = messagesContainer.find('.text-muted.text-center');

    if (placeholder.length > 0) {
        placeholder.remove();
    }

    // Check if message already exists (by ID).
    if (messageid) {
        const existing = messagesContainer.find(`[data-messageid="${messageid}"]`);
        if (existing.length > 0) {
            return; // Message already displayed.
        }
    }

    // Also check for duplicate content in the last few messages (prevent double-sending).
    const recentMessages = messagesContainer.find('.message').slice(-3);
    let isDuplicate = false;
    recentMessages.each(function() {
        const contentEl = $(this).find('.content');
        if (contentEl.length === 0) {
            return true; // Continue to next message.
        }
        const msgContent = contentEl.text();
        if (msgContent && msgContent.trim() === message.trim()) {
            isDuplicate = true;
            return false; // Break loop.
        }
    });
    if (isDuplicate && !messageid) {
        // If it's a duplicate and no ID provided, don't add it.
        return;
    }

    // Double-check for duplicate before rendering (race condition protection).
    if (messageid) {
        const existing = messagesContainer.find(`[data-messageid="${messageid}"]`);
        if (existing.length > 0) {
            return; // Message already displayed.
        }
    }

    // Ensure message is a string.
    const safeMessage = (message && typeof message === 'string') ? message : '';

    // Get current viewing turn for turns mode.
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    const behavior = container.data('behavior');
    const viewingTurn = behavior === 'turns' ? getViewingTurn(pageid) : null;

    const templateData = {
        content: safeMessage,
        id: messageid || 'temp-' + Date.now()
    };

    if (viewingTurn) {
        templateData.turn_id = viewingTurn;
    }

    Templates.render('mod_harpiasurvey/chat_user_message', templateData).then((html) => {
        // Final check before appending (prevent race conditions).
        if (messageid) {
            const existing = messagesContainer.find(`[data-messageid="${messageid}"]`);
            if (existing.length > 0) {
                return; // Message was added while we were rendering.
            }
        }
        Templates.appendNodeContents(messagesContainer[0], html);
        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(pageid);
        }, 50);
    }).catch(() => {
        // Fallback if template fails.
        // Final check before appending.
        if (messageid) {
            const existing = messagesContainer.find(`[data-messageid="${messageid}"]`);
            if (existing.length > 0) {
                return;
            }
        }
        let messageHtml = '<div class="message my-3" data-messageid="' + (messageid || 'temp-' + Date.now()) + '"';
        if (viewingTurn) {
            messageHtml += ' data-turn-id="' + viewingTurn + '"';
        }
        messageHtml += '><div class="d-flex justify-content-end">' +
            '<div class="border rounded p-2 bg-primary text-white" style="max-width: 80%;">' +
            '<div class="content">' + message + '</div>' +
            '</div></div></div>';
        messagesContainer.append(messageHtml);
        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(pageid);
        }, 50);
    });
};

/**
 * Display AI message in chat.
 *
 * @param {number} pageid Page ID
 * @param {string} content Message content
 * @param {number} messageid Message ID
 * @param {number|null} turnId Turn ID (for turns mode)
 */
const displayAIMessage = (pageid, content, messageid, turnId = null) => {
    const messagesContainer = $(`#chat-messages-page-${pageid}`);

    if (!messageid) {
        // eslint-disable-next-line no-console
        console.error('displayAIMessage called without messageid');
        return;
    }

    if (!content) {
        // eslint-disable-next-line no-console
        console.error('displayAIMessage called without content');
        return;
    }

    // Check if message already exists.
    const existing = messagesContainer.find(`[data-messageid="${messageid}"]`);
    if (existing.length > 0) {
        // eslint-disable-next-line no-console
        console.log('AI message already displayed:', messageid);
        return; // Message already displayed.
    }

    // Ensure content is a string and not empty.
    const safeContent = (content && typeof content === 'string') ? content : '';

    const templateData = {
        id: messageid,
        content: safeContent
    };

    if (turnId) {
        templateData.turn_id = turnId;
        templateData.pageid = pageid;
        // Get cmid from container.
        const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
        templateData.cmid = container.data('cmid');
    }

    Templates.render('mod_harpiasurvey/chat_ai_message', templateData).then((html) => {
        // Final check before appending (prevent race conditions).
        const existingCheck = messagesContainer.find(`[data-messageid="${messageid}"]`);
        if (existingCheck.length > 0) {
            return; // Message was added while we were rendering.
        }
        Templates.appendNodeContents(messagesContainer[0], html);

        // If this is a turn-based message, load evaluation questions.
        if (turnId) {
            setTimeout(() => {
                ensureTurnEvaluationQuestionsRendered(pageid, turnId).then(() => {
                    // Load saved responses for this turn.
                    loadTurnEvaluationResponses(pageid, turnId);
                });
            }, 100);

            // Update current turn if this is a new turn.
            const currentTurn = getCurrentTurn(pageid);
            if (turnId > currentTurn) {
                currentTurns[pageid] = turnId;
                updateTurnDisplay(pageid);
            }

            // After displaying AI message, filter to show only current turn (hide previous).
            const viewingTurn = getViewingTurn(pageid);
            if (viewingTurn === turnId) {
                filterMessagesByTurn(pageid, turnId, false);
            }
        }

        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(pageid);
        }, 50);
    }).catch(() => {
        // Fallback if template fails.
        // Final check before appending.
        const existingCheck = messagesContainer.find(`[data-messageid="${messageid}"]`);
        if (existingCheck.length > 0) {
            return;
        }
        const messageHtml = '<div class="message my-3" data-messageid="' + messageid + '">' +
            '<div class="d-flex">' +
            '<div class="border rounded p-2 bg-light" style="max-width: 80%;">' +
            '<div class="content">' + content + '</div>' +
            '</div></div></div>';
        messagesContainer.append(messageHtml);
        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(pageid);
        }, 50);
    });
};

/**
 * Send message to AI via AJAX.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 * @param {string} message Message content
 * @param {number} modelid Model ID
 * @param {jQuery} button Send button element
 * @param {jQuery} input Input textarea element
 * @param {jQuery} loading Loading indicator element
 */
const sendMessage = (cmid, pageid, message, modelid, button, input, loading) => {
    // Get current viewing turn (for turns mode).
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    const behavior = container.data('behavior');
    const viewingTurn = behavior === 'turns' ? getViewingTurn(pageid) : null;

    const params = new URLSearchParams({
        action: 'send_ai_message',
        cmid: cmid,
        pageid: pageid,
        message: message,
        modelid: modelid,
        sesskey: Config.sesskey
    });

    // For turns mode, send the viewing turn so backend can use it.
    if (behavior === 'turns' && viewingTurn) {
        params.append('turn_id', viewingTurn);
    }

    fetch(Config.wwwroot + '/mod/harpiasurvey/ajax.php?' + params.toString(), {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        loading.hide();

        if (data.success) {
            // Update user message with actual ID from server (if we have parentid).
            if (data.parentid) {
                // Find the last user message (the one we just sent) and update its ID and turn_id.
                const messagesContainer = $(`#chat-messages-page-${pageid}`);
                const userMessages = messagesContainer.find('.message').filter(function() {
                    return $(this).find('.bg-primary').length > 0;
                });
                if (userMessages.length > 0) {
                    const lastUserMsg = userMessages.last();
                    const currentId = lastUserMsg.attr('data-messageid');
                    // Only update if it's a temporary ID.
                    if (currentId && currentId.startsWith('temp-')) {
                        lastUserMsg.attr('data-messageid', data.parentid);
                        // Update turn_id if provided.
                        if (data.user_message_turn_id) {
                            lastUserMsg.attr('data-turn-id', data.user_message_turn_id);
                        }
                    }
                }
            }

            // Display AI response (check for duplicates first).
            if (data.messageid && data.content) {
                displayAIMessage(pageid, data.content, data.messageid, data.turn_id);

                // Update current turn if a new turn was created.
                if (data.turn_id) {
                    const currentTurn = getCurrentTurn(pageid);
                    if (data.turn_id > currentTurn) {
                        currentTurns[pageid] = data.turn_id;
                        updateTurnDisplay(pageid);
                        updateChatLockState(pageid);
                    }
                }
            } else {
                Notification.addNotification({
                    message: 'Received response but missing message ID or content',
                    type: 'error'
                });
            }
        } else {
            Notification.addNotification({
                message: data.message || 'Error sending message',
                type: 'error'
            });
        }

        // Re-enable input and button.
        input.prop('disabled', false);
        button.prop('disabled', false);
        input.focus();
    })
    .catch(error => {
        loading.hide();
        // eslint-disable-next-line no-console
        console.error('Error sending message:', error);
        Notification.addNotification({
            message: 'Error sending message: ' + error.message,
            type: 'error'
        });
        input.prop('disabled', false);
        button.prop('disabled', false);
        input.focus();
    });
};

/**
 * Scroll chat to bottom.
 *
 * @param {number} pageid Page ID
 */
const scrollToBottom = (pageid) => {
    const messagesContainer = $(`#chat-messages-page-${pageid}`);
    if (messagesContainer.length === 0) {
        return;
    }

    // Use requestAnimationFrame to ensure DOM is updated before scrolling.
    requestAnimationFrame(() => {
        const container = messagesContainer[0];
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
};

/**
 * Ensure turn evaluation questions are rendered for a specific turn.
 * This will render them if they don't exist, or do nothing if they already exist.
 *
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 * @return {Promise} Promise that resolves when questions are rendered
 */
const ensureTurnEvaluationQuestionsRendered = (pageid, turnId) => {
    // Check if questions are already rendered for this turn.
    // Look in the container below the chat, not inside messages.
    const containerWrapper = $(`#turn-evaluation-questions-container-${pageid}`);
    if (containerWrapper.length === 0) {
        // Container wrapper doesn't exist - not in turns mode or page not loaded yet.
        return Promise.resolve();
    }

    const existingContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);
    if (existingContainer.length > 0 && existingContainer.find('.question-item').length > 0) {
        // Questions already rendered, return resolved promise.
        return Promise.resolve();
    }

    // Questions not rendered yet, render them.
    return loadTurnEvaluationQuestions(pageid, turnId);
};

/**
 * Load and render turn evaluation questions for a specific turn.
 *
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 * @return {Promise} Promise that resolves when questions are rendered
 */
const loadTurnEvaluationQuestions = (pageid, turnId) => {
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    const questionsDataEl = $(`#turn-evaluation-questions-data-${pageid}`);

    if (questionsDataEl.length === 0) {
        return Promise.resolve(); // No evaluation questions configured.
    }

    try {
        const allQuestions = JSON.parse(questionsDataEl.text());
        // Filter questions that should appear for this turn (min_turn <= turnId).
        const questionsForTurn = allQuestions.filter(q => (q.min_turn || 1) <= turnId);

        if (questionsForTurn.length === 0) {
            return Promise.resolve(); // No questions for this turn.
        }

        // Find or create the evaluation container below the chat (not inside messages).
        const containerWrapper = $(`#turn-evaluation-questions-container-${pageid}`);
        if (containerWrapper.length === 0) {
            // Container wrapper doesn't exist - this shouldn't happen in turns mode, but handle gracefully.
            return Promise.resolve();
        }

        // Find or create the container for this specific turn.
        let evaluationContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);
        if (evaluationContainer.length === 0) {
            // Create container for this turn.
            const containerHtml = `<div class="turn-evaluation-questions mt-3 pt-3 border-top" data-turn-id="${turnId}" data-pageid="${pageid}" data-cmid="${cmid}"></div>`;
            containerWrapper.append(containerHtml);
            evaluationContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);
        }

        // Render questions using the same template as regular questions.
        const cmid = container.data('cmid');
        // eslint-disable-next-line no-console
        console.log('Rendering turn evaluation questions:', {pageid, turnId, cmid, questionsCount: questionsForTurn.length});

        return Templates.render('mod_harpiasurvey/turn_evaluation_questions', {
            questions: questionsForTurn,
            has_questions: questionsForTurn.length > 0,
            turn_id: turnId,
            pageid: pageid,
            cmid: cmid
        }).then((html) => {
            // Clear container first to remove any previous questions.
            evaluationContainer.html(html);
            // eslint-disable-next-line no-console
            console.log('Turn evaluation questions rendered. HTML length:', html.length);
            // Verify button exists after rendering.
            const button = evaluationContainer.find('.save-turn-evaluation-questions-btn');
            // eslint-disable-next-line no-console
            console.log('Save button found after render:', button.length, 'Button data:', {
                turnId: button.data('turn-id'),
                pageid: button.data('pageid'),
                cmid: button.data('cmid')
            });
            // Return promise that resolves after rendering is complete.
            return Promise.resolve();
        }).catch((error) => {
            // eslint-disable-next-line no-console
            console.error('Error rendering turn evaluation questions:', error);
            return Promise.reject(error);
        });
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Error parsing turn evaluation questions data:', error);
    }
};

/**
 * Save turn evaluation questions responses.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 * @param {object} responses Object with questionId -> responseValue mappings
 * @param {jQuery} button Save button element
 * @param {string} originalText Original button text
 */
const saveTurnEvaluationQuestions = (cmid, pageid, turnId, responses, button, originalText) => {
    // Validate turnId before proceeding.
    if (!turnId || turnId < 1) {
        Notification.addNotification({
            message: 'Invalid turn ID',
            type: 'error'
        });
        button.prop('disabled', false);
        button.text(originalText);
        return;
    }

    // Save each response individually.
    const savePromises = Object.keys(responses).map(questionId => {
        const params = new URLSearchParams({
            action: 'save_response',
            cmid: cmid,
            pageid: pageid,
            questionid: questionId,
            response: responses[questionId],
            turn_id: turnId.toString(), // Ensure it's a string for URLSearchParams.
            sesskey: Config.sesskey
        });

        // Debug: Log the URL being called.
        const url = Config.wwwroot + '/mod/harpiasurvey/ajax.php?' + params.toString();
        // eslint-disable-next-line no-console
        console.log('Saving turn evaluation response:', {
            questionId: questionId,
            turnId: turnId,
            url: url
        });

        return fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.json()).then(data => {
            // eslint-disable-next-line no-console
            console.log('Response from save:', data);
            return data;
        });
    });

    Promise.all(savePromises).then(results => {
        const allSuccess = results.every(r => r.success);
        if (allSuccess) {
            // Don't show notification for turn evaluation questions - they save silently.
            // Just update button state and show timestamps.
            const containerWrapper = $(`#turn-evaluation-questions-container-${pageid}`);
            const evaluationContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);
            const now = Math.floor(Date.now() / 1000); // Current timestamp in seconds.
            
            // Show timestamps for all saved questions.
            Object.keys(responses).forEach(questionId => {
                showTurnEvaluationTimestamp(evaluationContainer, questionId, now);
            });
        } else {
            Notification.addNotification({
                message: 'Some responses could not be saved',
                type: 'error'
            });
        }
        button.prop('disabled', false);
        button.text(originalText);
    }).catch(error => {
        // eslint-disable-next-line no-console
        console.error('Error saving turn evaluation questions:', error);
        Notification.addNotification({
            message: 'Error saving responses: ' + error.message,
            type: 'error'
        });
        button.prop('disabled', false);
        button.text(originalText);
    });
};

/**
 * Load saved responses for a specific turn and populate the form.
 *
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 */
const loadTurnEvaluationResponses = (pageid, turnId) => {
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    const cmid = container.data('cmid');
    const containerWrapper = $(`#turn-evaluation-questions-container-${pageid}`);
    const evaluationContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);

    if (evaluationContainer.length === 0) {
        return; // Container not found.
    }

    const params = new URLSearchParams({
        action: 'get_turn_responses',
        cmid: cmid,
        pageid: pageid,
        turn_id: turnId,
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
        if (data.success && data.responses && Object.keys(data.responses).length > 0) {
            // Populate form fields with saved responses.
            Object.keys(data.responses).forEach(questionId => {
                const responseData = data.responses[questionId];
                const responseValue = typeof responseData === 'object' ? responseData.response : responseData;
                const timestamp = typeof responseData === 'object' ? responseData.timemodified : null;
                
                const questionItem = evaluationContainer.find(`[data-questionid="${questionId}"]`);
                const questionType = questionItem.data('questiontype');

                if (questionType === 'multiplechoice') {
                    // Multiple choice: parse JSON and check boxes.
                    try {
                        const values = JSON.parse(responseValue);
                        if (Array.isArray(values)) {
                            values.forEach(val => {
                                const selector = `input[name="question_${questionId}_turn[]"][value="${val}"]`;
                                evaluationContainer.find(selector).prop('checked', true);
                            });
                        }
                    } catch (e) {
                        // Invalid JSON, ignore.
                    }
                } else if (questionType === 'select') {
                    // Select: set selected option.
                    evaluationContainer.find(`select[name="question_${questionId}_turn"]`).val(responseValue);
                } else if (questionType === 'singlechoice' || questionType === 'likert') {
                    // Single choice: check radio button.
                    const selector = `input[name="question_${questionId}_turn"][value="${responseValue}"]`;
                    evaluationContainer.find(selector).prop('checked', true);
                } else if (questionType === 'number' || questionType === 'shorttext') {
                    // Number or short text: set input value.
                    evaluationContainer.find(`input[name="question_${questionId}_turn"]`).val(responseValue);
                } else if (questionType === 'longtext') {
                    // Long text: set textarea value.
                    evaluationContainer.find(`textarea[name="question_${questionId}_turn"]`).val(responseValue);
                }

                // Show timestamp if available.
                if (timestamp) {
                    showTurnEvaluationTimestamp(evaluationContainer, questionId, timestamp);
                } else {
                    hideTurnEvaluationTimestamp(evaluationContainer, questionId);
                }
            });
        } else {
            // No responses found - clear the form.
            clearTurnEvaluationForm(pageid, turnId);
            // Hide all timestamps.
            evaluationContainer.find('.turn-evaluation-saved-message').remove();
        }
    })
    .catch(error => {
        // eslint-disable-next-line no-console
        console.error('Error loading turn evaluation responses:', error);
    });
};

/**
 * Clear the form for a specific turn (used when creating a new turn).
 *
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 */
const clearTurnEvaluationForm = (pageid, turnId) => {
    const containerWrapper = $(`#turn-evaluation-questions-container-${pageid}`);
    const evaluationContainer = containerWrapper.find(`.turn-evaluation-questions[data-turn-id="${turnId}"]`);

    if (evaluationContainer.length === 0) {
        return; // Container not found.
    }

    // Clear all form fields.
    evaluationContainer.find('input[type="radio"]').prop('checked', false);
    evaluationContainer.find('input[type="checkbox"]').prop('checked', false);
    evaluationContainer.find('select').val('');
    evaluationContainer.find('input[type="text"], input[type="number"]').val('');
    evaluationContainer.find('textarea').val('');

    // Hide all timestamps.
    evaluationContainer.find('.turn-evaluation-saved-message').remove();
};

/**
 * Show timestamp for a turn evaluation question.
 *
 * @param {jQuery} evaluationContainer Container element
 * @param {number} questionId Question ID
 * @param {number} timestamp Unix timestamp
 */
const showTurnEvaluationTimestamp = (evaluationContainer, questionId, timestamp) => {
    // Remove existing timestamp if any.
    hideTurnEvaluationTimestamp(evaluationContainer, questionId);

    const questionItem = evaluationContainer.find(`[data-questionid="${questionId}"]`);
    if (questionItem.length === 0) {
        return;
    }

    // Format timestamp.
    const date = new Date(timestamp * 1000);
    const datetimeStr = date.toLocaleString();

    // Create timestamp message.
    const messageHtml = '<div class="mt-2 small text-muted turn-evaluation-saved-message" ' +
        `data-questionid="${questionId}">` +
        '<i class="fa fa-check-circle" aria-hidden="true"></i> ' +
        `Saved on ${datetimeStr}</div>`;

    questionItem.append(messageHtml);
};

/**
 * Hide timestamp for a turn evaluation question.
 *
 * @param {jQuery} evaluationContainer Container element
 * @param {number} questionId Question ID
 */
const hideTurnEvaluationTimestamp = (evaluationContainer, questionId) => {
    evaluationContainer.find(`.turn-evaluation-saved-message[data-questionid="${questionId}"]`).remove();
};

/**
 * Get the current turn for a page (highest turn with messages).
 *
 * @param {number} pageid Page ID
 * @return {number} Current turn number
 */
const getCurrentTurn = (pageid) => {
    return currentTurns[pageid] || 1;
};

/**
 * Get the viewing turn for a page (which turn is being displayed).
 *
 * @param {number} pageid Page ID
 * @return {number} Viewing turn number
 */
const getViewingTurn = (pageid) => {
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    return parseInt(container.data('viewing-turn') || getCurrentTurn(pageid), 10);
};

/**
 * Set the viewing turn for a page.
 *
 * @param {number} pageid Page ID
 * @param {number} turn Turn number
 */
const setViewingTurn = (pageid, turn) => {
    const container = $(`#chat-messages-page-${pageid}`).closest('.ai-conversation-container');
    container.data('viewing-turn', turn);
};

/**
 * Update turn display (badge and navigation buttons).
 *
 * @param {number} pageid Page ID
 */
const updateTurnDisplay = (pageid) => {
    const viewingTurn = getViewingTurn(pageid);
    const currentTurn = getCurrentTurn(pageid);

    // Update turn number badge.
    $(`#current-turn-number-${pageid}`).text(viewingTurn);

    // Show/hide previous button.
    const prevBtn = $(`.prev-turn-btn[data-pageid="${pageid}"]`);
    if (viewingTurn > 1) {
        prevBtn.show();
    } else {
        prevBtn.hide();
    }

    // Update next button text (if viewing current turn, show "Create next turn", otherwise show "Go to current").
    const nextBtn = $(`.next-turn-btn[data-pageid="${pageid}"]`);
    if (viewingTurn < currentTurn) {
        // Use direct text since we can't use Mustache here.
        nextBtn.html('Go to current turn <i class="fa fa-chevron-right"></i>');
    } else {
        nextBtn.html('Create next turn <i class="fa fa-chevron-right"></i>');
    }
};

/**
 * Update chat lock state (disable input if viewing past turn).
 *
 * @param {number} pageid Page ID
 */
const updateChatLockState = (pageid) => {
    const viewingTurn = getViewingTurn(pageid);
    const currentTurn = getCurrentTurn(pageid);
    const input = $(`#chat-input-page-${pageid}`);
    const sendBtn = $(`.chat-send-btn[data-pageid="${pageid}"]`);
    const lockMessage = $(`#turn-locked-message-${pageid}`);

    if (viewingTurn < currentTurn) {
        // Viewing a past turn - lock chat.
        input.prop('disabled', true);
        sendBtn.prop('disabled', true);
        if (lockMessage.length > 0) {
            lockMessage.show();
        }
    } else {
        // Viewing current turn - unlock chat.
        input.prop('disabled', false);
        sendBtn.prop('disabled', false);
        if (lockMessage.length > 0) {
            lockMessage.hide();
        }
    }
};

/**
 * Filter messages by turn (show only messages from specified turn, hide previous turns).
 *
 * @param {number} pageid Page ID
 * @param {number} turnId Turn ID
 * @param {boolean} showPrevious Whether to show previous turn messages
 */
const filterMessagesByTurn = (pageid, turnId, showPrevious = false) => {
    const messagesContainer = $(`#chat-messages-page-${pageid}`);
    const toggleContainer = $(`#previous-messages-toggle-${pageid}`);
    let hasPreviousMessages = false;

    // Show all messages from this turn or earlier (for context).
    messagesContainer.find('.message').each(function() {
        const messageTurnId = $(this).data('turn-id');
        if (messageTurnId) {
            const msgTurn = parseInt(messageTurnId, 10);
            // Show if message is from this turn.
            if (msgTurn === turnId) {
                $(this).show();
                $(this).removeClass('previous-turn-message');
            } else if (msgTurn < turnId) {
                // Previous turn message.
                hasPreviousMessages = true;
                if (showPrevious) {
                    $(this).show();
                    $(this).addClass('previous-turn-message');
                } else {
                    $(this).hide();
                    $(this).addClass('previous-turn-message');
                }
            } else {
                // Future turn message - always hide.
                $(this).hide();
                $(this).removeClass('previous-turn-message');
            }
        } else {
            // Messages without turn_id (shouldn't happen in turns mode, but show them anyway).
            $(this).show();
            $(this).removeClass('previous-turn-message');
        }
    });

    // Show/hide toggle button if there are previous messages.
    if (hasPreviousMessages && turnId > 1) {
        toggleContainer.show();
        const toggleBtn = toggleContainer.find('.show-previous-messages-btn');
        if (showPrevious) {
            toggleBtn.html('<i class="fa fa-chevron-up"></i> Hide previous messages');
            toggleBtn.data('showing', true);
        } else {
            toggleBtn.html('<i class="fa fa-chevron-down"></i> Show previous messages');
            toggleBtn.data('showing', false);
        }
    } else {
        toggleContainer.hide();
    }

    // Scroll to bottom after filtering.
    setTimeout(() => {
        scrollToBottom(pageid);
    }, 50);
};
