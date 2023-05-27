{if $enovate["invoice_number"]}
    <a id="oblio_{$enovateAction}_button_view" class="btn btn-default btn-outline-secondary" href="{$link->getAdminLink('AdminOblioInvoice')|escape:'html':'UTF-8'}&amp;enovate_action={$enovateAction}&amp;id_order={$id_order}&amp;redirect=1" target="_blank">
      <i class="icon-file"></i>
      <span>Vezi comanda in WinMentor</span>
    </a>
{else}
    <a id="enovate_{$enovateAction}_button" class="btn btn-default btn-outline-secondary enovate-generate-{$enovateAction}" href="{$link->getAdminLink('AdminEnovateMentor')|escape:'html':'UTF-8'}&amp;enovate_action={$enovateAction}&amp;id_order={$id_order}" target="_blank">
      <i class="icon-file"></i>
      <span>Trimite comanda in WinMentor</span>
    </a>
{/if}

{literal}
<style type="text/css">
body.page-is-loading * {cursor:wait!important;}
.oblio-form-horizontal {margin:15px 0 0;}
.hidden {display:none;}
</style>
<script type="text/javascript">
"use strict";
(function($) {
    $(document).ready(function() {
        var buttons = $('.enovate-generate-{/literal}{$enovateAction}{literal}'),
            message = $('.enovate-response');
        buttons.click(function(e) {
            var self = $(this), postData = {};
            if (self.hasClass('disabled')) {
                return false;
            }
            if (!self.hasClass('enovate-generate-{/literal}{$enovateAction}{literal}')) {
                return true;
            }
            
            e.preventDefault();
            self.addClass('disabled');
            $(document.body).addClass('page-is-loading');
            
            jQuery.ajax({
                method: 'POST',
                dataType: 'json',
                url: self.attr('href'),
                data: postData,
                success: function(response) {
                    var alert = '';
                    self.removeClass('disabled');
                    $(document.body).removeClass('page-is-loading');
                    
                    if ('link' in response) {
                        buttons
                            .not(self)
                            .hide()
                        self
                            .attr('href', response.link)
                            .removeClass('enovate-generate-{/literal}{$enovateAction}{literal}')
                            .text(`Vezi {/literal}{$enovateAction}{literal} ${response.seriesName} ${response.number}`);
                        alert = '<div class="alert alert-success alert-dismissible" role="alert">\
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
                          {/literal}{$enovateAction|ucfirst}{literal} a fost emisa\
                        </div>';
                    } else if ('error' in response) {
                        alert = '<div class="alert alert-danger alert-dismissible" role="alert">\
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
                          ' + response.error + '\
                        </div>';
                    }
                    message.html(alert);
                }
            });
        });
        
        deleteButton.click(function(e) {
            var self = $(this);
            if (self.hasClass('disabled')) {
                return false;
            }
            e.preventDefault();
            self.addClass('disabled');
            jQuery.ajax({
                dataType: 'json',
                url: self.attr('href'),
                data: {},
                success: function(response) {
                    var alert = '';
                    if (response.type == 'success') {
                        location.reload();
                    } else {
                        alert = '<div class="alert alert-danger alert-dismissible" role="alert">\
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
                          ' + response.message + '\
                        </div>';
                        message.html(alert);
                        self.removeClass('disabled');
                    }
                }
            });
        });
    });
})(jQuery);
</script>
{/literal}