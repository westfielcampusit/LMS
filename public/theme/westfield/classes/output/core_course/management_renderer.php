<?php
namespace theme_westfield\output\core_course;
defined('MOODLE_INTERNAL') || die();

/** Keep advanced bulk controls available without crowding the course workspace. */
class management_renderer extends \core_course_management_renderer {
    private bool $defercategoryactions = false;
    private string $categoryactions = '';

    public function category_listing(?\core_course_category $category = null) {
        $this->defercategoryactions = true;
        $this->categoryactions = '';
        try {
            $listing = parent::category_listing($category);
        } finally {
            $this->defercategoryactions = false;
        }
        // Remain inside #category-listing so Moodle's selection handlers still find these controls.
        return $listing . $this->categoryactions;
    }

    public function category_bulk_actions(?\core_course_category $category = null) {
        $content = parent::category_bulk_actions($category);
        if ($content === '') {
            return '';
        }
        $content = \html_writer::tag('p', get_string('coursemanagementmovehint', 'theme_westfield'),
            ['class' => 'westfield-category-move-hint']) . $content;
        $actions = $this->advanced_actions($content, 'coursemanagementcategoryactions');
        if ($this->defercategoryactions) {
            $this->categoryactions = $actions;
            return '';
        }
        return $actions;
    }

    public function course_bulk_actions(\core_course_category $category) {
        return $this->advanced_actions(parent::course_bulk_actions($category), 'coursemanagementcourseactions');
    }

    public function course_search_bulk_actions() {
        return $this->advanced_actions(parent::course_search_bulk_actions(), 'coursemanagementcourseactions');
    }

    private function advanced_actions(string $content, string $label): string {
        return \html_writer::tag('details',
            \html_writer::tag('summary', get_string($label, 'theme_westfield')) . $content,
            ['class' => 'westfield-management-bulk-options']);
    }
}
