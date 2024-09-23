<?php

/**
 * Name: U-M: Wordpress Github Updater Library
 * Description: Provides simple method to distribute releases using github rather than wordpress plugin repo.
 * Version: 1.0.2
 * Project URI: https://github.com/umdigital/wordpress-github-updater
 * Author: U-M: OVPC Digital
 * Author URI: https://vpcomm.umich.edu
 */

namespace Umich\GithubUpdater {
    if( !class_exists( '\Umich\GithubUpdater\Init' ) ) {
        class Init
        {
            static private $_version = 0;

            public function __construct( $options = array() )
            {
                $class = '\Umich\GithubUpdater\v'. str_replace( '.', 'd', self::$_version ) .'\Actions';

                new $class( $options );
            }

            static public function load( $version )
            {
                if( version_compare( $version, self::$_version ) > 0 ) {
                    self::$_version = $version;
                }
            }
        }
    }
}


namespace Umich\GithubUpdater\v1d0d2 {
    if( !class_exists( '\Umich\GithubUpdater\v1d0d2\Actions' ) ) {
        class Actions
        {
            CONST VERSION = '1.0.2';

            private $_githubBase = [
                'main' => 'https://github.com/',
                'api'  => 'https://api.github.com/repos/',
                'raw'  => 'https://raw.githubusercontent.com/',
            ];

            private $_requiredOptions = [
                'repo',
                'slug'
            ];

            private $_options = [
                'repo'          => '',
                'slug'          => '',
                'config'        => 'wordpress.json',
                'changelog'     => 'CHANGELOG',
                'description'   => 'README.md',
                'cache_timeout' => 60 * 60 * 6, // 6 hours
            ];

            private $_data = [];

            public function __construct( $options )
            {
                // dynamic defaults
                $this->_options['slug'] = plugin_basename( __FILE__ );

                // remove keys not used
                $options = array_intersect_key( $options, $this->_options );

                // override defaults
                $this->_options = array_merge(
                    $this->_options, $options
                );

                // check for required options
                $invalidOptions = [];
                foreach( $this->_requiredOptions as $key ) {
                    if( empty( $this->_options[ $key ] ) ) {
                        $invalidOptions[] = $key;
                    }
                }

                if( $invalidOptions && function_exists( '\_doing_it_wrong' ) ) {
                    \_doing_it_wrong(
                        '\Umich\GithubUpdater\Init',
                        'Missing required options: '. implode( ', ', $invalidOptions ) .'.',
                        self::VERSION
                    );
                }

                /** WORDPRESS HOOKS **/
                // Update Check
                add_filter( 'update_plugins_github.com', function( $update, $pluginData, $pluginFile ){
                    if( $pluginFile == $this->_options['slug'] ) {
                        // get latest release
                        $release = $this->_callAPI( 'releases/latest', 'gh_release_latest' );

                        if( $release ) {
                            $update = [
                                'slug'    => $this->_options['slug'],
                                'version' => $release->tag_name,
                                'url'     => $this->_githubBase['main'] . $this->_options['repo'] .'/releases/latest',
                                'package' => $release->zipball_url
                            ];

                            foreach( $release->assets as $asset ) {
                                if( $asset->name == basename( $this->_options['repo'] ) ."-{$release->tag_name}.zip" ) {
                                    $update['package'] = $asset->browser_download_url;
                                }
                            }
                        }
                    }

                    return $update;
                }, 10, 3 );

                // Plugin Details
                add_filter( 'plugins_api', function( $return, $action, $args ){
                    if( !isset( $args->slug ) || ($args->slug != $this->_options['slug']) ) {
                        return $return;
                    }

                    $release    = $this->_callAPI( 'releases/latest', 'gh_release_latest' );
                    $pluginData = get_plugin_data( WP_PLUGIN_DIR .'/'. $this->_options['slug'] );

                    if( $release && $pluginData ) {
                        if( ($wpConfig = $this->_getRaw( $this->_options['config'], $release->tag_name )) !== false ) {
                            foreach( [ 'description', 'changelog' ] as $key ) {
                                if( isset( $wpConfig->{$key} ) ) {
                                    $this->_options[ $key ] = $wpConfig->{$key};
                                }
                            }
                        }

                        $return = (object) [
                            'slug'           => $args->slug,
                            'name'           => $pluginData['Name'],
                            'version'        => $release->tag_name,
                            'requires'       => '',
                            'tested'         => '',
                            'requires_php'   => '',
                            'last_updated'   => date( 'Y-m-d h:ia e', strtotime( $release->published_at ) ),
                            'author'         => $pluginData['Author'],
                            'homepage'       => $pluginData['PluginURI'],
                            'sections'       => [ // as html
                                'description' => $this->_getMarkdown(
                                    $this->_options['description'],
                                    $release->tag_name,
                                    $pluginData['Description'] ?: $pluginData['Name']
                                ),
                                'changelog'   => $this->_getMarkdown(
                                    $this->_options['changelog'],
                                    $release->tag_name,
                                    $release->body
                                ),
                            ],
                            'download_link'  => $release->zipball_url, // zip file
                            'banners'        => [
                                'low'  => '', // image link (750x250)
                                'high' => '', // image link large (1500x500)
                            ]
                        ];

                        foreach( $release->assets as $asset ) {
                            if( $asset->name == basename( $this->_options['repo'] ) ."-{$release->tag_name}.zip" ) {
                                $return->download_link = $asset->browser_download_url;
                            }
                        }

                        if( $wpConfig ) {
                            foreach( array( 'requires', 'tested', 'requires_php', 'banners:low', 'banners:high' ) as $key ) {
                                if( isset( $wpConfig->{$key} ) ) {
                                    if( strpos( $key, ':' ) !== false ) {
                                        $kParts = explode( ':', $key, 2 );
                                        $return->{$kParts[0]}[ $kParts[1] ] = $wpConfig->{$key};
                                    }
                                    else {
                                        $return->{$key} = $wpConfig->{$key};
                                    }
                                }
                            }
                        }

                        foreach( $return->banners as $key => $img ) {
                            if( strpos( $img, '/' ) === 0 ) {
                                $return->banners[ $key ] = plugins_url( $img, dirname( __FILE__ ) );
                            }
                        }
                    }

                    return $return;
                }, 20, 3 );

                // force directory name to stay the same
                add_filter( 'upgrader_post_install', function( $true, $extra, $result ){
                    global $wp_filesystem;

                    $newDest = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->_options['slug'] );

                    $wp_filesystem->move( $result['destination'], $newDest );

                    $result['destination'] = $newDest;

                    activate_plugin( WP_PLUGIN_DIR . $this->_options['slug'] );

                    return $result;
                }, 10, 3 );
            }

            private function _callAPI( $endpoint, $key = null, $method = 'GET', $data = null )
            {
                if( $key && isset( $this->_data[ $key ] ) ) {
                    return $this->_data[ $key ];
                }

                $params = [
                    'timeout' => 5,
                    'method'  => $method,
                    'headers' => []
                ];

                if( $data ) {
                    $params['body']    = is_string( $data ) ? $data : json_encode( $data );
                    $params['headers'] = array_merge( $params['headers'], [
                        'Accept'       => 'application/vnd.github+json',
                        'Content-Type' => 'application/json',
                    ]);
                }

                $data = false;

                if( $key ) {
                    $data = get_site_transient( $this->_getTransientKey( $key ) );
                }

                if( !$data || isset( $_GET['force-check'] ) ) {
                    $res = wp_remote_request(
                        rtrim( "{$this->_githubBase['api']}{$this->_options['repo']}/{$endpoint}", '/' ),
                        $params
                    );

                    if( is_wp_error( $res ) || (@$res['response']['code'] != 200) ) {
                        return false;
                    }

                    $data = json_decode( $res['body'] );

                    if( $key ) {
                        set_site_transient(
                            $this->_getTransientKey( $key ),
                            $data,
                            $this->_options['cache_timeout']
                        );

                        $this->_data[ $key ] = $data;
                    }
                }

                return $data;
            }

            private function _getRaw( $file, $version = null )
            {
                $asset = trim( "{$version}/{$file}", '/' );

                $url = "{$this->_githubBase['raw']}{$this->_options['repo']}/{$asset}";

                $res = wp_remote_get( $url );

                if( is_wp_error( $res ) || (@$res['response']['code'] != 200) ) {
                    return false;
                }

                return $res['body'];
            }

            private function _getTransientKey( $key )
            {
                return substr( $this->_options['repo'], 0, 100 ) .'-'. $key;
            }

            private function _getMarkdown( $file, $version = null, $default = '' )
            {
                if( ($content = $this->_getRaw( $file, $version )) !== false ) {
                    $mRes = wp_remote_post(
                        'https://api.github.com/markdown', [
                            'body'    => json_encode([ 'text' => $content ]),
                            'timeout' => 5,
                            'headers' => [
                                'Accept'       => 'application/vnd.github+json',
                                'Content-Type' => 'application/json',
                            ]
                        ]
                    );

                    if( !is_wp_error( $mRes ) && @$mRes['response']['code'] == 200 ) {
                        return $mRes['body'];
                    }
                }

                return $default;
            }
        }

        \Umich\GithubUpdater\Init::load( Actions::VERSION );
    }
}
