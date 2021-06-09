Recently we subscribed to [Laybuy](https://www.laybuy.com/uk/for-merchants) with our Prestashop-based eCommerce website. It provides Prestashop module out of a box. The module is easy to install and setup. However, immediately we have found a problem. In certain circumstances Laybuy module reported an error - totals didn't sum up:

![](https://www.vallka.com/media/markdownx/2021/05/22/395b4707-a074-4093-8b51-bb3f10c5eb6a.png)

What is the problem? Enabling a debug mode in Laybuy module (thank you developers!) shows the source of the problem: Laybuy adds a delivery cost - which should be 0 for free shipping to the total, so total totals become different. Why this is happening? It looks like there is something wrong in Prestashop itself, not in the Laybuy module.

Even looking at the screenshot above one can notice something not quite right: there is a subtotal of £56.00, plus a discount of £6.95, plus free shipping, so the final total should be shown as £49.05. However, it is shown as £56.00. This is because the shown discount £6.95 as actually is a discount for free shipping - normal shipping cost is £6.95, so adding a discount of £6.95 gives customer a free shipping. But looking at the given pictures it is difficult to realize. (However, we didn't get any complaints from our customers.) I think the line "Discount(s) - £6.95" shouldn't be shown at all, or have some comment. Where is the error, in our Prestashop theme (highly customized), or in the [Promotions and discounts module](https://addons.prestashop.com/en/promotions-gifts/9129-promotions-and-discounts-3x2-sales-offers-packs.html?_ga=2.86574555.2145252126.1621709716-1707616425.1616341995), which we use? Or in the core Prestashop code? I don't know.

Interesting, the situation with the Laybuy module is exactly the opposite. Laybuy gets order totals, using core Prestashop classes, and somehow gets £6.95 as a shipping cost. Which should be 0 as Free shipping discount applies to orders over £50. This discount is created using Promotions and discounts module.
Where is the error? Most probably in the same place as the first error with showing discount to the customer, that is in Prestashop itself. I have a look at the Prestashop code… well, it doesn't seem possible to find and fix. I can only make things worse. So instead I decided to "fix" the Laybuy module, adding a simple patch to its code. If totals are not equal - make them equal by subtracting the shipping cost. Easy-peasy!


Where is the error? Most probably in the same place as the first error with showing discount to customer, that is in Prestashop itself. I have a look at the Prestashop code... well, it isn't seems possible to find and fix. I can only make things worse. So instead I decided to "fix" the Laybuy module, adding a simple patch to its code. If totals are not equal - make them equal by subtract the shipping cost. Easy-peasy!



```
/**
    * Process the Order Items
    *
    * @return array
    * since 1.0.0
    */
    private function _processItems() {

        $tax = floatval($this->cart->getOrderTotal() - $this->cart->getOrderTotal(false));

        // items total
        $shipping_total = $this->cart->getOrderTotal(false, Cart::ONLY_SHIPPING);

        //vallka:
        $items_price = $this->cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING);
        $amount = $this->cart->getOrderTotal();

        if (Laybuy::$debug) {
            PrestaShopLogger::addLog("laybuy-vallka:$amount,$items_price,$tax,$shipping_total", 3, NULL, "Laybuy", 1);
        }

        if ($items_price+$tax == $amount) {
            $shipping_total = 0;
            PrestaShopLogger::addLog("laybuy-vallka-corrected!:$amount,$items_price,$tax,$shipping_total", 3, NULL, "Laybuy", 1);
        }
        //vallka end


        // shipping
        if ($shipping_total > 0) {
            $items[] = array(
                'id' => 'shipping_fee#' . $this->cart->id,
                'description' => 'Shipping fee',
                'quantity' => '1',
                'price' =>  $shipping_total
            );
        }

        // tax
        if ($tax) {
            $items[] = array(
                'id' => 'total_tax_amount_for_order#' . $this->cart->id,
                'description' => 'Tax amount for this order',
                'quantity' => '1',
                'price' => $tax
            );
        }

        $items[] = [
            'id'          => 'item_for_order___#' . $this->cart->id,
            'description' => 'Items',
            'quantity'    => 1,
            'price'       => $this->cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING)
        ];

        return $items;
    }
```

And it does work!

What Cart::BOTH_WITHOUT_SHIPPING parameter value for getOrderTotal method is, I can only guess… Well, I cannot even guess what meaning Prestashop developers wanted to give to it….

My fixed version of Laybuy module is here: [https://github.com/vallka/prestashop-laybuy](https://github.com/vallka/prestashop-laybuy)

I will make a pull request to the original repository to let them know. Maybe my fix isn't 100% correct. Or it can be done in a more simple way. I also hope the Prestashop developers will notice this problem and at least comment my post. But for a time being the fix is working and we can use Laybuy / Prestashop / Promotions and discounts module together without errors.
