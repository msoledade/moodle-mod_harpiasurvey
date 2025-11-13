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

/**
 * Initialize the AI conversation functionality.
 */
export const init = () => {
    if (initialized) {
        return;
    }

    // Handle send button clicks.
    $(document).on('click', '.chat-send-btn', function(e) {
        e.preventDefault();
        const button = $(this);
        const questionid = parseInt(button.data('questionid'), 10);
        const cmid = parseInt(button.data('cmid'), 10);
        const pageid = parseInt(button.data('pageid'), 10);

        const input = $(`#chat-input-${questionid}`);
        const message = input.val().trim();

        if (!message) {
            return;
        }

        // Get model from container data attribute (first available model).
        const container = $(`.ai-conversation-container[data-questionid="${questionid}"]`);
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
        displayUserMessage(questionid, message, tempId);

        // Show loading indicator.
        const loading = $(`#chat-loading-${questionid}`);
        loading.show();

        // Send message to AI.
        sendMessage(cmid, pageid, questionid, message, modelid, button, input, loading);
    });

    // Handle Enter key in textarea (Shift+Enter for new line, Enter to send).
    $(document).on('keydown', '.chat-input', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $(this).closest('.ai-conversation-container').find('.chat-send-btn').click();
        }
    });

    initialized = true;
};

/**
 * Display user message in chat.
 *
 * @param {number} questionid Question ID
 * @param {string} message Message content
 * @param {number} messageid Optional message ID to prevent duplicates
 */
const displayUserMessage = (questionid, message, messageid = null) => {
    const messagesContainer = $(`#chat-messages-${questionid}`);
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
        const msgContent = $(this).find('.content').text().trim();
        if (msgContent === message.trim()) {
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

    Templates.render('mod_harpiasurvey/chat_user_message', {
        content: message,
        id: messageid || 'temp-' + Date.now()
    }).then((html) => {
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
            scrollToBottom(questionid);
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
        const messageHtml = '<div class="message my-3" data-messageid="' + (messageid || 'temp-' + Date.now()) + '">' +
            '<div class="d-flex justify-content-end">' +
            '<div class="border rounded p-2 bg-primary text-white" style="max-width: 80%;">' +
            '<div class="content">' + message + '</div>' +
            '</div></div></div>';
        messagesContainer.append(messageHtml);
        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(questionid);
        }, 50);
    });
};

/**
 * Display AI message in chat.
 *
 * @param {number} questionid Question ID
 * @param {string} content Message content
 * @param {number} messageid Message ID
 */
const displayAIMessage = (questionid, content, messageid) => {
    const messagesContainer = $(`#chat-messages-${questionid}`);

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

    Templates.render('mod_harpiasurvey/chat_ai_message', {
        id: messageid,
        content: content
    }).then((html) => {
        // Final check before appending (prevent race conditions).
        const existingCheck = messagesContainer.find(`[data-messageid="${messageid}"]`);
        if (existingCheck.length > 0) {
            return; // Message was added while we were rendering.
        }
        Templates.appendNodeContents(messagesContainer[0], html);
        // Scroll after a brief delay to ensure DOM is fully updated.
        setTimeout(() => {
            scrollToBottom(questionid);
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
            scrollToBottom(questionid);
        }, 50);
    });
};

/**
 * Send message to AI via AJAX.
 *
 * @param {number} cmid Course module ID
 * @param {number} pageid Page ID
 * @param {number} questionid Question ID
 * @param {string} message Message content
 * @param {number} modelid Model ID
 * @param {jQuery} button Send button element
 * @param {jQuery} input Input textarea element
 * @param {jQuery} loading Loading indicator element
 */
const sendMessage = (cmid, pageid, questionid, message, modelid, button, input, loading) => {
    const params = new URLSearchParams({
        action: 'send_ai_message',
        cmid: cmid,
        pageid: pageid,
        questionid: questionid,
        message: message,
        modelid: modelid,
        sesskey: Config.sesskey
    });

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
                // Find the last user message (the one we just sent) and update its ID.
                const messagesContainer = $(`#chat-messages-${questionid}`);
                const userMessages = messagesContainer.find('.message').filter(function() {
                    return $(this).find('.bg-primary').length > 0;
                });
                if (userMessages.length > 0) {
                    const lastUserMsg = userMessages.last();
                    const currentId = lastUserMsg.attr('data-messageid');
                    // Only update if it's a temporary ID.
                    if (currentId && currentId.startsWith('temp-')) {
                        lastUserMsg.attr('data-messageid', data.parentid);
                    }
                }
            }

            // Display AI response (check for duplicates first).
            if (data.messageid && data.content) {
                displayAIMessage(questionid, data.content, data.messageid);
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
 * @param {number} questionid Question ID
 */
const scrollToBottom = (questionid) => {
    const messagesContainer = $(`#chat-messages-${questionid}`);
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
