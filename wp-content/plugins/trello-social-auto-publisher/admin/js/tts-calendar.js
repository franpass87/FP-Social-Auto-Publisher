(function($){
    $(document).on('click', '.tts-calendar-nav a', function(e){
        e.preventDefault();
        var month = $(this).data('month');
        if(month){
            var url = new URL(window.location.href);
            url.searchParams.set('month', month);
            window.location.href = url.toString();
        }
    });
})(jQuery);
