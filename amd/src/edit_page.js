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
 * Edit page module.
 *
 * @module     mod_harpiasurvey/edit_page
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Config from 'core/config';
import $ from 'jquery';

/**
 * Initialize the edit page functionality.
 */
export const init = () => {
    // Handle evaluates conversation dropdown changes.
    $("body").on("change", ".evaluates-conversation-select", function() {
        const select = $(this);
        const pagequestionid = select.data('pagequestionid');
        const cmid = select.data('cmid');
        const evaluatesConversationId = parseInt(select.val(), 10) || 0;

        // Disable the select while updating.
        select.prop('disabled', true);

        const wwwroot = Config.wwwroot;
        const sesskey = Config.sesskey || M.cfg.sesskey;
        const ajaxUrl = wwwroot + '/mod/harpiasurvey/ajax.php';
        const params = new URLSearchParams({
            action: 'update_evaluates_conversation',
            cmid: cmid,
            pagequestionid: pagequestionid,
            evaluates_conversation_id: evaluatesConversationId,
            sesskey: sesskey
        });

        fetch(ajaxUrl + '?' + params.toString())
            .then((response) => response.json())
            .then((response) => {
                select.prop('disabled', false);
                if (response.success) {
                    Notification.addNotification({
                        message: response.message || 'Conversation relationship updated',
                        type: 'success'
                    });
                } else {
                    // Revert the selection on error.
                    select.val(select.data('previous-value') || 0);
                    Notification.addNotification({
                        message: response.message || 'Error updating conversation relationship',
                        type: 'error'
                    });
                }
            })
            .catch((error) => {
                select.prop('disabled', false);
                // Revert the selection on error.
                select.val(select.data('previous-value') || 0);
                Notification.exception(error);
            });
    });

    // Store previous value on focus for potential revert.
    $("body").on("focus", ".evaluates-conversation-select", function() {
        $(this).data('previous-value', $(this).val());
    });
};

