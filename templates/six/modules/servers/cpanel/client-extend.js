function doFtpCreate() {
    jQuery("#btnCreateFtpLoader").addClass('fa-spinner fa-spin').removeClass('fa-plus');
    jQuery("#ftpCreateSuccess").slideUp();
    jQuery("#ftpCreateFailed").slideUp();
    jQuery.post(
        "index.php?m=cpanelextender&action=addftpuser",
        jQuery("#frmCreateFtpAccount").serialize(),
        function( data ) {
            jQuery("#btnCreateFtpLoader").removeClass('fa-spinner fa-spin').addClass('fa-plus');
            if (data.result === 1) {
                jQuery("#ftpCreateSuccess").hide().removeClass('hidden')
                    .slideDown();
            } else {
                jQuery("#ftpCreateFailedErrorMsg").html(data.reason);
                jQuery("#ftpCreateFailed").hide().removeClass('hidden')
                    .slideDown();
            }
        },
        "json"
    );
}
