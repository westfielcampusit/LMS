<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_westfieldpayments_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026100900) {
        $table = new xmldb_table('local_wfp_accounts');
        $duedate = new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'plan');
        if (!$dbman->field_exists($table, $duedate)) {
            $dbman->add_field($table, $duedate);
        }
        $paymentdue = new xmldb_field('paymentdue', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'duedate');
        if (!$dbman->field_exists($table, $paymentdue)) {
            $dbman->add_field($table, $paymentdue);
        }
        upgrade_plugin_savepoint(true, 2026100900, 'local', 'westfieldpayments');
    }

    if ($oldversion < 2026101000) {
        $accounttable = new xmldb_table('local_wfp_accounts');
        $installmenttable = new xmldb_table('local_wfp_installments');
        if (!$dbman->table_exists($installmenttable)) {
            $installmenttable->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, true));
            $installmenttable->addField(new xmldb_field('accountid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('installmentnumber', XMLDB_TYPE_INTEGER, '4', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('amount', XMLDB_TYPE_INTEGER, '15', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('paymentdue', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0'));
            $installmenttable->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
            $installmenttable->addKey(new xmldb_key('accountid', XMLDB_KEY_FOREIGN, ['accountid'],
                'local_wfp_accounts', ['id']));
            $installmenttable->addIndex(new xmldb_index('account-installment', XMLDB_INDEX_UNIQUE,
                ['accountid', 'installmentnumber']));
            $dbman->create_table($installmenttable);
        }
        $duedate = new xmldb_field('duedate');
        $paymentdue = new xmldb_field('paymentdue');
        if ($dbman->field_exists($accounttable, $duedate) && $dbman->field_exists($accounttable, $paymentdue)) {
            foreach ($DB->get_records_select('local_wfp_accounts', 'duedate > 0') as $account) {
                if ($DB->record_exists('local_wfp_installments', [
                        'accountid' => $account->id, 'installmentnumber' => 1])) {
                    continue;
                }
                $amount = (int)$account->fee - (int)$account->discount;
                if ($amount <= 0 || (int)$account->duedate <= 0) {
                    continue;
                }
                $DB->insert_record('local_wfp_installments', (object)[
                    'accountid' => $account->id,
                    'installmentnumber' => 1,
                    'amount' => $amount,
                    'duedate' => (int)$account->duedate,
                    'paymentdue' => (int)$account->paymentdue,
                ]);
            }
            $dbman->drop_field($accounttable, $duedate);
            $dbman->drop_field($accounttable, $paymentdue);
        }
        upgrade_plugin_savepoint(true, 2026101000, 'local', 'westfieldpayments');
    }

    if ($oldversion < 2026101100) {
        $coursetable = new xmldb_table('local_wfp_courseplans');
        $coursefields = [
            new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, true),
            new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL),
            new xmldb_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL),
            new xmldb_field('fee', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('discount', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('plan', XMLDB_TYPE_TEXT),
            new xmldb_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL),
            new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL),
        ];
        if (!$dbman->table_exists($coursetable)) {
            foreach ($coursefields as $field) {
                $coursetable->addField($field);
            }
            $coursetable->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
            $coursetable->addKey(new xmldb_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'],
                'course', ['id']));
            $dbman->create_table($coursetable);
        }

        $installmenttable = new xmldb_table('local_wfp_courseinst');
        if (!$dbman->table_exists($installmenttable)) {
            $installmenttable->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, true));
            $installmenttable->addField(new xmldb_field('planid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('installmentnumber', XMLDB_TYPE_INTEGER, '4', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('amount', XMLDB_TYPE_INTEGER, '15', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL));
            $installmenttable->addField(new xmldb_field('paymentdue', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0'));
            $installmenttable->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
            $installmenttable->addKey(new xmldb_key('planid', XMLDB_KEY_FOREIGN, ['planid'],
                'local_wfp_courseplans', ['id']));
            $installmenttable->addIndex(new xmldb_index('plan-installment', XMLDB_INDEX_UNIQUE,
                ['planid', 'installmentnumber']));
            $dbman->create_table($installmenttable);
        }
        upgrade_plugin_savepoint(true, 2026101100, 'local', 'westfieldpayments');
    }

    if ($oldversion < 2026101200) {
        $paymenttable = new xmldb_table('local_wfp_payments');
        $currency = new xmldb_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'LKR', 'amount');
        if (!$dbman->field_exists($paymenttable, $currency)) {
            $dbman->add_field($paymenttable, $currency);
            $accountcache = [];
            foreach ($DB->get_records('local_wfp_payments', [], '', 'id, accountid') as $payment) {
                if (!isset($accountcache[$payment->accountid])) {
                    $accountcache[$payment->accountid] = $DB->get_field(
                        'local_wfp_accounts', 'currency', ['id' => $payment->accountid], MUST_EXIST);
                }
                $DB->set_field('local_wfp_payments', 'currency', $accountcache[$payment->accountid],
                    ['id' => $payment->id]);
            }
        }
        upgrade_plugin_savepoint(true, 2026101200, 'local', 'westfieldpayments');
    }

    return true;
}
