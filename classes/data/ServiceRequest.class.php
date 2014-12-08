<?php
class ServiceRequestDAO {
	public static function getServiceRequestByMethod($db, $method) {
		return $db->SelectOne("SELECT r.method, r.request_string, e.endpoint FROM pgrm_service_requests r INNER JOIN pgrm_service_endpoints e ON r.endpoint_id = e.id WHERE r.method=?", array($method));
	}
}

?>