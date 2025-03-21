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

{block name='page_content'}
<div>
	<h3>{l s='Available Payment Methods' mod='vc_prontopaga'}</h3>
    <br><br>
	{if isset($payment_methods) && $payment_methods}
		<ul class="list-group">
			{foreach from=$payment_methods item=method}
				<li class="list-group-item">
					<img src="{$method.logo|escape:'htmlall':'UTF-8'}" alt="{$method.name|escape:'htmlall':'UTF-8'}" style="height:80px; margin-right:10px;">
					<strong>{$method.name|escape:'htmlall':'UTF-8'}</strong>
				</li>
			{/foreach}
		</ul>
	{else}
		<p class="alert alert-danger">{l s='No available payment methods. Please contact support.' mod='vc_prontopaga'}</p>
	{/if}
</div>
{/block}
