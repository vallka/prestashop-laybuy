jQuery(function($) {

    if (!$('#LAYBUY_CURRENCY').val()) {
        $('#LAYBUY_CURRENCY').val('NZD');
    }
    $('#LAYBUY_API_ENVIRONMENT').on('change', function () {
        $('#LAYBUY_CURRENCY').trigger('change');
    }).trigger('change');

    function showCredentials(currency) {

        var env = $('#LAYBUY_API_ENVIRONMENT').val().toUpperCase();
        var envHide = env == 'PRODUCTION' ? 'SANDBOX' : 'PRODUCTION';
        currency = currency.toUpperCase();

        $('#LAYBUY_' + env + '_' + currency + '_MERCHANT_ID').closest('.form-group').show();
        $('#LAYBUY_' + env + '_' + currency + '_API_KEY').closest('.form-group').show();

        $('#LAYBUY_' + envHide + '_' + currency + '_MERCHANT_ID').closest('.form-group').hide();
        $('#LAYBUY_' + envHide + '_' + currency + '_API_KEY').closest('.form-group').hide();
    }

    function hideAllCredentials() {

        var currencies = [];

        currenciesList = $('#LAYBUY_CURRENCY option');

        currenciesList.each(function(){
            currencies.push($(this).val());
        });

        currencies.push('global');

        for (var i in currencies) {

            var currency = currencies[i].toUpperCase();

            $('#LAYBUY_SANDBOX_' + currency + '_MERCHANT_ID').closest('.form-group').hide();
            $('#LAYBUY_SANDBOX_' + currency + '_API_KEY').closest('.form-group').hide();

            $('#LAYBUY_PRODUCTION_' + currency + '_MERCHANT_ID').closest('.form-group').hide();
            $('#LAYBUY_PRODUCTION_' + currency + '_API_KEY').closest('.form-group').hide();

        };
    }

    hideAllCredentials();

    $('#LAYBUY_CURRENCY').on('change', function () {

        hideAllCredentials();

        if ($('#LAYBUY_GLOBAL').val() == 'Yes') {
            $('#LAYBUY_CURRENCY').closest('.form-group').hide();
            showCredentials('global');
        } else {
            $('#LAYBUY_CURRENCY').closest('.form-group').show();
            var currencies = $('#LAYBUY_CURRENCY').val();
            for (var i in currencies) {
                showCredentials(currencies[i]);
            }
        }
    }).trigger('change');

    $('#LAYBUY_GLOBAL').on('change', function () {
        var currencies = $('#LAYBUY_CURRENCY').val();

        hideAllCredentials();

        if ($(this).val() == 'Yes') {
            $('#LAYBUY_CURRENCY').closest('.form-group').hide();
            showCredentials('global');
        } else {
            $('#LAYBUY_CURRENCY').closest('.form-group').show();
            for (var i in currencies) {
                showCredentials(currencies[i]);
            }
        }
    });
});