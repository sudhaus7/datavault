define(['jquery', 'TYPO3/CMS/Guard7/Guard7Tools'], function ($, Guard7Tools) {

    var unlockData = function () {
        if (Guard7Tools.hasPrivateKey()) {
            if (sudhaus7guard7tables && sudhaus7guard7tables.length > 0) {

                var ajaxUrl = TYPO3.settings.ajaxUrls['guard7_backend_list_data'];


                sudhaus7guard7tables.forEach(function (tablename) {

                    var uids=[];
                    $('table[data-table="' + tablename + '"] tr[data-uid]').each(function(i,tr) {
                        if ($(tr).data('uid')) {
                            uids.push($(tr).data('uid'));
                        }
                    });
                    if (uids.length > 0) {
                        $.getJSON(ajaxUrl, {'table': tablename, 'uids': uids}, function (sudhaus7guard7data) {
                            var privkey = Guard7Tools.getPrivateKey();
                            sudhaus7guard7data.forEach(function (e) {
                                Guard7Tools.decode(privkey, e, function (data) {
                                    $('table[data-table="' + e.tablename + '"] tr[data-uid="' + e.tableuid + '"] td:nth-child(2) span').attr('title', data).text(data);

                                    $('table[data-table="' + e.tablename + '"] tr[data-uid="' + e.tableuid + '"] td:nth-child(2) a').attr('title', data).text(data);
                                });
                            });
                        });
                    }
                });



            }
        }
    };

    var lockData = function () {
        sudhaus7guard7tables.forEach(function (e) {
            //$('table[data-table="'+e+'"] tr[data-uid] td.col-title span').attr('title',"🔒").text("🔒");
            //$('table[data-table="'+e+'"] tr[data-uid] td.col-title a').attr('title',"🔒").text("🔒");

            $('table[data-table="'+e+'"] tr[data-uid] td.needslocked').each(function(ii,ee) {
                $(ee).text("🔒");
            });

        });
    };
    var cleanLock = function () {
        sudhaus7guard7tables.forEach(function (e) {

            $('table[data-table="'+e+'"] tr[data-uid] td').each(function(ii,ee) {
                if ($(ee).text()==='&amp;#128274;' || $(ee).text()==='&#128274;') {
                    $(ee).text("🔒").addClass('needslocked');
                }
            });

        });
    };

    if (sudhaus7guard7tables) {
        cleanLock();
        if (Guard7Tools.hasPrivateKey()) {
            unlockData();
        } else {

            lockData();
        }
    }

    top.TYPO3.jQuery('body').on('sudhaus7-guard7-privkey-activated', function () {
        unlockData();
    });
    top.TYPO3.jQuery('body').on('sudhaus7-guard7-privkey-removed', function () {
        lockData();
    });

});
