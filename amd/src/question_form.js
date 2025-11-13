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
 * Question form conditional fields module.
 *
 * @module     mod_harpiasurvey/question_form
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

let initialized = false;

/**
 * Initialize the question form conditional fields.
 */
export const init = () => {
    if (initialized) {
        return;
    }

    const updateFields = () => {
        const typeSelect = document.getElementById('id_type');
        if (!typeSelect) {
            return;
        }

        const selectedType = typeSelect.value;

        // Helper function to show/hide a section by header ID.
        const toggleSection = (headerId, show) => {
            const header = document.getElementById('id_' + headerId);
            if (!header) {
                return;
            }

            // Find the header's parent fitem and all following fitems until the next header.
            const headerFitem = header.closest('.fitem');
            if (!headerFitem) {
                return;
            }

            // Get all following siblings until the next header.
            let current = headerFitem.nextElementSibling;
            const itemsToToggle = [headerFitem];

            while (current) {
                // Stop if we hit another header.
                if (current.querySelector('h3, .fheader')) {
                    break;
                }
                itemsToToggle.push(current);
                current = current.nextElementSibling;
            }

            // Show or hide all items.
            itemsToToggle.forEach((item) => {
                item.style.display = show ? '' : 'none';
            });

            // Expand/collapse header if needed.
            if (show) {
                header.classList.add('expanded');
                const collapsible = header.querySelector('.collapsible-actions');
                if (collapsible) {
                    collapsible.setAttribute('aria-expanded', 'true');
                }
            }
        };

        // Show/hide selection settings (for singlechoice, multiplechoice, select).
        toggleSection('selection_settings', ['singlechoice', 'multiplechoice', 'select'].includes(selectedType));

        // Show/hide number settings (for number type).
        toggleSection('number_settings', selectedType === 'number');

        // Show/hide AI settings (for aiconversation type).
        toggleSection('ai_settings', selectedType === 'aiconversation');
    };

    // Listen for type changes.
    $(document).on('change', '#id_type', updateFields);

    // Initial update on page load.
    updateFields();

    initialized = true;
};

