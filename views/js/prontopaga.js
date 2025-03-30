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

document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.prontopaga-methods');
    const paymentLink = container ? container.getAttribute('data-link') : null;

    if (!paymentLink) {
        console.error('No payment link found.');
        return;
    }

    const loader = document.createElement('div');
    loader.innerHTML = '<div class="spinner">Procesando...</div>';
    loader.className = 'prontopaga-loader-overlay';
    document.body.appendChild(loader);
    loader.style.display = 'none';

    document.querySelectorAll('.prontopaga-pay-btn').forEach(button => {
        button.addEventListener('click', function () {
            const method = this.getAttribute('data-method');
            if (!method) return;

            // Show loader and disable buttons
            loader.style.display = 'flex';
            document.querySelectorAll('.prontopaga-pay-btn').forEach(btn => btn.disabled = true);

            fetch(paymentLink, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ paymentMethod: method })
            })
            .then(response => response.json())
            .then(data => {
                if (data.link_autorized) {
                    window.location.href = data.link_autorized;
                } else {
                    alert('No se pudo generar el enlace de pago.');
                    loader.style.display = 'none';
                    document.querySelectorAll('.prontopaga-pay-btn').forEach(btn => btn.disabled = false);
                }
            })
            .catch(error => {
                console.error('Error al generar el pago:', error);
                alert('Hubo un problema al contactar con el servidor.');
                loader.style.display = 'none';
                document.querySelectorAll('.prontopaga-pay-btn').forEach(btn => btn.disabled = false);
            });
        });
    });
});