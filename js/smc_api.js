(function ($, Drupal) {

  function listingGridJackpot(jackpotSource, fieldId) {
    var id = fieldId.substr(0, 41);
    if (jackpotSource == 'manual') {
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-game-id-wrapper']`).hide();
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-amount-wrapper']`).show();
    } else if (jackpotSource == 'smc-api') {
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-game-id-wrapper']`).show();
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-amount-wrapper']`).hide();
    } else {
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-game-id-wrapper']`).hide();
      $(`div[id*='${id}-field-jackpot-widget-0-subform-field-jackpot-amount-wrapper']`).hide();
    }
  }

  Drupal.behaviors.listingGrid = {
    attach: function () {
      /* Hides the Jackpot widget title field for listing grid 1-Column Carousel */
      $("div[id*='-field-jackpot-widget-0-subform-field-title-wrapper']").hide();

      /* Hides the Jackpot widget show title field for listing grid 1-Column Carousel */
      $("div[id*='-field-jackpot-widget-0-subform-field-show-title']").hide();

      // Add functionality for Jackpot integration for 1 column carousel
      $("select[id*='-subform-field-jackpot-widget-0-subform-field-jackpot-source']").change(function () {
        var jackpotSource = $(this).val();
        var id = $(this).attr('id');
        listingGridJackpot(jackpotSource, id);
      });

      $("select[id*='-subform-field-jackpot-widget-0-subform-field-jackpot-source']").trigger("change");
    }
  };
}(jQuery, Drupal));
