import { GeocoderAutocomplete } from '@geoapify/geocoder-autocomplete';

document.addEventListener('DOMContentLoaded', () => {
  const autocomplete = new GeocoderAutocomplete(
    document.getElementById('business_location_container'),
    'ff51cec2c6ee41d296032c492455155d',
    {
      lang: 'en',
      filter: { countrycode: ['ph'] },
      addDetails: true
    }
  );

  autocomplete.on('select', location => {
    if (location && location.properties) {
      document.getElementById('latitude').value = location.properties.lat;
      document.getElementById('longitude').value = location.properties.lon;
      document.getElementById('business_location').value = location.properties.formatted;
      window.addressSelected = true;
    }
  });

  autocomplete.on('suggestions', () => {
    window.addressSelected = false;
  });
});
