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
 * Sortable table functionality for experiments table.
 *
 * @module     mod_harpiasurvey/sortable_table
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    /**
     * Initialize sortable table functionality.
     *
     * @param {string} tableSelector CSS selector for the table (optional, defaults to all sortable tables)
     */
    function init(tableSelector) {
        // If no selector provided, find all sortable tables.
        const selector = tableSelector || '.harpiasurvey-sortable-table[data-sortable="true"], .harpiasurvey-sortable-table';
        const tables = $(selector);

        if (!tables.length) {
            return;
        }

        // Initialize each table.
        tables.each(function() {
            initTable($(this));
        });
    }

    /**
     * Initialize a single table.
     *
     * @param {jQuery} table The table element
     */
    function initTable(table) {

        if (table.data('sortable-initialized')) {
            return; // Already initialized.
        }
        table.data('sortable-initialized', true);

        const headers = table.find('thead th');

        // Make headers clickable and add cursor style.
        headers.each(function(index) {
            const header = $(this);
            // Skip the actions column (last column).
            if (index === headers.length - 1) {
                return;
            }

            header.css('cursor', 'pointer');
            header.attr('role', 'button');
            header.attr('tabindex', '0');
            const headerText = header.text().trim().replace(/ [↑↓↕]$/, '');
            header.attr('aria-label', headerText + ' - Click to sort');

            // Add click handler.
            header.on('click', function() {
                sortTable(table, index, header);
            });

            // Add keyboard support.
            header.on('keypress', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    sortTable(table, index, header);
                }
            });
        });
    }

    /**
     * Sort the table by the specified column.
     *
     * @param {jQuery} table The table element
     * @param {number} columnIndex The column index to sort by
     * @param {jQuery} header The header element that was clicked
     */
    function sortTable(table, columnIndex, header) {
        const tbody = table.find('tbody');
        const rows = tbody.find('tr').toArray();
        const headers = table.find('thead th');

        // Determine current sort direction.
        let sortDirection = 'asc';
        const currentSort = header.data('sort-direction');
        if (currentSort === 'asc') {
            sortDirection = 'desc';
        }

        // Remove sort indicators from all headers.
        headers.each(function() {
            const h = $(this);
            h.removeClass('sort-asc sort-desc');
            h.data('sort-direction', null);
            // Remove arrows from text.
            let text = h.html();
            text = text.replace(/ [↑↓↕]$/, '');
            h.html(text);
        });

        // Add sort indicator to current header.
        header.addClass('sort-' + sortDirection);
        header.data('sort-direction', sortDirection);
        let headerText = header.html().replace(/ [↑↓↕]$/, '');
        const arrow = sortDirection === 'asc' ? ' ↑' : ' ↓';
        header.html(headerText + arrow);

        // Sort rows.
        rows.sort(function(a, b) {
            const aCell = $(a).find('td').eq(columnIndex);
            const bCell = $(b).find('td').eq(columnIndex);

            // Get text content, handling links.
            let aText = aCell.text().trim();
            let bText = bCell.text().trim();

            // Check if it's a numeric column (participants, max participants).
            const isNumeric = columnIndex === 1 || columnIndex === 2;

            if (isNumeric) {
                // Handle "unlimited" or other text values.
                const aNum = parseFloat(aText) || 0;
                const bNum = parseFloat(bText) || 0;
                if (aNum === bNum) {
                    return 0;
                }
                return sortDirection === 'asc' ? (aNum - bNum) : (bNum - aNum);
            } else {
                // Text sorting.
                aText = aText.toLowerCase();
                bText = bText.toLowerCase();
                if (aText === bText) {
                    return 0;
                }
                if (sortDirection === 'asc') {
                    return aText < bText ? -1 : 1;
                } else {
                    return aText > bText ? -1 : 1;
                }
            }
        });

        // Re-append sorted rows.
        tbody.empty();
        rows.forEach(function(row) {
            tbody.append(row);
        });
    }

    return {
        init: init
    };
});

