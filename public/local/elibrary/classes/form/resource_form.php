<?php
namespace local_elibrary\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class resource_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('name', 'local_elibrary'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', 'moodle', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_elibrary'),
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('filemanager', 'resourcefile', get_string('file', 'local_elibrary'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL,
        ]);
        $mform->addHelpButton('resourcefile', 'file', 'local_elibrary');
        $mform->addRule('resourcefile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
