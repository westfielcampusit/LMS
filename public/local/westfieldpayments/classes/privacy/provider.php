<?php
namespace local_westfieldpayments\privacy;
defined('MOODLE_INTERNAL') || die();

/** Privacy support for student-owned payment accounts. */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('local_wfp_accounts', [
            'userid' => 'privacy:student', 'courseid' => 'privacy:course',
            'fee' => 'privacy:fees', 'discount' => 'privacy:fees', 'currency' => 'privacy:fees',
            'plan' => 'privacy:plan', 'createdby' => 'privacy:admin', 'modifiedby' => 'privacy:admin',
            'timecreated' => 'privacy:date', 'timemodified' => 'privacy:date',
        ], 'privacy:accounts');
        $collection->add_database_table('local_wfp_payments', [
            'accountid' => 'privacy:accounts', 'amount' => 'privacy:fees',
            'paidat' => 'privacy:date', 'method' => 'privacy:payment', 'reference' => 'privacy:payment',
            'note' => 'privacy:payment', 'requestid' => 'privacy:payment',
            'createdby' => 'privacy:admin', 'timecreated' => 'privacy:date',
        ], 'privacy:payment');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $list = new \core_privacy\local\request\contextlist();
        $list->add_from_sql('SELECT c.id FROM {context} c JOIN {local_wfp_accounts} a
            ON a.userid = c.instanceid WHERE c.contextlevel = :level AND a.userid = :userid',
            ['level' => CONTEXT_USER, 'userid' => $userid]);
        return $list;
    }

    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_wfp_accounts} WHERE userid = ?', [$context->instanceid]);
        }
    }

    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || $context->instanceid != $contextlist->get_user()->id) {
                continue;
            }
            $accounts = $DB->get_records('local_wfp_accounts', ['userid' => $context->instanceid]);
            foreach ($accounts as $account) {
                $payments = $DB->get_records('local_wfp_payments', ['accountid' => $account->id]);
                \core_privacy\local\request\writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_westfieldpayments'), (string)$account->courseid],
                    (object)['account' => $account, 'payments' => array_values($payments)]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            self::delete_student((int)$context->instanceid);
        }
    }

    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $contextlist->get_user()->id) {
                self::delete_student((int)$context->instanceid);
            }
        }
    }

    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER && in_array($context->instanceid, $userlist->get_userids())) {
            self::delete_student((int)$context->instanceid);
        }
    }

    private static function delete_student(int $userid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach ($DB->get_records('local_wfp_accounts', ['userid' => $userid], '', 'id') as $account) {
            $DB->delete_records('local_wfp_payments', ['accountid' => $account->id]);
        }
        $DB->delete_records('local_wfp_accounts', ['userid' => $userid]);
        $transaction->allow_commit();
    }
}
