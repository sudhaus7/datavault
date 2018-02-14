define(['jquery','TYPO3/CMS/Datavault/DatavaultTools'],function($,DatavaultTools) {

    if (window.sessionStorage.getItem('privkey')) {
        $('#sudhaus7-datavault-controller-toolbarcontroller > a > span').removeClass('fa-lock').addClass('fa-unlock');
        $('#sudhaus7-datavault-controller-toolbarcontroller .clearKey').show();
        $('#sudhaus7-datavault-controller-toolbarcontroller .newkey-elem').hide();
    }

    $('#sudhaus7-datavault-controller-toolbarcontroller .newkey-elem button').on('click',function(ev) {
        ev.stopPropagation();
        ev.preventDefault();
        var key = $('#sudhaus7-datavault-controller-toolbarcontroller [name="newkey"]').val();
        if (key.length > 0) {
            DatavaultTools.setPrivateKey(key);
            //window.sessionStorage.setItem('privkey',key);
            $('#sudhaus7-datavault-controller-toolbarcontroller > a > span').removeClass('fa-lock').addClass('fa-unlock');
            $('#sudhaus7-datavault-controller-toolbarcontroller .clearKey').show();
            $('#sudhaus7-datavault-controller-toolbarcontroller .newkey-elem').hide();
            $('body').trigger('sudhaus7-datavault-privkey-activated');
            //$('#sudhaus7-datavault-controller-toolbarcontroller [name="newkey"]').val('');
        }

    });

    $('#sudhaus7-datavault-controller-toolbarcontroller .clearKey button').on('click',function(ev) {
        ev.stopPropagation();
        ev.preventDefault();
        if (DatavaultTools.hasPrivateKey()) {
            DatavaultTools.clearPrivateKey();
            $('#sudhaus7-datavault-controller-toolbarcontroller > a > span').removeClass('fa-unlock').addClass('fa-lock');
            $('#sudhaus7-datavault-controller-toolbarcontroller .clearKey').hide();
            $('#sudhaus7-datavault-controller-toolbarcontroller .newkey-elem').show();
            $('body').trigger('sudhaus7-datavault-privkey-removed');

        }
    });

});
