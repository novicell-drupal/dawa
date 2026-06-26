"use strict";
(function ($, Drupal, drupalSettings) {
  var autofocus_addresses = [];

  Drupal.behaviors.dawa = {
    attach: function (context, settings) {

      $('.js-dawa-autocomplete').once('dawa_autocomplete').each(function() {
        var adressevaelgerSettings = (drupalSettings.dawa && drupalSettings.dawa.adressevaelger) || {};
        if (!adressevaelgerSettings.token) {
          return;
        }

        var input = $(this).find('.js-autocomplete-field');
        var id_field = input.attr('data-dawa-id');
        var target = $(this).find('input[name="' + id_field + '"]');

        adressevaelger.adressevaelger(input[0], {
          select: function(selected) {
            var selectedAddress = selected.adresse || {};
            var selectedId = selectedAddress.id_lokalid || '';

            if (selectedId.length > 0) {
              target.val(selectedId);
              if (selectedAddress.adressebetegnelse) {
                input.val(selectedAddress.adressebetegnelse);
              }
              input.trigger('dawa:selected');
            } else {
              target.val('');
            }
            input.trigger('change');
            // Trigger event after a short delay to ensure drupals form api doesn't forget the entered value.
            setTimeout(function () {
              autofocus_addresses.push(selectedId);
              input.trigger('dawa_autocomplete_finished');
            }, 30);
          },
          token: adressevaelgerSettings.token,
          apiUrl: adressevaelgerSettings.apiUrl || undefined,
          maksimum: 10
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
})(jQuery, Drupal, drupalSettings);
