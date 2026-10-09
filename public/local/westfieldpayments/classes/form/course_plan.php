<?php
namespace local_westfieldpayments\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class course_plan extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'fee', get_string('fee', 'local_westfieldpayments'));
        $mform->setType('fee', PARAM_RAW_TRIMMED);
        $mform->addRule('fee', null, 'required', null, 'client');
        $mform->addElement('text', 'discount', get_string('discount', 'local_westfieldpayments'));
        $mform->setType('discount', PARAM_RAW_TRIMMED);
        $mform->setDefault('discount', '0.00');
        $currencies = array_combine(\local_westfieldpayments\ledger::CURRENCIES,
            \local_westfieldpayments\ledger::CURRENCIES);
        $mform->addElement('select', 'currency', get_string('currency', 'local_westfieldpayments'), $currencies);
        $mform->setDefault('currency', 'LKR');
        $mform->addElement('text', 'installmentcount', get_string('installmentcount', 'local_westfieldpayments'),
            ['type' => 'number', 'min' => 0, 'max' => \local_westfieldpayments\ledger::MAX_INSTALLMENTS,
                'data-max-installments' => \local_westfieldpayments\ledger::MAX_INSTALLMENTS]);
        $mform->setType('installmentcount', PARAM_INT);
        $mform->setDefault('installmentcount', 0);
        $mform->addHelpButton('installmentcount', 'installmentcount', 'local_westfieldpayments');
        for ($index = 1; $index <= \local_westfieldpayments\ledger::MAX_INSTALLMENTS; $index++) {
            $mform->addElement('static', 'installmentheader_' . $index, '',
                get_string('installmentnumber', 'local_westfieldpayments', $index));
            $amount = 'installmentamount_' . $index;
            $mform->addElement('text', $amount, get_string('installmentamount', 'local_westfieldpayments'));
            $mform->setType($amount, PARAM_RAW_TRIMMED);
            $date = 'installmentdate_' . $index;
            $mform->addElement('date_selector', $date, get_string('duedate', 'local_westfieldpayments'),
                ['optional' => true]);
            $due = 'installmentdue_' . $index;
            $mform->addElement('hidden', $due, 0);
            $mform->setType($due, PARAM_BOOL);
            $mform->setDefault($due, 0);
        }
        $this->add_action_buttons(!empty($this->_customdata['showcancel']),
            get_string('savecourseplan', 'local_westfieldpayments'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['fee', 'discount'] as $field) {
            try {
                \local_westfieldpayments\ledger::cents((string)($data[$field] ?? ''));
            } catch (\invalid_parameter_exception $e) {
                $errors[$field] = get_string('invalidamount', 'local_westfieldpayments');
            }
        }
        if (!in_array($data['currency'] ?? '', \local_westfieldpayments\ledger::CURRENCIES, true)) {
            $errors['currency'] = get_string('invalidplan', 'local_westfieldpayments');
        }
        if (!$errors && \local_westfieldpayments\ledger::cents($data['discount']) >
                \local_westfieldpayments\ledger::cents($data['fee'])) {
            $errors['discount'] = get_string('discounttoolarge', 'local_westfieldpayments');
        }
        $count = (int)($data['installmentcount'] ?? 0);
        if ($count < 0 || $count > \local_westfieldpayments\ledger::MAX_INSTALLMENTS) {
            $errors['installmentcount'] = get_string('invalidinstallmentcount', 'local_westfieldpayments');
        }
        if (!$errors && $count > 0) {
            $sum = 0;
            for ($index = 1; $index <= $count; $index++) {
                $amountfield = 'installmentamount_' . $index;
                try {
                    $amount = \local_westfieldpayments\ledger::cents((string)($data[$amountfield] ?? ''));
                    if ($amount <= 0) {
                        $errors[$amountfield] = get_string('invalidinstallmentamount', 'local_westfieldpayments');
                    } else {
                        $sum += $amount;
                    }
                } catch (\invalid_parameter_exception $e) {
                    $errors[$amountfield] = get_string('invalidinstallmentamount', 'local_westfieldpayments');
                }
                if (empty($data['installmentdate_' . $index])) {
                    $errors['installmentdate_' . $index] = get_string('missingduedate', 'local_westfieldpayments');
                }
            }
            if (!$errors && $sum !==
                    \local_westfieldpayments\ledger::cents($data['fee']) -
                    \local_westfieldpayments\ledger::cents($data['discount'])) {
                $errors['installmentcount'] = get_string('installmentstotalmismatch', 'local_westfieldpayments');
            }
        }
        return $errors;
    }
}
