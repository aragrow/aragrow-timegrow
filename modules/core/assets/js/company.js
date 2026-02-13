jQuery(document).ready(function($) {

    // Handle filter button click
    $('#filter_companies').on('click', function(e) {
        e.preventDefault();

        var filterSearch = $('#filter_search').val();
        var filterState = $('#filter_state').val();
        var filterCity = $('#filter_city').val();
        var filterCountry = $('#filter_country').val();
        var filterStatus = $('#filter_status').val();

        var url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('filter_state');
        url.searchParams.delete('filter_city');
        url.searchParams.delete('filter_country');
        url.searchParams.delete('filter_status');

        if (filterSearch) url.searchParams.set('s', filterSearch);
        if (filterState) url.searchParams.set('filter_state', filterState);
        if (filterCity) url.searchParams.set('filter_city', filterCity);
        if (filterCountry) url.searchParams.set('filter_country', filterCountry);
        if (filterStatus) url.searchParams.set('filter_status', filterStatus);

        window.location.href = url.toString();
    });

    // Allow Enter key to trigger filter
    $('#filter_search, #filter_state, #filter_city, #filter_country, #filter_status').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#filter_companies').click();
        }
    });

});