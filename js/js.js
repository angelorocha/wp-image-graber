jQuery(function ($) {
    $('.wig-delete-all').click(function () {
        $('.wig-delete-image').not(this).prop('checked', this.checked);
    });
});