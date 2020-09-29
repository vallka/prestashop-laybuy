{if $workflow == 'standard' }
	<div class="laybuy-cart-widget">
		<h3>Check out with <img style = "{$attrs['style']}" src="{$logo_url}" alt = "Laybuy" /> and pay by instalment.</h3>
		<p>Pay 6 weekly interest-free payments from <strong>{$amount}</strong>. <a href="#" class="laybuy-logo-link"><span id="laybuy-what-is-modal">&nbsp;What's this?</span></a></p>
	</div>
{/if}

{if $workflow == 'pay_today' }
	<div class="laybuy-cart-widget">
		<h3>Check out with Laybuy and pay by instalment.</h3>
		<p>Pay <strong>{$pay_today}</strong> today & 5 weekly interest-free payments of <strong>{$amount}</strong> <a href="#" class="laybuy-logo-link"><span id="laybuy-what-is-modal">What's this?</span></p>
	</div>
{/if}