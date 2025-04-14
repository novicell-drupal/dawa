"use strict";
(function ($, Drupal) {
  var autofocus_addresses = [];

  Drupal.behaviors.dawa = {
    attach: function (context, settings) {

      $('.js-dawa-autocomplete').once('dawa_autocomplete').each(function() {
        var input = $(this).find('.js-autocomplete-field');
        var id_field = input.attr('data-dawa-id');
        var target = $(this).find('input[name="' + id_field + '"]');

        dawaAutocomplete.dawaAutocomplete(input[0], {
          select: function(selected) {
            if (selected.data.id.length > 0) {
              target.val(selected.data.id);
              input.trigger('dawa:selected');
            } else {
              target.val('');
            }
            input.trigger('change');
            // Trigger event after a short delay to ensure drupals form api doesn't forget the entered value.
            setTimeout(function () {
              autofocus_addresses.push(selected.data.id);
              input.trigger('dawa_autocomplete_finished');
            }, 30);
          },
          params: {per_side: 10},
          multiline: true
        });

        input.on('focus', function () {
          var index = autofocus_addresses.indexOf(target.val());
          if (target.val() !== '' && index > -1) {
            input.blur();
            autofocus_addresses.splice(index, 1);
          }
        });

        // Trigger reset when removing data from field.
        input.on('keyup', function (e) {
          if (e.which !== 13) {
            target.val('');
          }
        });
      });
    }
  };
})(jQuery, Drupal);
