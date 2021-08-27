=== SMSAPI WooCommerce  ===

Contributors: wpdesk, swoboda
Donate link: http://www.wpdesk.pl/sklep/woocommerce-smsapi/
Tags: smsapi, woocommerce, sms
Requires at least: 4.0
Tested up to: 5.4.2
Stable tag: 2.1
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integracja WooCommerce z bramką SMSAPI.

== Description ==


Wtyczka WooCommerce SMSAPI umożliwia wysyłanie wiadomości SMS do klientów sklepu. Możesz aktywować wiadomości SMS dla zamówień w trakcie realizacji i zrealizowanych oraz po dodaniu notatki do zamówienia.

= EN =

WooCommerce SMSAPI is a plugin that extends WooCommerce, allowing you to send SMS messages to your customers. You can enable SMS messages for processing, completed orders and when you add a customer note to the order.

== Installation	 ==

Instalację wykonujemy pobierając wtyczkę manualnie lub automatycznie z poziomu panelu administratora Wordpress. 
Do poprawnej konfiguracji konieczne jest wygenerowanie tokena API w panelu klienta SMSAPI.
Wtyczka wykorzystuje domyślnie ustawione pole nadawcy w panelu SMSAPI dla wysyłek SMS.

Manualną instalację można wykonać w kilku krokach:

1. Ściągnij i rozpakuj plik z wtyczką.
2. Wgraj cały katalog wtyczki do katalogu /wp-content/plugins/ na serwerze.
3. Aktywuj wtyczkę w menu Wtyczki w panelu administracyjnym WordPressa.

= EN =

Installation is realized manually or automatic by Wordpress administrator panel. 
For configuration, you need API Token that could be generated in SMSAPI customer panel. 
Plugin uses default sender name for SMS shipment. It could be set in SMSAPI customer panel. 

Manual install could be made in a few steps:

1. Download and unzip the latest release zip file.
2. Upload the entire plugin directory to your /wp-content/plugins/ directory.
3. Activate the plugin through the Plugins menu in WordPress Administration.

== Frequently Asked Questions ==

= Czy muszę mieć konto SMSAPI, aby korzystać z wtyczki? =

Tak, musisz mieć konto SMSAPI, aby korzystać z wtyczki. Możesz zarejestrować się [tutaj](http://wpde.sk/smsapi).

= Do I need SMSAPI account to use this plugin? =

Yes, in order to use this payment gateway you need a SMSAPI account. You can register [here](http://wpde.sk/smsapi).

== Screenshots ==

1. Ustawienia wtyczki WooCommerce SMSAPI.
2. Settings of WooCommerce SMSAPI plugin. 

== Changelog ==

= 2.1 - 2021.08.26 =
* Fix security issues

= 2.0 - 2020.08.27 =
* Adding sender field support
* Adding variables to the message content
* Update API Php client to v2.6
* Changing the minimum required PHP Version to 7.0
* New translation files

= 1.2 - 2018.01.22 =
* Add support for SMSAPI.com
* Oauth Token support

= 1.1 - 2015.06.20 =
* Tweaked SMS sender for Eco and Pro

= 1.0 - 2015.05.25 =
* First Release!

== Upgrade Notice ==


`<?php code(); // goes in backticks ?>`
