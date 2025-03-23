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

{extends file='page.tpl'}

{block name='breadcrumb'}
    <nav data-role="breadcrumb" class="breadcrumb hidden-sm-down">
        <ol>
            <li>
                <a href="{$link->getPageLink('index', true)}">
                    {l s='Home' d='Shop.Theme.Global'}
                </a>
            </li>
            <li>
                <a href="{$link->getPageLink('order', true)}">
                    {l s='Checkout' d='Shop.Theme.Checkout'}
                </a>
            </li>
            <li>
                <span>{l s='ProntoPaga' mod='vc_prontopaga'}</span>
            </li>
        </ol>
    </nav>
{/block}

{block name='page_content'}
<div class="container">
    <h3>{l s='Available Payment Methods' mod='vc_prontopaga'}</h3>
    <p style="margin-bottom: 25px;">
        {l s='Please select a payment method to be redirected to ProntoPaga.' mod='vc_prontopaga'}
    </p>

    {if isset($payment_methods) && $payment_methods}
        <div class="row prontopaga-methods">
            {foreach from=$payment_methods item=method name=loop}
                <div class="col-md-6 mb-4">
                    <div class="payment-method-item d-flex align-items-center justify-content-between border p-3 rounded">
                        <div class="d-flex align-items-center">
                            <img src="{$method.logo|escape:'htmlall':'UTF-8'}"
                                 alt="{$method.name|escape:'htmlall':'UTF-8'}"
                                 style="max-height:60px; object-fit:contain; margin-right:15px;" />
                        </div>
                        <a href="{$method.paymentUrl|escape:'htmlall':'UTF-8'}" class="btn btn-primary">
                            {l s='Pay now' mod='vc_prontopaga'} →
                        </a>
                    </div>
                </div>
            {/foreach}
        </div>
    {else}
        <p class="alert alert-danger">
            {l s='No available payment methods. Please contact support.' mod='vc_prontopaga'}
        </p>
    {/if}
</div>
{/block}

{block name='page_footer'}
    <a href="{$link->getPageLink('order', true, null, 'step=3')}" class="account-link">
        &lt; {l s='Back to payment methods' mod='vc_prontopaga'}
    </a>
{/block}