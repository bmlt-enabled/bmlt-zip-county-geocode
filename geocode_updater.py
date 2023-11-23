import requests
import json


class GeocodeUpdater:
    GOOGLE_MAPS_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json?key='
    USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36'

    def __init__(self, root_server='', google_maps_api_key='', table_prefix='na', location_lookup_bias='country:us'):
        self.table_prefix = table_prefix
        self.root_server = root_server
        self.google_maps_api_key = google_maps_api_key
        self.location_lookup_bias = location_lookup_bias
        self.meetings = []

    def run(self):
        self.fetch_meetings()
        self.update_meetings()

    def fetch_meetings(self):
        try:
            meetings_response = self.get(f"{self.root_server}/client_interface/json/?switcher=GetSearchResults")
            # This currently filters only for empty zip and counties. Remove this to overwrite any existing data.
            self.meetings = [item for item in json.loads(meetings_response) if
                             not item['location_sub_province'] or not item['location_postal_code_1']]
        except Exception as e:
            print(f'Caught exception: {str(e)}')

    def update_meetings(self):
        template_delete = f"DELETE FROM {self.table_prefix}_comdef_meetings_data WHERE `key`='%s' AND meetingid_bigint='%s';\n"
        template_insert = f"INSERT INTO {self.table_prefix}_comdef_meetings_data (meetingid_bigint, field_prompt, `key`, lang_enum, visibility, data_string) VALUES ('%s', '%s', '%s', 'en', '0', '%s');\n"

        for meeting in self.meetings:
            meeting_address = f"{meeting['location_street']}, {meeting['location_municipality']} {meeting['location_province']}"
            meeting_details = self.get_details_for_address(meeting_address)

            if meeting_details['postal_code']:
                output_sql = (template_delete % ("location_sub_province", meeting['id_bigint']) +
                              template_insert % (
                              meeting['id_bigint'], "County", "location_sub_province", meeting_details['county']) +
                              template_delete % ("location_postal_code_1", meeting['id_bigint']) +
                              template_insert % (meeting['id_bigint'], "Zip Code", "location_postal_code_1",
                                                 meeting_details['postal_code']))
                print(output_sql)
            else:
                print(f"-- Could not geocode for address: {meeting_address} for meeting id: {meeting['id_bigint']}")

    def get_details_for_address(self, address):
        details = {
            'postal_code': None,
            'county': None,
        }

        if len(address) > 0:
            try:
                map_details_response = self.get(
                    f"{self.GOOGLE_MAPS_ENDPOINT}{self.google_maps_api_key}&address={address}&components={self.location_lookup_bias}")
                map_details = json.loads(map_details_response)

                for result in map_details['results']:
                    for address_components in result['address_components']:
                        if 'postal_code' in address_components['types']:
                            details['postal_code'] = address_components['long_name']
                        if 'administrative_area_level_2' in address_components['types']:
                            details['county'] = address_components['long_name'].replace(' County', '')

            except Exception as e:
                print(f'Caught exception: {str(e)}')

        return details

    def get(self, url):
        try:
            response = requests.get(url, headers={'User-Agent': self.USER_AGENT})
            response.raise_for_status()
            return response.text
        except requests.exceptions.RequestException as e:
            raise Exception(str(e))


# Create an instance of GeocodeUpdater with your settings
geocode_updater = GeocodeUpdater(
    'your_root_server',
    'your_google_maps_api_key',
)

geocode_updater.run()
