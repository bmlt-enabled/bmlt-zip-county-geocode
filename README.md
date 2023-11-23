# bmlt-zip-county-geocode

# Geocode Updater (Zip + County)

The Geocode Updater is a PHP script designed to update zip and county meeting location data based on geocoding information from the Google Maps API. This script is useful for maintaining accurate location data for meetings in a database.

## Prerequisites

Before using this script, you'll need the following:

- **MySQL Database**: Make sure you have access to the MySQL database where your meeting location data is stored.

- **Google Maps API Key**: Obtain a valid Google Maps API key with Geocoding enabled. You can get one from the [Google Cloud Console](https://console.cloud.google.com/).

## Configuration

You need to configure the script by setting the following parameters at the top of `GeocodeUpdater` class:

- `$tablePrefix`: The database table prefix for your MySQL server.

- `$googleMapsApiKey`: Your Google Maps API key.

- `$rootServer`: The root server URL where your BMLT installation is hosted.

- `$locationLookupBias`: Optional parameter used for better geocoding results. You can customize it according to your requirements. Default is `'country:us'`.

## Running the Script

1. Create an instance of `GeocodeUpdater` with your configuration settings:

```php
$geocodeUpdater = new GeocodeUpdater(
    'your_table_prefix',
    'your_root_server',
    'your_google_maps_api_key',
    'your_location_lookup_bias'
);
```

2. Run the script by calling the `run` method:

```php
$geocodeUpdater->run();
```

This will trigger the process of fetching meeting data, geocoding addresses, and generating SQL statements for updating the database.

3. You can choose to print the SQL output to the console or redirect it to a file:

To print to the console:

```bash
php GeocodeUpdater.php
```

To save the SQL output to a file:

```bash
php GeocodeUpdater.php > geocode.sql
```

## Additional Information

After running the script, you may want to review the generated SQL queries. Some lines may contain "Could not geocode for address," indicating that geocoding failed for specific addresses. You can investigate these cases to ensure accurate meeting location data.

## Disclaimer

Backup your database before running this script, as the maintainers cannot be held responsible for any issues that may arise during the geocoding and data update process.

Happy geocoding!
