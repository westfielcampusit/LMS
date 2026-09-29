<?php

namespace theme_westfield\output\core_user;

defined('MOODLE_INTERNAL') || die();

/** Profile tabs using Moodle's existing profile visibility and completion APIs. */
class myprofile_renderer extends \core_user\output\myprofile\renderer {
    public function render_tree(\core_user\output\myprofile\tree $tree) {
        global $CFG, $USER;

        if ($this->page->pagetype !== 'user-profile' || $this->page->context->contextlevel !== CONTEXT_USER) {
            return parent::render_tree($tree);
        }

        $userid = $this->page->context->instanceid;
        $details = '';
        $coursehtml = '';
        foreach ($tree->categories as $name => $category) {
            if ($name === 'coursedetails') {
                $coursehtml .= $this->render($category);
            } else {
                $details .= $this->render($category);
            }
        }

        $courses = [];
        $isowner = (int)$userid === (int)$USER->id;
        if ($isowner) {
            require_once($CFG->libdir . '/enrollib.php');
            require_once($CFG->libdir . '/completionlib.php');
            $enrolled = enrol_get_users_courses($userid, true, 'startdate, enablecompletion', 'fullname ASC');
            foreach ($enrolled as $course) {
                $context = \context_course::instance($course->id);
                if (!$course->category || !can_access_course($course)) {
                    continue;
                }
                $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
                $percentage = $progress === null ? null : (int)round($progress);
                $image = \core_course\external\course_summary_exporter::get_course_image($course);
                $courses[] = [
                    'name' => format_string($course->fullname, true, ['context' => $context]),
                    'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'image' => $image ?: $this->output->get_generated_image_for_id($course->id),
                    'startdate' => $course->startdate ? userdate($course->startdate, get_string('strftimedate', 'langconfig')) : '',
                    'hasprogress' => $percentage !== null,
                    'progress' => $percentage,
                    'degrees' => ($percentage ?? 0) * 3.6,
                ];
            }
        }

        return $this->render_from_template('theme_westfield/profile_tabs', [
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'isowner' => $isowner,
            // Other users retain exactly the course information permitted by Moodle's profile tree.
            'coursehtml' => $isowner ? '' : $coursehtml,
            'details' => $details,
        ]);
    }
}
