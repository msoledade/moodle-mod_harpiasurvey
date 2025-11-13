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

namespace mod_harpiasurvey\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use renderable;

/**
 * Main renderer class for harpiasurvey module.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the experiment view page.
     *
     * @param renderable $page
     * @return string
     * @throws \moodle_exception
     */
    public function render_experiment_view(renderable $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_harpiasurvey/experiment_view', $data);
    }

    /**
     * Render a page view.
     *
     * @param renderable $page
     * @return string
     * @throws \moodle_exception
     */
    public function render_page_view(renderable $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_harpiasurvey/page_view', $data);
    }

    /**
     * Render the question bank.
     *
     * @param renderable $page
     * @return string
     * @throws \moodle_exception
     */
    public function render_question_bank(renderable $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_harpiasurvey/question_bank', $data);
    }

    /**
     * Render the experiments table.
     *
     * @param renderable $page
     * @return string
     * @throws \moodle_exception
     */
    public function render_experiments_table(renderable $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_harpiasurvey/experiments_table', $data);
    }

    /**
     * Render the stats table.
     *
     * @param renderable $page
     * @return string
     * @throws \moodle_exception
     */
    public function render_stats_table(renderable $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_harpiasurvey/stats_table', $data);
    }
}

