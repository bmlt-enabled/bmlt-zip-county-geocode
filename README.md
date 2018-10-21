# bmlt-zip-county-geocode

*Backup your database, the maintainers of this script cannot be held responsible for things that could go wrong.*

This will geocode the zip and county data.  You will need to set 3 configuration values at the top of bmlt-zip-county-geocode.php.

```php
$table_prefix = "";  // database prefix for your MySQL sever
$google_maps_api_key = "";
$root_server = "";
$location_lookup_bias = ""; // Used for better geocoding results
```

more info on location_lookup_bias options can be found here. https://developers.google.com/maps/documentation/geocoding/intro#ComponentFiltering
the default is `country:us`

Once you are ready run it

`php bmlt-zip-county-geocode.php`

You will get a list of `INSERT` queries to run on your root server MySQL.  Run them.