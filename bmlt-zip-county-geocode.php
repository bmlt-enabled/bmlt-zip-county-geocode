<?
// CONFIGURATION
// Google Stuff
$google_maps_api_key = "";
$google_maps_endpoint = "https://maps.googleapis.com/maps/api/geocode/json?key=" . trim($google_maps_api_key);
$location_lookup_bias = "country:us";
// BMLT Stuff
$table_prefix = "na";  // Server Default
$root_server = "";
// END CONFIGURATION

$meetings_respone = get($root_server . "/client_interface/json/?switcher=GetSearchResults");
$meetings = json_decode($meetings_respone);

$template_delete_county = "DELETE FROM " . $table_prefix
	. '_comdef_meetings_data WHERE `key`="location_sub_province" AND meetingid_bigint='
	. '%s' .";\n";
$template_insert_county = "INSERT INTO " . $table_prefix
	. '_comdef_meetings_data (meetingid_bigint, field_prompt, `key`, lang_enum, visibility, data_string) VALUES ('
	. '%s' . ', "County", "location_sub_province", "en", "0", "'
	. '%s' . '");' . "\n";
$template_delete_zip = "DELETE FROM " . $table_prefix
	. '_comdef_meetings_data WHERE `key`="location_postal_code_1" AND meetingid_bigint='
	. '%s' . ";\n";
$template_insert_zip = "INSERT INTO " . $table_prefix
	. '_comdef_meetings_data (meetingid_bigint, field_prompt, `key`, lang_enum, visibility, data_string) VALUES ('
	. '%s' . ', "Zip Code", "location_postal_code_1", "en", "0", "'
	. '%s' . '")' . ";\n";

foreach ($meetings as $meeting) {
		$meeting_details = getDetailsForAddress(
			$meeting->location_street . ", "
			. $meeting->location_municipality . " "
			. $meeting->location_province);
		$output_sql = sprintf($template_delete_county, $meeting->id_bigint)
			. sprintf($template_insert_county, $meeting->id_bigint, $meeting_details->county)
			. sprintf($template_delete_zip, $meeting->id_bigint)
			. sprintf($template_insert_zip, $meeting->id_bigint, $meeting_details->postal_code);
		print($output_sql);
}

function getDetailsForAddress($address) {
	$details = new Details();
	if (strlen($address) > 0) {
		$map_details_response = get($GLOBALS['google_maps_endpoint']
			. "&address="
			. urlencode($address)
			. "&components=" . urlencode($GLOBALS['location_lookup_bias']));
		$map_details = json_decode($map_details_response);
		foreach($map_details->results as $results) {
			foreach($results->address_components as $address_components) {
				if(isset($address_components->types) && $address_components->types[0] == 'postal_code') {
					$details->postal_code  = $address_components->long_name;
				}
				if(isset($address_components->types) && $address_components->types[0] == 'administrative_area_level_2') {
					$details->county  	   = str_replace ( ' County', '', $address_components->long_name);
				}
			}
		}	
	}
	return $details;
}

function get($url) {
	error_log($url);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +bmltgeo' );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	$errorno = curl_errno($ch);
	curl_close($ch);
	if ($errorno > 0) {
		throw new Exception(curl_strerror($errorno));
	}
	return $data;
}

class Details {
	public $postal_code;
	public $county;
}
