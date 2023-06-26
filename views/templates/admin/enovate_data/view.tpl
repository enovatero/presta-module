
<div id="enovate_message"></div>

<div class="panel">
    <div class="panel-heading">Sincronizare preturi WME->PRESTA</div>
    <p>Aceasta actiune iti permite sa sincronizezi preturile din WinMentor catre Presta.</p>
    <a id="enovate_update_prices" class="btn btn-default" href="">
      <i class="icon-file"></i>
      {$btnName}
    </a>
</div>

<div class="panel">
    <div class="panel-heading">Sincronizare stocuri</div>
    <p>Aceasta actiune iti permite sa sincronizezi stocurile din WinMentor catre Presta.</p>
    <a id="enovate_update_stock" class="btn btn-default" href="">
        <i class="icon-file"></i>
        {$btnName}
    </a>
</div>

<div class="panel">
    <div class="panel-heading">Sincronizare produse</div>
    <p>Aceasta actiune iti permite sa sincronizezi produsele din Presta catre WinMentor.</p>
    <a id="enovate_update_products" class="btn btn-default" href="">
        <i class="icon-file"></i>
        {$btnName}
    </a>
</div>

<div class="panel">
    <div class="panel-heading">Sincronizare preturi PRESTA->WME</div>
    <p>Aceasta actiune iti permite sa sincronizezi preturile din Presta catre WinMentor.</p>
    <a id="enovate_send_prices" class="btn btn-default" href="">
        <i class="icon-file"></i>
        {$btnName}
    </a>
</div>

<script type="text/javascript">
"use strict";
var ajaxLink = "{$link->getAdminLink('AdminEnovateData')|escape:'UTF-8'}&action=ajax";
{literal}
$(document).ready(function() {
    $('#enovate_update_stock').click(function(e) {
        var self = $(this);
        self.find('i').attr('class', 'icon-circle-o-notch icon-spin');
        e.preventDefault();
        $.ajax({
            url: ajaxLink + '&type=sync_stock',
            dataType: 'json',
            success: function(data) {
                if (data[1]) {
                    addMessage(data[1], 'danger');
                } else {
                    addMessage(`pentru <b>${data[0]['updated']}</b> produse a fost sincronizat stocul<br>${data[0]['total']} produse gasite in WME<br>${data[0]['notFound']} produse nu au fost gasite in catalog`, 'success');
                }
                self.find('i').attr('class', 'icon-file');
            }
        });
    });
    $('#enovate_update_prices').click(function(e) {
        var self = $(this);
        self.find('i').attr('class', 'icon-circle-o-notch icon-spin');
        e.preventDefault();
        $.ajax({
            url: ajaxLink + '&type=sync_prices',
            dataType: 'json',
            success: function(data) {
                if (data[1]) {
                    addMessage(data[1], 'danger');
                } else {
                    addMessage(`pentru <b>${data[0]['updated']}</b> produse au fost sincronizare preturile<br>${data[0]['total']} produse gasite in WME<br>${data[0]['notFound']} produse nu au fost gasite in catalog`, 'success');
                }
                self.find('i').attr('class', 'icon-file');
            }
        });
    });
    $('#enovate_update_products').click(function(e) {
        var self = $(this);
        self.find('i').attr('class', 'icon-circle-o-notch icon-spin');
        e.preventDefault();
        $.ajax({
            url: ajaxLink + '&type=sync_products',
            dataType: 'json',
            success: function(data) {
                if (data[1]) {
                    addMessage(data[1], 'danger');
                } else {
                    if (data[0] == 0) {
                        addMessage(`Nu au fost gasite produse care necesita sincronizare`, 'warning');
                    } else {
                        addMessage(`Au fost sincronizate ${data[0]} produse`, 'success');
                    }
                }
                self.find('i').attr('class', 'icon-file');
            }
        });
    });
    $('#enovate_send_prices').click(function(e) {
        var self = $(this);
        self.find('i').attr('class', 'icon-circle-o-notch icon-spin');
        e.preventDefault();
        $.ajax({
            url: ajaxLink + '&type=send_prices',
            dataType: 'json',
            success: function(data) {
                if (data[1]) {
                    addMessage(data[1], 'danger');
                } else {
                    if (data[0] == 0) {
                        addMessage(`Nu au fost gasite produse care necesita sincronizare`, 'warning');
                    } else {
                        addMessage(`Au fost sincronizate ${data[0]} produse`, 'success');
                    }
                }
                self.find('i').attr('class', 'icon-file');
            }
        });
    });
    
    function addMessage(message, type) {
        var response = $('#enovate_message'), html = '';
        html = '<div class="alert alert-' + type + ' alert-dismissible" role="alert">\
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
          ' + message + '\
        </div>';
        response.html(html);
    }
});
{/literal}
</script>