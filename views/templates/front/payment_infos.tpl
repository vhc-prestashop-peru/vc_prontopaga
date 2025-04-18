{*
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
*}

{if isset($payment_methods) && $payment_methods}
    <div class="prontopaga-methods with-opacity text-center" data-link="{$payment_link|escape:'htmlall':'UTF-8'}">
        <div>
            {foreach from=$payment_methods item=method}
                <div class="payment-method-item">
                    <button type="button"
                            class="prontopaga-pay-btn btn"
                            data-method="{$method.method|escape:'htmlall':'UTF-8'}"
                            style="background: none; border: none; padding: 0; cursor: pointer;">
                        <img src="{$method.logo|escape:'htmlall':'UTF-8'}"
                             alt="{$method.name|escape:'htmlall':'UTF-8'}"
                             title="{$method.name|escape:'htmlall':'UTF-8'}"
                             class="payment-method-img"
                             style="max-height:60px; max-width:100%; object-fit:contain;" />
                    </button>
                </div>
            {/foreach}
        </div>
    </div>
{else}
    <p class="alert alert-danger">{l s='No available payment methods. Please contact support.' mod='vc_prontopaga'}</p>
{/if}