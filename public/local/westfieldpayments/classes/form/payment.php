<?php
namespace local_westfieldpayments\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class payment extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'amount', get_string('amount', 'local_westfieldpayments') . ' (' . $this->_customdata['currency'] . ')');
        $mform->setType('amount', PARAM_RAW_TRIMMED);
        $mform->addRule('amount', null, 'required', null, 'client');
        $mform->addElement('date_time_selector', 'paidat', get_string('paidat', 'local_westfieldpayments'));
        $mform->setDefault('paidat', time());
        $methods = [];
        foreach (\local_westfieldpayments\ledger::METHODS as $method) {
            $methods[$method] = get_string($method, 'local_westfieldpayments');
        }
        $mform->addElement('select', 'method', get_string('method', 'local_westfieldpayments'), $methods);
        $mform->addElement('text', 'reference', get_string('reference', 'local_westfieldpayments'), ['maxlength' => 100]);
        $mform->setType('reference', PARAM_TEXT);
        $mform->addElement('textarea', 'note', get_string('note', 'local_westfieldpayments'), ['rows' => 3]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addElement('hidden', 'requestid', bin2hex(random_bytes(16)));
        $mform->setType('requestid', PARAM_ALPHANUM);
        $this->add_action_buttons(false, get_string('addpayment', 'local_westfieldpayments'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        try {
            if (\local_westfieldpayments\ledger::cents((string)$data['amount']) <= 0) {
                $errors['amount'] = get_string('invalidpayment', 'local_westfieldpayments');
            }
        } catch (\invalid_parameter_exception $e) {
            $errors['amount'] = get_string('invalidamount', 'local_westfieldpayments');
        }
        if ($data['paidat'] > time()) {
            $errors['paidat'] = get_string('futuredate', 'local_westfieldpayments');
        }
        return $errors;
    }
}
