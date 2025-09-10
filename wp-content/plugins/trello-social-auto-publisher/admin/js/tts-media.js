(function($){
    $(function(){
        var frame;
        function updateAttachmentIds(){
            var ids = [];
            $('#tts_attachments_list .tts-attachment-item').each(function(){
                var cb = $(this).find('.tts-attachment-select');
                if(cb.is(':checked')){
                    ids.push(cb.val());
                }
            });
            $('#tts_attachment_ids').val(ids.join(','));
        }
        $('#tts_attachments_list').sortable({
            stop: updateAttachmentIds
        });
        $('#tts_attachments_list').on('change', '.tts-attachment-select', updateAttachmentIds);
        updateAttachmentIds();
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
