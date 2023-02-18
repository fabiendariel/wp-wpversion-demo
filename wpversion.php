<?php
/*
Plugin Name: Wp version
Description: Plugin permettant d'obtenir des informations sur les versions de Wordpress
Author: Fabien DARIEL
Version: 1.0
GitHub Plugin URI: https://github.com/fabiendariel/wp-wpversion-demo
Text Domain: wpversion
*/

wp_register_style('wpversion', plugins_url('includes/css/wpversion.css', __FILE__));
wp_enqueue_style('wpversion');

class wpVersionClass
{
    private $versions = array();

    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_wpversion_menu']);
    }

    public function admin_wpversion_menu()
    {
        wp_register_style('wpversion', plugins_url('includes/css/wpversion.css', __FILE__));
        wp_enqueue_style('wpversion');
        wp_register_script('wpversion', plugins_url('includes/js/wpversion.js', __FILE__), array('jquery'));
        wp_enqueue_script('wpversion');
        add_menu_page(
            __('Wp Version', 'wpversion'),
            __('Wp version', 'wpversion'),
            'manage_options',
            'wpversion',
            [
                &$this, 'load_wpversion_page'
            ],
        );
    }

    public function load_wpversion_page()
    {

        echo '<h1>' . __('Wp Version', 'wpversion') . '</h1>';
        echo '<p>Last refresh : <span id="last_refresh">' . $_COOKIE["wp_version_date"] . '</span></p>';
        echo '<p><button class="wp_version_btn" id="btn_refresh_wpversion">Refresh</button></p>';
    }

    public function set_api_request($refresh = false)
    {
        if (isset($_COOKIE['wp_version_datas']) && !$refresh)
        {
            $this->versions = unserialize(base64_decode($_COOKIE['wp_version_datas']));
        }
        else
        {
            $request = wp_remote_get('https://endoflife.date/api/wordpress.json');

            if (is_wp_error($request)) {
                return false; // Si il y a une erreur, on s'arrête là
            }
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);

            $tab_version = array();
            foreach ($data as $item) {
                $tab_version[] = [
                    'cycle' => $item['cycle'],
                    'latest' => $item['latest'],
                    'type' => $item['eol'] != 0 ? 'insecure' : ($item['support'] != 1 ? 'outdated' : 'latest')
                ];
            }

            $this->versions = $tab_version;
            setcookie("wp_version_date", date('m-d-Y H:i'), time() + 86400, "/");
            setcookie("wp_version_datas", base64_encode(serialize($tab_version)), time() + 86400, "/");
        }

        return true;
    }

    public function replace_shortcode($atts)
    {
        $tab_versions = $this->versions;
        extract(shortcode_atts(
            array(
                'color' => 'yes',
                'type' => 'mine',
                'version' => 6
            ),
            $atts
        ));
        switch ($type) {
            case 'latest':
                $latest = 0;
                foreach ($tab_versions as $wpversion) {
                    if ($wpversion['latest'] > $latest)
                        $latest = $wpversion['latest'];
                }
                return '<span>' . $latest . '</span>';
                break;
            case 'validate':
                $validate = [];
                foreach ($tab_versions as $wpversion) {
                    if ($wpversion['cycle'] == $version)
                        $validate = $wpversion;
                }
                return '<span class="' . ($color ? (isset($validate['type']) ? $validate['type'] : '') : '') . '">' . $version . '</span>';
                break;
            case 'subversion':
                $html = '<table class="wp_version_table"><tr><td><span>Branch ' . $version . '</span></td></tr>';
                foreach ($tab_versions as $wpversion) {
                    $release = explode('.', $wpversion['cycle'])[0];
                    if ($release == $version) {
                        $html .= '<tr><td><span class="' . ($color ? $wpversion['type'] : '') . '">' . $wpversion['cycle'] . '</td></tr>';
                    }
                }
                $html .= '</table>';
                return $html;
                break;
            case 'mine':
                $mine = [];
                $actual = get_bloginfo('version');
                $actual_major = explode('.', $actual);
                foreach ($tab_versions as $wpversion) {
                    if ($wpversion['cycle'] == $actual || ($wpversion['cycle'] == ($actual_major[0] . '.' . $actual_major[1])))
                        $mine = $wpversion;
                }
                return '<span class="' . ($color ? (isset($mine['type']) ? $mine['type'] : '') : '') . '">' . $actual . '</span>';
                break;
        }
    }
}

$wpVersionClass = new wpVersionClass();
$wpVersionClass->set_api_request();

function shortcode_wpversion($atts)
{
    $wpVersionClass = new wpVersionClass();
    $wpVersionClass->set_api_request();
    $wpVersionClass->replace_shortcode($atts);
    
}

add_shortcode('wpversion', 'shortcode_wpversion');

//wp_enqueue_script('ajax-script', plugins_url('/js/wpversion.js', __FILE__), array('jquery'));

add_action('wp_ajax_refresh_datas', 'refresh_datas' );

function refresh_datas() {

    $wpVersionClass = new wpVersionClass();
    $wpVersionClass->set_api_request(true);

	wp_die();
}
