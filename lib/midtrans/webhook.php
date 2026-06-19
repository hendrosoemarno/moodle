require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/midtrans/lib.php');

$plugin = enrol_get_plugin('midtrans');
if ($plugin) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input['transaction_status'] === 'settlement') {
        $orderid = $input['order_id'];
        $userid = substr($orderid, strpos($orderid, '-') + 1);
        $plugin->enrol_user($userid, $plugin->get_instance_id_from_order($orderid));
    }
}