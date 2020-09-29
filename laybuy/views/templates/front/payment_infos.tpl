{*
* 2007-2015 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<section>
	<div class="laybuy-checkout-content">

		{if $workflow == 'standard' }
			<p class="title"> Pay it in 6 weekly, interest-free payments from <strong>{$amount}</strong></p>
		{/if}
		{if $workflow == 'pay_today' }
			<p class="title"> Pay <strong>{$pay_today}</strong> today & 5 weekly interest-free payments of <strong>{$amount}</strong></p>
		{/if}

		<div class="laybuy-checkout-img">
			<img class="left-column" src="https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_pay.jpg">
			<img src="https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_schedule.jpg">
			<img class="left-column second-row" src="https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_complete.jpg">
			<img class="second-row" src="https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_done.jpg">
		</div>
	</div>
	<div style="clear:both" />
</section>