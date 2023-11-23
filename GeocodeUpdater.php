<?php
class GeocodeUpdater {
    private $tablePrefix;
    private $rootServer;
    private $googleMapsApiKey;
    private $locationLookupBias;
    private $meetings;
    const GOOGLE_MAPS_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json?key=';
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36';


    public function __construct(
        $rootServer = '',
        $googleMapsApiKey = '',
        $tablePrefix = 'na',
        $locationLookupBias = 'country:us'
    ) {
        $this->tablePrefix = $tablePrefix;
        $this->rootServer = $rootServer;
        $this->googleMapsApiKey = $googleMapsApiKey;
        $this->locationLookupBias = $locationLookupBias;
        $this->meetings = [];
    }

    public function run() {
        $this->fetchMeetings();
        $this->updateMeetings();
    }

    private function fetchMeetings() {
        try {
            $meetingsResponse = $this->get($this->rootServer . "/client_interface/json/?switcher=GetSearchResults");
            // This currently filters only for empty zip and counties. Remove this to overwrite any existing data.
            $this->meetings = array_filter(json_decode($meetingsResponse), function ($item) {
                return empty($item->location_sub_province) || empty($item->location_postal_code_1);
            });
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    private function updateMeetings() {
        $tablePrefix = $this->tablePrefix;
        $templateDelete = "DELETE FROM {$tablePrefix}_comdef_meetings_data WHERE `key`='%s' AND meetingid_bigint='%s';\n";
        $templateInsert = "INSERT INTO {$tablePrefix}_comdef_meetings_data (meetingid_bigint, field_prompt, `key`, lang_enum, visibility, data_string) VALUES ('%s', '%s', '%s', 'en', '0', '%s');\n";

        foreach ($this->meetings as $meeting) {
            $meetingAddress = $meeting->location_street . ", " . $meeting->location_municipality . " " . $meeting->location_province;
            $meetingDetails = $this->getDetailsForAddress($meetingAddress);

            if ($meetingDetails['postal_code']) {
                $outputSql = sprintf($templateDelete, "location_sub_province", $meeting->id_bigint) . sprintf($templateInsert, $meeting->id_bigint, "County", "location_sub_province", $meetingDetails['county']) . sprintf($templateDelete, "location_postal_code_1", $meeting->id_bigint) . sprintf($templateInsert, $meeting->id_bigint, "Zip Code", "location_postal_code_1", $meetingDetails['postal_code']);
                print($outputSql);
            } else {
                print("-- Could not geocode for address: " . $meetingAddress . " for meeting id: " . $meeting->id_bigint . "\n");
            }
        }
    }

    private function getDetailsForAddress(string $address): array {
        $details = [
            'postal_code' => null,
            'county' => null,
        ];

        if (strlen($address) > 0) {
            try {
                $mapDetailsResponse = $this->get(self::GOOGLE_MAPS_ENDPOINT . $this->googleMapsApiKey . "&address=" . urlencode($address) . "&components=" . urlencode($this->locationLookupBias));
                $mapDetails = json_decode($mapDetailsResponse);

                foreach ($mapDetails->results as $results) {
                    foreach ($results->address_components as $addressComponents) {
                        if (isset($addressComponents->types) && $addressComponents->types[0] == 'postal_code') {
                            $details['postal_code'] = $addressComponents->long_name;
                        }
                        if (isset($addressComponents->types) && $addressComponents->types[0] == 'administrative_area_level_2') {
                            $details['county'] = str_replace(' County', '', $addressComponents->long_name);
                        }
                    }
                }
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        }

        return $details;
    }

    private function get(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return $data;
    }
}

// Create an instance of GeocodeUpdater with your settings
$geocodeUpdater = new GeocodeUpdater(
    'your_root_server',
    'your_google_maps_api_key',
);

$geocodeUpdater->run();
