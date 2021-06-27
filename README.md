# Weather from Openweathermap.org

Write a module for Drupal 8 that fetches Current Weather from OpenWeatherMap.org
and displays on a page.

### Task Backlog

* User should be able to request page /weather and see current weather
  for configured default location
* (Optional) User should be able to request
  page ***/weather/[city]***, or ***/weather/[city]/[country code]***
  and see current weather for given location.
* Admin must be able to configure module
* Admin should be able to configure city name
* Admin should be able to configure country code
* Admin should be able to configure API endpoint and API key

### Requirements

* Custom module implement Drupal service for communication with API
* There should be menu path ***/weather*** registered by module
* Weather page can have whatever layout
* Module must implement Drupal settings form for configuration under
  ***/admin/config/services/weather*** path and add menu item in admin menu
* Module must define permissions for viewing the weather page and configuration
* Module should expose config schema

Here is documentation of the API: https://openweathermap.org/current#name
OpenWeatherMap.org exposes API for current weather for free, just need api
* key: ***use your own***
