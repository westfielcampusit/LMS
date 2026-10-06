<?php
namespace local_westfieldpayments\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class account extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'fee', get_string('fee', 'local_westfieldpayments'));
        $mform->setType('fee', PARAM_RAW_TRIMMED);
        $mform->addRule('fee', null, 'required', null, 'client');
        $mform->addElement('text', 'discount', get_string('discount', 'local_westfieldpayments'));
        $mform->setType('discount', PARAM_RAW_TRIMMED);
        $mform->setDefault('discount', '0.00');
        $currencies = array_combine(\local_westfieldpayments\ledger::CURRENCIES, \local_westfieldpayments\ledger::CURRENCIES);
        $mform->addElement('select', 'currency', get_string('currency', 'local_westfieldpayments'), $currencies);
        $mform->setDefault('currency', 'LKR');
        if ($this->_customdata['account']) {
            $mform->freeze('currency');
        }
        $mform->addElement('textarea', 'plan', get_string('plan', 'local_westfieldpayments'), ['rows' => 5]);
        $mform->setType('plan', PARAM_TEXT);
        $mform->addHelpButton('plan', 'plan', 'local_westfieldpayments');
        $this->add_action_buttons(false, get_string('saveplan', 'local_westfieldpayments'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['fee', 'discount'] as $field) {
            try {
                \local_westfieldpayments\ledger::cents((string)$data[$field]);
            } catch (\invalid_parameter_exception $e) {
                $errors[$field] = get_string('invalidamount', 'local_westfieldpayments');
            }
        }
        if (!$errors && \local_westfieldpayments\ledger::cents($data['discount']) > \local_westfieldpayments\ledger::cents($data['fee'])) {
            $errors['discount'] = get_string('discounttoolarge', 'local_westfieldpayments');
        }
        return $errors;
    }
}
