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

use renderable;
use templatable;
use renderer_base;

/**
 * Experiment view renderable class.
 *
 * @package     mod_harpiasurvey
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class experiment_view implements renderable, templatable {

    /**
     * @var object Experiment object
     */
    public $experiment;

    /**
     * @var array Pages array
     */
    public $pages;

    /**
     * @var object Context object
     */
    public $context;

    /**
     * @var int Course module ID
     */
    public $cmid;

    /**
     * @var int Experiment ID
     */
    public $experimentid;

    /**
     * @var object|null Viewing page object
     */
    public $viewingpage;

    /**
     * @var bool Whether we're in edit mode
     */
    public $editing;

    /**
     * @var string Form HTML (if editing)
     */
    public $formhtml;

    /**
     * @var string Page view HTML (if viewing)
     */
    public $pageviewhtml;

    /**
     * @var bool Whether user can manage experiments (is admin)
     */
    public $canmanage;

    /**
     * Class constructor.
     *
     * @param object $experiment
     * @param array $pages
     * @param object $context
     * @param int $cmid
     * @param int $experimentid
     * @param object|null $viewingpage
     * @param bool $editing
     * @param string $formhtml
     * @param string $pageviewhtml
     * @param bool $canmanage
     */
    public function __construct($experiment, $pages, $context, $cmid, $experimentid, $viewingpage = null, $editing = false, $formhtml = '', $pageviewhtml = '', $canmanage = true) {
        $this->experiment = $experiment;
        $this->pages = $pages;
        $this->context = $context;
        $this->cmid = $cmid;
        $this->experimentid = $experimentid;
        $this->viewingpage = $viewingpage;
        $this->editing = $editing;
        $this->formhtml = $formhtml;
        $this->pageviewhtml = $pageviewhtml;
        $this->canmanage = $canmanage;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;

        // Prepare pages list.
        $pageslist = [];
        $pagenum = 1;
        $currentpageid = $this->viewingpage ? $this->viewingpage->id : null;
        foreach ($this->pages as $page) {
            $pageurl = new \moodle_url('/mod/harpiasurvey/view_experiment.php', [
                'id' => $this->cmid,
                'experiment' => $this->experimentid,
                'page' => $page->id
            ]);
            $deleteurl = new \moodle_url('/mod/harpiasurvey/delete_page.php', [
                'id' => $this->cmid,
                'experiment' => $this->experimentid,
                'page' => $page->id
            ]);
            $pageslist[] = [
                'id' => $page->id,
                'number' => $pagenum++,
                'title' => format_string($page->title),
                'url' => $pageurl->out(false),
                'deleteurl' => $deleteurl->out(false),
                'is_active' => ($currentpageid && $page->id == $currentpageid),
            ];
        }

        // Prepare tabs.
        $questionsurl = new \moodle_url('/mod/harpiasurvey/question_bank.php', [
            'id' => $this->cmid,
            'experiment' => $this->experimentid
        ]);
        $pagesurl = new \moodle_url('/mod/harpiasurvey/view_experiment.php', [
            'id' => $this->cmid,
            'experiment' => $this->experimentid
        ]);

        // Add page URL.
        $addpageurl = new \moodle_url('/mod/harpiasurvey/edit_page.php', [
            'id' => $this->cmid,
            'experiment' => $this->experimentid
        ]);

        // Back to course URL.
        $courseid = $this->context->get_course_context()->instanceid;
        $backtocourseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        return [
            'experimentname' => format_string($this->experiment->name),
            'pageslabel' => get_string('pages', 'mod_harpiasurvey'),
            'questionsurl' => $questionsurl->out(false),
            'pagesurl' => $pagesurl->out(false),
            'questionslabel' => get_string('questions', 'mod_harpiasurvey'),
            'questionbanklabel' => get_string('questionbank', 'mod_harpiasurvey'),
            'pageslist' => $pageslist,
            'has_pages' => !empty($pageslist),
            'nopages' => get_string('nopages', 'mod_harpiasurvey'),
            'addpageurl' => $addpageurl->out(false),
            'addpagelabel' => get_string('addpage', 'mod_harpiasurvey'),
            'selectpagetoadd' => get_string('selectpagetoadd', 'mod_harpiasurvey'),
            'viewingpage' => $this->viewingpage !== null,
            'editing' => $this->editing,
            'formhtml' => $this->formhtml,
            'pageviewhtml' => $this->pageviewhtml,
            'backtocourseurl' => $backtocourseurl->out(false),
            'backtocourselabel' => get_string('backtocourse', 'mod_harpiasurvey'),
            'canmanage' => $this->canmanage,
            'wwwroot' => $CFG->wwwroot,
        ];
    }
}

