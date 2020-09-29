jQuery(function($) {

    $(document).ready(function () {

        var laybys = '<div id="layby-modal" class="laybuy-popup-modal"><div id="laybuy-popup-outer"><div class="laybuy-popup-modal-content"><iframe src="https://popup.laybuy.com/"></iframe><span class="close">&times;</span></div> </div></div>';
        $('body').append(laybys);

        if ($('.laybuy-inline-widget').length) {
            $btn = $('.laybuy-inline-widget #laybuy-what-is-modal');
        } else {
            $btn = $('#laybuy-what-is-modal');
        }

        $($btn).on("click", function(event) {
            event.preventDefault();
            $("#layby-modal").show();
        });

        $(".laybuy-popup-modal-content .close").on("click", function(event) {
            event.preventDefault();
            $("#layby-modal").hide();
        });
    })
});