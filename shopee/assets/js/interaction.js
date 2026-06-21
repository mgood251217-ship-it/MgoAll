// $(function() {
//     $('.show-alert__error').click(function() {
//         FancyAlerts.show({msg: 'Uh oh something went wrong!',type: 'error'})
//     })
//     $('.show-alert__success').click(function() {
//         FancyAlerts.show({msg: 'Nailed it! This totally worked.'})
//     })
//     $('.show-alert__info').click(function() {
//         FancyAlerts.show({msg: 'So long and thanks for all the shoes.',type: 'info'})
//     });
// })


var FancyAlerts = (function() {
    
    var self = this;
    
    self.show = function(options) {
            if($('.fancy-alert').length > -1) {
                FancyAlerts.hide();
            }
            var defaults = {
                type: 'success',
                msg: 'Success',
                timeout: 5000,
                icon: 'fa fa-check',
                onClose: function() {}
            };

            if(options.type === 'error' && !options.icon) options.icon = 'fa fa-exclamation-triangle';
            if(options.type === 'info' && !options.icon) options.icon = 'fa fa-cog';

            var options = $.extend(defaults, options);

            var $alert = $('<div class="fancy-alert '+ options.type +' ">' +
                                '<div class="">' +
                                    '<i class="fancy-alert--icon ' + options.icon + '"></i>' +
                                    '<div class="fancy-alert--content">' +
                                        '<div class="fancy-alert--words">' +options.msg + '</div>' +
                                        '<a class="fancy-alert--close" href="#"><i class="fa fa-times"></i></a>' +
                                    '</div>' +
                                '</div>' +
                            '</div>');
            
            $('body').prepend($alert);
            setTimeout(function() {
                $alert.addClass('fancy-alert__active');
            }, 10);

            setTimeout(function() {
                $alert.addClass('fancy-alert__extended');
            }, 500);

            if(options.timeout) {
                self.hide(options.timeout);    
            }
            $('.fancy-alert--close').on('click', function(e) {
                e.preventDefault();
                self.hide();
            });

            $alert.on('fancyAlertClosed', function() {
                options.onClose();
            });
        };
    
    
        self.hide = function(_delay) {
            var delay = _delay || 0;

            var $alert = $('.fancy-alert');
            setTimeout(function() {
                setTimeout(function() {
                    $alert.removeClass("fancy-alert__extended");
                }, 10);

                setTimeout(function() {
                    $alert.removeClass('fancy-alert__active');
                }, 500);
                setTimeout(function() {
                    $alert.trigger('fancyAlertClosed');
                    $alert.remove();
                }, 1000);
            }, delay);
        }
    
    return self;
    
})();