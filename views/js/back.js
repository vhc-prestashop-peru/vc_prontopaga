/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(function() {
  var toggleUrl = $('#vc-prontopaga-toggle-url').val(); // URL generada dinámicamente

  $('.toggle-status-btn').on('click', function() {
    var $text = $(this).find('.status-text');
    var $row = $(this).closest('tr');
    var idMethod = $row.data('method-id');

    if (!idMethod) {
      console.error('Method ID not found.');
      return;
    }

    var originalText = $text.text().trim(); // Guardamos el estado original (Active o Inactive)
    var originalStatus = (originalText === 'Active') ? 1 : 0;
    var newStatus = (originalStatus === 1) ? 0 : 1;

    // Opcional: puedes poner un loading temporal
    $text.text('Updating...').css('color', 'gray');

    // Enviar AJAX
    fetch(toggleUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      credentials: 'same-origin', // 🔥 Mantener sesión
      body: `id_method=${idMethod}&new_status=${newStatus}&ajax=1`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // ✅ Aquí actualizar visualmente basado en lo que diga el servidor
        console.log((parseInt(data.new_status) === 1))
        if (parseInt(data.new_status) === 1) {
            console.log('Active GREEN')
          $text.text('Active').css('color', 'green');
        } else {
            console.log('Inactive RED')
          $text.text('Inactive').css('color', 'red');
        }
      } else {
        // 🔄 Rollback visual
        console.log('rollbackState')
        rollbackState($text, originalStatus);
      }
    })
    .catch(error => {
      console.error('Error updating status:', error);
    console.log('rollbackState - catch')
      rollbackState($text, originalStatus);
    });
  });

  // 🔄 Función rollback (si falla el fetch)
  function rollbackState($element, originalStatus) {
    if (originalStatus === 1) {
      $element.text('Active').css('color', 'green');
    } else {
      $element.text('Inactive').css('color', 'red');
    }
  }

  // Código para mostrar/ocultar contraseñas
  $('.toggle-secret').each(function() {
    var target = $(this).data('target');
    $('#' + target).attr('type', 'password');
  });

  $('.toggle-secret').on('click', function(e) {
    e.preventDefault();
    var target = $(this).data('target');
    var $input = $('#' + target);
    if ($input.attr('type') === 'password') {
      $input.attr('type', 'text');
      $(this).find('i').removeClass('icon-eye').addClass('icon-eye-slash');
    } else {
      $input.attr('type', 'password');
      $(this).find('i').removeClass('icon-eye-slash').addClass('icon-eye');
    }
  });
});