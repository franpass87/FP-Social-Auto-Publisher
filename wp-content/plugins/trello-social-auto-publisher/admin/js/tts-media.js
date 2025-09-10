(function($){
    $(function(){
        var frame;
        $('.tts-select-media').on('click', function(e){
            e.preventDefault();
            if(frame){
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Seleziona o Carica file',
                button: { text: 'Usa questo file' },
                multiple: false
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#tts_manual_media').val(attachment.id);
            });
            frame.open();
        });
    });
})(jQuery);
