/**
 * @file
 * Simple countdown used on the download form.
 */

(function (Drupal) {

  'use strict';

  Drupal.emeCoundown = Drupal.emeCoundown || {};
  Drupal.emeCoundown.index = Drupal.emeCoundown.index || 0;
  Drupal.emeCoundown.intervals = Drupal.emeCoundown.intervals || {};

  Drupal.behaviors.emeCountdown = {
    attach: function attach(context) {
      var elements = context.getElementsByClassName('js-eme-countdown');
      if (elements.length) {
        for (var i = 0, max = elements.length; i < max; i++) {
          if (
            elements[i].getAttribute('data-processed') ||
            Number(parseFloat(elements[i].textContent)) != elements[i].textContent
          ) {
            continue;
          }
          var initialCount = parseInt(elements[i].textContent, 10);
          var index = Drupal.emeCoundown.index++;
          Drupal.emeCoundown.intervals[index] = setInterval(function (element, initialCount, index) {
            var current = parseInt(element.textContent, 10) - 1;
            element.textContent = current;
            if (current === 0) {
              clearInterval(Drupal.emeCoundown.intervals[index]);
            }
          }, 1000, elements[i], initialCount, index);

          elements[i].setAttribute('data-processed', '');
        }
      }
    }
  };

})(Drupal);
