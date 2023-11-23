#!/usr/bin/env bash

# Define variables
ROOT_SERVER="${ROOT_SERVER:-YOUR_ROOT_SERVER_URL}"
GOOGLE_MAPS_API_KEY="${GOOGLE_MAPS_API_KEY:-YOUR_GOOGLE_API_KEY}"
GOOGLE_MAPS_ENDPOINT="https://maps.googleapis.com/maps/api/geocode/json?key="
USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36"
TABLE_PREFIX="na"
LOCATION_LOOKUP_BIAS="country:us"

get() {
  local url="$1"
  curl -s -H "User-Agent: $USER_AGENT" "$url"
}

get_details_for_address() {
  local address="$1"
  local encoded_address=$(echo "$address" | sed 's/ /+/g')
  local url="${GOOGLE_MAPS_ENDPOINT}${GOOGLE_MAPS_API_KEY}&address=${encoded_address}&components=${LOCATION_LOOKUP_BIAS}"
  local map_details_response=$(get "$url")
  local postal_code
  local county

  postal_code=$(echo "$map_details_response" | jq -r '.results[0].address_components[] | select(.types[] | contains("postal_code")).short_name')
  county=$(echo "$map_details_response" | jq -r '.results[0].address_components[] | select(.types[] | contains("administrative_area_level_2")).long_name' | sed 's/ County//')
  echo "{\"postal_code\":\"$postal_code\",\"county\":\"$county\"}"
}

fetch_meetings() {
  local meetings_response=$(get "${ROOT_SERVER}/client_interface/json/?switcher=GetSearchResults")

  if [ -n "$meetings_response" ]; then
    meetings=$(echo "$meetings_response" | jq -c '.[] | select(.location_sub_province == "" or .location_postal_code_1 == "")' | jq -r '@base64' )
  else
    echo "Error: Failed to fetch meetings data."
    exit 1
  fi

  update_meetings "$meetings"
}

update_meetings() {
  local meetings_base64="$1"
  local meetings_json=$(echo "$meetings_base64" | base64 -d)
  local template_delete="DELETE FROM ${TABLE_PREFIX}_comdef_meetings_data WHERE \`key\`='%s' AND meetingid_bigint='%s';"
  local template_insert="INSERT INTO ${TABLE_PREFIX}_comdef_meetings_data (meetingid_bigint, field_prompt, \`key\`, lang_enum, visibility, data_string) VALUES ('%s', '%s', '%s', 'en', '0', '%s');"

  for meeting in $meetings_base64; do
    meeting_id=$(echo "$meeting" | base64 -d | jq -r '.id_bigint')
    meeting_address=$(echo "$meeting" | base64 -d | jq -r -c '.location_street + ", " + .location_municipality + " " + .location_province')
    meeting_details_json=$(get_details_for_address "$meeting_address")
    postal_code=$(echo "$meeting_details_json" | jq -r '.postal_code')
    county=$(echo "$meeting_details_json" | jq -r '.county')

    if [ -n "$postal_code" ]; then
      output_sql=$(printf "$template_delete\n$template_insert\n" "location_sub_province" "$meeting_id" "County" "location_sub_province" "$county" "location_postal_code_1" "$meeting_id" "Zip Code" "location_postal_code_1" "$postal_code")
      echo "$output_sql"
    else
      echo "-- Could not geocode for address: $meeting_address for meeting id: $meeting_id"
    fi
  done
}

fetch_meetings
