jQuery(document).ready(function($){
    // Step 1 validation
    $('.tts-step-1').on('submit', function(){
        var key = $('input[name="trello_key"]').val();
        var token = $('input[name="trello_token"]').val();
        if(!key || !token){
            alert('Trello key and token are required');
            return false;
        }
    });

    // Dynamic Trello lists loading for step 3
    var $lists = $('#tts-lists');
    if($lists.length){
        var data = {
            action: 'tts_get_lists',
            nonce: ttsWizard.nonce,
            board: $lists.data('board'),
            key: $lists.data('key'),
            token: $lists.data('token')
        };
        $.post(ttsWizard.ajaxUrl, data, function(resp){
            if(resp.success){
                var channels = ['facebook','instagram','youtube','tiktok'];
                resp.data.forEach(function(list){
                    var row = $('<p/>');
                    row.append($('<span/>').text(list.name + ' '));
                    var select = $('<select/>').attr('name','tts_trello_map['+list.id+'][canale_social]');
                    select.append($('<option/>').val('').text('--'));
                    channels.forEach(function(ch){
                        select.append($('<option/>').val(ch).text(ch));
                    });
                    row.append(select);
                    $lists.append(row);
                });
            } else {
                $lists.append($('<p/>').text('Unable to load lists'));
            }
        });
    }
});
