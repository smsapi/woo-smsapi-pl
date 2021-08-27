<?php
/**
 * Plugin Name: WooCommerce SMSAPI.pl
 * Plugin URI: http://www.wpdesk.pl/sklep/smsapi-woocommerce/
 * Description: WooCommerce integration with <a href="https://ssl.smsapi.pl/rejestracja/4PT2" target="_blank">SMSAPI.pl</a>.
 * Version: 2.0
 * Author: Inspire Labs
 * Author URI: http://www.inspirelabs.pl/
 * Text Domain: woocommerce-smsapi
 * Domain Path: /languages
 *
 * WC requires at least: 3.9
 * WC tested up to: 4.3
 *
 * Copyright 2015-2020 Inspire Labs Sp. z o.o.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace SMSApi;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('InspirePlugin4')) {
    require_once('class/inspire/plugin4.php');
}

class WooCommerceSmsApiPlugin extends \InspirePlugin4
{
    private static $_oInstance = false;

    protected $_pluginNamespace = "woocommerce-smsapi";
    
    public function __construct()
    {
        parent::__construct();

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('woocommerce_integrations_init', array($this, 'initSmsApiIntergrationAction'));
            add_filter('woocommerce_integrations', array($this, 'addIntegrationFilter'));
        }

        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'initAdminCssAction'), 75);
        }
    }

    public function loadPluginTextDomain()
    {
        parent::loadPluginTextDomain();
        load_plugin_textdomain('woocommerce-smsapi', false, basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * WordPress action
     *
     * Inits css
     */
    public function initAdminCssAction()
    {
        wp_enqueue_style('smsapi_admin_style', $this->getPluginUrl() . '/assets/css/admin.css');
    }

    public static function getInstance()
    {
        if (self::$_oInstance == false) {
            self::$_oInstance = new WooCommerceSmsApiPlugin();
        }
        return self::$_oInstance;
    }

    public function initBaseVariables()
    {
        parent::initBaseVariables();
    }

    public function initSmsApiIntergrationAction()
    {
        if (!class_exists('WcSmsApiIntegration')) {
            require_once('class/wcSmsApiIntegration.php');
        }
    }

    public function addIntegrationFilter($integrations)
    {
        $integrations[] = 'WcSmsApiIntegration';
        return $integrations;
    }

    public function linksFilter($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=woocommerce-smsapi') . '">' . __('Settings', 'woocommerce-smsapi') . '</a>',
            '<a href="http://www.wpdesk.pl/docs/woocommerce-smsapi-docs/">' . __('Docs', 'woocommerce-smsapi') . '</a>',
            '<a href="http://www.wpdesk.pl/support/">' . __('Support', 'woocommerce-smsapi') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }
}

WooCommerceSmsApiPlugin::getInstance();
