/**
 * @file
 * Defines Javascript behaviors for the giving module.
 */

(function ($, Drupal) {

  "use strict";

  // All the JavaScript for this file.

  Drupal.behaviors.coursesAccordion = {
    attach: function(context) {
        function c(a) {
            var b = 0;
            null != a ? b = a : $("input[name^='GIFT_AMOUNT']:checked", context).each(function() {
                b = (parseFloat($(this, context).attr("value")) + parseFloat(b)).toFixed(2)
            });
            $("#gift_total", context).text("$" + b)
        }
        
        $("#gift-total", context).prepend("<h3>Gift Total: <span id='gift_total'>$0.00</span></h3>");
        $("label[class*='gift_other_text']", context).each(function() {
            $(this, context).css("display", "none")
        });
        $("input[id^='gift_other_']", context).each(function() {
            $(this, context).attr({
                value: 0
            })
        });
        $("input[id^='gift_value_']", context).each(function(a, b) {
            $(b, context).blur(function() {

                if ($(b, context).val() === "") {
                    $(b, context).attr("value", "0");
                }

                $(b, context).val().match(RegExp(/^([0-9]?){10}\.?([0-9]?){2}$/)) ? ($("#givingErrorNum" + a, context).remove(), "" != $(b, context).attr("value") ? $($("input[id^='gift_other_']", context)[a]).attr({
                    checked: "checked",
                    value: $(b, context).val()
                }) : $($("input[id^='gift_other_']", context)[a], context).attr({
                    value: 0
                })) : $("#givingErrorNum" + a, context).hasClass("error") || ($($("input[id^='gift_other_']", context)[a], context).attr({
                    value: 0
                }), $(b, context).after(" <em class='error' id='givingErrorNum" + a + "'>Please enter a valid dollar amount</em>"));
                c()
            })
        });
        $("input[name^='FUND']", context).each(function(a) {
            $("input[name='GIFT_AMOUNT" + (a + 1) + "']:not([id^='gift_other'])", context).each(function(b, c) {
                $(c, context).change(function() {
                    $($("label[class*='gift_other_text']", context)[a], context).hide()
                })
            })
        });
        $("input[id^='gift_other_']", context).each(function(a, b) {
            $(b, context).change(function() {
                $($("label[class*='gift_other_text']", context)[a], context).show().focus()
            }).click(function() {
                $($("label[class*='gift_other_text']", context)[a], context).show().focus()
            })
        });
        $("input[name^='GIFT_AMOUNT']", context).change(function() {
            c()
        }).click(function() {
            c()
        }).select(function() {
            c()
        }).keypress(function() {
            c()
        });
        $("form[id='gift_form']", context).bind("submit", function() {
            var a = !0;
            $("input[name^='GIFT_AMOUNT']:checked", context).each(function() {
                $(this, context).attr("value").match(RegExp(/^([0-9]?){10}\.?([0-9]?){2}$/)) || (a = !1)
            });
            $("input[id^='gift_other_']:checked", context).each(function() {
                0 < $("em[class*='error']", context).length && (a = !1)
            });
            if (!a) return !1
        });
        $("input[type='reset']", context).click(function() {
            c("0.00");
            $("input[id^='gift_other_']", context).each(function() {
              $(this, context).attr({
                value: 0
              });
              $(this, context).removeAttr("checked");
            });
            $("label[class*='gift_other_text']", context).each(function() {
                $(this, context).css("display", "none")
            });
        });

        document.getElementById("gift_form").onsubmit = function validateForm() {
            var totalValue = document.getElementById("gift_total").innerText;
            if (totalValue == "$0.00" || totalValue == "$NAN") {
                alert("Please select or enter an amount for at least one fund.");
                return false;
            } else {
                return true;
            }
        }
    }
  }



})(jQuery, Drupal);






