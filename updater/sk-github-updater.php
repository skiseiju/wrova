<?php
/**
 * SK GitHub Updater SDK
 * 
 * Provides a standardized mechanism to update WordPress plugins/themes from GitHub private repositories.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SK_GitHub_Updater' ) ) {

    class SK_GitHub_Updater {

        /**
         * @var array Registry of projects mapped by their slug.
         */
        private static $registry = [];

        /**
         * Register a new project for GitHub updates.
         *
         * @param array $args {
         *     @type string $type         'plugin' or 'theme'
         *     @type string $slug         The folder name of the plugin/theme (e.g. 'quench-optimizer')
         *     @type string $repo         GitHub repo in 'username/repo' format
         *     @type string $access_token Fine-grained PAT for GitHub API
         * }
         * @return bool True if registered successfully, false otherwise.
         */
        public static function register( $args = [] ) {
            if ( empty( $args['type'] ) || empty( $args['slug'] ) || empty( $args['repo'] ) || empty( $args['access_token'] ) ) {
                return false;
            }

            self::$registry[ $args['slug'] ] = [
                'type'         => $args['type'],
                'slug'         => $args['slug'],
                'repo'         => $args['repo'],
                'access_token' => $args['access_token'],
            ];

            return true;
        }

        /**
         * Get all registered projects.
         *
         * @return array
         */
        public static function get_registered_projects() {
            return self::$registry;
        }

        /**
         * Fetch the latest release information from GitHub API.
         * Includes strict caching, error handling, and timeout limits.
         *
         * @param string $repo  GitHub repository (e.g. 'skiseiju/quench-optimizer')
         * @param string $token GitHub Fine-grained PAT
         * @return array|false The latest version data (version, zipball_url) or false on failure
         */
        public static function fetch_latest_release( $repo, $token ) {
            $cache_key = 'sk_github_release_' . md5( $repo );
            
            // 1. Try to get valid active cache first
            $cached_data = get_site_transient( $cache_key );
            
            // For maximum resilience, we maintain a persistent 'stale' cache
            // that doesn't expire. This saves us if GitHub API goes down or hits rate limits.
            $stale_cache_key = $cache_key . '_stale';
            $stale_data      = get_site_option( $stale_cache_key ); 

            // If we have a valid active cache, return it immediately.
            if ( false !== $cached_data && ! empty( $cached_data['version'] ) ) {
                return $cached_data;
            }

            // 2. Cache missed or expired. Fetch from GitHub API.
            $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';

            $args = [
                'timeout' => 5, // Strict 5 seconds timeout to prevent server hanging
                'headers' => [
                    'Accept'               => 'application/vnd.github.v3+json',
                    'User-Agent'           => 'SK-GitHub-Updater-SDK/1.0',
                    'Authorization'        => 'Bearer ' . $token,
                    'X-GitHub-Api-Version' => '2022-11-28'
                ],
            ];

            $response = wp_safe_remote_get( $url, $args );

            // Helper payload for fallback
            $fallback_stale = ( false !== $stale_data && ! empty( $stale_data['version'] ) ) ? $stale_data : false;

            // 3. Handle connection errors or timeouts
            if ( is_wp_error( $response ) ) {
                return $fallback_stale;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $body          = wp_remote_retrieve_body( $response );
            $data          = json_decode( $body, true );

            // 4. Handle API errors (403 Rate Limit, 404 Not Found, Invalid Token, etc.)
            if ( 200 !== $response_code || empty( $data ) || empty( $data['tag_name'] ) ) {
                // CRITICAL RULE: NEVER overwrite cache with empty/failed data.
                return $fallback_stale;
            }

            // 5. Success! Extract the version tag and zipball URL.
            $payload = [
                'version'     => $data['tag_name'], // e.g. 'v1.2.0'
                'zipball_url' => ! empty( $data['zipball_url'] ) ? $data['zipball_url'] : $data['tarball_url']
            ];

            // Save active cache (6 hours = 21600 seconds)
            set_site_transient( $cache_key, $payload, 21600 );
            
            // Save stale cache persistently (overwrites only on successful fetch)
            update_site_option( $stale_cache_key, $payload );

            return $payload;
        }

        /**
         * Initialize the updater components.
         * This will hook into WordPress transient and upgrader processes.
         */
        public static function init() {
            // Priority 20 to ensure it runs after default WP checks
            add_filter( 'pre_set_site_transient_update_themes', [ __CLASS__, 'check_updates' ], 20 );
            add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_updates' ], 20 );
            
            // Hook to rename the directory after extracting the ZIP from GitHub
            add_filter( 'upgrader_source_selection', [ __CLASS__, 'rename_github_zip_folder' ], 10, 4 );
            
            // For plugins, inject details for the "View version details" thickbox popup
            add_filter( 'plugins_api', [ __CLASS__, 'plugin_details' ], 20, 3 );
            
            // To pass the Authorization header to standard WordPress upgrader downloads,
            // we have to hook into http_request_args
            add_filter( 'http_request_args', [ __CLASS__, 'inject_github_download_headers' ], 10, 2 );
        }

        /**
         * Check for updates against registered GitHub repos.
         *
         * @param object $transient
         * @return object
         */
        public static function check_updates( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            foreach ( self::$registry as $slug => $project ) {
                $is_plugin = ( 'plugin' === $project['type'] );
                $is_theme  = ( 'theme' === $project['type'] );
                
                // For plugins, the checked key is usually plugin-folder/plugin-file.php
                // We'll iterate through checking if the slug exists as a prefix
                $current_version = '';
                $item_key        = '';
                
                foreach ( $transient->checked as $key => $version ) {
                    if ( strpos( $key, $slug ) === 0 ) {
                        $current_version = $version;
                        $item_key        = $key;
                        break;
                    }
                }
                
                if ( empty( $current_version ) ) {
                    continue; // Local project not active or not in checking queue
                }

                $release = self::fetch_latest_release( $project['repo'], $project['access_token'] );
                
                if ( ! $release ) {
                    continue; // Fetch failed completely
                }

                // GitHub tags often have a 'v' prefix. Normalize it for comparison.
                $remote_version = ltrim( $release['version'], 'v' );

                if ( version_compare( $current_version, $remote_version, '<' ) ) {
                    // Update available
                    // Attach a special query param so we can intercept http_request_args later
                    $package_url = add_query_arg( [
                        'sk_github_slug' => $slug
                    ], $release['zipball_url'] );

                    $update_data = [
                        'slug'        => $slug,
                        'new_version' => $remote_version,
                        'url'         => 'https://github.com/' . $project['repo'],
                        'package'     => $package_url,
                    ];
                    
                    if ( $is_plugin ) {
                        // WP expects a stdClass object for plugin updates
                        $plugin_obj = new stdClass();
                        $plugin_obj->slug        = $slug;
                        $plugin_obj->plugin      = $item_key;
                        $plugin_obj->new_version = $remote_version;
                        $plugin_obj->url         = $update_data['url'];
                        $plugin_obj->package     = $update_data['package'];
                        
                        $transient->response[ $item_key ] = $plugin_obj;
                        
                    } elseif ( $is_theme ) {
                        // WP expects an array for theme updates
                        $update_data['theme']         = $slug;
                        $transient->response[ $slug ] = $update_data;
                    }
                }
            }

            return $transient;
        }

        /**
         * Inject GitHub Authorization headers when downloading the update package.
         */
        public static function inject_github_download_headers( $args, $url ) {
            // Check if this is a download URL from our updater
            if ( strpos( $url, 'api.github.com' ) !== false && strpos( $url, 'sk_github_slug=' ) !== false ) {
                $parsed      = wp_parse_url( $url );
                $query_args  = [];
                if ( ! empty( $parsed['query'] ) ) {
                    wp_parse_str( $parsed['query'], $query_args );
                }
                
                if ( ! empty( $query_args['sk_github_slug'] ) ) {
                    $slug = $query_args['sk_github_slug'];
                    if ( isset( self::$registry[ $slug ] ) ) {
                        $token = self::$registry[ $slug ]['access_token'];
                        // Add the required headers for GitHub API file download
                        $args['headers']['Authorization'] = 'Bearer ' . $token;
                        $args['headers']['Accept']        = 'application/octet-stream';
                    }
                }
            }
            return $args;
        }

        /**
         * Rename the extracted GitHub ZIP folder.
         * GitHub zips extract to `owner-repo-commitHash`. We need it to be exactly `$slug`.
         */
        public static function rename_github_zip_folder( $source, $remote_source, $upgrader, $hook_extra ) {
            global $wp_filesystem;
            
            // Determine if this is our plugin/theme being updated
            foreach ( self::$registry as $slug => $project ) {
                $is_target = false;
                
                if ( isset( $hook_extra['theme'] ) && $hook_extra['theme'] === $slug ) {
                    $is_target = true;
                } elseif ( isset( $hook_extra['plugin'] ) && strpos( $hook_extra['plugin'], $slug ) === 0 ) {
                    $is_target = true;
                }
                
                if ( $is_target && $wp_filesystem->exists( $remote_source ) ) {
                    // Create a new folder with the exact slug
                    $correct_dir = trailingslashit( $remote_source ) . $slug;
                    $wp_filesystem->mkdir( $correct_dir );
                    
                    // Copy everything from the extracted random-named folder into our slug-named folder
                    copy_dir( $source, $correct_dir );
                    
                    // Delete the old random-named folder
                    $wp_filesystem->delete( $source, true );
                    
                    return $correct_dir;
                }
            }

            return $source;
        }
        
        /**
         * Provide basic plugin details for the "View version details" thickbox popup.
         */
        public static function plugin_details( $res, $action, $args ) {
            if ( 'plugin_information' !== $action ) {
                return $res;
            }

            if ( isset( $args->slug ) && isset( self::$registry[ $args->slug ] ) ) {
                $project = self::$registry[ $args->slug ];
                $release = self::fetch_latest_release( $project['repo'], $project['access_token'] );
                
                if ( $release ) {
                    $res = new stdClass();
                    $res->name        = $project['slug'];
                    $res->slug        = $project['slug'];
                    $res->version     = ltrim( $release['version'], 'v' );
                    $res->author      = 'SK GitHub Updater SDK';
                    $res->homepage    = 'https://github.com/' . $project['repo'];
                    $res->sections    = [
                        'description' => 'Updates provided securely via GitHub Private Repository.',
                        'changelog'   => 'Please refer to GitHub Releases for changelog.'
                    ];
                }
            }
            
            return $res;
        }
    }

}
