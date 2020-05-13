=== Automater ===
Contributors: automater
Tags: automater, woocomerce, automation, allegro, ebay
Requires at least: 5.0
Tested up to: 5.4
Requires PHP: 5.5
Stable tag: 0.2.2
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Reliable system for sales automation and shipping digital goods purchased on eBay and online stores.

== Description ==
The connection of **Automater** system with **WooCommerce** platform allows you to automate sending codes or files to Customers after payment. The cost of handling each transaction is **1% of its value**, but not less than 0,05$. Credits for handling transactions are not charged - only commission is charged.

> **Example 1:** transaction for amount of **$1.00** = commission **$0.05**.
> **Example 2:** transaction for amount of **$100.00** = commission **$1.00**.

Automater plugin integrated with online store allows you to automatically:

+ connect products from the store with products on Automater
+ create a transaction in Automater after purchase in store
+ posting payments in Automater after payment in store

== Installation ==

**Manually**
+ Download the installation package.
+ Log in to the WordPress admin panel and go to _plugins / add new_.
+ Select _upload plugin_ button and select the previously downloaded plugin file, then install and enable the plugin.

**Automatically**
+ Log in to the WordPress admin panel and go to _plugins / add new_.
* In the search field, enter _automater_.
* With the appropriate plugin click _install now_.

**Configuration**
+ Go to the tab _WooCommerce/ settings_ and select the tab _integration_.
+ Log in to Automater and go to the _settings / settings / API_.
+ If the keys are not generated, click _generate new keys_.
+ Rewrite API Key and API Secret values to the appropriate fields in the configuration. In order to synchronize the list of products with Automater, press the _Import products from your Automater account_ button.
+ In the product settings in the store, you must associate the product from the product store with the Automater account by adding a new attribute _product from Automater_ (if there is no equivalent it must be created).
+ Done - now, products related with Automater will be sent automatically.

== Frequently Asked Questions ==
If you have problems installing and configuring the plug-in, please contact [by clicking here](https://automater.com/p/p-contact). However, to learn more about the wider possibilities of integration, we invite you to read [our API](https://github.com/automater-pl/rest-api/).

For additional information andhelp with configuration, see [Help](https://help.automater.com/help).
